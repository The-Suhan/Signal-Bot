<?php

namespace App\Services\MarketData;

use Illuminate\Support\Facades\Redis;

/**
 * Node ingestion servisinin Redis'e yazdığı son fiyatı okur. Tek yerden
 * okunuyor ki key formatı/prefix ayarları ileride değişirse tek noktadan
 * güncellenebilsin (bkz. daha önce yaşanan REDIS_PREFIX uyumsuzluğu).
 */
class PriceReader
{
    public function last(): ?float
    {
        $tick = $this->lastTick();

        return isset($tick['price']) ? (float) $tick['price'] : null;
    }

    public function lastTimestamp(): ?string
    {
        $tick = $this->lastTick();

        return $tick['timestamp'] ?? null;
    }

    /** @return array{symbol?: string, price?: float, timestamp?: string}|null */
    public function lastTick(): ?array
    {
        $raw = Redis::get(config('services.market_data.last_price_key'));

        if (! $raw) {
            return null;
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : null;
    }
}
