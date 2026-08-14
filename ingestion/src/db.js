import pg from 'pg';
import { config } from './config.js';
import { logger } from './logger.js';

const { Pool } = pg;

export function createPgPool() {
  const pool = new Pool({
    host: config.pg.host,
    port: config.pg.port,
    database: config.pg.database,
    user: config.pg.user,
    password: config.pg.password,
    // candles.opened_at "timestamp without time zone" — Laravel tarafı (app
    // timezone = UTC) ile tutarlı kalması için bu bağlantının session'ını da
    // UTC'ye sabitliyoruz. Aksi halde `now()` sunucu/OS saat dilimine göre
    // (örn. Asia/Ashgabat, UTC+5) yazılır ve Laravel'in UTC now()'ı ile
    // karşılaştırıldığında mumlar "gelecekte" görünür. Bağlantı parametresi
    // olarak veriliyor ki her client hazır olduğu an TZ zaten UTC olsun
    // (ayrı bir SET sorgusunun yol açtığı race condition'ı önlemek için).
    options: '-c TimeZone=UTC',
  });

  pool.on('error', (err) => {
    logger.error('Postgres pool error:', err.message);
  });

  return pool;
}

/**
 * Bir Date'i "timestamp without time zone" kolonuna güvenli şekilde yazmak
 * için UTC wall-clock string'e çevirir (örn. "2026-08-14 09:22:00.000").
 * node-postgres bir Date parametresini serialize ederken Node prosesinin
 * YEREL saat dilimini kullanır — bu yüzden Date'i doğrudan geçmek yerine
 * burada UTC'ye göre biçimlendirilmiş metni geçiyoruz.
 */
function toUtcTimestampLiteral(date) {
  return date.toISOString().replace('T', ' ').replace('Z', '');
}

/**
 * Kapanmış bir 1m mumu candles tablosuna yazar (varsa upsert eder).
 * Migration'daki unique(['symbol', 'timeframe', 'opened_at']) kısıtına dayanır.
 */
export async function upsertCandle(pool, candle) {
  const query = `
    insert into candles (symbol, timeframe, open, high, low, close, volume, opened_at, created_at, updated_at)
    values ($1, $2, $3, $4, $5, $6, $7, $8, $9, $9)
    on conflict (symbol, timeframe, opened_at)
    do update set
      open = excluded.open,
      high = excluded.high,
      low = excluded.low,
      close = excluded.close,
      volume = excluded.volume,
      updated_at = excluded.updated_at
  `;

  const nowLiteral = toUtcTimestampLiteral(new Date());

  const values = [
    candle.symbol,
    candle.timeframe,
    candle.open,
    candle.high,
    candle.low,
    candle.close,
    candle.volume,
    toUtcTimestampLiteral(candle.openedAt),
    nowLiteral,
  ];

  await pool.query(query, values);
}
