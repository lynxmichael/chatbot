<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Alertes produites par la supervision IA.
     *
     * Le « fingerprint » identifie le problème de manière stable :
     * un même ticket oublié ne crée pas une alerte par passage du scan.
     */
    public function up(): void
    {
        Schema::create('ai_insights', function (Blueprint $table) {
            $table->id();

            $table->foreignId('organization_id')
                ->constrained('organizations')
                ->cascadeOnDelete();

            $table->string('fingerprint');

            $table->string('type');

            $table->enum('severity', [
                'low',
                'medium',
                'high',
                'critical',
            ])->default('medium');

            $table->string('title');

            $table->text('detail')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Objet concerné
            |--------------------------------------------------------------------------
            |
            | subject_type vaut ticket, conversation, call, agent ou client.
            |
            */

            $table->string('subject_type')->nullable();

            $table->unsignedBigInteger('subject_id')->nullable();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('client_id')
                ->nullable()
                ->constrained('clients')
                ->nullOnDelete();

            $table->json('metrics')->nullable();

            $table->enum('status', [
                'open',
                'acknowledged',
                'resolved',
            ])->default('open');

            $table->timestamp('detected_at')->nullable();

            $table->timestamp('resolved_at')->nullable();

            $table->timestamps();

            $table->unique(['organization_id', 'fingerprint']);

            $table->index(['organization_id', 'status', 'severity']);
            $table->index(['organization_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_insights');
    }
};
