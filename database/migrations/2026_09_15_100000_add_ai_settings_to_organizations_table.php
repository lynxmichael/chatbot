<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Réglages IA propres à chaque organisation.
     *
     * Contenu attendu (toutes les clés sont facultatives, les valeurs
     * manquantes retombent sur config/ai.php) :
     *
     * {
     *   "level": "assist",
     *   "allowed_actions": ["search_knowledge", "create_ticket"],
     *   "tone": "professionnel et chaleureux",
     *   "language": "fr",
     *   "confidence_threshold": 0.6,
     *   "auto_close_after_hours": 72,
     *   "escalate_on_negative_sentiment": true,
     *   "business_name": "MAKOR Telecom",
     *   "persona": "Awa, assistante du service client"
     * }
     */
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->json('ai_settings')
                ->nullable()
                ->after('widget_token');
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn('ai_settings');
        });
    }
};
