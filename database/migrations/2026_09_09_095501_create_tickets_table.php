<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();

            /*
            |--------------------------------------------------------------------------
            | Organisation
            |--------------------------------------------------------------------------
            */

            $table->foreignId('organization_id')
                ->constrained('organizations')
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Client
            |--------------------------------------------------------------------------
            */

            $table->foreignId('client_id')
                ->constrained('clients')
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Conversation associée
            |--------------------------------------------------------------------------
            |
            | Une réclamation peut provenir d'une conversation existante,
            | mais elle peut également être créée indépendamment.
            |
            */

            $table->foreignId('conversation_id')
                ->nullable()
                ->constrained('conversations')
                ->nullOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Agent responsable
            |--------------------------------------------------------------------------
            */

            $table->foreignId('assigned_to')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Informations du ticket
            |--------------------------------------------------------------------------
            */

            $table->string('ticket_number')
                ->unique();

            $table->string('subject');

            $table->text('description');

            /*
            |--------------------------------------------------------------------------
            | Catégorie
            |--------------------------------------------------------------------------
            |
            | string plutôt que enum afin de pouvoir ajouter facilement
            | de nouvelles catégories plus tard.
            |
            */

            $table->string('category')
                ->default('general');

            /*
            |--------------------------------------------------------------------------
            | Statut
            |--------------------------------------------------------------------------
            */

            $table->enum('status', [
                'open',
                'pending',
                'in_progress',
                'resolved',
                'closed',
            ])->default('open');

            /*
            |--------------------------------------------------------------------------
            | Priorité
            |--------------------------------------------------------------------------
            */

            $table->enum('priority', [
                'low',
                'normal',
                'high',
                'urgent',
            ])->default('normal');

            /*
            |--------------------------------------------------------------------------
            | Canal d'origine
            |--------------------------------------------------------------------------
            */

            $table->enum('channel', [
                'web',
                'widget',
                'whatsapp',
                'email',
                'phone',
            ])->default('web');

            /*
            |--------------------------------------------------------------------------
            | SLA
            |--------------------------------------------------------------------------
            */

            $table->timestamp('sla_due_at')
                ->nullable();

            $table->timestamp('first_response_at')
                ->nullable();

            /*
            |--------------------------------------------------------------------------
            | Résolution
            |--------------------------------------------------------------------------
            */

            $table->text('resolution')
                ->nullable();

            $table->timestamp('resolved_at')
                ->nullable();

            $table->timestamp('closed_at')
                ->nullable();

            /*
            |--------------------------------------------------------------------------
            | Dates
            |--------------------------------------------------------------------------
            */

            $table->timestamps();

            /*
            |--------------------------------------------------------------------------
            | Index
            |--------------------------------------------------------------------------
            */

            $table->index([
                'organization_id',
                'status',
            ]);

            $table->index([
                'organization_id',
                'priority',
            ]);

            $table->index([
                'organization_id',
                'assigned_to',
            ]);

            $table->index([
                'organization_id',
                'client_id',
            ]);

            $table->index([
                'organization_id',
                'created_at',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
