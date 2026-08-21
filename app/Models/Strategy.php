<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Strategy extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'class',
        'parameters',
        'is_active',
        'optimization_enabled',
    ];

    protected $casts = [
        'parameters' => 'array',
        'is_active' => 'boolean',
        'optimization_enabled' => 'boolean',
    ];

    public function backtests()
    {
        return $this->hasMany(Backtest::class);
    }

    public function signals()
    {
        return $this->hasMany(Signal::class);
    }

    /** strategies.class alanındaki FQCN'den bir strateji instance'ı üretir. */
    public function makeInstance(): \App\Strategies\StrategyInterface
    {
        $class = $this->class;

        return new $class($this->parameters ?? []);
    }
}
