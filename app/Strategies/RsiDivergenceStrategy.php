<?php

namespace App\Strategies;

/**
 * Fiyat ile RSI arasındaki uyumsuzluğu (divergence) arar:
 *   - Bearish divergence: fiyat daha yüksek bir zirve yaparken RSI daha
 *     düşük bir zirve yapıyorsa -> momentum zayıflıyor -> sell.
 *   - Bullish divergence: fiyat daha düşük bir dip yaparken RSI daha
 *     yüksek bir dip yapıyorsa -> momentum güçleniyor -> buy.
 *
 * Sadece serinin SON birkaç barı içinde oluşan (taze) divergence'lar
 * sinyale dönüşür — eski/bayat bir uyumsuzluk yakalanmaz.
 *
 * parameters (strategies.parameters json):
 *   rsi_period     (int, default 14)
 *   pivot_width    (int, default 2)   — pivot tespiti için her yandaki bar sayısı
 *   lookback       (int, default 40)  — divergence aranacak pencere genişliği
 *   confirm_window (int, default 3)   — ikinci pivot serinin son kaç barı içinde olmalı
 *   tp_pips        (float, default 100)
 */
class RsiDivergenceStrategy implements StrategyInterface
{
    private int $rsiPeriod;

    private int $pivotWidth;

    private int $lookback;

    private int $confirmWindow;

    private float $tpPips;

    public function __construct(array $parameters = [])
    {
        $this->rsiPeriod = (int) ($parameters['rsi_period'] ?? 14);
        $this->pivotWidth = (int) ($parameters['pivot_width'] ?? 2);
        $this->lookback = (int) ($parameters['lookback'] ?? 40);
        $this->confirmWindow = (int) ($parameters['confirm_window'] ?? 3);
        $this->tpPips = (float) ($parameters['tp_pips'] ?? RiskRules::MIN_TP_PIPS);
    }

    public function evaluate(CandleSeries $candles): ?SignalCandidate
    {
        if (! RiskRules::isValid($this->tpPips)) {
            return null;
        }

        $minBars = $this->rsiPeriod + $this->lookback;

        if ($candles->count() < $minBars) {
            return null;
        }

        $n = $candles->count();
        $windowStart = max(0, $n - $this->lookback);

        $closes = $candles->closes();
        $highs = $candles->highs();
        $lows = $candles->lows();
        $rsi = $candles->rsi($this->rsiPeriod);

        $direction = $this->detectBearish($highs, $rsi, $windowStart, $n)
            ? 'sell'
            : ($this->detectBullish($lows, $rsi, $windowStart, $n) ? 'buy' : null);

        if ($direction === null) {
            return null;
        }

        $entry = (float) $candles->latest()->close;

        return new SignalCandidate(
            direction: $direction,
            entryPrice: $entry,
            slPips: RiskRules::FIXED_SL_PIPS,
            tpPips: $this->tpPips,
            confidencePct: 65.0,
            meta: [
                'strategy' => 'rsi_divergence',
                'rsi_period' => $this->rsiPeriod,
                'rsi_last' => round(end($rsi) ?? 0, 2),
            ],
        );
    }

    private function detectBearish(array $highs, array $rsi, int $from, int $to): bool
    {
        $pivots = array_values(array_filter(
            Indicators::pivotHighs(array_slice($highs, $from, $to - $from), $this->pivotWidth),
            fn ($i) => $rsi[$i + $from] !== null
        ));

        if (count($pivots) < 2) {
            return false;
        }

        [$p1, $p2] = array_slice($pivots, -2);
        $p1 += $from;
        $p2 += $from;

        if ($p2 < $to - $this->confirmWindow) {
            return false; // en son pivot çok eski, taze değil
        }

        return $highs[$p2] > $highs[$p1] && $rsi[$p2] < $rsi[$p1];
    }

    private function detectBullish(array $lows, array $rsi, int $from, int $to): bool
    {
        $pivots = array_values(array_filter(
            Indicators::pivotLows(array_slice($lows, $from, $to - $from), $this->pivotWidth),
            fn ($i) => $rsi[$i + $from] !== null
        ));

        if (count($pivots) < 2) {
            return false;
        }

        [$p1, $p2] = array_slice($pivots, -2);
        $p1 += $from;
        $p2 += $from;

        if ($p2 < $to - $this->confirmWindow) {
            return false;
        }

        return $lows[$p2] < $lows[$p1] && $rsi[$p2] > $rsi[$p1];
    }
}
