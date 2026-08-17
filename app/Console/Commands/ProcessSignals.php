<?php

namespace App\Console\Commands;

use App\Services\MarketData\MarketHours;
use App\Services\MarketData\PriceReader;
use App\Services\Signals\SignalManager;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

#[Signature('signals:process {--symbol=XAUUSD} {--timeframe=1m}')]
#[Description('Sinyal state machine\'ini bir adım ilerletir: PENDING/TRIGGERED sinyalleri son fiyata göre günceller, aktif strateji için yeni sinyal adayı arar. Scheduler tarafından her dakika çalıştırılır.')]
class ProcessSignals extends Command
{
    /**
     * Bu süreden daha eski bir tick fiyatı "bayat" kabul edilir ve işlenmez.
     * Yaşanan olay: ingestion servisinin WebSocket bağlantısı 17+ saat
     * "zombi" kaldı (uyku modu sonrası close event hiç tetiklenmedi), Redis'teki
     * son fiyat hiç güncellenmedi ama signals:process her dakika "başarıyla"
     * çalışmaya devam etti — bayat fiyatla sessizce işlem yapıp TRIGGERED bir
     * sinyalin TP/SL'ye ulaşıp ulaşmadığını yanlış (asla) değerlendirdi.
     *
     * NOT: Bu eşik sadece piyasa AÇIKKEN gerçek bir ingestion arızasını
     * hafta sonu kapanışından ayırt etmek için kullanılır — piyasa kapalıyken
     * fiyatın dakikalarca değişmemesi zaten normaldir, aşağıda ayrıca ele alınır.
     */
    private const MAX_TICK_AGE_MINUTES = 3;

    public function handle(SignalManager $manager, PriceReader $priceReader, MarketHours $marketHours): int
    {
        $symbol = $this->option('symbol');
        $timeframe = $this->option('timeframe');

        $lastPrice = $priceReader->last();
        $lastTimestamp = $priceReader->lastTimestamp();

        if ($lastPrice === null) {
            $message = "Redis'te {$symbol} için son fiyat bulunamadı (ingestion servisi çalışıyor mu?) — bu döngü atlandı.";
            $this->warn($message);
            // Scheduler çıktıyı /dev/null'a yönlendiriyor — bu yüzden konsol
            // uyarısı tek başına yetmez, mutlaka loglanmalı.
            Log::warning($message);

            return self::SUCCESS;
        }

        if (! $marketHours->isOpen()) {
            // Piyasa kapalı (hafta sonu): yeni sinyal ARANMAZ ve ingestion
            // "bayat veri" uyarısı burada anlamsız olurdu (hafta sonu zaten
            // fiyat akmaz, bu bir arıza değil) — sadece açık pozisyonlar
            // (varsa) elimizdeki son fiyatla takip edilmeye devam eder ki
            // piyasa açılınca kaldığı yerden devam etsin.
            Log::info("Piyasa kapalı (hafta sonu) — {$symbol} için yeni sinyal aranmıyor, mevcut pozisyonlar (varsa) takip ediliyor.");

            $manager->trackOpenPositions($symbol, $timeframe, $lastPrice);

            $this->info("Piyasa kapalı, sadece pozisyon takibi yapıldı: {$symbol} @ {$lastPrice}");

            return self::SUCCESS;
        }

        if ($lastTimestamp !== null) {
            $ageMinutes = Carbon::parse($lastTimestamp)->diffInMinutes(now());

            if ($ageMinutes > self::MAX_TICK_AGE_MINUTES) {
                $message = "Son fiyat {$ageMinutes} dakikadır güncellenmemiş ({$lastTimestamp}) — "
                    ."veri bayat kabul edilip bu döngü atlandı (ingestion servisinin bağlantısı düşmüş olabilir).";
                $this->warn($message);
                Log::warning($message);

                return self::SUCCESS;
            }
        }

        $manager->process($symbol, $timeframe, $lastPrice);

        $this->info("İşlendi: {$symbol} @ {$lastPrice}");

        return self::SUCCESS;
    }
}
