<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('organization_id')
                ->constrained('organizations')
                ->cascadeOnDelete();

            $table->foreignId('client_id')
                ->constrained('clients')
                ->cascadeOnDelete();

            $table->foreignId('assigned_to')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('subject')->nullable();

            $table->enum('channel', [
                'web',
                'whatsapp',
                'email',
                'phone',
            ])->default('web');

            $table->enum('status', [
                'open',
                'pending',
                'resolved',
                'closed',
            ])->default('open');

            $table->enum('priority', [
                'low',
                'normal',
                'high',
                'urgent',
            ])->default('normal');

            $table->boolean('ai_enabled')->default(true);

            $table->timestamp('last_message_at')->nullable();

            $table->timestamps();

            $table->index([
                'organization_id',
                'status',
            ]);

            $table->index([
                'organization_id',
                'client_id',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
