<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Journal de toutes les actions décidées par l'IA.
     *
     * Cette table sert à la fois de :
     *
     * - piste d'audit (qui a fait quoi, quand, avec quelles données) ;
     * - file de validation lorsque l'Autopilot est en mode « assist ».
     */
    public function up(): void
    {
        Schema::create('ai_actions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('organization_id')
                ->constrained('organizations')
                ->cascadeOnDelete();

            $table->foreignId('conversation_id')
                ->nullable()
                ->constrained('conversations')
                ->nullOnDelete();

            $table->foreignId('client_id')
                ->nullable()
                ->constrained('clients')
                ->nullOnDelete();

            $table->foreignId('ticket_id')
                ->nullable()
                ->constrained('tickets')
                ->nullOnDelete();

            $table->foreignId('message_id')
                ->nullable()
                ->constrained('messages')
                ->nullOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Action
            |--------------------------------------------------------------------------
            |
            | Nom de l'outil appelé par l'IA : create_ticket, escalate_to_human...
            |
            */

            $table->string('tool');

            $table->json('input')->nullable();

            $table->json('output')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Statut
            |--------------------------------------------------------------------------
            |
            | executed  : l'action a été réalisée ;
            | pending   : l'action attend la validation d'un humain ;
            | approved  : validée puis exécutée par un humain ;
            | rejected  : refusée par un humain ;
            | denied    : bloquée par les règles de l'Autopilot ;
            | failed    : erreur pendant l'exécution.
            |
            */

            $table->enum('status', [
                'executed',
                'pending',
                'approved',
                'rejected',
                'denied',
                'failed',
            ])->default('executed');

            $table->string('autopilot_level')->nullable();

            $table->text('reason')->nullable();

            $table->foreignId('reviewed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('reviewed_at')->nullable();

            $table->timestamp('executed_at')->nullable();

            $table->timestamps();

            $table->index(['organization_id', 'status']);
            $table->index(['organization_id', 'tool']);
            $table->index(['organization_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_actions');
    }
};
