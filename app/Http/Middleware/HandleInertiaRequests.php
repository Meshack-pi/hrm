<?php

namespace App\Http\Middleware;

use App\Http\Controllers\ReportController;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user() ? $request->user()->load('roles') : null,
                'permissions' => $request->user()?->getAllPermissions()->pluck('name') ?? [],
                'roles' => $request->user()?->getRoleNames() ?? [],
            ],
            'reportSections' => fn () => $request->user()
                ? app(ReportController::class)->sections($request->user())
                : [],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'warning' => fn () => $request->session()->get('warning'),
            ],
            'appName' => config('app.name'),
        ];
    }
}
