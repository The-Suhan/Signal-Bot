<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('strategies', function (Blueprint $table) {
            // strategies:optimize'ın bir stratejiyi "aday" olarak
            // değerlendirip değerlendirmeyeceğini kontrol eder. is_active'ten
            // farklı: is_active şu an canlıda çalışanı gösterir,
            // optimization_enabled=false olan bir strateji walk-forward
            // tarafından hiç backtest edilmez/kazanamaz (örn. tutarsız/negatif
            // edge nedeniyle havuzdan çıkarılan RSI Divergence — bkz. 2026-08-21
            // performans analizi).
            $table->boolean('optimization_enabled')->default(true)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('strategies', function (Blueprint $table) {
            $table->dropColumn('optimization_enabled');
        });
    }
};
