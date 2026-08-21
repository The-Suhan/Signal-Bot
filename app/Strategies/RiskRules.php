<?php

namespace App\Strategies;

/**
 * Tüm stratejiler için ZORUNLU risk kuralları. Hiçbir strateji bu kuralları
 * ezemez — bir SignalCandidate bu kurallara uymuyorsa sinyale dönüşmez.
 *
 *  - SL her zaman sabit 40 pip (2026-08-21 performans analizi sonrası
 *    60'tan düşürüldü — 1.5 haftalık gerçek veriyle doğrulandı: EMA Cross
 *    expectancy 0.0526R->0.1071R (~2x), SR Breakout 0.1290R->0.3291R (~2.5x))
 *  - TP en az 100 pip olmalı (stratejiler daha büyüğünü isteyebilir, ama azını isteyemez)
 */
class RiskRules
{
    /** XAUUSD için 1 pip = 0.1 fiyat birimi (örn. 4351.0 -> 4351.1 = 1 pip) */
    public const PIP_SIZE = 0.1;

    public const FIXED_SL_PIPS = 40;

    public const MIN_TP_PIPS = 100;

    public static function isValid(float $tpPips): bool
    {
        return $tpPips >= self::MIN_TP_PIPS;
    }

    public static function slPriceFor(string $direction, float $entryPrice): float
    {
        $offset = self::FIXED_SL_PIPS * self::PIP_SIZE;

        return $direction === 'buy' ? $entryPrice - $offset : $entryPrice + $offset;
    }

    public static function tpPriceFor(string $direction, float $entryPrice, float $tpPips): float
    {
        $offset = $tpPips * self::PIP_SIZE;

        return $direction === 'buy' ? $entryPrice + $offset : $entryPrice - $offset;
    }
}
