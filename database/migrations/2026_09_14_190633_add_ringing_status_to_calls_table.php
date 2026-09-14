<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('calls', function (Blueprint $table) {
            $table->enum('status', [
                'ringing',
                'answered',
                'missed',
                'busy',
                'failed',
                'cancelled',
            ])->default('ringing')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('calls', function (Blueprint $table) {
            $table->enum('status', [
                'answered',
                'missed',
                'busy',
                'failed',
                'cancelled',
            ])->default('answered')->change();
        });
    }
};
