<?php

namespace App\Services\MarketData;

use Illuminate\Support\Carbon;

/**
 * XAU/USD gibi forex/emtia enstrümanlarının haftalık kapanış takvimi.
 * Piyasa Cuma ~23:00 UTC'de kapanır, Pazar ~22:00 UTC'de yeniden açılır
 * (broker'dan broker'a birkaç dakika oynayabilir — burada makul/muhafazakâr
 * bir pencere kullanılıyor: gerçek açılıştan biraz önce/sonra "kapalı"
 * sayılması, olası ilk dakikaların ince/volatil likiditesinden yeni sinyal
 * üretilmesini de doğal olarak geciktirir).
 *
 * Tüm karşılaştırmalar UTC üzerinden yapılır (bkz. config/app.php —
 * uygulama içi zaman her zaman UTC, gösterim için display_timezone ayrı).
 */
class MarketHours
{
    private const CLOSE_DAY = Carbon::FRIDAY;

    private const CLOSE_HOUR_UTC = 23;

    private const REOPEN_DAY = Carbon::SUNDAY;

    private const REOPEN_HOUR_UTC = 22;

    public function isOpen(?Carbon $at = null): bool
    {
        return ! $this->isClosed($at);
    }

    public function isClosed(?Carbon $at = null): bool
    {
        $now = ($at ?? now())->clone()->utc();
        $dayOfWeek = $now->dayOfWeek; // Carbon: 0=Pazar ... 6=Cumartesi

        if ($dayOfWeek === Carbon::SATURDAY) {
            return true;
        }

        if ($dayOfWeek === self::CLOSE_DAY && $now->hour >= self::CLOSE_HOUR_UTC) {
            return true;
        }

        if ($dayOfWeek === self::REOPEN_DAY && $now->hour < self::REOPEN_HOUR_UTC) {
            return true;
        }

        return false;
    }
}
