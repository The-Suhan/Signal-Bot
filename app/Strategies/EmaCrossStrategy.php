<?php

namespace App\Strategies;

/**
 * Hızlı EMA'nın yavaş EMA'yı yukarı kesmesi -> buy, aşağı kesmesi -> sell.
 *
 * parameters (strategies.parameters json):
 *   fast_period (int, default 12)
 *   slow_period (int, default 26)
 *   tp_pips     (float, default 100 — RiskRules::MIN_TP_PIPS'ten küçük olamaz)
 */
class EmaCrossStrategy implements StrategyInterface
{
    private int $fastPeriod;

    private int $slowPeriod;

    private float $tpPips;

    public function __construct(array $parameters = [])
    {
        $this->fastPeriod = (int) ($parameters['fast_period'] ?? 12);
        $this->slowPeriod = (int) ($parameters['slow_period'] ?? 26);
        $this->tpPips = (float) ($parameters['tp_pips'] ?? RiskRules::MIN_TP_PIPS);
    }

    public function evaluate(CandleSeries $candles): ?SignalCandidate
    {
        // Zorunlu risk kuralı: bu ayarlarla üretilecek her sinyal en az
        // MIN_TP_PIPS TP taşımalı. Uymuyorsa strateji hiç sinyal üretmesin.
        if (! RiskRules::isValid($this->tpPips)) {
            return null;
        }

        // EMA'nın "seed" olmadan bir önceki bar ile kıyaslanabilmesi için
        // en az slowPeriod + 1 bar gerekir.
        if ($candles->count() < $this->slowPeriod + 1) {
            return null;
        }

        $fastEma = $candles->ema($this->fastPeriod);
        $slowEma = $candles->ema($this->slowPeriod);

        $n = $candles->count();
        $prevFast = $fastEma[$n - 2] ?? null;
        $prevSlow = $slowEma[$n - 2] ?? null;
        $currFast = $fastEma[$n - 1] ?? null;
        $currSlow = $slowEma[$n - 1] ?? null;

        if ($prevFast === null || $prevSlow === null || $currFast === null || $currSlow === null) {
            return null;
        }

        $direction = $this->detectCross($prevFast, $prevSlow, $currFast, $currSlow);

        if ($direction === null) {
            return null;
        }

        $entry = (float) $candles->latest()->close;

        return new SignalCandidate(
            direction: $direction,
            entryPrice: $entry,
            slPips: RiskRules::FIXED_SL_PIPS,
            tpPips: $this->tpPips,
            confidencePct: $this->confidence($currFast, $currSlow),
            meta: [
                'strategy' => 'ema_cross',
                'fast_period' => $this->fastPeriod,
                'slow_period' => $this->slowPeriod,
                'fast_ema' => round($currFast, 3),
                'slow_ema' => round($currSlow, 3),
            ],
        );
    }

    private function detectCross(float $prevFast, float $prevSlow, float $currFast, float $currSlow): ?string
    {
        if ($prevFast <= $prevSlow && $currFast > $currSlow) {
            return 'buy';
        }

        if ($prevFast >= $prevSlow && $currFast < $currSlow) {
            return 'sell';
        }

        return null;
    }

    /**
     * Basit heuristik: EMA'lar arasındaki ayrışma ne kadar büyükse (fiyata
     * oranla) o kadar güçlü bir momentum kabul edilir. 50-90 aralığına clamp'lenir.
     */
    private function confidence(float $currFast, float $currSlow): float
    {
        if ($currSlow == 0.0) {
            return 50.0;
        }

        $spreadPct = abs($currFast - $currSlow) / $currSlow * 100;
        $confidence = 50 + ($spreadPct * 200);

        return round(min(90.0, max(50.0, $confidence)), 2);
    }
}
