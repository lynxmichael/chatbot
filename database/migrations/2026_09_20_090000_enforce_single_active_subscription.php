<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Une seule souscription active par organisation, garantie en base.
     *
     * Le code y veillait déjà, mais un code peut se tromper : une
     * notification traitée deux fois en parallèle, une correction
     * manuelle en SQL, un import. Deux souscriptions actives
     * signifieraient deux échéances contradictoires pour le même
     * client, et personne ne saurait laquelle fait foi.
     *
     * MySQL ne connaît pas les index uniques partiels. On passe donc
     * par une colonne dédiée qui vaut l'identifiant de l'organisation
     * tant que la souscription est active, et NULL sinon : plusieurs
     * NULL cohabitent dans un index unique, une seule valeur non nulle
     * est admise.
     */
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->unsignedBigInteger('active_for')->nullable()->after('status');
        });

        /*
         * Ménage avant de poser la contrainte : s'il existe déjà des
         * doublons, on ne garde que la souscription la plus récente.
         */
        $duplicates = DB::table('subscriptions')
            ->select('organization_id')
            ->where('status', 'active')
            ->groupBy('organization_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('organization_id');

        foreach ($duplicates as $organizationId) {
            $keep = DB::table('subscriptions')
                ->where('organization_id', $organizationId)
                ->where('status', 'active')
                ->orderByDesc('id')
                ->value('id');

            DB::table('subscriptions')
                ->where('organization_id', $organizationId)
                ->where('status', 'active')
                ->where('id', '!=', $keep)
                ->update(['status' => 'cancelled']);
        }

        DB::table('subscriptions')
            ->where('status', 'active')
            ->update(['active_for' => DB::raw('organization_id')]);

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->unique('active_for');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropUnique(['active_for']);
            $table->dropColumn('active_for');
        });
    }
};
