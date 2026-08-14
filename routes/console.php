<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Ingestion servisinin yazdığı 1m mumları 5m/15m/1h'a roll-up eder.
Schedule::command('candles:aggregate')
    ->everyMinute()
    ->withoutOverlapping();

// Sinyal state machine: PENDING/TRIGGERED sinyalleri son fiyata göre
// ilerletir, aktif strateji için yeni sinyal adayı arar.
Schedule::command('signals:process')
    ->everyMinute()
    ->withoutOverlapping();

// Walk-forward optimizasyon: her gece tüm stratejileri son 30 günle backtest
// edip en iyi performans göstereni is_active yapar.
Schedule::command('strategies:optimize --days=30')
    ->dailyAt('02:00')
    ->withoutOverlapping();
