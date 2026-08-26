<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/* ---- FEATURE — Verified Payment Escrow (Member 3) ---- */
Schedule::command('escrow:auto-confirm')->hourly();
Schedule::command('escrow:retry-payouts')->everyFifteenMinutes();