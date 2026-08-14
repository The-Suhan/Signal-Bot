# XAU/USD Ingestion Service

Twelve Data WebSocket'ine bağlanıp XAU/USD tick verisini alan, Redis'e yayınlayan
ve 1 dakikalık mumları doğrudan Postgres `candles` tablosuna yazan bağımsız
Node.js servisi.

## Mimari kararı

- **Redis**: her tick `price:XAUUSD:tick` kanalına publish edilir + `price:XAUUSD:last`
  anahtarına son fiyat olarak cache'lenir. Laravel tarafı (canlı grafik, ETA hesaplayıcı vb.)
  bunu subscribe ederek veya okuyarak kullanabilir.
- **Postgres**: bu servis sadece **1m** mumları yazar (gerçek zamanlı tek kaynak).
  5m/15m/1h gibi üst zaman dilimleri kasıtlı olarak burada üretilmiyor — Laravel
  tarafında zamanlanmış bir command 1m mumları roll-up ederek üretir. Böylece
  aggregation mantığı iki farklı dilde tekrar edilmemiş olur.
- Aynı `candles` tablosuna doğrudan yazıyoruz (Laravel API endpoint'i üzerinden değil) —
  ekstra bir HTTP hop'u ve auth katmanı gerektirmeden basit ve düşük gecikmeli.

## Kurulum

```bash
cd ingestion
npm install
cp .env.example .env   # zaten .env dolu geliyor, kendi ortamınızda değerleri güncelleyin
```

## Çalıştırma

```bash
npm start
```

Beklenen çıktı: bağlantı, subscribe onayı ve ardından gelen tick'lerin logu,
her dakika kapanışında oluşan 1m mumun logu.

## Reconnect / rate limit

- WebSocket koptuğunda exponential backoff (1s → 2s → 4s ... max 30s) ile otomatik reconnect.
- Free plan bağlantıyı canlı tutmak için periyodik heartbeat mesajı gönderilir (10s).
- Twelve Data'dan gelen hata/rate-limit mesajları loglanır (`status: error` / `event: error`).
