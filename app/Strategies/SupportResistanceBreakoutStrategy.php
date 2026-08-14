<?php

namespace App\Strategies;

/**
 * Son $lookback bar'ın (mevcut bar hariç) en yükseği direnç, en düşüğü
 * destek kabul edilir. Mevcut bar'ın kapanışı direncin üzerinde kapanırsa
 * yukarı kırılım (buy), desteğin altında kapanırsa aşağı kırılım (sell).
 *
 * parameters (strategies.parameters json):
 *   lookback     (int, default 20)
 *   buffer_pips  (float, default 2)  — yanlış kırılımları filtrelemek için
 *                                      seviyenin bu kadar ötesine geçmesi istenir
 *   tp_pips      (float, default 100)
 */
class SupportResistanceBreakoutStrategy implements StrategyInterface
{
    private int $lookback;

    private float $bufferPips;

    private float $tpPips;

    public function __construct(array $parameters = [])
    {
        $this->lookback = (int) ($parameters['lookback'] ?? 20);
        $this->bufferPips = (float) ($parameters['buffer_pips'] ?? 2);
        $this->tpPips = (float) ($parameters['tp_pips'] ?? RiskRules::MIN_TP_PIPS);
    }

    public function evaluate(CandleSeries $candles): ?SignalCandidate
    {
        if (! RiskRules::isValid($this->tpPips)) {
            return null;
        }

        if ($candles->count() < $this->lookback + 1) {
            return null;
        }

        $n = $candles->count();
        $highs = $candles->highs();
        $lows = $candles->lows();

        // mevcut bar hariç, ondan önceki $lookback bar
        $priorHighs = array_slice($highs, $n - 1 - $this->lookback, $this->lookback);
        $priorLows = array_slice($lows, $n - 1 - $this->lookback, $this->lookback);

        $resistance = max($priorHighs);
        $support = min($priorLows);
        $buffer = $this->bufferPips * RiskRules::PIP_SIZE;

        $close = (float) $candles->latest()->close;

        $direction = null;
        if ($close > $resistance + $buffer) {
            $direction = 'buy';
        } elseif ($close < $support - $buffer) {
            $direction = 'sell';
        }

        if ($direction === null) {
            return null;
        }

        return new SignalCandidate(
            direction: $direction,
            entryPrice: $close,
            slPips: RiskRules::FIXED_SL_PIPS,
            tpPips: $this->tpPips,
            confidencePct: 60.0,
            meta: [
                'strategy' => 'sr_breakout',
                'resistance' => round($resistance, 3),
                'support' => round($support, 3),
            ],
        );
    }
}
