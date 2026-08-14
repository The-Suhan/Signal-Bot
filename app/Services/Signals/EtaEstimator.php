<?php

namespace App\Services\Signals;

use Illuminate\Support\Collection;

/**
 * Fiyat momentumuna (son birkaç dakikanın hız/yön eğilimine) dayanarak,
 * bir PENDING sinyalin entry fiyatına kaç dakika içinde ulaşacağını
 * kabaca tahmin eder.
 *
 * Yöntem kasıtlı olarak basit tutuldu: son $momentumWindowMinutes bar'ın
 * kapanışları arasındaki ortalama değişim hızı (fiyat birimi/dakika)
 * hesaplanır, entry'ye olan mesafe bu hıza bölünür. Momentum entry'den
 * UZAKLAŞAN yöndeyse ya da çok zayıfsa (gürültü seviyesinde) güvenilir
 * bir tahmin yapılamayacağından null döner.
 */
class EtaEstimator
{
    public function __construct(
        private readonly int $momentumWindowMinutes = 5,
        private readonly float $minVelocityPerMinute = 0.01,
    ) {}

    /**
     * @param  Collection<int, \App\Models\Candle>  $recentCandles  opened_at ASC sıralı, en az
     *                                                                momentumWindowMinutes+1 bar
     * @return float|null  tahmini dakika; hesaplanamıyorsa null
     */
    public function estimateMinutes(Collection $recentCandles, float $currentPrice, float $entryPrice): ?float
    {
        if ($recentCandles->count() < $this->momentumWindowMinutes + 1) {
            return null;
        }

        $closes = $recentCandles->pluck('close')->map(fn ($v) => (float) $v)->values();

        $latest = $closes->last();
        $past = $closes->get($closes->count() - 1 - $this->momentumWindowMinutes);

        $velocity = ($latest - $past) / $this->momentumWindowMinutes;
        $distance = $entryPrice - $currentPrice;

        if ($distance == 0.0) {
            return 0.0; // zaten değinilmiş durumda (trigger mantığı ayrıca ele alır)
        }

        if (abs($velocity) < $this->minVelocityPerMinute) {
            return null; // momentum gürültü seviyesinde, güvenilir tahmin yok
        }

        $movingTowardEntry = ($distance > 0 && $velocity > 0) || ($distance < 0 && $velocity < 0);

        if (! $movingTowardEntry) {
            return null; // fiyat entry'den uzaklaşıyor
        }

        return abs($distance) / abs($velocity);
    }
}
