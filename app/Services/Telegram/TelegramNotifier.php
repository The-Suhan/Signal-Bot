<?php

namespace App\Services\Telegram;

use App\Models\Signal;
use Illuminate\Support\Facades\Log;
use Telegram\Bot\Exceptions\TelegramSDKException;
use Telegram\Bot\Laravel\Facades\Telegram;

/**
 * Sinyal yaşam döngüsündeki her aşama için Telegram bildirimi gönderir:
 * oluşturuldu (PENDING) -> yaklaşıyor -> tetiklendi (TRIGGERED) -> kapandı (TP/SL/expired).
 *
 * Gönderim hataları (ağ, rate limit, geçersiz chat_id vb.) sinyal akışını
 * kesmemeli — bu yüzden burada yakalanıp loglanır, exception fırlatılmaz.
 */
class TelegramNotifier
{
    public function signalCreated(Signal $signal): void
    {
        $emoji = $signal->direction === 'buy' ? '🟢' : '🔴';
        $directionLabel = $signal->direction === 'buy' ? 'ALIŞ (BUY)' : 'SATIŞ (SELL)';

        $eta = $this->localTime($signal->expected_entry_at) ?? '-';

        $text = "{$emoji} *Yeni Sinyal — {$signal->symbol}*\n\n"
            ."Yön: *{$directionLabel}*\n"
            ."Strateji: {$signal->strategy?->name}\n"
            .'Entry: `'.$this->fmt($signal->entry_price)."`\n"
            .'SL: `'.$this->fmt($signal->sl_price)."` ({$signal->sl_pips} pip)\n"
            .'TP: `'.$this->fmt($signal->tp_price)."` ({$signal->tp_pips} pip)\n"
            .($signal->confidence_pct ? "Güven: %{$signal->confidence_pct}\n" : '')
            ."Beklenen giriş: ~{$eta}\n\n"
            .'_Durum: PENDING_';

        $this->send($text);
    }

    public function entryApproaching(Signal $signal, int $minutesLeft): void
    {
        $emoji = $signal->direction === 'buy' ? '🟢' : '🔴';

        $text = "⏱ *Giriş Yaklaşıyor — {$signal->symbol}* {$emoji}\n\n"
            ."Tahmini giriş ~{$minutesLeft} dakika içinde\n"
            .'Entry: `'.$this->fmt($signal->entry_price).'`';

        $this->send($text);
    }

    public function signalTriggered(Signal $signal): void
    {
        $emoji = $signal->direction === 'buy' ? '🟢' : '🔴';

        $text = "🚀 *Entry Tetiklendi — {$signal->symbol}* {$emoji}\n\n"
            .'Entry: `'.$this->fmt($signal->entry_price)."`\n"
            .'SL: `'.$this->fmt($signal->sl_price)."`\n"
            .'TP: `'.$this->fmt($signal->tp_price)."`\n\n"
            .'_Durum: TRIGGERED_';

        $this->send($text);
    }

    public function signalClosedTp(Signal $signal): void
    {
        $text = "✅ *TP VURULDU — {$signal->symbol}*\n\n"
            .'Kapanış: `'.$this->fmt($signal->tp_price)."`\n"
            ."Kazanç: +{$signal->tp_pips} pip 🎉";

        $this->send($text);
    }

    public function signalClosedSl(Signal $signal): void
    {
        $text = "❌ *SL VURULDU — {$signal->symbol}*\n\n"
            .'Kapanış: `'.$this->fmt($signal->sl_price)."`\n"
            ."Kayıp: -{$signal->sl_pips} pip";

        $this->send($text);
    }

    public function signalExpired(Signal $signal): void
    {
        $text = "⌛ *Sinyal Süresi Doldu — {$signal->symbol}*\n\n"
            .'Entry tetiklenmeden geçerlilik süresi sona erdi.';

        $this->send($text);
    }

    private function send(string $text): void
    {
        $chatId = config('services.telegram.chat_id');

        if (! $chatId) {
            Log::warning('Telegram: TELEGRAM_CHAT_ID tanımlı değil, mesaj gönderilmedi.');

            return;
        }

        try {
            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => $text,
                'parse_mode' => 'Markdown',
            ]);
        } catch (TelegramSDKException $e) {
            Log::error('Telegram mesaj gönderilemedi: '.$e->getMessage());
        }
    }

    private function fmt(mixed $price): string
    {
        return number_format((float) $price, 3);
    }

    /**
     * DB/Carbon'da her şey UTC saklanır (bkz. config/app.php); Telegram
     * mesajında insana gösterilecek saat burada açıkça display_timezone'a
     * (Asia/Ashgabat) çevrilir.
     */
    private function localTime(?\Illuminate\Support\Carbon $date): ?string
    {
        return $date?->clone()->setTimezone(config('app.display_timezone'))->format('H:i');
    }
}
