# XAU/USD Signal Bot

Laravel + Inertia.js + Vue 3 tabanlı, XAU/USD (altın) için otomatik sinyal üreten ve Telegram'a bildirim gönderen sistem.

---

## 🖥️ Yeni Bilgisayarda Kurulum (Sıfırdan)

Bu projeyi başka bir bilgisayarda `git clone` ile aldığında aşağıdaki adımları sırayla takip et.

### 1. Sistem Bağımlılıklarını Kur

**Arch/CachyOS için:**
```bash
sudo pacman -S php php-pgsql postgresql redis nodejs npm composer git
```

**Ubuntu/Debian için:**
```bash
sudo apt install php php-pgsql postgresql redis-server nodejs npm composer git
```

Kontrol et:
```bash
php -v
php -m | grep pgsql   # pdo_pgsql ve pgsql görünmeli
node -v
composer -V
```

### 2. Servisleri Başlat

```bash
sudo systemctl enable --now postgresql
sudo systemctl enable --now redis
```

### 3. Projeyi Klonla

```bash
git clone https://github.com/The-Suhan/Signal-Bot.git
cd Signal-Bot
```

### 4. Bağımlılıkları Kur

```bash
composer install
npm install
```

### 5. PostgreSQL Veritabanını Oluştur

```bash
sudo -u postgres psql
```

İçinde:
```sql
CREATE DATABASE xauusd_signal;
ALTER USER postgres PASSWORD 'senin_sifren';
\q
```

### 6. `.env` Dosyasını Oluştur

```bash
cp .env.example .env
php artisan key:generate
nano .env
```

Aşağıdaki bölümü `.env` içinde doldur — key'leri nereden alacağını aşağıdaki **"Gerekli Key'ler"** bölümünde bulacaksın:

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=xauusd_signal
DB_USERNAME=postgres
DB_PASSWORD=senin_sifren

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
CACHE_STORE=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

TWELVEDATA_API_KEY=
TELEGRAM_BOT_TOKEN=
TELEGRAM_CHAT_ID=
```

### 7. Migration'ları Çalıştır

```bash
php artisan migrate
```

### 8. Dev Sunucularını Başlat

İki ayrı terminalde:
```bash
php artisan serve
```
```bash
npm run dev
```

Tarayıcıda `127.0.0.1:8000` adresini aç.

---

## 🔑 Gerekli Key'ler — Nereden Alınır

| Key | Nereden | Nasıl Alınır |
|---|---|---|
| `TWELVEDATA_API_KEY` | https://twelvedata.com/pricing | Free plan → "Get free API key" → email ile kayıt ol (kart istemez) → Dashboard'da API key hazır görünür |
| `TELEGRAM_BOT_TOKEN` | Telegram'da **@BotFather** | `/newbot` yaz → isim ve username belirle → dönen token'ı kopyala |
| `TELEGRAM_CHAT_ID` | Kendi Telegram hesabın | Aşağıdaki "Chat ID Alma" adımlarını izle |

### Telegram Chat ID Alma

1. Telegram'da kendi botunu bul (username'i BotFather'dan aldığın isim)
2. Bota `/start` yaz ve gönder
3. Tarayıcıda şu adresi aç (kendi bot token'ınla):
   ```
   https://api.telegram.org/bot<BOT_TOKEN>/getUpdates
   ```
4. Dönen JSON içinde `"chat":{"id":XXXXXXXXX,...}` kısmındaki sayı senin chat_id'n

---

## ⚠️ Önemli Notlar

- **Token'ları asla GitHub'a commit etme** — `.env` dosyası `.gitignore` içinde olmalı (varsayılan Laravel kurulumunda zaten öyle)
- **PostgreSQL şifresini unutma** — bir yere güvenli şekilde not al (şifre yöneticisi önerilir)
- Git push için GitHub artık şifre kabul etmiyor, **Personal Access Token (classic, `repo` scope)** kullanman gerekiyor: https://github.com/settings/tokens/new?scopes=repo
- SSH key kurulumu yaparsan token girmek zorunda kalmazsın (isteğe bağlı, kalıcı çözüm):
  ```bash
  ssh-keygen -t ed25519 -C "email@ornek.com"
  cat ~/.ssh/id_ed25519.pub   # çıkan key'i GitHub → Settings → SSH keys'e ekle
  git remote set-url origin git@github.com:The-Suhan/Signal-Bot.git
  ```

### 🔴 KRİTİK: Bilgisayar Uyku Moduna Geçmemeli

**Sistemin çalıştığı laptop/PC hep açık ve uyanık kalmalı — ne boşta kalınca ne kapak kapatınca uyku moduna geçmemeli.**

Sebebi: Fiyat verisini çeken ingestion servisi (WebSocket bağlantısı) bilgisayar uyku moduna girdiğinde bağlantısı **"zombi" hale gelir** — yani bağlantı görünüşte açık kalır ama gerçekte hiç veri akmaz, ve sistem bunu uzun süre (saatler) fark etmeyebilir. Bu süre boyunca sinyal motoru bayat/donmuş fiyatla "çalışıyormuş gibi" davranır, ama gerçekte hiçbir yeni sinyal veya doğru TP/SL kontrolü yapılmaz.

Bunu önlemek için GNOME'da (CachyOS/Arch) şu ayarlar yapılmalı:

```bash
# Boşta kalma nedeniyle uykuya geçmeyi kapat
gsettings set org.gnome.settings-daemon.plugins.power sleep-inactive-ac-type 'nothing'
gsettings set org.gnome.settings-daemon.plugins.power sleep-inactive-battery-type 'nothing'

# Kapak kapatma nedeniyle uykuya geçmeyi kapat (sudo gerektirir)
sudo sed -i 's/^#HandleLidSwitch=suspend/HandleLidSwitch=ignore/' /etc/systemd/logind.conf
sudo systemctl restart systemd-logind
```

Doğrulama:
```bash
gsettings get org.gnome.settings-daemon.plugins.power sleep-inactive-ac-type   # 'nothing' dönmeli
busctl get-property org.freedesktop.login1 /org/freedesktop/login1 org.freedesktop.login1.Manager HandleLidSwitch   # s "ignore" dönmeli
```

**Not:** `systemctl restart systemd-logind` grafik oturumunu (masaüstü) kısa süreliğine yeniden başlatabilir — normal ve zararsız bir yan etki, endişelenme.

Ekranın kararması (screensaver, ör. 5 dk sonra) sorun değil — sadece görsel, arka plandaki servisler (ingestion/scheduler) bundan etkilenmez, çalışmaya devam eder.

### Servislerin Sürekli/Kalıcı Çalışması (systemd)

Sistem üç `systemd --user` servisi olarak çalışacak şekilde kurulmalı — böylece bilgisayar yeniden başlasa bile elle hiçbir şey çalıştırman gerekmez:

| Servis | Görevi |
|---|---|
| `xauusd-ingestion` | Twelve Data WebSocket → Redis/Postgres (fiyat verisi) |
| `xauusd-scheduler` | `candles:aggregate`, `signals:process`, `strategies:optimize` (her dakika/gece) |
| `xauusd-web` | Laravel web arayüzü (`127.0.0.1:8000`) |

Servis dosyaları `~/.config/systemd/user/` altında bulunur. Reboot sonrası da otomatik başlamaları için:

```bash
systemctl --user enable xauusd-ingestion xauusd-scheduler xauusd-web
loginctl enable-linger $USER   # kullanıcı oturum açmasa bile servisler ayakta kalsın diye ŞART
```

Durumu kontrol etmek için:
```bash
systemctl --user status xauusd-ingestion xauusd-scheduler xauusd-web
```

Üçü de `active (running)` göstermeli. Loglara bakmak için:
```bash
journalctl --user -u xauusd-ingestion -f
journalctl --user -u xauusd-scheduler -f
```

---

## 📁 Proje Yapısı

```
Signal-Bot/
├── app/
│   ├── Http/Controllers/     # Laravel controller'lar
│   └── Models/                # Eloquent modeller
├── database/
│   └── migrations/            # candles, strategies, backtests, signals tabloları
├── resources/
│   └── js/
│       ├── Pages/              # Inertia/Vue sayfaları
│       └── Components/         # Vue bileşenleri
├── ingestion/                  # Twelve Data WebSocket → Redis ingestion servisi (Node.js)
└── .env                        # Ortam değişkenleri (repo'ya dahil değil)
```

---

## 🗄️ Veritabanı Şeması

- **candles**: symbol, timeframe, open, high, low, close, volume, opened_at
- **strategies**: name, class, parameters (json), is_active
- **backtests**: strategy_id, period_start, period_end, win_rate, expectancy, max_drawdown, total_signals, wins, losses
- **signals**: strategy_id, symbol, direction (buy/sell), entry_price, sl_price, tp_price, sl_pips (60), tp_pips (min 100), confidence_pct, status (pending/triggered/closed_tp/closed_sl/expired), expected_entry_at, triggered_at, closed_at

---

## 🛠️ Kullanılan Teknolojiler

- **Backend**: Laravel 13, PHP 8.5
- **Frontend**: Vue 3 + Inertia.js + Tailwind CSS
- **Veritabanı**: PostgreSQL 18
- **Cache/Queue**: Redis
- **Fiyat Verisi**: Twelve Data API (WebSocket + REST)
- **Bildirim**: Telegram Bot API (irazasyed/telegram-bot-sdk)