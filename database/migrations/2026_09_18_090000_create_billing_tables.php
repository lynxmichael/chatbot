<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * Abonnement en cours d'une entreprise.
         *
         * Une seule ligne active par organisation. L'historique est
         * conservé : savoir depuis quand un client paie, et ce qu'il
         * payait avant, sert autant au support qu'à la comptabilité.
         */
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('organization_id')
                ->constrained('organizations')
                ->cascadeOnDelete();

            $table->string('plan');

            $table->enum('status', [
                'pending',
                'active',
                'expired',
                'cancelled',
            ])->default('pending');

            $table->unsignedInteger('amount')->default(0);

            $table->string('currency', 3)->default('XOF');

            $table->timestamp('starts_at')->nullable();

            $table->timestamp('ends_at')->nullable();

            /*
             * Empêche de relancer le même client trois fois le jour
             * où son abonnement expire.
             */
            $table->timestamp('expiry_notified_at')->nullable();

            $table->timestamps();

            $table->index(['organization_id', 'status']);
            $table->index(['status', 'ends_at']);
        });

        /*
         * Paiements, aboutis ou non.
         *
         * Les tentatives échouées sont conservées : c'est la première
         * chose qu'on regarde quand un client dit « j'ai payé ».
         */
        Schema::create('payments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('organization_id')
                ->constrained('organizations')
                ->cascadeOnDelete();

            $table->foreignId('subscription_id')
                ->nullable()
                ->constrained('subscriptions')
                ->nullOnDelete();

            $table->foreignId('initiated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('plan');

            $table->unsignedInteger('amount');

            $table->string('currency', 3)->default('XOF');

            $table->string('provider');

            /*
             * Référence que nous transmettons au prestataire, et qui
             * nous revient dans sa notification.
             */
            $table->string('reference')->unique();

            $table->string('provider_reference')->nullable();

            $table->enum('status', [
                'pending',
                'paid',
                'failed',
                'cancelled',
            ])->default('pending');

            $table->string('method')->nullable();

            $table->timestamp('paid_at')->nullable();

            $table->json('payload')->nullable();

            $table->timestamps();

            $table->index(['organization_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('subscriptions');
    }
};
