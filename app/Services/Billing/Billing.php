<?php

namespace App\Services\Billing;

use App\Models\Organization;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Billing\Gateways\CinetPayGateway;
use App\Services\Billing\Gateways\ManualGateway;
use App\Services\Billing\Gateways\PaymentGateway;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Abonnements et encaissement.
 *
 * Une règle gouverne tout ce fichier : la formule d'une entreprise ne
 * change qu'après confirmation de l'argent reçu, jamais avant.
 */
class Billing
{
    public function gateway(?string $name = null): PaymentGateway
    {
        $name ??= config('ai.billing.provider', 'manual');

        return match ($name) {
            'cinetpay' => app(CinetPayGateway::class),
            'manual' => app(ManualGateway::class),
            default => throw new RuntimeException(
                'Prestataire de paiement inconnu : ' . $name
            ),
        };
    }

    /**
     * Formules proposées, telles qu'affichées au client.
     */
    public function plans(): array
    {
        $plans = [];

        foreach (config('ai.plans', []) as $name => $plan) {
            $quota = array_merge(
                config('ai.quota', []),
                $plan['quota'] ?? []
            );

            $plans[] = [
                'name' => $name,
                'label' => $plan['label'] ?? ucfirst($name),
                'price' => (int) ($plan['price'] ?? 0),
                'pitch' => $plan['pitch'] ?? null,
                'currency' => config('ai.billing.currency', 'XOF'),

                'ai_messages' => (int) ($quota['ai_messages'] ?? 0),
                'voice_calls' => (int) ($quota['voice_calls'] ?? 0),
                'level' => $plan['level'] ?? config('ai.autopilot.level'),
                'actions' => $plan['allowed_actions']
                    ?? config('ai.autopilot.allowed_actions', []),
            ];
        }

        return $plans;
    }

    public function priceOf(string $plan): int
    {
        return (int) config("ai.plans.{$plan}.price", 0);
    }

    /**
     * Abonnement en cours, s'il existe.
     */
    public function subscription(Organization $organization): ?Subscription
    {
        return Subscription::query()
            ->where('organization_id', $organization->id)
            ->active()
            ->latest('id')
            ->first();
    }

    /**
     * Ouvre un paiement et retourne l'adresse de règlement.
     *
     * @return array{payment: Payment, redirect: ?string}
     */
    public function checkout(
        Organization $organization,
        string $plan,
        ?User $user = null
    ): array {
        if (!array_key_exists($plan, config('ai.plans', []))) {
            throw new RuntimeException('Formule inconnue : ' . $plan);
        }

        $amount = $this->priceOf($plan);

        if ($amount <= 0) {
            throw new RuntimeException(
                'Cette formule est gratuite : aucun paiement à effectuer.'
            );
        }

        $gateway = $this->gateway();

        $payment = Payment::create([
            'organization_id' => $organization->id,
            'initiated_by' => $user?->id,
            'plan' => $plan,
            'amount' => $amount,
            'currency' => config('ai.billing.currency', 'XOF'),
            'provider' => $gateway->name(),
            'reference' => $this->reference(),
            'status' => 'pending',
        ]);

        $redirect = $gateway->checkout($payment);

        return ['payment' => $payment, 'redirect' => $redirect];
    }

    /**
     * Confirme un paiement et applique la formule.
     *
     * Volontairement idempotent : une notification reçue deux fois ne
     * doit pas offrir deux mois d'abonnement. Les prestataires
     * renvoient régulièrement la même notification.
     */
    public function confirm(Payment $payment, array $payload = []): Subscription
    {
        return DB::transaction(function () use ($payment, $payload) {
            $locked = Payment::query()
                ->lockForUpdate()
                ->find($payment->id);

            if ($locked->status === 'paid') {
                Log::info(
                    'Paiement déjà confirmé, notification ignorée.',
                    ['payment_id' => $locked->id]
                );

                return $locked->subscription
                    ?? $this->subscription($locked->organization);
            }

            $organization = $locked->organization;

            /*
             * Un abonnement encore valide est prolongé depuis son
             * échéance, pas depuis aujourd'hui : un client qui paie en
             * avance ne doit pas perdre les jours restants.
             */
            $current = $this->subscription($organization);

            $start = $current && $current->ends_at && $current->ends_at->isFuture()
                ? $current->ends_at
                : now();

            $days = (int) config('ai.billing.period_days', 30);

            if ($current) {
                $current->update(['status' => 'cancelled']);
            }

            $subscription = Subscription::create([
                'organization_id' => $organization->id,
                'plan' => $locked->plan,
                'status' => 'active',
                'amount' => $locked->amount,
                'currency' => $locked->currency,

                /*
                 * La période commence là où la précédente s'arrête, pas
                 * aujourd'hui. Avec « now() », un renouvellement anticipé
                 * affichait une période chevauchant l'ancienne : trente
                 * jours annoncés au lieu des quarante-et-un réellement
                 * couverts.
                 */
                'starts_at' => $start,
                'ends_at' => $start->copy()->addDays($days),
            ]);

            $locked->update([
                'status' => 'paid',
                'paid_at' => now(),
                'subscription_id' => $subscription->id,
                'payload' => $payload ?: $locked->payload,
            ]);

            $this->applyPlan($organization, $locked->plan);

            Log::info(
                'Abonnement activé.',
                [
                    'organization_id' => $organization->id,
                    'plan' => $locked->plan,
                    'ends_at' => $subscription->ends_at->toDateString(),
                ]
            );

            return $subscription;
        });
    }

    /**
     * Rebascule une entreprise en formule gratuite.
     */
    public function downgrade(Organization $organization): void
    {
        $subscription = $this->subscription($organization);

        $subscription?->update(['status' => 'expired']);

        $this->applyPlan($organization, 'free');

        Log::info(
            'Retour en formule gratuite.',
            ['organization_id' => $organization->id]
        );
    }

    /**
     * Change la formule sans toucher aux réglages propres.
     *
     * Les exceptions accordées à un client — un plafond relevé, un ton
     * particulier — survivent au changement.
     */
    public function applyPlan(Organization $organization, string $plan): void
    {
        $organization->update([
            'ai_settings' => array_merge(
                is_array($organization->ai_settings)
                    ? $organization->ai_settings
                    : [],
                ['plan' => $plan]
            ),
        ]);
    }

    /**
     * Référence transmise au prestataire.
     *
     * Préfixée et datée : lisible dans un relevé, et sans collision
     * possible avec celles d'un autre environnement.
     */
    private function reference(): string
    {
        do {
            $reference = 'AB-' . now()->format('ymd') . '-'
                . Str::upper(Str::random(8));
        } while (Payment::where('reference', $reference)->exists());

        return $reference;
    }
}
