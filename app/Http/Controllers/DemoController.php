<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use Illuminate\Support\Str;

/**
 * Page de démonstration d'une entreprise.
 *
 * Chaque entreprise dispose de son propre lien pour essayer son widget
 * sur un site fictif, avec ses couleurs, son logo et sa base de
 * connaissances réelles.
 *
 * L'adresse repose sur le jeton du widget plutôt que sur le nom de
 * l'entreprise. Un nom se devine — « /demo/boutique-awa » — et chaque
 * message envoyé depuis cette page consomme les réponses du client.
 * Le jeton, lui, ne se devine pas.
 *
 * Il n'expose rien de plus qu'avant : ce même jeton figure déjà en
 * clair dans le code du widget posé sur le site du client.
 */
class DemoController extends Controller
{
    public function show(string $token)
    {
        $organization = Organization::query()
            ->where('widget_token', $token)
            ->first();

        abort_unless($organization, 404);

        /*
         * Une entreprise suspendue ne doit pas pouvoir faire tourner son
         * assistant par une porte dérobée.
         */
        abort_if($organization->status === 'suspended', 404);

        $branding = $organization->branding();

        $initials = collect(preg_split('/\s+/', $branding['name']))
            ->filter()
            ->take(2)
            ->map(fn ($word) => Str::upper(Str::substr($word, 0, 1)))
            ->implode('') ?: '?';

        return view('demo', [
            'token' => $organization->widget_token,
            'branding' => $branding,
            'initials' => $initials,
        ]);
    }
}
