<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Planification IA
|--------------------------------------------------------------------------
|
| Nécessite un cron unique sur le serveur :
|
| * * * * * cd /chemin/du/projet && php artisan schedule:run >> /dev/null 2>&1
|
*/

/*
 * Relances programmées : toutes les cinq minutes.
 */
Schedule::command('ai:follow-ups')
    ->everyFiveMinutes()
    ->withoutOverlapping();

/*
 * Supervision : tous les quarts d'heure, avec notification
 * des responsables sur les alertes graves.
 */
Schedule::command('ai:supervise --notify')
    ->everyFifteenMinutes()
    ->withoutOverlapping();

/*
 * Clôture des tickets résolus : une fois par heure.
 */
Schedule::command('ai:auto-close')
    ->hourly()
    ->withoutOverlapping();

/*
 * Abonnements : alerte avant échéance, retour en gratuit après.
 * Une fois par jour suffit.
 */
Schedule::command('ai:subscriptions')
    ->dailyAt('07:00')
    ->withoutOverlapping();
