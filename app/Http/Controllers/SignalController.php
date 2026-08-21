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

            // Bilgi amaçlı: son canlı parametre değişikliğinden (bkz.
            // config/strategies.php) önceki/sonraki dönemin performansını
            // ayrı ayrı gösterir — "yeni parametreler gerçekten iyileşme mi
            // getirdi" sorusunu canlı veriyle takip edebilmek için.
            'parameterComparison' => fn () => $this->parameterComparison($stats),
        ]);
    }

    private function parameterComparison(SignalStats $stats): ?array
    {
        $changedAt = config('strategies.last_parameter_change_at');

        if (! $changedAt) {
            return null;
        }

        $cutoff = \Illuminate\Support\Carbon::parse($changedAt, 'UTC');

        return [
            'changed_at' => $cutoff->toIso8601String(),
            'summary' => config('strategies.last_parameter_change_summary'),
            'before' => $stats->summary(null, $cutoff),
            'after' => $stats->summary($cutoff, null),
        ];
    }
}
