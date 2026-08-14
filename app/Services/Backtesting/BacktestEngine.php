<?php

namespace App\Services\Backtesting;

use App\Strategies\CandleSeries;
use App\Strategies\RiskRules;
use App\Strategies\StrategyInterface;
use Illuminate\Support\Collection;

/**
 * Geçmiş mum verisini bir stratejiye "replay" eder (walk-forward): her barda
 * o ana kadarki veriyle strateji tekrar değerlendirilir, tıpkı canlı ortamda
 * olacağı gibi. Aynı anda tek pozisyon açık tutulur; TP/SL barın high/low'una
 * göre kontrol edilir (aynı barda ikisine de değinilirse muhafazakâr
 * davranılıp SL öncelikli sayılır).
 */
class BacktestEngine
{
    public function run(StrategyInterface $strategy, CandleSeries $series, int $warmup = 30): BacktestResult
    {
        $candles = $series->all()->values();
        $total = $candles->count();

        $trades = collect();
        $openTrade = null;

        for ($i = max($warmup, 1); $i < $total; $i++) {
            $candle = $candles[$i];

            if ($openTrade) {
                $outcome = $this->checkExit($openTrade, $candle);

                if ($outcome !== null) {
                    $trades->push($outcome);
                    $openTrade = null;
                }

                continue; // pozisyon açıkken yeni sinyal aranmaz
            }

            $slice = CandleSeries::fromCollection($candles->slice(0, $i + 1));
            $candidate = $strategy->evaluate($slice);

            if (! $candidate || ! RiskRules::isValid($candidate->tpPips)) {
                continue;
            }

            $openTrade = [
                'direction' => $candidate->direction,
                'sl' => RiskRules::slPriceFor($candidate->direction, $candidate->entryPrice),
                'tp' => RiskRules::tpPriceFor($candidate->direction, $candidate->entryPrice, $candidate->tpPips),
                'planned_r' => $candidate->tpPips / $candidate->slPips,
            ];
        }

        return BacktestResult::fromTrades($trades);
    }

    /**
     * @return array{won: bool, r: float, planned_r: float}|null
     */
    private function checkExit(array $openTrade, $candle): ?array
    {
        $high = (float) $candle->high;
        $low = (float) $candle->low;

        $hitSl = $openTrade['direction'] === 'buy'
            ? $low <= $openTrade['sl']
            : $high >= $openTrade['sl'];

        if ($hitSl) {
            return ['won' => false, 'r' => -1.0, 'planned_r' => $openTrade['planned_r']];
        }

        $hitTp = $openTrade['direction'] === 'buy'
            ? $high >= $openTrade['tp']
            : $low <= $openTrade['tp'];

        if ($hitTp) {
            return ['won' => true, 'r' => $openTrade['planned_r'], 'planned_r' => $openTrade['planned_r']];
        }

        return null;
    }
}
