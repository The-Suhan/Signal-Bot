<?php

namespace App\Console\Commands;

use App\Services\MarketData\PriceReader;
use App\Services\Signals\SignalManager;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('signals:process {--symbol=XAUUSD} {--timeframe=1m}')]
#[Description('Sinyal state machine\'ini bir adım ilerletir: PENDING/TRIGGERED sinyalleri son fiyata göre günceller, aktif strateji için yeni sinyal adayı arar. Scheduler tarafından her dakika çalıştırılır.')]
class ProcessSignals extends Command
{
    public function handle(SignalManager $manager, PriceReader $priceReader): int
    {
        $symbol = $this->option('symbol');
        $timeframe = $this->option('timeframe');

        $lastPrice = $priceReader->last();

        if ($lastPrice === null) {
            $this->warn("Redis'te {$symbol} için son fiyat bulunamadı (ingestion servisi çalışıyor mu?) — bu döngü atlandı.");

            return self::SUCCESS;
        }

        $manager->process($symbol, $timeframe, $lastPrice);

        $this->info("İşlendi: {$symbol} @ {$lastPrice}");

        return self::SUCCESS;
    }
}
