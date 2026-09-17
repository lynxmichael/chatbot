<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/*
|--------------------------------------------------------------------------
| Fabriques de test
|--------------------------------------------------------------------------
|
| Les modèles du projet n'ont pas tous de factory. Ces fonctions créent
| le strict nécessaire pour les tests, avec les colonnes obligatoires.
|
*/

function makeOrganization(array $attributes = []): \App\Models\Organization
{
    $name = 'Organisation ' . \Illuminate\Support\Str::random(5);

    return \App\Models\Organization::create(array_merge([
        'name' => $name,
        'slug' => \Illuminate\Support\Str::slug($name) . '-' . \Illuminate\Support\Str::random(4),
        'email' => \Illuminate\Support\Str::random(8) . '@example.test',
        'status' => 'active',
        'widget_token' => \Illuminate\Support\Str::random(40),
    ], $attributes));
}

function makeAgent(
    \App\Models\Organization $organization,
    array $attributes = []
): \App\Models\User {
    return \App\Models\User::create(array_merge([
        'organization_id' => $organization->id,
        'name' => 'Agent ' . \Illuminate\Support\Str::random(4),
        'email' => \Illuminate\Support\Str::random(8) . '@example.test',
        'password' => bcrypt('password'),
        'role' => 'agent',
        'is_active' => true,
        'is_available' => true,
        'max_open_tickets' => 15,
    ], $attributes));
}

function makeClient(
    \App\Models\Organization $organization,
    array $attributes = []
): \App\Models\Client {
    return \App\Models\Client::create(array_merge([
        'organization_id' => $organization->id,
        'first_name' => 'Client',
        'last_name' => \Illuminate\Support\Str::random(4),
        'phone' => '+2250700' . random_int(100000, 999999),
        'status' => 'active',
    ], $attributes));
}
