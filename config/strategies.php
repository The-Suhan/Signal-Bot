<?php

use App\Strategies\EmaCrossStrategy;
use App\Strategies\RsiDivergenceStrategy;
use App\Strategies\SupportResistanceBreakoutStrategy;

/**
 * Sistemde bilinen tüm strateji sınıfları ve varsayılan parametreleri.
 * strategies:optimize komutu bu listedeki her sınıf için bir DB satırı
 * (yoksa) oluşturur, sonra hepsini backtest edip en iyisini aktif eder.
 *
 * Yeni bir strateji eklemek için: sınıfı App\Strategies altına yaz,
 * StrategyInterface'i implement et, buraya bir satır ekle.
 */
return [
    /**
     * En son canlı parametre değişikliğinin (SL/TP, strateji parametreleri
     * vb.) uygulandığı UTC zaman damgası. Sinyaller sayfasındaki "eski
     * dönem / yeni dönem" karşılaştırma kartı bu tarihi kesim noktası
     * olarak kullanır. Yeni bir parametre değişikliği onaylanıp
     * uygulandığında bu satır güncellenmeli.
     */
    'last_parameter_change_at' => '2026-08-21 10:48:06',
    'last_parameter_change_summary' => 'SL 60→40 pip, EMA fast_period 12→20, SR buffer_pips 2→10',

    'registry' => [
        [
            'name' => 'EMA Cross',
            'class' => EmaCrossStrategy::class,
            // fast_period 2026-08-21 performans analizi sonrası 12'den 20'ye
            // çıkarıldı — 1.5 haftalık gerçek veriyle doğrulandı: expectancy
            // 0.0526R -> 0.1594R (~3x). bkz. RiskRules::FIXED_SL_PIPS notu.
            'parameters' => ['fast_period' => 20, 'slow_period' => 26, 'tp_pips' => 100],
        ],
        [
            'name' => 'RSI Divergence',
            'class' => RsiDivergenceStrategy::class,
            'parameters' => ['rsi_period' => 14, 'pivot_width' => 2, 'lookback' => 40, 'confirm_window' => 3, 'tp_pips' => 100],
            // 2026-08-21 performans analizi: 5 ayrı gecelik walk-forward
            // backtest'inde (30 günlük/kümülatif pencere) hiç pozitif
            // expectancy üretemedi (-0.03 ile -0.62 arası). Grid-search en
            // iyi kombinasyonun zaten bu varsayılan parametreler olduğunu
            // gösterdi — yani parametre sorunu değil, tutarsız/rejime bağımlı
            // bir edge sorunu. Kullanıcı onayıyla optimizasyon havuzundan
            // çıkarıldı: strategies:optimize bunu artık hiç backtest etmez,
            // asla is_active=true yapamaz.
            'optimization_enabled' => false,
        ],
        [
            'name' => 'Support/Resistance Breakout',
            'class' => SupportResistanceBreakoutStrategy::class,
            // buffer_pips 2026-08-21 performans analizi sonrası 2'den 10'a
            // çıkarıldı — 1.5 haftalık gerçek veriyle doğrulandı: fakeout
            // (yanlış kırılım) filtrelemesi iyileşti, expectancy 0.1290R ->
            // 0.2533R (~2x). bkz. RiskRules::FIXED_SL_PIPS notu.
            'parameters' => ['lookback' => 20, 'buffer_pips' => 10, 'tp_pips' => 100],
        ],
    ],
];
