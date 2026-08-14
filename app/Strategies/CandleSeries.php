<?php

namespace App\Strategies;

use App\Models\Candle;
use Illuminate\Support\Collection;

/**
 * opened_at ASC sıralı, salt-okunur bir mum serisi. Hem gerçek Eloquent
 * Candle model'lerini hem de backtest/test amaçlı bellekte oluşturulmuş
 * (kaydedilmemiş) Candle instance'larını sarabilir.
 */
class CandleSeries
{
    /** @param Collection<int, Candle> $candles */
    public function __construct(private readonly Collection $candles) {}

    public static function fromCollection(Collection $candles): self
    {
        return new self($candles->values());
    }

    public function count(): int
    {
        return $this->candles->count();
    }

    public function isEmpty(): bool
    {
        return $this->candles->isEmpty();
    }

    public function latest(): ?Candle
    {
        return $this->candles->last();
    }

    public function get(int $index): ?Candle
    {
        return $this->candles->get($index);
    }

    public function all(): Collection
    {
        return $this->candles;
    }

    /** @return float[] */
    public function closes(): array
    {
        return $this->candles->map(fn (Candle $c) => (float) $c->close)->all();
    }

    /** @return float[] */
    public function highs(): array
    {
        return $this->candles->map(fn (Candle $c) => (float) $c->high)->all();
    }

    /** @return float[] */
    public function lows(): array
    {
        return $this->candles->map(fn (Candle $c) => (float) $c->low)->all();
    }

    /** @return array<int, float|null> */
    public function ema(int $period): array
    {
        return Indicators::ema($this->closes(), $period);
    }

    /** @return array<int, float|null> */
    public function rsi(int $period = 14): array
    {
        return Indicators::rsi($this->closes(), $period);
    }
}
