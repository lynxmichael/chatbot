<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Informations utilisées par le routage automatique des tickets.
     *
     * skills : liste de catégories maîtrisées par l'agent
     *          (facturation, livraison, technique...).
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('skills')
                ->nullable()
                ->after('role');

            $table->boolean('is_available')
                ->default(true)
                ->after('is_active');

            $table->unsignedSmallInteger('max_open_tickets')
                ->default(15)
                ->after('is_available');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'skills',
                'is_available',
                'max_open_tickets',
            ]);
        });
    }
};
