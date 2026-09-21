<?php

use App\Domains\Accounting\Services\MobileMoneyService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Un parent qui ferme l'application après avoir validé sur son téléphone ne
// sonde plus l'opérateur : cette tâche impute les paiements confirmés et
// expire les demandes abandonnées.
Artisan::command('mobile-money:reconcile', function (MobileMoneyService $mobileMoney) {
    $this->info($mobileMoney->reconcilePending().' demande(s) mobile money actualisée(s).');
})->purpose('Actualise les demandes de paiement mobile money en attente');

Schedule::command('mobile-money:reconcile')->everyMinute()->withoutOverlapping();
