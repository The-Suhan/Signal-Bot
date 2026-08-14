<?php

namespace App\Http\Controllers;

use App\Models\Signal;
use App\Models\Strategy;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        $symbol = config('services.market_data.symbol');

        return Inertia::render('Dashboard', [
            'symbol' => $symbol,
            'activeStrategy' => Strategy::where('is_active', true)->first(['id', 'name', 'parameters']),
            'activeSignalsCount' => Signal::whereIn('status', ['pending', 'triggered'])->count(),
            'latestSignal' => Signal::with('strategy:id,name')
                ->latest('id')
                ->first(['id', 'strategy_id', 'symbol', 'direction', 'entry_price', 'sl_price', 'tp_price', 'status', 'created_at']),
        ]);
    }
}
