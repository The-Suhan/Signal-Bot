<?php

namespace App\Http\Controllers;

use App\Models\Strategy;
use App\Services\Backtesting\BacktestEngine;
use App\Strategies\CandleSeries;
use App\Models\Candle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class StrategyController extends Controller
{
    public function index(): Response
    {
        $strategies = Strategy::withCount('signals')
            ->with(['backtests' => fn ($q) => $q->latest('period_end')->limit(1)])
            ->orderBy('name')
            ->get()
            ->map(function (Strategy $strategy) {
                $latest = $strategy->backtests->first();

                return [
                    'id' => $strategy->id,
                    'name' => $strategy->name,
                    'class' => $strategy->class,
                    'parameters' => $strategy->parameters,
                    'is_active' => $strategy->is_active,
                    'signals_count' => $strategy->signals_count,
                    'latest_backtest' => $latest ? [
                        'win_rate' => (float) $latest->win_rate,
                        'expectancy' => (float) $latest->expectancy,
                        'max_drawdown' => (float) $latest->max_drawdown,
                        'total_signals' => $latest->total_signals,
                        'wins' => $latest->wins,
                        'losses' => $latest->losses,
                        'period_start' => $latest->period_start,
                        'period_end' => $latest->period_end,
                    ] : null,
                ];
            });

        return Inertia::render('Strategies/Index', [
            'strategies' => $strategies,
        ]);
    }

    public function update(Request $request, Strategy $strategy): RedirectResponse
    {
        $validated = $request->validate([
            'is_active' => 'required|boolean',
            'parameters' => 'nullable|array',
        ]);

        $wasActive = $strategy->is_active;
        $oldParameters = $strategy->parameters;
        $user = $request->user();

        // Aynı anda tek strateji aktif olsun kuralı manuel panelde de korunuyor
        // (walk-forward optimizasyonunun beklediği invaryant ile tutarlı).
        if ($validated['is_active']) {
            Strategy::where('id', '!=', $strategy->id)->update(['is_active' => false]);
        }

        $strategy->update([
            'is_active' => $validated['is_active'],
            'parameters' => $validated['parameters'] ?? $strategy->parameters,
        ]);

        // Walk-forward optimizasyonun otomatik seçimleriyle manuel panel
        // müdahalelerini birbirinden ayırt edebilmek için (bkz. 08-18 gecesi
        // aktivasyon geçmişinin logdan tespit edilemediği olay) her manuel
        // değişiklik kim/ne zaman/ne yaptı bilgisiyle kalıcı olarak loglanır.
        if ($wasActive !== $validated['is_active']) {
            Log::info(sprintf(
                'Manuel panel: %s -> is_active=%s (kullanıcı: %s, önceki: %s)',
                $strategy->name,
                $validated['is_active'] ? 'true' : 'false',
                $user?->email ?? 'bilinmiyor',
                $wasActive ? 'true' : 'false'
            ));
        }

        if ($validated['parameters'] && $validated['parameters'] !== $oldParameters) {
            Log::info(sprintf(
                'Manuel panel: %s parametreleri değişti (kullanıcı: %s): %s -> %s',
                $strategy->name,
                $user?->email ?? 'bilinmiyor',
                json_encode($oldParameters),
                json_encode($validated['parameters'])
            ));
        }

        return back()->with('success', "{$strategy->name} güncellendi.");
    }

    public function runBacktest(Request $request, Strategy $strategy): RedirectResponse
    {
        $validated = $request->validate([
            'days' => 'sometimes|integer|min:1|max:365',
        ]);

        $days = $validated['days'] ?? 30;

        $periodEnd = now();
        $periodStart = $periodEnd->copy()->subDays($days);

        $candles = Candle::query()
            ->symbol('XAUUSD')
            ->timeframe('1m')
            ->whereBetween('opened_at', [$periodStart, $periodEnd])
            ->orderBy('opened_at')
            ->get();

        $series = CandleSeries::fromCollection($candles);
        $instance = $strategy->makeInstance();
        $result = app(BacktestEngine::class)->run($instance, $series);

        $strategy->backtests()->create([
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'win_rate' => $result->winRate,
            'expectancy' => $result->expectancy,
            'max_drawdown' => $result->maxDrawdown,
            'total_signals' => $result->totalSignals,
            'wins' => $result->wins,
            'losses' => $result->losses,
        ]);

        return back()->with('success', "{$strategy->name} için backtest tamamlandı ({$candles->count()} mum, {$result->totalSignals} sinyal).");
    }
}
