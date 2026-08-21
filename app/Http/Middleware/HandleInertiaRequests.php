<?php

namespace App\Http\Middleware;

use App\Support\Format\IndiaDate;
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
                'error' => fn () => $request->session()->get('error'),
                'print_receipt_id' => fn () => $request->session()->get('print_receipt_id'),
            ],
            'tenant' => fn () => $request->user()?->tenant ? [
                'name' => $request->user()->tenant->name,
                'single_batch_mode' => $request->user()->tenant->usesSingleBatch(),
            ] : null,
            'locale' => [
                'dateDisplay' => IndiaDate::pattern(),
                'dateDisplayHint' => 'dd-mm-yyyy',
            ],
        ];
    }
}
