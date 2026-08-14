<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candles', function (Blueprint $table) {
            $table->id();
            $table->string('symbol')->default('XAUUSD');
            $table->string('timeframe'); // 1m, 5m, 15m, 1h
            $table->decimal('open', 10, 3);
            $table->decimal('high', 10, 3);
            $table->decimal('low', 10, 3);
            $table->decimal('close', 10, 3);
            $table->decimal('volume', 15, 2)->nullable();
            $table->timestamp('opened_at');
            $table->timestamps();

            $table->unique(['symbol', 'timeframe', 'opened_at']);
            $table->index(['symbol', 'timeframe', 'opened_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candles');
    }
};