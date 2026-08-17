<?php

namespace App\Services\Signals;

use App\Models\Candle;
use App\Models\Signal;
use App\Models\Strategy;
use App\Services\Telegram\TelegramNotifier;
use App\Strategies\CandleSeries;
use App\Strategies\RiskRules;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Sinyal yaşam döngüsünü canlı ortamda ilerleten motor:
 *
 *   PENDING ──(fiyat entry'ye değindi)──▶ TRIGGERED ──(TP/SL vuruldu)──▶ CLOSED_TP / CLOSED_SL
 *      │  └──(5-10dk içinde değineceği tahmin edildi, bir kez)──▶ "yaklaşıyor" bildirimi
 *      └──(süre doldu, entry hiç değinilmedi)──▶ EXPIRED
 *
 * Her çağrıda tek bir "tick" (en güncel fiyat) işlenir: önce açık
 * sinyallerin durumu güncellenir, sonra aktif stratejinin yeni bir sinyal
 * üretip üretmediğine bakılır. Aynı anda sistemde (symbol başına) tek bir
 * açık (pending/triggered) sinyal olması invaryantı korunur — bu,
 * BacktestEngine'in "aynı anda tek pozisyon" varsayımıyla tutarlıdır.
 */
class SignalManager
{
    /** Fiyatın entry_price'a "değindi" sayılması için tolerans (pip). */
    private const ENTRY_TOLERANCE_PIPS = 5;

    /** Bu süre içinde entry'ye değinilmezse sinyal EXPIRED olur. */
    private const PENDING_EXPIRY_MINUTES = 30;

    /** "Yaklaşıyor" bildiriminin gönderileceği tahmini dakika aralığı. */
    private const APPROACHING_MIN_MINUTES = 5;

    private const APPROACHING_MAX_MINUTES = 10;

    /** Yeni sinyal üretmek için strateji değerlendirmesinde kullanılacak bar sayısı. */
    private const EVALUATION_WINDOW = 250;

    public function __construct(
        private readonly TelegramNotifier $notifier,
        private readonly EtaEstimator $etaEstimator,
    ) {}

    public function process(string $symbol, string $timeframe, float $lastPrice): void
    {
        $this->trackOpenPositions($symbol, $timeframe, $lastPrice);
        $this->maybeOpenNewSignal($symbol, $timeframe);
    }

    /**
     * Sadece mevcut açık (pending/triggered) sinyalleri son fiyata göre
     * ilerletir — YENİ sinyal aramaz. Piyasa kapalıyken (hafta sonu) bu
     * metod çağrılmaya devam eder ki açık pozisyonlar takip edilsin; piyasa
     * açılınca kaldığı yerden devam eder. bkz. ProcessSignals command'ı.
     */
    public function trackOpenPositions(string $symbol, string $timeframe, float $lastPrice): void
    {
        $this->resolvePending($symbol, $timeframe, $lastPrice);
        $this->resolveTriggered($symbol, $timeframe, $lastPrice);
    }

    private function resolvePending(string $symbol, string $timeframe, float $lastPrice): void
    {
        $tolerance = self::ENTRY_TOLERANCE_PIPS * RiskRules::PIP_SIZE;

        $pendingSignals = Signal::where('symbol', $symbol)
            ->where('status', 'pending')
            ->get();

        if ($pendingSignals->isEmpty()) {
            return;
        }

        // Momentum hesaplamak için gereken son mumlar tüm pending sinyaller
        // için tek seferde çekiliyor (normalde zaten tek bir açık sinyal olur).
        $recentCandles = $this->recentCandlesForMomentum($symbol, $timeframe);

        $pendingSignals->each(function (Signal $signal) use ($lastPrice, $tolerance, $recentCandles, $timeframe) {
            $expiresAt = CarbonImmutable::parse($signal->created_at)
                ->addMinutes(self::PENDING_EXPIRY_MINUTES);

            if (now()->greaterThan($expiresAt)) {
                $signal->update(['status' => 'expired', 'closed_at' => now()]);
                Log::info("Sinyal #{$signal->id} süresi doldu (entry'ye hiç değinilmedi).");
                $this->notifier->signalExpired($signal);

                return;
            }

            // ÖNCE: sinyal oluşturulduğundan beri kapanmış mumların high/low
            // aralığına bak. Sadece anlık son tick'e bakmak, bir dakika
            // içinde entry'ye değinip geri dönen fiyatı KAÇIRIR (gerçek
            // olay: #17, entry=4401.990 — 01:46 barının low'u 4402.183 ile
            // tolerans içine girdi ama bir sonraki dakikalık kontrolde fiyat
            // zaten uzaklaşmıştı, hiç fark edilmedi). BacktestEngine zaten
            // mum bazlı çalışıyor — canlı motor artık aynı mantığı kullanıyor.
            $touchCandle = $this->findLevelTouch($signal->symbol, $timeframe, $signal->created_at, (float) $signal->entry_price, $tolerance);

            // SONRA (yedek): mum henüz kapanıp yazılmadıysa (birkaç saniyelik
            // gecikme normal) anlık tick de kontrol edilir — gerçek zamanlı
            // bir değinmeyi bir dakika geciktirmemek için.
            $touchedEntry = $touchCandle !== null || abs($lastPrice - (float) $signal->entry_price) <= $tolerance;

            if ($touchedEntry) {
                $triggeredAt = $touchCandle?->opened_at ?? now();
                $signal->update(['status' => 'triggered', 'triggered_at' => $triggeredAt]);
                Log::info("Sinyal #{$signal->id} entry'ye değindi -> TRIGGERED (".($touchCandle ? "mum {$touchCandle->opened_at}" : "anlık fiyat={$lastPrice}").').');
                $this->notifier->signalTriggered($signal->fresh());

                return;
            }

            $this->maybeNotifyApproaching($signal, $recentCandles, $lastPrice);
        });
    }

    /**
     * $since'ten bu yana kapanmış mumlar arasında, [entry-tolerans,
     * entry+tolerans] aralığıyla kesişen İLK mumu bulur (high/low bazlı —
     * bkz. yukarıdaki açıklama). Bulunamazsa null.
     */
    private function findLevelTouch(string $symbol, string $timeframe, $since, float $level, float $tolerance): ?Candle
    {
        return Candle::query()
            ->symbol($symbol)
            ->timeframe($timeframe)
            ->where('opened_at', '>=', $since)
            ->where('high', '>=', $level - $tolerance)
            ->where('low', '<=', $level + $tolerance)
            ->orderBy('opened_at')
            ->first();
    }

    private function maybeNotifyApproaching(Signal $signal, Collection $recentCandles, float $lastPrice): void
    {
        if ($signal->approaching_notified_at !== null) {
            return; // bu sinyal için zaten bir kez gönderildi
        }

        $etaMinutes = $this->etaEstimator->estimateMinutes(
            $recentCandles,
            $lastPrice,
            (float) $signal->entry_price
        );

        if ($etaMinutes === null) {
            return;
        }

        if ($etaMinutes < self::APPROACHING_MIN_MINUTES || $etaMinutes > self::APPROACHING_MAX_MINUTES) {
            return;
        }

        $signal->update(['approaching_notified_at' => now()]);
        Log::info("Sinyal #{$signal->id} entry'ye yaklaşıyor (tahmini ~".round($etaMinutes)." dk).");
        $this->notifier->entryApproaching($signal->fresh(), (int) round($etaMinutes));
    }

    /** @return Collection<int, Candle> opened_at ASC sıralı */
    private function recentCandlesForMomentum(string $symbol, string $timeframe): Collection
    {
        return Candle::query()
            ->symbol($symbol)
            ->timeframe($timeframe)
            ->orderByDesc('opened_at')
            ->limit(10)
            ->get()
            ->sortBy('opened_at')
            ->values();
    }

    private function resolveTriggered(string $symbol, string $timeframe, float $lastPrice): void
    {
        Signal::where('symbol', $symbol)
            ->where('status', 'triggered')
            ->get()
            ->each(function (Signal $signal) use ($lastPrice, $timeframe) {
                $isBuy = $signal->direction === 'buy';
                $sl = (float) $signal->sl_price;
                $tp = (float) $signal->tp_price;

                // ÖNCE: triggered_at'ten bu yana kapanmış mumların high/low
                // aralığına bak (BacktestEngine'le aynı mantık — bkz.
                // resolvePending'deki findLevelTouch açıklaması, aynı sınıf
                // bug burada TP/SL tespiti için de geçerliydi).
                //
                // DİKKAT: burada AND değil OR gerekiyor — "mumun aralığı
                // SL-TP koridoruyla kesişiyor mu" değil, "mum alt sınırı
                // (min(sl,tp)) DELDİ mi YA DA üst sınırı (max(sl,tp)) AŞTI mı"
                // sorusu soruluyor. AND kullanmak neredeyse her mumla eşleşir
                // (normal fiyat hareketi zaten o koridorun içinde kalır) —
                // bu, ilk implementasyonda fark edilip test sırasında düzeltildi.
                $range = Candle::query()
                    ->symbol($signal->symbol)
                    ->timeframe($timeframe)
                    ->where('opened_at', '>=', $signal->triggered_at)
                    ->where(function ($q) use ($sl, $tp) {
                        $q->where('low', '<=', min($sl, $tp))
                            ->orWhere('high', '>=', max($sl, $tp));
                    })
                    ->orderBy('opened_at')
                    ->first();

                if ($range) {
                    // Aynı mumda teorik olarak ikisine de değinilmiş olabilir
                    // (örn. veri boşluğu/gap sonrası) — SL öncelikli sayılır,
                    // backtest motoruyla aynı muhafazakâr kural.
                    $hitSl = $isBuy ? $range->low <= $sl : $range->high >= $sl;

                    if ($hitSl) {
                        $signal->update(['status' => 'closed_sl', 'closed_at' => $range->opened_at]);
                        Log::info("Sinyal #{$signal->id} SL'ye değindi -> CLOSED_SL (mum {$range->opened_at}, sl={$sl}).");
                        $this->notifier->signalClosedSl($signal->fresh());

                        return;
                    }

                    $signal->update(['status' => 'closed_tp', 'closed_at' => $range->opened_at]);
                    Log::info("Sinyal #{$signal->id} TP'ye değindi -> CLOSED_TP (mum {$range->opened_at}, tp={$tp}).");
                    $this->notifier->signalClosedTp($signal->fresh());

                    return;
                }

                // SONRA (yedek): en son mum henüz yazılmadıysa anlık tick'i
                // de kontrol et — gerçek zamanlı bir kapanışı geciktirmemek için.
                $hitSl = $isBuy ? $lastPrice <= $sl : $lastPrice >= $sl;

                if ($hitSl) {
                    $signal->update(['status' => 'closed_sl', 'closed_at' => now()]);
                    Log::info("Sinyal #{$signal->id} SL'ye değindi -> CLOSED_SL (anlık fiyat={$lastPrice}, sl={$sl}).");
                    $this->notifier->signalClosedSl($signal->fresh());

                    return;
                }

                $hitTp = $isBuy ? $lastPrice >= $tp : $lastPrice <= $tp;

                if ($hitTp) {
                    $signal->update(['status' => 'closed_tp', 'closed_at' => now()]);
                    Log::info("Sinyal #{$signal->id} TP'ye değindi -> CLOSED_TP (anlık fiyat={$lastPrice}, tp={$tp}).");
                    $this->notifier->signalClosedTp($signal->fresh());
                }
            });
    }

    private function maybeOpenNewSignal(string $symbol, string $timeframe): void
    {
        $strategy = Strategy::where('is_active', true)->first();

        if (! $strategy) {
            return;
        }

        $hasOpenSignal = Signal::where('symbol', $symbol)
            ->whereIn('status', ['pending', 'triggered'])
            ->exists();

        if ($hasOpenSignal) {
            return; // tek seferde tek pozisyon
        }

        $candles = Candle::query()
            ->symbol($symbol)
            ->timeframe($timeframe)
            ->orderByDesc('opened_at')
            ->limit(self::EVALUATION_WINDOW)
            ->get()
            ->sortBy('opened_at')
            ->values();

        if ($candles->isEmpty()) {
            return;
        }

        $candidate = $strategy->makeInstance()->evaluate(CandleSeries::fromCollection($candles));

        if (! $candidate || ! RiskRules::isValid($candidate->tpPips)) {
            return;
        }

        $signal = Signal::create([
            'strategy_id' => $strategy->id,
            'symbol' => $symbol,
            'direction' => $candidate->direction,
            'entry_price' => $candidate->entryPrice,
            'sl_price' => RiskRules::slPriceFor($candidate->direction, $candidate->entryPrice),
            'tp_price' => RiskRules::tpPriceFor($candidate->direction, $candidate->entryPrice, $candidate->tpPips),
            'sl_pips' => $candidate->slPips,
            'tp_pips' => $candidate->tpPips,
            'confidence_pct' => $candidate->confidencePct,
            'status' => 'pending',
            'expected_entry_at' => now()->addMinutes(7),
        ]);

        Log::info("Yeni sinyal oluşturuldu: #{$signal->id} {$strategy->name} {$candidate->direction} @ {$candidate->entryPrice}");
        $this->notifier->signalCreated($signal->fresh('strategy'));
    }
}
