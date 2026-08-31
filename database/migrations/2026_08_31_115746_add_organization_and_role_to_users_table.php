<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('organization_id')
                ->nullable()
                ->after('id')
                ->constrained('organizations')
                ->nullOnDelete();

            $table->string('role')->default('agent')->after('password');

            $table->boolean('is_active')->default(true)->after('role');

            $table->timestamp('last_login_at')
                ->nullable()
                ->after('is_active');

            $table->index(['organization_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['organization_id']);
            $table->dropIndex(['users_organization_id_role_index']);

            $table->dropColumn([
                'organization_id',
                'role',
                'is_active',
                'last_login_at',
            ]);
        });
    }
};
