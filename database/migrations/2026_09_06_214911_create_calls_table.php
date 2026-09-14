<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calls', function (Blueprint $table) {
            $table->id();

            // Organisation propriétaire de l'appel
            $table->foreignId('organization_id')
                ->constrained()
                ->cascadeOnDelete();

            // Client concerné
            $table->foreignId('client_id')
                ->constrained()
                ->cascadeOnDelete();

            // Conversation éventuellement liée
            $table->foreignId('conversation_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            // Agent ayant géré l'appel
            $table->foreignId('user_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            // Type d'appel
            $table->enum('type', [
                'incoming',
                'outgoing',
            ])->default('incoming');

            // Statut de l'appel
            $table->enum('status', [
                'answered',
                'missed',
                'busy',
                'failed',
                'cancelled',
            ])->default('answered');

            // Numéro utilisé pour l'appel
            $table->string('phone', 30);

            // Durée en secondes
            $table->unsignedInteger('duration')->default(0);

            // Motif et compte rendu
            $table->string('reason')->nullable();
            $table->text('notes')->nullable();

            // Date et heure de l'appel
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();

            $table->timestamps();

            // Index utiles pour les recherches et statistiques
            $table->index(['organization_id', 'client_id']);
            $table->index(['organization_id', 'status']);
            $table->index(['organization_id', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calls');
    }
};