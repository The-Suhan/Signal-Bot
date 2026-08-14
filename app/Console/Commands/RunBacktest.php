<?php

namespace App\Console\Commands;

use App\Models\Backtest;
use App\Models\Candle;
use App\Models\Strategy;
use App\Services\Backtesting\BacktestEngine;
use App\Strategies\CandleSeries;
use App\Strategies\EmaCrossStrategy;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('backtest:run
    {--symbol=XAUUSD}
    {--timeframe=1m}
    {--days=30}
    {--fast=12}
    {--slow=26}
    {--tp=100}
    {--save : sonucu backtests tablosuna kaydet}')]
#[Description('EMA Cross stratejisini geçmiş candles verisiyle replay edip win rate/expectancy/max drawdown hesaplar')]
class RunBacktest extends Command
{
    public function handle(BacktestEngine $engine): int
    {
        $symbol = $this->option('symbol');
        $timeframe = $this->option('timeframe');
        $days = (int) $this->option('days');

        $parameters = [
            'fast_period' => (int) $this->option('fast'),
            'slow_period' => (int) $this->option('slow'),
            'tp_pips' => (float) $this->option('tp'),
        ];

        $periodEnd = now();
        $periodStart = $periodEnd->copy()->subDays($days);

        $candles = Candle::query()
            ->symbol($symbol)
            ->timeframe($timeframe)
            ->whereBetween('opened_at', [$periodStart, $periodEnd])
            ->orderBy('opened_at')
            ->get();

        $this->info("{$candles->count()} adet {$timeframe} mumu bulundu ({$symbol}, son {$days} gün).");

        if ($candles->count() < 60) {
            $this->warn('Yetersiz mum verisi — anlamlı bir backtest için genelde birkaç yüz bar gerekir. Yine de deneniyor...');
        }

        $series = CandleSeries::fromCollection($candles);
        $strategy = new EmaCrossStrategy($parameters);
        $result = $engine->run($strategy, $series);

        $this->table(['Metrik', 'Değer'], [
            ['Toplam Sinyal', $result->totalSignals],
            ['Kazanan', $result->wins],
            ['Kaybeden', $result->losses],
            ['Win Rate %', $result->winRate],
            ['Expectancy (R)', $result->expectancy],
            ['Max Drawdown (R)', $result->maxDrawdown],
            ['Ort. Planlanan R:R', $result->avgRr],
        ]);

        if ($this->option('save')) {
            $strategyRecord = Strategy::firstOrCreate(
                ['class' => EmaCrossStrategy::class],
                ['name' => 'EMA Cross', 'parameters' => $parameters, 'is_active' => false]
            );

            Backtest::create([
                'strategy_id' => $strategyRecord->id,
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'win_rate' => $result->winRate,
                'expectancy' => $result->expectancy,
                'max_drawdown' => $result->maxDrawdown,
                'total_signals' => $result->totalSignals,
                'wins' => $result->wins,
                'losses' => $result->losses,
            ]);

            $this->info('Sonuç backtests tablosuna kaydedildi.');
        }

        return self::SUCCESS;
    }
}
