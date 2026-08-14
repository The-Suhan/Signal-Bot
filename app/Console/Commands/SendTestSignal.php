<?php

namespace App\Console\Commands;

use App\Models\Signal;
use App\Models\Strategy;
use App\Services\Telegram\TelegramNotifier;
use App\Strategies\EmaCrossStrategy;
use App\Strategies\RiskRules;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('telegram:test-signal {--direction=buy}')]
#[Description('Telegram entegrasyonunu doğrulamak için PENDING -> TRIGGERED -> CLOSED_TP akışını gerçek verilerle simüle edip 3 mesaj gönderir')]
class SendTestSignal extends Command
{
    public function handle(TelegramNotifier $notifier): int
    {
        $direction = $this->option('direction') === 'sell' ? 'sell' : 'buy';

        $strategy = Strategy::firstOrCreate(
            ['class' => EmaCrossStrategy::class],
            ['name' => 'EMA Cross', 'parameters' => ['fast_period' => 12, 'slow_period' => 26, 'tp_pips' => 100], 'is_active' => false]
        );

        $entry = 4351.500;
        $slPips = RiskRules::FIXED_SL_PIPS;
        $tpPips = 100;
        $sl = RiskRules::slPriceFor($direction, $entry);
        $tp = RiskRules::tpPriceFor($direction, $entry, $tpPips);

        $signal = Signal::create([
            'strategy_id' => $strategy->id,
            'symbol' => 'XAUUSD',
            'direction' => $direction,
            'entry_price' => $entry,
            'sl_price' => $sl,
            'tp_price' => $tp,
            'sl_pips' => $slPips,
            'tp_pips' => $tpPips,
            'confidence_pct' => 72.5,
            'status' => 'pending',
            'expected_entry_at' => now()->addMinutes(7),
        ]);

        $this->info("Test sinyali oluşturuldu (#{$signal->id}, {$direction}). Telegram'a 3 mesaj gönderiliyor...");

        $notifier->signalCreated($signal);
        $this->line('1/3 gönderildi: sinyal oluşturuldu (PENDING)');

        $signal->update(['status' => 'triggered', 'triggered_at' => now()]);
        $notifier->signalTriggered($signal);
        $this->line('2/3 gönderildi: entry tetiklendi (TRIGGERED)');

        $signal->update(['status' => 'closed_tp', 'closed_at' => now()]);
        $notifier->signalClosedTp($signal);
        $this->line('3/3 gönderildi: TP vuruldu (CLOSED_TP)');

        $this->info('Tamamlandı. Telegram sohbetini kontrol edin.');

        return self::SUCCESS;
    }
}
