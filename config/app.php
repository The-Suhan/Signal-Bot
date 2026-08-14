<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Application Name
    |--------------------------------------------------------------------------
    |
    | This value is the name of your application, which will be used when the
    | framework needs to place the application's name in a notification or
    | other UI elements where an application name needs to be displayed.
    |
    */

    'name' => env('APP_NAME', 'Laravel'),

    /*
    |--------------------------------------------------------------------------
    | Application Environment
    |--------------------------------------------------------------------------
    |
    | This value determines the "environment" your application is currently
    | running in. This may determine how you prefer to configure various
    | services the application utilizes. Set this in your ".env" file.
    |
    */

    'env' => env('APP_ENV', 'production'),

    /*
    |--------------------------------------------------------------------------
    | Application Debug Mode
    |--------------------------------------------------------------------------
    |
    | When your application is in debug mode, detailed error messages with
    | stack traces will be shown on every error that occurs within your
    | application. If disabled, a simple generic error page is shown.
    |
    */

    'debug' => (bool) env('APP_DEBUG', false),

    /*
    |--------------------------------------------------------------------------
    | Application URL
    |--------------------------------------------------------------------------
    |
    | This URL is used by the console to properly generate URLs when using
    | the Artisan command line tool. You should set this to the root of
    | the application so that it's available within Artisan commands.
    |
    */

    'url' => env('APP_URL', 'http://localhost'),

    /*
    |--------------------------------------------------------------------------
    | Application Timezone
    |--------------------------------------------------------------------------
    |
    | BİLİNÇLİ OLARAK "UTC" SABİT BIRAKILDI — APP_TIMEZONE'a BAĞLAMAYIN.
    |
    | Bu değer PHP'nin date_default_timezone_set()'ini ve dolayısıyla
    | Eloquent'in now()/Carbon çıktısını belirler. candles/signals
    | tablolarındaki tüm sütunlar "timestamp without time zone" ve UTC
    | wall-clock olarak saklanıyor (Node ingestion servisi de aynı şekilde
    | UTC yazıyor — bkz. ingestion/src/db.js). Bu değer Asia/Ashgabat gibi
    | bir yerel saat dilimine çevrilirse:
    |   - now() yerel saati döndürür ama DB'ye hâlâ UTC etiketiyle yazılır
    |   - Backtest/optimize'daki whereBetween(opened_at, [now()-Xgün, now()])
    |     pencereleri 5 saat kayar (Node'un yazdığı gerçek UTC candle'larla
    |     karşılaştırıldığında) — daha önce Redis prefix ve Node Date
    |     serialization'da yaşanan "UTC uyumsuzluğu" bug'larıyla aynı sınıf.
    |
    | Kullanıcıya GÖSTERİLECEK yerel saat için 'display_timezone' (aşağıda)
    | kullanılır — APP_TIMEZONE oraya bağlı.
    |
    */

    'timezone' => 'UTC',

    /*
    |--------------------------------------------------------------------------
    | Display Timezone (kullanıcıya gösterim için)
    |--------------------------------------------------------------------------
    |
    | DB'de/Carbon'da her şey UTC kalır; bu değer sadece insan tarafından
    | okunacak çıktılarda (Telegram mesajları, sunucu tarafında formatlanan
    | tarihler) ve frontend'e paylaşılan prop olarak kullanılır.
    | bkz. app/Http/Middleware/HandleInertiaRequests.php ve
    | app/Services/Telegram/TelegramNotifier.php.
    |
    */

    'display_timezone' => env('APP_TIMEZONE', 'UTC'),

    /*
    |--------------------------------------------------------------------------
    | Application Locale Configuration
    |--------------------------------------------------------------------------
    |
    | The application locale determines the default locale that will be used
    | by Laravel's translation / localization methods. This option can be
    | set to any locale for which you plan to have translation strings.
    |
    */

    'locale' => env('APP_LOCALE', 'en'),

    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'en'),

    'faker_locale' => env('APP_FAKER_LOCALE', 'en_US'),

    /*
    |--------------------------------------------------------------------------
    | Encryption Key
    |--------------------------------------------------------------------------
    |
    | This key is utilized by Laravel's encryption services and should be set
    | to a random, 32 character string to ensure that all encrypted values
    | are secure. You should do this prior to deploying the application.
    |
    */

    'cipher' => 'AES-256-CBC',

    'key' => env('APP_KEY'),

    'previous_keys' => [
        ...array_filter(
            explode(',', (string) env('APP_PREVIOUS_KEYS', ''))
        ),
    ],

    /*
    |--------------------------------------------------------------------------
    | Maintenance Mode Driver
    |--------------------------------------------------------------------------
    |
    | These configuration options determine the driver used to determine and
    | manage Laravel's "maintenance mode" status. The "cache" driver will
    | allow maintenance mode to be controlled across multiple machines.
    |
    | Supported drivers: "file", "cache", "array"
    |
    */

    'maintenance' => [
        'driver' => env('APP_MAINTENANCE_DRIVER', 'file'),
        'store' => env('APP_MAINTENANCE_STORE', 'database'),
    ],

];
