<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();

            $table->foreignId('conversation_id')
                ->constrained('conversations')
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->enum('sender_type', [
                'client',
                'agent',
                'ai',
                'system',
            ]);

            $table->text('content');

            $table->string('channel')->default('web');

            $table->boolean('ai_generated')->default(false);

            $table->boolean('ai_processed')->default(false);

            $table->json('metadata')->nullable();

            $table->timestamp('read_at')->nullable();

            $table->timestamps();

            $table->index([
                'conversation_id',
                'created_at',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
