import WebSocket from 'ws';
import { config } from './config.js';
import { logger } from './logger.js';

const HEARTBEAT_INTERVAL_MS = 10_000; // Twelve Data bağlantıyı canlı tutmak için periyodik heartbeat ister
const MAX_BACKOFF_MS = 30_000;
const BASE_BACKOFF_MS = 1_000;

// Uygulama seviyesindeki heartbeat (yukarıda) sadece sunucuya mesaj GÖNDERİR;
// yerel TCP soketinin gerçekten canlı olup olmadığını KONTROL ETMEZ. Bilgisayar
// uyku moduna girip çıktığında soket "zombi" kalabilir — ws.send() OS tampon
// seviyesinde başarılı görünür ama karşı taraftan hiçbir şey gelmez ve gerçek
// bir 'close' event'i saatlerce (hatta hiç) tetiklenmeyebilir (yaşanan olay:
// 17+ saat fark edilmeden bayat veri). Bu yüzden native WS ping/pong ile ayrı
// bir canlılık (liveness) kontrolü yapılıyor: pong gelmezse soket zorla kapatılır.
const PING_INTERVAL_MS = 15_000;
const PONG_TIMEOUT_MS = 35_000; // bu süre içinde pong gelmezse soket zombi kabul edilir

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
    this.pingTimer = null;
    this.lastPongAt = null;
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
    this.ws.on('pong', () => {
      this.lastPongAt = Date.now();
    });
    this.ws.on('close', (code, reason) => this._handleClose(code, reason));
    this.ws.on('error', (err) => logger.error('Twelve Data WS error:', err.message));
  }

  close() {
    this.closedByUser = true;
    this._stopHeartbeat();
    this._stopPingWatchdog();
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
    this._startPingWatchdog();
  }

  /**
   * Native WS ping/pong ile gerçek soket canlılığını kontrol eder. Zombi
   * bağlantıyı (uyku modu sonrası vb.) dakikalar içinde tespit edip
   * terminate() ile zorla kapatır — bu, 'close' event'ini hemen tetikler ve
   * mevcut reconnect mantığı devreye girer.
   */
  _startPingWatchdog() {
    this._stopPingWatchdog();
    this.lastPongAt = Date.now();

    this.pingTimer = setInterval(() => {
      if (this.ws?.readyState !== WebSocket.OPEN) return;

      if (Date.now() - this.lastPongAt > PONG_TIMEOUT_MS) {
        logger.warn(
          `Twelve Data WS: ${PONG_TIMEOUT_MS}ms içinde pong alınamadı, bağlantı zombi kabul edilip zorla kapatılıyor.`
        );
        this.ws.terminate();
        return;
      }

      this.ws.ping();
    }, PING_INTERVAL_MS);
  }

  _stopPingWatchdog() {
    if (this.pingTimer) {
      clearInterval(this.pingTimer);
      this.pingTimer = null;
    }
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
    this._stopPingWatchdog();
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
