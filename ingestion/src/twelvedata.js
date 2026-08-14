import WebSocket from 'ws';
import { config } from './config.js';
import { logger } from './logger.js';

const HEARTBEAT_INTERVAL_MS = 10_000; // Twelve Data bağlantıyı canlı tutmak için periyodik heartbeat ister
const MAX_BACKOFF_MS = 30_000;
const BASE_BACKOFF_MS = 1_000;

/**
 * Twelve Data WebSocket'ine bağlanır, XAU/USD fiyat tick'lerini dinler.
 * Bağlantı koptuğunda exponential backoff ile otomatik reconnect eder.
 *
 * onTick(price, timestamp) her fiyat güncellemesinde çağrılır.
 */
export class TwelveDataClient {
  constructor({ onTick }) {
    this.onTick = onTick;
    this.ws = null;
    this.heartbeatTimer = null;
    this.reconnectAttempts = 0;
    this.closedByUser = false;
  }

  connect() {
    this.closedByUser = false;
    const url = `${config.twelveData.wsUrl}?apikey=${config.twelveData.apiKey}`;
    logger.info(`Twelve Data WS: connecting...`);

    this.ws = new WebSocket(url);

    this.ws.on('open', () => this._handleOpen());
    this.ws.on('message', (data) => this._handleMessage(data));
    this.ws.on('close', (code, reason) => this._handleClose(code, reason));
    this.ws.on('error', (err) => logger.error('Twelve Data WS error:', err.message));
  }

  close() {
    this.closedByUser = true;
    this._stopHeartbeat();
    this.ws?.close();
  }

  _handleOpen() {
    this.reconnectAttempts = 0;
    logger.info('Twelve Data WS: connected');

    const subscribeMsg = {
      action: 'subscribe',
      params: { symbols: config.twelveData.symbol },
    };
    this.ws.send(JSON.stringify(subscribeMsg));
    logger.info(`Subscribed to ${config.twelveData.symbol}`);

    this._startHeartbeat();
  }

  _startHeartbeat() {
    this._stopHeartbeat();
    this.heartbeatTimer = setInterval(() => {
      if (this.ws?.readyState === WebSocket.OPEN) {
        this.ws.send(JSON.stringify({ action: 'heartbeat' }));
      }
    }, HEARTBEAT_INTERVAL_MS);
  }

  _stopHeartbeat() {
    if (this.heartbeatTimer) {
      clearInterval(this.heartbeatTimer);
      this.heartbeatTimer = null;
    }
  }

  _handleMessage(raw) {
    let msg;
    try {
      msg = JSON.parse(raw.toString());
    } catch {
      logger.warn('Non-JSON message from Twelve Data:', raw.toString());
      return;
    }

    if (msg.event === 'price') {
      const price = Number(msg.price);
      const timestamp = msg.timestamp ? new Date(msg.timestamp * 1000) : new Date();
      if (!Number.isFinite(price)) return;
      this.onTick(price, msg.day_volume ? Number(msg.day_volume) : 0, timestamp);
      return;
    }

    if (msg.event === 'subscribe-status') {
      if (msg.status === 'ok') {
        logger.info('Subscribe status: ok', msg.success?.map((s) => s.symbol).join(', '));
      } else {
        logger.warn('Subscribe status:', JSON.stringify(msg));
      }
      return;
    }

    if (msg.event === 'heartbeat') {
      return; // sunucudan gelen heartbeat onayı, işlem gerekmiyor
    }

    // rate limit / hata mesajları (free plan credit sınırı vs.)
    if (msg.status === 'error' || msg.event === 'error') {
      logger.error('Twelve Data error message:', JSON.stringify(msg));
      return;
    }

    logger.info('WS message:', JSON.stringify(msg));
  }

  _handleClose(code, reason) {
    this._stopHeartbeat();
    logger.warn(`Twelve Data WS: closed (code=${code} reason=${reason?.toString() || 'n/a'})`);

    if (this.closedByUser) return;

    this.reconnectAttempts += 1;
    const backoff = Math.min(
      BASE_BACKOFF_MS * 2 ** (this.reconnectAttempts - 1),
      MAX_BACKOFF_MS
    );
    logger.info(`Reconnecting in ${backoff}ms (attempt ${this.reconnectAttempts})...`);
    setTimeout(() => this.connect(), backoff);
  }
}
