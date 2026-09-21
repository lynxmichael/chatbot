<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Convertit les listes d'outils figées en listes d'exclusions.
     *
     * Une entreprise qui avait enregistré sa page Autopilot possédait
     * une liste « allowed_actions » arrêtée à ce jour-là. Cette liste
     * l'emportait sur sa formule : les outils ajoutés depuis, comme
     * l'envoi de photos, ne lui parvenaient jamais, même en payant.
     *
     * On conserve ses choix réels — ce qu'elle avait décoché parmi les
     * outils de l'époque — et on laisse tout le reste arriver.
     */
    private const TOOLS_AT_THE_TIME = [
        'search_knowledge',
        'get_client_profile',
        'get_order_status',
        'get_ticket_status',
        'record_insights',
        'create_ticket',
        'update_ticket',
        'schedule_follow_up',
        'escalate_to_human',
    ];

    public function up(): void
    {
        DB::table('organizations')
            ->whereNotNull('ai_settings')
            ->orderBy('id')
            ->each(function ($organization) {
                $settings = json_decode($organization->ai_settings, true);

                if (!is_array($settings) || !isset($settings['allowed_actions'])) {
                    return;
                }

                $saved = is_array($settings['allowed_actions'])
                    ? $settings['allowed_actions']
                    : [];

                /*
                 * Seul ce qui avait été volontairement décoché devient
                 * une exclusion. Les outils inconnus à l'époque ne
                 * faisaient pas partie du choix, ils arrivent actifs.
                 */
                $settings['disabled_actions'] = array_values(
                    array_diff(self::TOOLS_AT_THE_TIME, $saved)
                );

                unset($settings['allowed_actions']);

                DB::table('organizations')
                    ->where('id', $organization->id)
                    ->update(['ai_settings' => json_encode($settings)]);
            });
    }

    public function down(): void
    {
        /*
         * Pas de retour arrière : reconstituer une liste figée
         * recréerait exactement le défaut corrigé ici.
         */
    }
};
