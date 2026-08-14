import Redis from 'ioredis';
import { config } from './config.js';
import { logger } from './logger.js';

export function createRedisClient() {
  const client = new Redis({
    host: config.redis.host,
    port: config.redis.port,
    password: config.redis.password,
    retryStrategy: (times) => {
      const delay = Math.min(times * 500, 10000);
      return delay;
    },
  });

  client.on('connect', () => logger.info('Redis: connected'));
  client.on('error', (err) => logger.error('Redis error:', err.message));
  client.on('reconnecting', () => logger.warn('Redis: reconnecting...'));

  return client;
}

/**
 * Bir tick'i Redis'e yayınlar (pub/sub) ve son fiyatı cache'e yazar.
 */
export async function publishTick(redis, tick) {
  const payload = JSON.stringify(tick);
  await Promise.all([
    redis.publish(config.redis.tickChannel, payload),
    redis.set(config.redis.lastPriceKey, payload),
  ]);
}
