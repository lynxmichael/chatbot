<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Relances programmées.
     *
     * L'IA peut décider de revenir vers un client plus tard :
     * vérification d'une livraison, confirmation de résolution,
     * rappel avant fermeture automatique d'un ticket.
     */
    public function up(): void
    {
        Schema::create('follow_ups', function (Blueprint $table) {
            $table->id();

            $table->foreignId('organization_id')
                ->constrained('organizations')
                ->cascadeOnDelete();

            $table->foreignId('client_id')
                ->constrained('clients')
                ->cascadeOnDelete();

            $table->foreignId('conversation_id')
                ->nullable()
                ->constrained('conversations')
                ->nullOnDelete();

            $table->foreignId('ticket_id')
                ->nullable()
                ->constrained('tickets')
                ->nullOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Contenu de la relance
            |--------------------------------------------------------------------------
            |
            | « instruction » décrit ce que l'IA devra faire ou dire au moment
            | de la relance. Le message final est rédigé au moment de l'envoi,
            | avec le contexte à jour.
            |
            */

            $table->text('instruction');

            $table->string('channel')->default('widget');

            $table->timestamp('run_at');

            $table->enum('status', [
                'pending',
                'sent',
                'cancelled',
                'failed',
            ])->default('pending');

            $table->unsignedTinyInteger('attempts')->default(0);

            $table->text('last_error')->nullable();

            $table->boolean('created_by_ai')->default(true);

            $table->timestamp('executed_at')->nullable();

            $table->timestamps();

            $table->index(['organization_id', 'status', 'run_at']);
            $table->index(['organization_id', 'client_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('follow_ups');
    }
};
