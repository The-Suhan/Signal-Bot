<?php

namespace App\Services\Signals;

use App\Models\Signal;
use Illuminate\Support\Carbon;

/**
 * Sinyaller sayfasındaki özet performans kartları ve geçmiş tablosu zaman
 * filtresi için ortak istatistik/aralık hesaplama mantığı.
 *
 * Kural (bilinçli tasarım kararı): win rate SADECE closed_tp/closed_sl
 * sinyalleri üzerinden hesaplanır (payda = wins + losses). "Süresi Doldu"
 * (expired) sinyaller ne kazanç ne kayıp sayılır — win rate hesabına hiç
 * girmez, ayrı bir sayaç (`expired`) olarak raporlanır. `total` ise o
 * dönemde KAPANMIŞ (closed_tp + closed_sl + expired) tüm sinyalleri kapsar.
 */
class SignalStats
{
    /**
     * @return array{total:int, wins:int, losses:int, expired:int, win_rate:float|null, total_pips:int}
     */
    public function summary(?Carbon $from, ?Carbon $to = null): array
    {
        $base = Signal::whereIn('status', ['closed_tp', 'closed_sl', 'expired']);

        if ($from) {
            $base->where('closed_at', '>=', $from);
        }

        if ($to) {
            $base->where('closed_at', '<', $to);
        }

        $wins = (clone $base)->where('status', 'closed_tp')->count();
        $losses = (clone $base)->where('status', 'closed_sl')->count();
        $expired = (clone $base)->where('status', 'expired')->count();

        $tpPips = (int) (clone $base)->where('status', 'closed_tp')->sum('tp_pips');
        $slPips = (int) (clone $base)->where('status', 'closed_sl')->sum('sl_pips');

        $decided = $wins + $losses;

        return [
            'total' => $wins + $losses + $expired,
            'wins' => $wins,
            'losses' => $losses,
            'expired' => $expired,
            'win_rate' => $decided > 0 ? round($wins / $decided * 100, 1) : null,
            'total_pips' => $tpPips - $slPips,
        ];
    }

    /**
     * Verilen dönem anahtarı için [from, to) UTC aralığını döner. Sınırlar
     * kullanıcının gösterim saat dilimine (Asia/Ashgabat) göre hesaplanır ki
     * "bu hafta" / "bu ay" onun gününe göre doğru olsun — DB'de her şey UTC
     * kalır, sadece sınır hesabı yerel saatte yapılıp UTC'ye çevrilir.
     *
     * @return array{0: Carbon|null, 1: Carbon|null}
     */
    public function periodRange(string $key): array
    {
        $tz = config('app.display_timezone');
        $localNow = now()->clone()->setTimezone($tz);

        return match ($key) {
            'today' => [$localNow->clone()->startOfDay()->utc(), null],
            'week' => [$localNow->clone()->startOfWeek()->utc(), null],
            'month' => [$localNow->clone()->startOfMonth()->utc(), null],
            default => [null, null],
        };
    }
}
