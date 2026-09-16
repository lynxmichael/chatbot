<?php

namespace App\Http\Controllers;

use App\Models\Call;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Poste téléphonique de l'agent.
 *
 * La console interroge « incoming » en boucle. Dès qu'un appel destiné
 * à l'agent est en sonnerie, son navigateur affiche la fiche d'appel
 * et déclenche la sonnerie.
 *
 * Ces routes sont volontairement séparées de celles du widget : elles
 * passent par la session authentifiée, pas par le jeton public.
 */
class CallDeskController extends Controller
{
    /**
     * Appels qui sonnent pour cet agent, et appel en cours s'il y en a un.
     */
    public function incoming(Request $request): JsonResponse
    {
        $user = $request->user();

        $ringing = Call::query()
            ->where('organization_id', $user->organization_id)
            ->where('status', 'ringing')
            ->where(function ($query) use ($user) {
                /*
                 * L'appel lui est attribué, ou n'a trouvé personne
                 * au moment de sa création — dans ce cas, le premier
                 * agent qui décroche le prend.
                 */
                $query->where('user_id', $user->id)
                    ->orWhereNull('user_id');
            })
            /*
             * Un appel que cet agent vient de refuser ne doit pas
             * resonner chez lui. Le groupe imbriqué est indispensable :
             * un orWhere à plat sortirait du périmètre de l'organisation.
             */
            ->where(function ($query) use ($user) {
                $query->whereNull('declined_by')
                    ->orWhere('declined_by', '!=', $user->id);
            })
            ->with('client:id,first_name,last_name,email,phone,company')
            ->latest('id')
            ->first();

        $active = Call::query()
            ->where('organization_id', $user->organization_id)
            ->where('user_id', $user->id)
            ->where('status', 'answered')
            ->with('client:id,first_name,last_name,email,phone,company')
            ->latest('id')
            ->first();

        return response()->json([
            'ringing' => $ringing ? $this->present($ringing) : null,
            'active' => $active ? $this->present($active) : null,
        ]);
    }

    /**
     * L'agent décroche.
     */
    public function accept(Request $request, Call $call): JsonResponse
    {
        $user = $request->user();

        $this->authorizeCall($request, $call);

        if ($call->status !== 'ringing') {
            return response()->json([
                'success' => false,
                'status' => $call->status,
                'message' => $call->status === 'missed'
                    ? "L'appelant a raccroché."
                    : "Cet appel n'est plus disponible.",
            ], 409);
        }

        $call->update([
            'user_id' => $user->id,
            'status' => 'answered',
            'answered_at' => now(),
        ]);

        /*
         * L'agent prend la main : l'IA cesse de répondre
         * dans la conversation rattachée.
         */
        $call->conversation?->update([
            'assigned_to' => $user->id,
            'ai_enabled' => false,
            'status' => 'open',
        ]);

        return response()->json([
            'success' => true,
            'call' => $this->present($call->fresh('client')),
        ]);
    }

    /**
     * L'agent refuse l'appel.
     */
    public function decline(Request $request, Call $call): JsonResponse
    {
        $user = $request->user();

        $this->authorizeCall($request, $call);

        if ($call->status !== 'ringing') {
            return response()->json([
                'success' => true,
                'status' => $call->status,
            ]);
        }

        /*
         * On mémorise qui a refusé pour ne pas refaire sonner
         * le même poste dans la seconde qui suit.
         */
        $call->update(['declined_by' => $user->id]);

        /*
         * Un autre agent libre peut encore prendre l'appel.
         * Sinon, il part en manqué.
         */
        $other = User::query()
            ->where('organization_id', $user->organization_id)
            ->whereIn('role', ['agent', 'owner'])
            ->where('is_active', true)
            ->where('id', '!=', $user->id)
            ->where(function ($query) {
                $query->where('is_available', true)
                    ->orWhereNull('is_available');
            })
            ->whereNotIn(
                'id',
                Call::query()
                    ->where('organization_id', $user->organization_id)
                    ->whereIn('status', ['ringing', 'answered'])
                    ->whereNotNull('user_id')
                    ->pluck('user_id')
            )
            ->orderBy('id')
            ->first();

        if ($other) {
            $call->update(['user_id' => $other->id]);
        } else {
            $call->update([
                'status' => 'missed',
                'ended_at' => now(),
            ]);
        }

        return response()->json([
            'success' => true,
            'status' => $call->fresh()->status,
            'transferred' => (bool) $other,
        ]);
    }

    /**
     * L'agent raccroche.
     */
    public function hangUp(Request $request, Call $call): JsonResponse
    {
        $this->authorizeCall($request, $call);

        if (!in_array($call->status, ['ringing', 'answered'], true)) {
            return response()->json([
                'success' => true,
                'status' => $call->status,
                'duration' => (int) $call->duration,
            ]);
        }

        $duration = $call->answered_at
            ? (int) max(0, $call->answered_at->diffInSeconds(now()))
            : 0;

        $call->update([
            'status' => $call->status === 'answered' ? 'completed' : 'missed',
            'duration' => $duration,
            'ended_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'status' => $call->status,
            'duration' => $duration,
        ]);
    }

    /**
     * Données envoyées au navigateur de l'agent.
     */
    private function present(Call $call): array
    {
        return [
            'id' => $call->id,
            'status' => $call->status,
            'conversation_id' => $call->conversation_id,

            'client' => $call->client
                ? [
                    'id' => $call->client->id,
                    'name' => trim(
                        $call->client->first_name . ' ' . $call->client->last_name
                    ),
                    'email' => $call->client->email,
                    'phone' => $call->client->phone,
                    'company' => $call->client->company,
                ]
                : null,

            /*
             * Secondes écoulées, calculées côté serveur pour que le
             * chronomètre reste juste même si l'onglet a été en veille.
             */
            'elapsed' => $call->answered_at
                ? (int) max(0, $call->answered_at->diffInSeconds(now()))
                : 0,

            'ringing_for' => $call->started_at
                ? (int) max(0, $call->started_at->diffInSeconds(now()))
                : 0,
        ];
    }

    private function authorizeCall(Request $request, Call $call): void
    {
        abort_unless(
            $call->organization_id === $request->user()->organization_id,
            403
        );
    }
}
