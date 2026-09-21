<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\PlatformSetting;
use App\Services\AI\Usage\UsageMeter;
use App\Services\Billing\Billing;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Throwable;

/**
 * Espace abonnement, côté client.
 */
class SubscriptionController extends Controller
{
    public function __construct(
        private readonly Billing $billing,
        private readonly UsageMeter $usage,
    ) {
    }

    public function index(Request $request)
    {
        $organization = $this->organization($request);

        $subscription = $this->billing->subscription($organization);

        return Inertia::render('Subscription/Index', [
            'plans' => $this->billing->plans(),

            'current' => [
                'plan' => $organization->plan(),
                'status' => $subscription?->status ?? 'free',
                'ends_at' => optional($subscription?->ends_at)->toDateString(),
                'days_remaining' => $subscription?->daysRemaining(),
                'amount' => $subscription?->amount,
            ],

            'usage' => $this->usage->summary($organization),

            'provider' => config('ai.billing.provider'),

            'currency' => config('ai.billing.currency', 'XOF'),

            'payments' => Payment::query()
                ->where('organization_id', $organization->id)
                ->latest('id')
                ->limit(12)
                ->get()
                ->map(fn (Payment $payment) => [
                    'reference' => $payment->reference,
                    'plan' => $payment->plan,
                    'amount' => $payment->amount,
                    'currency' => $payment->currency,
                    'status' => $payment->status,
                    'method' => $payment->method,
                    'created_at' => optional($payment->created_at)->toDateString(),
                    'paid_at' => optional($payment->paid_at)->toDateString(),
                ]),

            /*
             * Coordonnées de règlement, renseignées une fois pour
             * toutes dans l'espace d'administration.
             */
            'payment_details' => PlatformSetting::paymentDetails(),
        ]);
    }

    /**
     * Ouvre un paiement pour la formule choisie.
     */
    public function checkout(Request $request)
    {
        $organization = $this->organization($request);

        $validated = $request->validate([
            'plan' => [
                'required',
                Rule::in(array_keys(config('ai.plans', []))),
            ],
        ]);

        try {
            $result = $this->billing->checkout(
                $organization,
                $validated['plan'],
                $request->user()
            );
        } catch (Throwable $exception) {
            return back()->with('error', $exception->getMessage());
        }

        /*
         * Prestataire en ligne : on envoie le client sur sa page de
         * règlement. Mode manuel : il reste ici, avec les consignes.
         */
        if ($result['redirect']) {
            return Inertia::location($result['redirect']);
        }

        return back()->with(
            'success',
            'Demande enregistrée sous la référence '
            . $result['payment']->reference
            . '. Le service sera activé dès réception du règlement.'
        );
    }

    /**
     * Retour du client depuis la page du prestataire.
     *
     * Cette page n'accorde rien : l'utilisateur peut fabriquer cette
     * adresse lui-même. Elle se contente d'interroger le prestataire.
     */
    public function return(Request $request)
    {
        $organization = $this->organization($request);

        /*
         * La référence vient du prestataire. CinetPay la nomme
         * « transaction_id », d'autres « cpm_trans_id » : on accepte les
         * deux, plus notre propre nom.
         *
         * Prendre « le dernier paiement en attente », comme le faisait
         * la version précédente, était faux : un client qui ouvre deux
         * onglets, ou qui abandonne un règlement puis en lance un autre,
         * aurait vu la mauvaise transaction vérifiée — et potentiellement
         * une formule activée par un paiement qui n'était pas le sien.
         */
        $reference = $request->input('transaction_id')
            ?? $request->input('cpm_trans_id')
            ?? $request->input('reference');

        if (!$reference) {
            return redirect()
                ->route('subscription.index')
                ->with(
                    'warning',
                    'Retour sans référence de transaction. Si vous avez '
                    . 'réglé, votre formule sera activée automatiquement '
                    . 'à la confirmation du prestataire.'
                );
        }

        $payment = Payment::query()
            ->where('organization_id', $organization->id)
            ->where('reference', $reference)
            ->first();

        if (!$payment) {
            return redirect()
                ->route('subscription.index')
                ->with('error', 'Transaction introuvable.');
        }

        /*
         * Déjà confirmée par la notification serveur, qui arrive
         * souvent avant le retour du navigateur.
         */
        if ($payment->status === 'paid') {
            return redirect()
                ->route('subscription.index')
                ->with('success', 'Paiement reçu, votre formule est active.');
        }

        $status = $this->billing->gateway($payment->provider)->verify($payment);

        if ($status === 'paid') {
            $this->billing->confirm($payment);

            return redirect()
                ->route('subscription.index')
                ->with('success', 'Paiement reçu, votre formule est active.');
        }

        if ($status === 'failed') {
            $payment->update(['status' => 'failed']);

            return redirect()
                ->route('subscription.index')
                ->with('error', "Le paiement n'a pas abouti.");
        }

        return redirect()
            ->route('subscription.index')
            ->with(
                'warning',
                'Paiement en cours de traitement. Votre formule sera '
                . 'activée dès confirmation, sans action de votre part.'
            );
    }

    private function organization(Request $request)
    {
        $user = $request->user();

        abort_unless($user->hasAbility('billing.manage'), 403);

        $organization = $user->organization;

        abort_unless($organization, 404);

        return $organization;
    }
}
