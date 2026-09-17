<?php

use App\Http\Controllers\Api\InboundEmailController;
use App\Http\Controllers\Api\VoiceWebhookController;
use App\Http\Controllers\Api\WidgetCallController;
use App\Http\Controllers\Api\WidgetController;
use Illuminate\Support\Facades\Route;

Route::prefix('widget')
    ->middleware('throttle:60,1')
    ->group(function () {

        /*
        |--------------------------------------------------------------------------
        | Chat du widget
        |--------------------------------------------------------------------------
        */

        Route::get('/config', [WidgetController::class, 'config'])
            ->name('widget.config');

        Route::post('/conversations', [WidgetController::class, 'createConversation'])
            ->name('widget.conversations.create');

        Route::post('/conversations/{conversation}/messages', [WidgetController::class, 'sendMessage'])
            ->name('widget.messages.store');

        Route::get('/conversations/{conversation}/messages', [WidgetController::class, 'messages'])
            ->name('widget.messages.index');


        /*
        |--------------------------------------------------------------------------
        | Appels du widget
        |--------------------------------------------------------------------------
        */

        Route::post('/calls', [WidgetCallController::class, 'start'])
            ->name('widget.calls.start');

        /*
         * Le décroché ne se fait plus depuis le widget : c'est l'agent
         * qui accepte l'appel depuis sa console (routes/web.php).
         */

        Route::post('/calls/{call}/end', [WidgetCallController::class, 'end'])
            ->name('widget.calls.end');

        Route::get('/calls/{call}/status', [WidgetCallController::class, 'status'])
            ->name('widget.calls.status');
    });

/*
|--------------------------------------------------------------------------
| Canal téléphonique
|--------------------------------------------------------------------------
|
| Webhooks appelés par l'opérateur téléphonique (format TwiML).
| Le jeton de l'organisation fait office d'identification.
|
| À configurer côté Twilio, sur le numéro entrant :
|
|   Voix   -> POST https://ton-domaine/api/voice/{token}/incoming
|   Statut -> POST https://ton-domaine/api/voice/{token}/status
|
*/

Route::prefix('voice/{token}')
    ->middleware(['throttle:120,1', 'twilio.signature'])
    ->group(function () {

        Route::post('/incoming', [VoiceWebhookController::class, 'incoming'])
            ->name('voice.incoming');

        Route::post('/handle', [VoiceWebhookController::class, 'handle'])
            ->name('voice.handle');

        Route::post('/status', [VoiceWebhookController::class, 'status'])
            ->name('voice.status');
    });

/*
|--------------------------------------------------------------------------
| Canal email
|--------------------------------------------------------------------------
|
| Webhook de réception, à brancher sur Postmark, Mailgun ou SendGrid :
|
|   POST https://ton-domaine/api/email/{token}/inbound
|
*/

Route::post('/email/{token}/inbound', [InboundEmailController::class, 'store'])
    ->middleware('throttle:120,1')
    ->name('email.inbound');
