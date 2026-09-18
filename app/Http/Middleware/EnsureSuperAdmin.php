<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Réserve l'espace plateforme aux administrateurs.
 *
 * Le contrôle porte sur un attribut dédié, pas sur le rôle : « owner »
 * désigne le responsable d'une entreprise cliente, ce qui n'a rien à
 * voir avec l'exploitation de la plateforme.
 */
class EnsureSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(
            $request->user()?->isSuperAdmin(),
            403,
            'Espace réservé à l\'administration de la plateforme.'
        );

        return $next($request);
    }
}
