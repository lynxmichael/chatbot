<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class InitialDataSeeder extends Seeder
{
    public function run(): void
    {
        $organization = Organization::updateOrCreate(
            ['slug' => 'ai-service-client'],
            [
                'name' => 'AI Service Client',
                'email' => 'admin@ai-service-client.test',
                'phone' => null,
                'status' => 'active',
            ]
        );

        User::updateOrCreate(
            ['email' => 'admin@ai-service-client.test'],
            [
                'organization_id' => $organization->id,
                'name' => 'Administrateur',
                'password' => 'Admin12345!',
                'role' => 'owner',
                'is_active' => true,
                'last_login_at' => null,
            ]
        );
    }
}
