<?php

namespace App\Strategies;

class Indicators
{
    /**
     * Klasik EMA hesaplama: ilk $period değerin SMA'sı ile seed edilir,
     * sonrasında standart EMA formülü uygulanır.
     *
     * @param  float[]  $values
     * @return array<int, float|null> $values ile aynı uzunlukta; ilk ($period-1)
     *                                 eleman null (yeterli veri yok demek)
     */
    public static function ema(array $values, int $period): array
    {
        $count = count($values);
        $result = array_fill(0, $count, null);

        if ($period < 1 || $count < $period) {
            return $result;
        }

        $multiplier = 2 / ($period + 1);
        $seed = array_sum(array_slice($values, 0, $period)) / $period;
        $result[$period - 1] = $seed;

        $prev = $seed;
        for ($i = $period; $i < $count; $i++) {
            $prev = (($values[$i] - $prev) * $multiplier) + $prev;
            $result[$i] = $prev;
        }

        return $result;
    }

    /**
     * Wilder'ın klasik RSI hesaplaması (ilk $period fark için basit ortalama
     * ile seed edilir, sonrasında Wilder smoothing uygulanır).
     *
     * @param  float[]  $closes
     * @return array<int, float|null> $closes ile aynı uzunlukta; ilk $period
     *                                 eleman null
     */
    public static function rsi(array $closes, int $period = 14): array
    {
        $count = count($closes);
        $result = array_fill(0, $count, null);

        if ($count < $period + 1) {
            return $result;
        }

        $gains = [];
        $losses = [];
        for ($i = 1; $i <= $period; $i++) {
            $diff = $closes[$i] - $closes[$i - 1];
            $gains[] = max($diff, 0);
            $losses[] = max(-$diff, 0);
        }

        $avgGain = array_sum($gains) / $period;
        $avgLoss = array_sum($losses) / $period;
        $result[$period] = self::rsiFromAverages($avgGain, $avgLoss);

        for ($i = $period + 1; $i < $count; $i++) {
            $diff = $closes[$i] - $closes[$i - 1];
            $gain = max($diff, 0);
            $loss = max(-$diff, 0);

            $avgGain = (($avgGain * ($period - 1)) + $gain) / $period;
            $avgLoss = (($avgLoss * ($period - 1)) + $loss) / $period;

            $result[$i] = self::rsiFromAverages($avgGain, $avgLoss);
        }

        return $result;
    }

    private static function rsiFromAverages(float $avgGain, float $avgLoss): float
    {
        if ($avgLoss == 0.0) {
            return 100.0;
        }

        $rs = $avgGain / $avgLoss;

        return 100 - (100 / (1 + $rs));
    }

    /**
     * Basit pivot (swing) tespiti: bir bar, kendisinden önceki ve sonraki
     * $width bar'dan daha yüksek (pivot high) ya da daha düşük (pivot low)
     * ise "pivot" sayılır. Serinin en baş/son $width bar'ı hiçbir zaman
     * pivot olamaz (karşılaştıracak yeterli komşusu yok).
     *
     * @param  float[]  $values
     * @return int[] pivot indeksleri, artan sırada
     */
    public static function pivotHighs(array $values, int $width = 2): array
    {
        return self::findPivots($values, $width, fn ($v, $n) => $v > max($n));
    }

    /** @return int[] */
    public static function pivotLows(array $values, int $width = 2): array
    {
        return self::findPivots($values, $width, fn ($v, $n) => $v < min($n));
    }

    /**
     * @param  callable(float, float[]): bool  $isPivot
     * @return int[]
     */
    private static function findPivots(array $values, int $width, callable $isPivot): array
    {
        $count = count($values);
        $pivots = [];

        for ($i = $width; $i < $count - $width; $i++) {
            $neighbors = [
                ...array_slice($values, $i - $width, $width),
                ...array_slice($values, $i + 1, $width),
            ];

            if ($isPivot($values[$i], $neighbors)) {
                $pivots[] = $i;
            }
        }

        return $pivots;
    }
}
