<?php

namespace App\Console\Commands;

use App\Models\Candle;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('candles:aggregate {--symbol=XAUUSD}')]
#[Description('1 dakikalık mumları (ingestion servisinin yazdığı) 5m/15m/1h mumlara roll-up eder')]
class AggregateCandles extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $symbol = $this->option('symbol');

        foreach (Candle::ROLLUP_TIMEFRAMES as $minutes => $timeframe) {
            $written = $this->rollup($symbol, $minutes, $timeframe);
            $this->info("{$timeframe}: {$written} mum yazıldı/güncellendi.");
        }

        return self::SUCCESS;
    }

    /**
     * Verilen dakika genişliğindeki bucket'lara göre 1m mumları toplar.
     * Sadece TAM dolu (o dakika sayısı kadar 1m mumu bulunan, yani kapanmış)
     * bucket'lar işlenir — henüz oluşmakta olan mum yanlışlıkla kapatılmaz.
     */
    private function rollup(string $symbol, int $minutes, string $timeframe): int
    {
        $bucketSeconds = $minutes * 60;

        $rows = DB::table('candles')
            ->selectRaw(
                'to_timestamp(floor(extract(epoch from opened_at) / ?) * ?) as bucket,
                 (array_agg(open order by opened_at asc))[1] as open,
                 max(high) as high,
                 min(low) as low,
                 (array_agg(close order by opened_at desc))[1] as close,
                 sum(coalesce(volume, 0)) as volume,
                 count(*) as candle_count',
                [$bucketSeconds, $bucketSeconds]
            )
            ->where('symbol', $symbol)
            ->where('timeframe', Candle::SOURCE_TIMEFRAME)
            ->groupBy('bucket')
            ->havingRaw('count(*) = ?', [$minutes])
            ->get();

        $count = 0;

        foreach ($rows as $row) {
            Candle::updateOrCreate(
                [
                    'symbol' => $symbol,
                    'timeframe' => $timeframe,
                    'opened_at' => $row->bucket,
                ],
                [
                    'open' => $row->open,
                    'high' => $row->high,
                    'low' => $row->low,
                    'close' => $row->close,
                    'volume' => $row->volume,
                ]
            );
            $count++;
        }

        return $count;
    }
}
