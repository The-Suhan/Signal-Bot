import { config } from './config.js';
import { logger } from './logger.js';
import { createRedisClient, publishTick } from './redis.js';
import { createPgPool, upsertCandle } from './db.js';
import { CandleAggregator } from './aggregator.js';
import { TwelveDataClient } from './twelvedata.js';

async function main() {
  logger.info(`Starting XAU/USD ingestion service (symbol=${config.twelveData.symbol})`);

  const redis = createRedisClient();
  const pgPool = createPgPool();

  // pg bağlantısını erken doğrula, yanlış .env ile sessizce çalışmasın
  await pgPool.query('select 1');
  logger.info('Postgres: connected');

  const aggregator = new CandleAggregator({
    symbol: config.candleSymbol,
    onCandleClose: (candle) => upsertCandle(pgPool, candle),
  });

  const client = new TwelveDataClient({
    onTick: (price, volume, timestamp) => {
      const tick = {
        symbol: config.candleSymbol,
        price,
        volume,
        timestamp: timestamp.toISOString(),
      };

      logger.info(`Tick: ${price} @ ${tick.timestamp}`);

      publishTick(redis, tick).catch((err) =>
        logger.error('Redis publish error:', err.message)
      );

      aggregator.addTick(price, volume, timestamp);
    },
  });

  client.connect();

  const shutdown = async (signal) => {
    logger.info(`${signal} received, shutting down...`);
    client.close();
    aggregator.flush();
    await redis.quit().catch(() => {});
    await pgPool.end().catch(() => {});
    process.exit(0);
  };

  process.on('SIGINT', () => shutdown('SIGINT'));
  process.on('SIGTERM', () => shutdown('SIGTERM'));
}

main().catch((err) => {
  logger.error('Fatal error during startup:', err);
  process.exit(1);
});
