<?php

namespace App\Http\Controllers;

use App\Models\Candle;
use App\Services\MarketData\PriceReader;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Vue tarafındaki canlı grafiğin beslendiği hafif JSON uçları. Inertia
 * sayfası olarak değil, düz JSON döndüren polling endpoint'leri olarak
 * tasarlandı (lightweight-charts periyodik olarak bunları çağırır).
 */
class MarketDataController extends Controller
{
    public function candles(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'timeframe' => 'sometimes|in:1m,5m,15m,1h',
            'limit' => 'sometimes|integer|min:10|max:1000',
        ]);

        $symbol = config('services.market_data.symbol');
        $timeframe = $validated['timeframe'] ?? '1m';
        $limit = $validated['limit'] ?? 300;

        $candles = Candle::query()
            ->symbol($symbol)
            ->timeframe($timeframe)
            ->orderByDesc('opened_at')
            ->limit($limit)
            ->get(['open', 'high', 'low', 'close', 'volume', 'opened_at'])
            ->sortBy('opened_at')
            ->values()
            ->map(fn (Candle $c) => [
                // lightweight-charts UNIX saniye (UTC) bekler
                'time' => $c->opened_at->timestamp,
                'open' => (float) $c->open,
                'high' => (float) $c->high,
                'low' => (float) $c->low,
                'close' => (float) $c->close,
            ]);

        return response()->json([
            'symbol' => $symbol,
            'timeframe' => $timeframe,
            'candles' => $candles,
        ]);
    }

    public function lastPrice(PriceReader $priceReader): JsonResponse
    {
        return response()->json([
            'price' => $priceReader->last(),
            'timestamp' => $priceReader->lastTimestamp(),
        ]);
    }
}
