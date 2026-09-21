<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
   public function share(Request $request): array
{
    $user = $request->user()?->loadMissing('organization');

    return [
        ...parent::share($request),

        /*
         * Nom du produit, pour que les pages publiques affichent la
         * marque et non celle du framework. Si APP_NAME a gardé sa
         * valeur d'installation, on retombe sur le vrai nom.
         */
        'app' => [
            'name' => config('app.name') === 'Laravel'
                ? 'AI Service Client'
                : config('app.name'),
        ],

        'auth' => [
            'user' => $user,

            /*
             * Droits de l'utilisateur, pour que l'interface n'affiche
             * pas des entrées de menu menant à une erreur 403.
             */
            'abilities' => $user
                ? array_values(array_filter(
                    array_keys(config('roles.abilities', [])),
                    fn (string $ability) => $user->hasAbility($ability)
                ))
                : [],
        ],

        'flash' => [
            'success' => $request->session()->get('success'),
            'error' => $request->session()->get('error'),
            'warning' => $request->session()->get('warning'),
        ],

        'notifications' => fn () => $user
            ? $user->notifications()
                ->latest()
                ->limit(10)
                ->get()
                ->map(function ($notification) {
                    return [
                        'id' => $notification->id,
                        'type' => $notification->type,
                        'data' => $notification->data,
                        'read_at' => $notification->read_at,
                        'created_at' => $notification->created_at?->toISOString(),
                    ];
                })
                ->values()
            : collect(),

        'unread_notifications_count' => fn () => $user
            ? $user->unreadNotifications()->count()
            : 0,
    ];
}
}
