<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cycle de vie complet d'un appel.
     *
     * L'ENUM d'origine ne contenait pas « ringing » : un appel était
     * donc créé directement en « answered », ce qui rendait impossible
     * de faire sonner le poste de l'agent. Il ne contenait pas non plus
     * « completed », si bien qu'un appel mené à son terme était
     * enregistré comme « cancelled » — et comptait comme un échec
     * dans les statistiques.
     *
     *   ringing   : le poste de l'agent sonne
     *   answered  : l'agent a décroché
     *   completed : conversation terminée normalement
     *   missed    : personne n'a décroché
     *   busy      : tous les agents sont occupés
     *   cancelled : le client a raccroché avant réponse
     *   failed    : erreur technique
     */
    public function up(): void
    {
        DB::statement(
            "ALTER TABLE calls MODIFY COLUMN status
             ENUM('ringing','answered','completed','missed','busy','failed','cancelled')
             NOT NULL DEFAULT 'answered'"
        );

        Schema::table('calls', function (Blueprint $table) {
            /*
             * Instant où l'agent a décroché.
             *
             * La durée facturable se compte à partir de là, pas depuis
             * le début de la sonnerie.
             */
            $table->timestamp('answered_at')
                ->nullable()
                ->after('started_at');

            $table->foreignId('declined_by')
                ->nullable()
                ->after('user_id')
                ->constrained('users')
                ->nullOnDelete();

            $table->index(['organization_id', 'status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('calls', function (Blueprint $table) {
            $table->dropIndex(['organization_id', 'status', 'created_at']);
            $table->dropConstrainedForeignId('declined_by');
            $table->dropColumn('answered_at');
        });

        DB::statement(
            "UPDATE calls SET status = 'answered' WHERE status IN ('ringing','completed')"
        );

        DB::statement(
            "ALTER TABLE calls MODIFY COLUMN status
             ENUM('answered','missed','busy','failed','cancelled')
             NOT NULL DEFAULT 'answered'"
        );
    }
};
