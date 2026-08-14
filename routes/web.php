<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MarketDataController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SignalController;
use App\Http\Controllers\StrategyController;
use Illuminate\Support\Facades\Route;

// Ana sayfa artık Breeze'in Welcome sayfasını göstermiyor: giriş yapmış
// kullanıcıyı doğrudan Dashboard'a, yapmamışı login sayfasına yönlendirir.
Route::get('/', function () {
    return redirect()->route(auth()->check() ? 'dashboard' : 'login');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/signals', [SignalController::class, 'index'])->name('signals.index');

    Route::get('/strategies', [StrategyController::class, 'index'])->name('strategies.index');
    Route::patch('/strategies/{strategy}', [StrategyController::class, 'update'])->name('strategies.update');
    Route::post('/strategies/{strategy}/backtest', [StrategyController::class, 'runBacktest'])->name('strategies.backtest');

    // Canlı grafik için hafif JSON polling uçları (Inertia sayfası değil)
    Route::get('/market-data/candles', [MarketDataController::class, 'candles'])->name('market-data.candles');
    Route::get('/market-data/last-price', [MarketDataController::class, 'lastPrice'])->name('market-data.last-price');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
