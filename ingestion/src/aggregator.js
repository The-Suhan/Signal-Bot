import { logger } from './logger.js';

/**
 * Gelen tick'leri (fiyat + zaman damgası) 1 dakikalık OHLC mumlara toplar.
 * Bir dakika kapandığında onCandleClose callback'i tetiklenir.
 *
 * 5m/15m/1h gibi üst zaman dilimleri kasıtlı olarak burada üretilmiyor —
 * bu iş Laravel tarafında zamanlanmış bir command'e bırakılıyor (1m mumları
 * roll-up ederek). Böylece aggregation mantığı tek yerde (Laravel) kalıyor
 * ve Node servisi sadece "gerçek zamanlı 1m kaynağı" görevini üstleniyor.
 */
export class CandleAggregator {
  constructor({ symbol, onCandleClose }) {
    this.symbol = symbol;
    this.onCandleClose = onCandleClose;
    this.current = null; // { openedAt, open, high, low, close, volume }
  }

  static bucketStart(date) {
    const bucket = new Date(date);
    bucket.setSeconds(0, 0);
    return bucket;
  }

  addTick(price, volume, timestamp) {
    const bucketStart = CandleAggregator.bucketStart(timestamp);

    if (!this.current) {
      this.current = this._newCandle(bucketStart, price, volume);
      return;
    }

    if (bucketStart.getTime() !== this.current.openedAt.getTime()) {
      // dakika değişti -> önceki mumu kapat, yeni mumu başlat
      this._closeCurrent();
      this.current = this._newCandle(bucketStart, price, volume);
      return;
    }

    this.current.high = Math.max(this.current.high, price);
    this.current.low = Math.min(this.current.low, price);
    this.current.close = price;
    this.current.volume += volume;
  }

  _newCandle(openedAt, price, volume) {
    return {
      openedAt,
      open: price,
      high: price,
      low: price,
      close: price,
      volume,
    };
  }

  _closeCurrent() {
    if (!this.current) return;
    const candle = {
      symbol: this.symbol,
      timeframe: '1m',
      ...this.current,
    };
    logger.info(
      `1m candle closed: ${candle.openedAt.toISOString()} O:${candle.open} H:${candle.high} L:${candle.low} C:${candle.close}`
    );
    this.onCandleClose(candle).catch((err) =>
      logger.error('Candle persist error:', err.message)
    );
  }

  /** Süreç kapanırken elde kalan açık mumu da kapatmak için */
  flush() {
    this._closeCurrent();
    this.current = null;
  }
}
