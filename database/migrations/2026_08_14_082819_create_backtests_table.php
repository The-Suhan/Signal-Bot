<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backtests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('strategy_id')->constrained()->cascadeOnDelete();
            $table->timestamp('period_start');
            $table->timestamp('period_end');
            $table->decimal('win_rate', 5, 2)->nullable(); // %72.50 gibi
            $table->decimal('expectancy', 10, 4)->nullable();
            $table->decimal('max_drawdown', 10, 4)->nullable();
            $table->unsignedInteger('total_signals')->default(0);
            $table->unsignedInteger('wins')->default(0);
            $table->unsignedInteger('losses')->default(0);
            $table->timestamps();

            $table->index(['strategy_id', 'period_end']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backtests');
    }
};