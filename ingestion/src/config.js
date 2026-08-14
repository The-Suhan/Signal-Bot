import 'dotenv/config';

function required(name) {
  const value = process.env[name];
  if (!value) {
    throw new Error(`Missing required env var: ${name}`);
  }
  return value;
}

export const config = {
  twelveData: {
    apiKey: required('TWELVEDATA_API_KEY'),
    symbol: process.env.TWELVEDATA_SYMBOL || 'XAU/USD',
    wsUrl: 'wss://ws.twelvedata.com/v1/quotes/price',
  },
  redis: {
    host: process.env.REDIS_HOST || '127.0.0.1',
    port: Number(process.env.REDIS_PORT || 6379),
    password: process.env.REDIS_PASSWORD || undefined,
    tickChannel: process.env.REDIS_TICK_CHANNEL || 'price:XAUUSD:tick',
    lastPriceKey: process.env.REDIS_LAST_PRICE_KEY || 'price:XAUUSD:last',
  },
  pg: {
    host: process.env.PGHOST || '127.0.0.1',
    port: Number(process.env.PGPORT || 5432),
    database: required('PGDATABASE'),
    user: required('PGUSER'),
    password: process.env.PGPASSWORD || '',
  },
  candleSymbol: process.env.CANDLE_SYMBOL || 'XAUUSD',
};
