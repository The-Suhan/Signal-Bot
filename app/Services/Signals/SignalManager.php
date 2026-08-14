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
        $this->resolvePending($symbol, $timeframe, $lastPrice);
        $this->resolveTriggered($symbol, $lastPrice);
        $this->maybeOpenNewSignal($symbol, $timeframe);
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

        $pendingSignals->each(function (Signal $signal) use ($lastPrice, $tolerance, $recentCandles) {
            $expiresAt = CarbonImmutable::parse($signal->created_at)
                ->addMinutes(self::PENDING_EXPIRY_MINUTES);

            if (now()->greaterThan($expiresAt)) {
                $signal->update(['status' => 'expired', 'closed_at' => now()]);
                Log::info("Sinyal #{$signal->id} süresi doldu (entry'ye hiç değinilmedi).");
                $this->notifier->signalExpired($signal);

                return;
            }

            $touchedEntry = abs($lastPrice - (float) $signal->entry_price) <= $tolerance;

            if ($touchedEntry) {
                $signal->update(['status' => 'triggered', 'triggered_at' => now()]);
                $this->notifier->signalTriggered($signal->fresh());

                return;
            }

            $this->maybeNotifyApproaching($signal, $recentCandles, $lastPrice);
        });
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

    private function resolveTriggered(string $symbol, float $lastPrice): void
    {
        Signal::where('symbol', $symbol)
            ->where('status', 'triggered')
            ->get()
            ->each(function (Signal $signal) use ($lastPrice) {
                $isBuy = $signal->direction === 'buy';

                $hitSl = $isBuy
                    ? $lastPrice <= (float) $signal->sl_price
                    : $lastPrice >= (float) $signal->sl_price;

                // Aynı tick'te teorik olarak ikisine de değinilmiş olabilir
                // (örn. veri boşluğu/gap sonrası) — SL öncelikli sayılır,
                // backtest motoruyla aynı muhafazakâr kural.
                if ($hitSl) {
                    $signal->update(['status' => 'closed_sl', 'closed_at' => now()]);
                    $this->notifier->signalClosedSl($signal->fresh());

                    return;
                }

                $hitTp = $isBuy
                    ? $lastPrice >= (float) $signal->tp_price
                    : $lastPrice <= (float) $signal->tp_price;

                if ($hitTp) {
                    $signal->update(['status' => 'closed_tp', 'closed_at' => now()]);
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
