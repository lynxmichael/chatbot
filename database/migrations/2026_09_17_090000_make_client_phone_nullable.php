<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Le téléphone d'un client devient facultatif.
     *
     * La colonne était obligatoire, alors que le widget crée des clients
     * à partir d'un simple nom et d'un email : le formulaire ne demande
     * pas de numéro. Toute conversation ou tout appel lancé sans
     * téléphone échouait donc sur une violation de contrainte — c'est
     * pourtant le cas le plus courant sur un canal web.
     *
     * Découvert par la suite de tests, pas en production.
     */
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('phone', 30)->nullable()->change();
        });
    }

    public function down(): void
    {
        /*
         * Les lignes sans téléphone empêcheraient le retour arrière :
         * on leur donne une valeur de remplacement.
         */
        Schema::table('clients', function (Blueprint $table) {
            $table->string('phone', 30)->default('')->nullable(false)->change();
        });
    }
};
