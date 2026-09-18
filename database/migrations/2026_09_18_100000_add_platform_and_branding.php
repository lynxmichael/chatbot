<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * Administrateur de la plateforme.
         *
         * Distinct du rôle « owner », qui reste rattaché à une
         * entreprise. Le super-administrateur est au-dessus des
         * organisations : il les voit toutes et n'appartient à aucune.
         */
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_super_admin')
                ->default(false)
                ->after('role');

            $table->index('is_super_admin');
        });

        /*
         * Identité visuelle de chaque entreprise.
         */
        Schema::table('organizations', function (Blueprint $table) {
            $table->string('logo_path')->nullable()->after('ai_settings');

            $table->string('brand_color', 7)->nullable()->after('logo_path');

            $table->string('support_email')->nullable()->after('brand_color');

            $table->string('welcome_message', 300)
                ->nullable()
                ->after('support_email');
        });

        /*
         * Réglages de la plateforme : coordonnées de règlement,
         * informations de facturation.
         *
         * Une table clé-valeur plutôt que des colonnes : ces réglages
         * changent souvent et ne servent qu'à l'affichage.
         */
        Schema::create('platform_settings', function (Blueprint $table) {
            $table->string('key')->primary();

            $table->json('value')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_settings');

        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn([
                'logo_path',
                'brand_color',
                'support_email',
                'welcome_message',
            ]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['is_super_admin']);
            $table->dropColumn('is_super_admin');
        });
    }
};
