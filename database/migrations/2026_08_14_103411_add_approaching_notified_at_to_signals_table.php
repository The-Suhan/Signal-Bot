<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('signals', function (Blueprint $table) {
            // "Entry'ye yaklaşıyor" Telegram bildiriminin bir sinyal için
            // sadece BİR KEZ gönderilmesini sağlamak için kullanılır.
            $table->timestamp('approaching_notified_at')->nullable()->after('expected_entry_at');
        });
    }

    public function down(): void
    {
        Schema::table('signals', function (Blueprint $table) {
            $table->dropColumn('approaching_notified_at');
        });
    }
};
