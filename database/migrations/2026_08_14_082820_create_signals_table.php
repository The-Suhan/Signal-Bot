<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('signals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('strategy_id')->constrained()->cascadeOnDelete();
            $table->string('symbol')->default('XAUUSD');
            $table->enum('direction', ['buy', 'sell']);
            $table->decimal('entry_price', 10, 3);
            $table->decimal('sl_price', 10, 3);
            $table->decimal('tp_price', 10, 3);
            $table->unsignedInteger('sl_pips')->default(60);
            $table->unsignedInteger('tp_pips');
            $table->decimal('confidence_pct', 5, 2)->nullable();
            $table->enum('status', ['pending', 'triggered', 'closed_tp', 'closed_sl', 'expired'])
                ->default('pending');
            $table->timestamp('expected_entry_at')->nullable(); // 5-10dk sonra tahmini giriş
            $table->timestamp('triggered_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['symbol', 'status']);
            $table->index('strategy_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('signals');
    }
};