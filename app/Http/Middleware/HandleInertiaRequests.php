<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user(),
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
            ],
            // Backend'de her şey UTC saklanır (bkz. config/app.php); frontend
            // tarih/saat gösterirken bunu kullanarak açıkça Asia/Ashgabat'a
            // çevirir (tarayıcının kendi saat dilimine güvenmek yerine).
            'displayTimezone' => config('app.display_timezone'),
        ];
    }
}
