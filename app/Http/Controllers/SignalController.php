<?php

namespace App\Http\Controllers;

use App\Models\Signal;
use Inertia\Inertia;
use Inertia\Response;

class SignalController extends Controller
{
    public function index(): Response
    {
        $active = Signal::with('strategy:id,name')
            ->whereIn('status', ['pending', 'triggered'])
            ->orderByDesc('created_at')
            ->get();

        $history = Signal::with('strategy:id,name')
            ->whereIn('status', ['closed_tp', 'closed_sl', 'expired'])
            ->orderByDesc('closed_at')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Signals/Index', [
            'activeSignals' => $active,
            'historySignals' => $history,
        ]);
    }
}
