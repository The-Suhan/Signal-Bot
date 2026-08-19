<?php

namespace App\Http\Controllers;

use App\Models\Signal;
use App\Services\Signals\SignalStats;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SignalController extends Controller
{
    /** Geçmiş tablosu zaman filtresi için izin verilen değerler. */
    private const PERIODS = ['today', 'week', 'month', 'all'];

    public function index(Request $request, SignalStats $stats): Response
    {
        $period = $request->query('period', 'all');

        if (! in_array($period, self::PERIODS, true)) {
            $period = 'all';
        }

        [$from] = $stats->periodRange($period);

        return Inertia::render('Signals/Index', [
            // Closure'a sarılmış prop'lar Laravel Inertia'da ilk (tam) sayfa
            // yüklemesinde HER ZAMAN çalışır, ama partial reload'da (Vue
            // tarafında `only: [...]` ile) sadece istenen anahtarlar için
            // çağrılır — yani geçmiş tablosu filtrelenirken (sadece
            // historySignals+filters istenir) bu iki sorgu gereksiz yere
            // tekrar çalışmaz. (Inertia::lazy() burada YANLIŞ olurdu — o,
            // ilk yüklemede de tamamen atlar; biz sadece partial'da atlamak
            // istiyoruz.)
            'activeSignals' => fn () => Signal::with('strategy:id,name')
                ->whereIn('status', ['pending', 'triggered'])
                ->orderByDesc('created_at')
                ->get(),

            'historySignals' => Signal::with('strategy:id,name')
                ->whereIn('status', ['closed_tp', 'closed_sl', 'expired'])
                ->when($from, fn ($q) => $q->where('closed_at', '>=', $from))
                ->orderByDesc('closed_at')
                ->paginate(20)
                ->withQueryString(),

            'filters' => ['period' => $period],

            'stats' => fn () => [
                'week' => $stats->summary(...$stats->periodRange('week')),
                'month' => $stats->summary(...$stats->periodRange('month')),
                'allTime' => $stats->summary(...$stats->periodRange('all')),
            ],
        ]);
    }
}
