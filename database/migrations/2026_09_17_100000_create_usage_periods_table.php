<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Consommation mensuelle par organisation.
     *
     * Une ligne par entreprise et par mois. Les compteurs sont
     * incrémentés au fil de l'eau : lire un quota doit coûter une seule
     * requête, puisque c'est fait avant chaque message.
     */
    public function up(): void
    {
        Schema::create('usage_periods', function (Blueprint $table) {
            $table->id();

            $table->foreignId('organization_id')
                ->constrained('organizations')
                ->cascadeOnDelete();

            /*
             * Période au format AAAA-MM. Une chaîne plutôt qu'une date :
             * c'est la clé de regroupement, pas un instant.
             */
            $table->string('period', 7);

            $table->unsignedInteger('ai_messages')->default(0);

            $table->unsignedBigInteger('input_tokens')->default(0);

            $table->unsignedBigInteger('output_tokens')->default(0);

            $table->unsignedInteger('voice_calls')->default(0);

            $table->unsignedBigInteger('voice_seconds')->default(0);

            $table->unsignedInteger('conversations')->default(0);

            /*
             * Horodatage des alertes déjà envoyées, pour ne pas prévenir
             * le responsable à chaque message une fois le seuil franchi.
             */
            $table->timestamp('warned_at')->nullable();

            $table->timestamp('blocked_at')->nullable();

            $table->timestamps();

            $table->unique(['organization_id', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usage_periods');
    }
};
