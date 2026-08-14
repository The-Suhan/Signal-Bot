<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Signal extends Model
{
    use HasFactory;

    protected $fillable = [
        'strategy_id',
        'symbol',
        'direction',
        'entry_price',
        'sl_price',
        'tp_price',
        'sl_pips',
        'tp_pips',
        'confidence_pct',
        'status',
        'expected_entry_at',
        'approaching_notified_at',
        'triggered_at',
        'closed_at',
    ];

    protected $casts = [
        'expected_entry_at' => 'datetime',
        'approaching_notified_at' => 'datetime',
        'triggered_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function strategy()
    {
        return $this->belongsTo(Strategy::class);
    }
}
