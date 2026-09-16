<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Résultat de l'analyse permanente faite par l'IA sur la conversation.
     *
     * Ces champs alimentent le dossier préparé pour l'agent humain,
     * la supervision et les statistiques.
     */
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->string('ai_intent')
                ->nullable()
                ->after('ai_enabled');

            $table->string('ai_sentiment')
                ->nullable()
                ->after('ai_intent');

            $table->text('ai_summary')
                ->nullable()
                ->after('ai_sentiment');

            $table->decimal('ai_confidence', 3, 2)
                ->nullable()
                ->after('ai_summary');

            $table->timestamp('ai_last_run_at')
                ->nullable()
                ->after('ai_confidence');

            $table->index(['organization_id', 'ai_sentiment']);
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropIndex(['organization_id', 'ai_sentiment']);

            $table->dropColumn([
                'ai_intent',
                'ai_sentiment',
                'ai_summary',
                'ai_confidence',
                'ai_last_run_at',
            ]);
        });
    }
};
