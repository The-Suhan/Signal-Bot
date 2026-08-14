<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Backtest extends Model
{
    use HasFactory;

    protected $fillable = [
        'strategy_id',
        'period_start',
        'period_end',
        'win_rate',
        'expectancy',
        'max_drawdown',
        'total_signals',
        'wins',
        'losses',
    ];

    protected $casts = [
        'period_start' => 'datetime',
        'period_end' => 'datetime',
    ];

    public function strategy()
    {
        return $this->belongsTo(Strategy::class);
    }
}
