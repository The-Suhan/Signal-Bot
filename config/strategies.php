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
    'registry' => [
        [
            'name' => 'EMA Cross',
            'class' => EmaCrossStrategy::class,
            'parameters' => ['fast_period' => 12, 'slow_period' => 26, 'tp_pips' => 100],
        ],
        [
            'name' => 'RSI Divergence',
            'class' => RsiDivergenceStrategy::class,
            'parameters' => ['rsi_period' => 14, 'pivot_width' => 2, 'lookback' => 40, 'confirm_window' => 3, 'tp_pips' => 100],
        ],
        [
            'name' => 'Support/Resistance Breakout',
            'class' => SupportResistanceBreakoutStrategy::class,
            'parameters' => ['lookback' => 20, 'buffer_pips' => 2, 'tp_pips' => 100],
        ],
    ],
];
