<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Champs nécessaires aux appels traités par l'IA.
     */
    public function up(): void
    {
        Schema::table('calls', function (Blueprint $table) {
            $table->boolean('ai_handled')
                ->default(false)
                ->after('status');

            $table->string('provider')
                ->nullable()
                ->after('ai_handled');

            $table->string('provider_call_id')
                ->nullable()
                ->after('provider');

            $table->json('transcript')
                ->nullable()
                ->after('notes');

            $table->index(['organization_id', 'provider_call_id']);
        });
    }

    public function down(): void
    {
        Schema::table('calls', function (Blueprint $table) {
            $table->dropIndex(['organization_id', 'provider_call_id']);

            $table->dropColumn([
                'ai_handled',
                'provider',
                'provider_call_id',
                'transcript',
            ]);
        });
    }
};
