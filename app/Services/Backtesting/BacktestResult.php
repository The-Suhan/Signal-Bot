<?php

namespace App\Services\Backtesting;

use Illuminate\Support\Collection;

class BacktestResult
{
    public function __construct(
        public readonly int $totalSignals,
        public readonly int $wins,
        public readonly int $losses,
        public readonly float $winRate,
        public readonly float $expectancy,
        public readonly float $maxDrawdown,
        public readonly float $avgRr,
    ) {}

    /**
     * @param  Collection<int, array{won: bool, r: float, planned_r: float}>  $trades
     */
    public static function fromTrades(Collection $trades): self
    {
        $total = $trades->count();

        if ($total === 0) {
            return new self(0, 0, 0, 0.0, 0.0, 0.0, 0.0);
        }

        $wins = $trades->where('won', true)->count();
        $losses = $total - $wins;
        $winRateFraction = $wins / $total;

        $avgWinR = $wins > 0 ? $trades->where('won', true)->avg('r') : 0.0;

        // Expectancy (R biriminde): kazanma ihtimali * ortalama kazanç R -
        // kaybetme ihtimali * 1R (SL sabit olduğu için her kayıp tam -1R).
        $expectancy = ($winRateFraction * $avgWinR) - ((1 - $winRateFraction) * 1.0);

        $avgRr = $trades->avg('planned_r') ?? 0.0;

        // Equity curve (R biriminde) üzerinden max drawdown.
        $equity = 0.0;
        $peak = 0.0;
        $maxDrawdown = 0.0;

        foreach ($trades as $trade) {
            $equity += $trade['won'] ? $trade['r'] : -1.0;
            $peak = max($peak, $equity);
            $maxDrawdown = max($maxDrawdown, $peak - $equity);
        }

        return new self(
            totalSignals: $total,
            wins: $wins,
            losses: $losses,
            winRate: round($wins / $total * 100, 2),
            expectancy: round($expectancy, 4),
            maxDrawdown: round($maxDrawdown, 4),
            avgRr: round($avgRr, 4),
        );
    }
}
