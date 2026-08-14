<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Candle extends Model
{
    use HasFactory;

    protected $fillable = [
        'symbol',
        'timeframe',
        'open',
        'high',
        'low',
        'close',
        'volume',
        'opened_at',
    ];

    protected $casts = [
        'open' => 'decimal:3',
        'high' => 'decimal:3',
        'low' => 'decimal:3',
        'close' => 'decimal:3',
        'volume' => 'decimal:2',
        'opened_at' => 'datetime',
    ];

    /** Node ingestion servisinin gerçek zamanlı yazdığı tek zaman dilimi. */
    public const SOURCE_TIMEFRAME = '1m';

    /** 1m'den roll-up ile üretilen üst zaman dilimleri (dakika => etiket). */
    public const ROLLUP_TIMEFRAMES = [
        5 => '5m',
        15 => '15m',
        60 => '1h',
    ];

    public function scopeSymbol($query, string $symbol)
    {
        return $query->where('symbol', $symbol);
    }

    public function scopeTimeframe($query, string $timeframe)
    {
        return $query->where('timeframe', $timeframe);
    }
}
