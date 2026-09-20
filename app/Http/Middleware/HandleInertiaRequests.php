<?php

namespace App\Http\Middleware;

use App\Http\Resources\NotificationResource;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $timezone = config('athar.timezone');

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user,
                'roles' => $user ? array_map(fn ($role) => $role->value, $user->roleCodes()) : [],
            ],
            // What the signed-in person may do, decided by the policy. The UI only shows or hides.
            'can' => [
                'create_request' => $user !== null && Gate::forUser($user)->allows('create', ServiceRequest::class),
                'manage_users' => $user !== null && Gate::forUser($user)->allows('viewAny', User::class),
            ],
            // The bell: unread count and the latest few. Cheap enough to send with every page.
            'notifications' => $user ? [
                'unread' => $user->unreadNotifications()->count(),
                'recent' => NotificationResource::collection($user->notifications()->limit(8)->get())->resolve(),
            ] : null,
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'locale' => app()->getLocale(),
            'translations' => $this->translations(app()->getLocale()),
            // Times are stored in UTC and shown in this zone. The offset lets the browser turn
            // "12 October" into "end of 12 October in Jakarta" no matter where the browser is.
            'timezone' => $timezone,
            'utcOffset' => now($timezone)->format('P'),
        ];
    }

    /**
     * UI strings for the current language (English fills any gaps). One dictionary, the same
     * JSON files the backend reads, so a string is written once. Backend-only messages are left out.
     *
     * @return array<string, string>
     */
    private function translations(string $locale): array
    {
        $load = function (string $code): array {
            $path = lang_path("{$code}.json");

            return is_file($path) ? (json_decode((string) file_get_contents($path), true) ?: []) : [];
        };

        $merged = $locale === 'en' ? $load('en') : [...$load('en'), ...$load($locale)];

        return array_filter($merged, fn (string $key) => ! str_starts_with($key, 'errors.'), ARRAY_FILTER_USE_KEY);
    }
}
