<?php

use App\Http\Controllers\AgentController;
use App\Http\Controllers\AiController;
use App\Http\Controllers\AutopilotController;
use App\Http\Controllers\CallDeskController;
use App\Http\Controllers\KnowledgeBaseController;
use App\Http\Controllers\Admin\PlatformController;
use App\Http\Controllers\BrandingController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\CallController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TicketController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Page d'accueil
|--------------------------------------------------------------------------
*/

/*
|--------------------------------------------------------------------------
| Page d'accueil
|--------------------------------------------------------------------------
|
| La vitrine du produit. Elle remplace la page de démonstration de
| Laravel, qui affichait son logo, les liens vers sa documentation et
| les versions de PHP — autrement dit, rien qui parle au client.
|
| Un utilisateur déjà connecté n'a rien à y faire : il est envoyé
| directement sur sa console.
|
*/

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('dashboard');
    }

    $currency = config('ai.billing.currency', 'XOF');

    return Inertia::render('Welcome', [
        'canRegister' => Route::has('register'),

        'plans' => collect(config('ai.plans', []))
            ->map(fn (array $plan, string $name) => [
                'name' => $name,
                'label' => $plan['label'] ?? ucfirst($name),
                'price' => (int) ($plan['price'] ?? 0),
                'pitch' => $plan['pitch'] ?? null,
                'currency' => $currency,
            ])
            ->values(),
    ]);
})->name('home');

/*
|--------------------------------------------------------------------------
| Page de test du widget
|--------------------------------------------------------------------------
|
| Les routes API du widget sont déclarées uniquement dans routes/api.php.
| Elles doivent rester hors du middleware « web » pour être appelables
| depuis un site tiers sans jeton CSRF.
|
*/

Route::get('/widget-test', function () {
    return view('widget-test');
})->name('widget.test');

/*
|--------------------------------------------------------------------------
| Démonstration par entreprise
|--------------------------------------------------------------------------
|
| Un lien propre à chaque entreprise pour essayer son widget réel.
| Public, mais non devinable : il repose sur le jeton du widget.
|
*/

Route::get('/demo/{token}', [\App\Http\Controllers\DemoController::class, 'show'])
    ->where('token', '[A-Za-z0-9]{20,}')
    ->middleware('throttle:60,1')
    ->name('demo.show');

/*
|--------------------------------------------------------------------------
| Espace authentifié
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'verified'])->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Tableau de bord
    |--------------------------------------------------------------------------
    */

    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->name('dashboard');

    /*
    |--------------------------------------------------------------------------
    | Clients
    |--------------------------------------------------------------------------
    */

    Route::resource('clients', ClientController::class);

    /*
    |--------------------------------------------------------------------------
    | Agents
    |--------------------------------------------------------------------------
    */

    Route::resource('agents', AgentController::class)
        ->except(['show']);

    Route::patch('/agents/{agent}/toggle', [AgentController::class, 'toggle'])
        ->name('agents.toggle');

    /*
    |--------------------------------------------------------------------------
    | Appels
    |--------------------------------------------------------------------------
    */

    Route::resource('calls', CallController::class);

    /*
    |--------------------------------------------------------------------------
    | Tickets / Réclamations
    |--------------------------------------------------------------------------
    */

    Route::resource('tickets', TicketController::class);

    /*
    |--------------------------------------------------------------------------
    | Conversations
    |--------------------------------------------------------------------------
    */

    Route::get('/conversations', [ConversationController::class, 'index'])
        ->name('conversations.index');

    Route::patch('/conversations/{conversation}', [ConversationController::class, 'update'])
        ->name('conversations.update');

    Route::post('/conversations/{conversation}/messages', [ConversationController::class, 'store'])
        ->name('conversations.messages.store');

    Route::post('/conversations/{conversation}/ai-test', [AiController::class, 'test'])
        ->name('conversations.ai-test');

    Route::get('/conversations/{conversation}', [ConversationController::class, 'show'])
        ->name('conversations.show');

    /*
    |--------------------------------------------------------------------------
    | Administration de la plateforme
    |--------------------------------------------------------------------------
    |
    | Réservé aux administrateurs, au-dessus des organisations.
    |
    */

    Route::middleware('super-admin')
        ->prefix('admin')
        ->name('admin.')
        ->group(function () {

            Route::get('/', [PlatformController::class, 'index'])
                ->name('index');

            Route::get('/payments', [PlatformController::class, 'payments'])
                ->name('payments');

            Route::post('/payments/{payment}/confirm', [PlatformController::class, 'confirmPayment'])
                ->name('payments.confirm');

            Route::post('/payments/{payment}/reject', [PlatformController::class, 'rejectPayment'])
                ->name('payments.reject');

            Route::post('/organizations', [PlatformController::class, 'storeOrganization'])
                ->name('organizations.store');

            Route::post('/organizations/{organization}/plan', [PlatformController::class, 'changePlan'])
                ->name('organizations.plan');

            Route::get('/settings', [PlatformController::class, 'settings'])
                ->name('settings');

            Route::patch('/settings', [PlatformController::class, 'updateSettings'])
                ->name('settings.update');
        });

    /*
    |--------------------------------------------------------------------------
    | Identité visuelle
    |--------------------------------------------------------------------------
    */

    Route::get('/branding', [BrandingController::class, 'index'])
        ->name('branding.index');

    Route::post('/branding', [BrandingController::class, 'update'])
        ->name('branding.update');

    /*
    |--------------------------------------------------------------------------
    | Abonnement
    |--------------------------------------------------------------------------
    */

    Route::prefix('subscription')->name('subscription.')->group(function () {

        Route::get('/', [SubscriptionController::class, 'index'])
            ->name('index');

        Route::post('/checkout', [SubscriptionController::class, 'checkout'])
            ->name('checkout');

        Route::get('/return', [SubscriptionController::class, 'return'])
            ->name('return');
    });

    /*
    |--------------------------------------------------------------------------
    | Base de connaissances
    |--------------------------------------------------------------------------
    |
    | C'est la matière première de l'assistant : sans fiches, il ne sait
    | rien répondre. Réservé au responsable.
    |
    */

    Route::prefix('knowledge')->name('knowledge.')->group(function () {

        Route::get('/', [KnowledgeBaseController::class, 'index'])
            ->name('index');

        Route::get('/import', [KnowledgeBaseController::class, 'importForm'])
            ->name('import');

        Route::post('/import', [KnowledgeBaseController::class, 'import'])
            ->name('import.store');

        Route::get('/create', [KnowledgeBaseController::class, 'create'])
            ->name('create');

        Route::post('/', [KnowledgeBaseController::class, 'store'])
            ->name('store');

        Route::get('/{knowledge}/edit', [KnowledgeBaseController::class, 'edit'])
            ->name('edit');

        Route::put('/{knowledge}', [KnowledgeBaseController::class, 'update'])
            ->name('update');

        Route::patch('/{knowledge}/toggle', [KnowledgeBaseController::class, 'toggle'])
            ->name('toggle');

        Route::delete('/{knowledge}', [KnowledgeBaseController::class, 'destroy'])
            ->name('destroy');

        /*
         * Photos illustrant une fiche.
         */

        Route::post('/{knowledge}/images', [KnowledgeBaseController::class, 'addImages'])
            ->name('images.store');

        Route::patch('/images/{image}', [KnowledgeBaseController::class, 'updateImage'])
            ->name('images.update');

        Route::delete('/images/{image}', [KnowledgeBaseController::class, 'destroyImage'])
            ->name('images.destroy');
    });

    /*
    |--------------------------------------------------------------------------
    | Poste téléphonique de l'agent
    |--------------------------------------------------------------------------
    |
    | Interrogées en boucle par la console pour faire sonner le poste.
    |
    */

    Route::prefix('call-desk')->name('call-desk.')->group(function () {

        Route::get('/incoming', [CallDeskController::class, 'incoming'])
            ->name('incoming');

        Route::post('/{call}/accept', [CallDeskController::class, 'accept'])
            ->name('accept');

        Route::post('/{call}/decline', [CallDeskController::class, 'decline'])
            ->name('decline');

        Route::post('/{call}/hang-up', [CallDeskController::class, 'hangUp'])
            ->name('hang-up');
    });

    /*
    |--------------------------------------------------------------------------
    | Autopilot IA
    |--------------------------------------------------------------------------
    |
    | Réservé au propriétaire de l'organisation : c'est ici que se règle
    | ce que l'IA a le droit de faire, que se valident ses actions et que
    | se consultent les alertes de supervision.
    |
    */

    Route::prefix('autopilot')->name('autopilot.')->group(function () {

        Route::get('/', [AutopilotController::class, 'index'])
            ->name('index');

        Route::patch('/', [AutopilotController::class, 'update'])
            ->name('update');

        Route::get('/approvals', [AutopilotController::class, 'approvals'])
            ->name('approvals');

        Route::post('/approvals/{action}/approve', [AutopilotController::class, 'approve'])
            ->name('approvals.approve');

        Route::post('/approvals/{action}/reject', [AutopilotController::class, 'reject'])
            ->name('approvals.reject');

        Route::get('/insights', [AutopilotController::class, 'insights'])
            ->name('insights');

        Route::post('/insights/{insight}/acknowledge', [AutopilotController::class, 'acknowledgeInsight'])
            ->name('insights.acknowledge');

        Route::post('/insights/{insight}/resolve', [AutopilotController::class, 'resolveInsight'])
            ->name('insights.resolve');
    });

    /*
    |--------------------------------------------------------------------------
    | Notifications
    |--------------------------------------------------------------------------
    */

    Route::post('/notifications/{notification}/read', [NotificationController::class, 'read'])
        ->name('notifications.read');

    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])
        ->name('notifications.read-all');
});

/*
|--------------------------------------------------------------------------
| Profil utilisateur
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])
        ->name('profile.edit');

    Route::patch('/profile', [ProfileController::class, 'update'])
        ->name('profile.update');

    Route::delete('/profile', [ProfileController::class, 'destroy'])
        ->name('profile.destroy');
});

/*
|--------------------------------------------------------------------------
| Authentification Breeze
|--------------------------------------------------------------------------
*/

require __DIR__ . '/auth.php';
