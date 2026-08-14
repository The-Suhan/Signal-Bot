<?php

namespace App\Strategies;

/**
 * Tüm stratejiler için ZORUNLU risk kuralları. Hiçbir strateji bu kuralları
 * ezemez — bir SignalCandidate bu kurallara uymuyorsa sinyale dönüşmez.
 *
 *  - SL her zaman sabit 60 pip
 *  - TP en az 100 pip olmalı (stratejiler daha büyüğünü isteyebilir, ama azını isteyemez)
 */
class RiskRules
{
    /** XAUUSD için 1 pip = 0.1 fiyat birimi (örn. 4351.0 -> 4351.1 = 1 pip) */
    public const PIP_SIZE = 0.1;

    public const FIXED_SL_PIPS = 60;

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
