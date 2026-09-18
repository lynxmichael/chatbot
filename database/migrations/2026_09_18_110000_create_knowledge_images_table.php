<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Images rattachées aux fiches de connaissances.
     *
     * Une chambre d'hôtel ou un plat se vendent en images, pas en
     * descriptions. L'assistant peut donc joindre des photos à ses
     * réponses, à condition qu'elles soient rattachées à une fiche :
     * il ne choisit jamais une image au hasard, il montre celles que
     * l'entreprise a associées au sujet.
     */
    public function up(): void
    {
        Schema::create('knowledge_images', function (Blueprint $table) {
            $table->id();

            $table->foreignId('organization_id')
                ->constrained('organizations')
                ->cascadeOnDelete();

            $table->foreignId('knowledge_base_id')
                ->constrained('knowledge_bases')
                ->cascadeOnDelete();

            $table->string('path');

            /*
             * Légende montrée au client sous l'image, et lue par
             * l'assistant pour savoir ce que l'image représente.
             */
            $table->string('caption')->nullable();

            $table->unsignedSmallInteger('position')->default(0);

            $table->timestamps();

            $table->index(['organization_id', 'knowledge_base_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_images');
    }
};
