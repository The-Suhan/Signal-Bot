<?php

namespace App\Console\Commands;

use App\Models\Candle;
use App\Models\Strategy;
use App\Services\Backtesting\BacktestEngine;
use App\Services\Backtesting\BacktestResult;
use App\Strategies\CandleSeries;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

#[Signature('strategies:optimize
    {--symbol=XAUUSD}
    {--timeframe=1m}
    {--days=30}
    {--min-signals=10 : Bir stratejinin "aktif adayı" sayılması için gereken asgari sinyal sayısı}')]
#[Description('Walk-forward optimizasyon: kayıtlı tüm stratejileri son N günün verisiyle backtest eder, en iyi performans göstereni is_active=true yapar (diğerlerini false).')]
class OptimizeStrategies extends Command
{
    public function handle(BacktestEngine $engine): int
    {
        $this->syncRegistry();

        $symbol = $this->option('symbol');
        $timeframe = $this->option('timeframe');
        $days = (int) $this->option('days');
        $minSignals = (int) $this->option('min-signals');

        $periodEnd = now();
        $periodStart = $periodEnd->copy()->subDays($days);

        $candles = Candle::query()
            ->symbol($symbol)
            ->timeframe($timeframe)
            ->whereBetween('opened_at', [$periodStart, $periodEnd])
            ->orderBy('opened_at')
            ->get();

        $this->info("{$candles->count()} adet {$timeframe} mumu bulundu ({$symbol}, son {$days} gün).");

        $series = CandleSeries::fromCollection($candles);

        $rows = [];
        // optimization_enabled=false olan stratejiler (örn. tutarsız/negatif
        // edge nedeniyle havuzdan çıkarılan RSI Divergence) hiç backtest
        // edilmez ve kazanamaz — bkz. 2026-08-21 performans analizi.
        $strategies = Strategy::where('optimization_enabled', true)->get();

        $excluded = Strategy::where('optimization_enabled', false)->pluck('name');
        if ($excluded->isNotEmpty()) {
            $this->line('Havuz dışı (optimization_enabled=false): '.$excluded->implode(', '));
        }

        foreach ($strategies as $strategy) {
            $result = $engine->run($strategy->makeInstance(), $series);

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

            $rows[] = [
                'strategy' => $strategy,
                'result' => $result,
                'eligible' => $result->totalSignals >= $minSignals,
            ];

            $this->line(sprintf(
                '%-30s sinyal=%-4d winrate=%6.2f%% expectancy=%7.4fR maxdd=%6.4fR %s',
                $strategy->name,
                $result->totalSignals,
                $result->winRate,
                $result->expectancy,
                $result->maxDrawdown,
                $result->totalSignals >= $minSignals ? '' : '(yetersiz sinyal, aday değil)'
            ));
        }

        $winner = $this->pickWinner($rows);

        // Scheduler bu komutun konsol çıktısını /dev/null'a yönlendiriyor —
        // bu yüzden "neden bu strateji seçildi/seçilmedi" kalıcı olarak
        // SADECE burada, Log::info ile kaydediliyor (08-18 gecesi hangi
        // stratejinin neden aktif olduğunun logdan tespit edilemediği olay
        // sonrası eklendi).
        $summary = collect($rows)->map(fn ($r) => sprintf(
            '%s(sinyal=%d,exp=%.4f%s)',
            $r['strategy']->name,
            $r['result']->totalSignals,
            $r['result']->expectancy,
            $r['eligible'] ? '' : ',yetersiz'
        ))->implode(' | ');

        if (! $winner) {
            $this->warn('Hiçbir strateji asgari sinyal eşiğini geçemedi — mevcut aktif strateji değiştirilmedi.');
            Log::info('strategies:optimize: kazanan yok (asgari sinyal eşiği geçilemedi), aktif strateji değişmedi. Havuz dışı: '.($excluded->implode(', ') ?: '(yok)')." Sonuçlar: {$summary}");

            return self::SUCCESS;
        }

        Strategy::query()->update(['is_active' => false]);
        $winner['strategy']->update(['is_active' => true]);

        $this->info(sprintf(
            "\n🏆 Kazanan: %s (expectancy=%.4fR, winrate=%.2f%%, %d sinyal) -> is_active=true",
            $winner['strategy']->name,
            $winner['result']->expectancy,
            $winner['result']->winRate,
            $winner['result']->totalSignals
        ));

        Log::info(sprintf(
            'strategies:optimize: 🏆 %s kazandı (expectancy=%.4fR, winrate=%.2f%%, %d sinyal) -> is_active=true. Havuz dışı: %s. Tüm sonuçlar: %s',
            $winner['strategy']->name,
            $winner['result']->expectancy,
            $winner['result']->winRate,
            $winner['result']->totalSignals,
            $excluded->implode(', ') ?: '(yok)',
            $summary
        ));

        return self::SUCCESS;
    }

    /** config/strategies.php'deki bilinen stratejileri DB'de yoksa oluşturur. */
    private function syncRegistry(): void
    {
        foreach (config('strategies.registry', []) as $entry) {
            Strategy::firstOrCreate(
                ['class' => $entry['class']],
                [
                    'name' => $entry['name'],
                    'parameters' => $entry['parameters'],
                    'is_active' => false,
                    'optimization_enabled' => $entry['optimization_enabled'] ?? true,
                ]
            );
        }
    }

    /**
     * Asgari sinyal eşiğini geçen adaylar arasından en yüksek expectancy'ye
     * sahip olanı seçer. Hiçbir aday eşiği geçemezse null döner (mevcut
     * aktif strateji korunur — "yanlış güvenle" strateji değiştirilmez).
     *
     * @param  array<int, array{strategy: Strategy, result: BacktestResult, eligible: bool}>  $rows
     */
    private function pickWinner(array $rows): ?array
    {
        $eligible = array_filter($rows, fn ($r) => $r['eligible']);

        if (empty($eligible)) {
            return null;
        }

        usort($eligible, fn ($a, $b) => $b['result']->expectancy <=> $a['result']->expectancy);

        return $eligible[0];
    }
}
