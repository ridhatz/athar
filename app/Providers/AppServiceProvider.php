<?php

namespace App\Providers;

use App\Models\ServiceRequest;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureRouteBindings();
        $this->configureRateLimiting();
    }

    /**
     * {serviceRequest} accepts a numeric id or a request number (ATH-000124) and is looked up
     * through the caller's visibility scope. A request you may not see is therefore a plain
     * 404, indistinguishable from one that does not exist, so its existence is never leaked.
     */
    protected function configureRouteBindings(): void
    {
        Route::bind('serviceRequest', function (string $value, $route) {
            $user = request()->user();

            abort_if($user === null, 401);

            $query = ServiceRequest::query()->visibleTo($user);

            return (ctype_digit($value)
                ? $query->whereKey($value)
                : $query->where('requests.request_number', $value)
            )->firstOrFail();
        });
    }

    protected function configureRateLimiting(): void
    {
        // Baseline for every authenticated API call (see throttleApi() in bootstrap/app.php).
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(120)
            ->by($request->user()?->id ?: $request->ip()));

        RateLimiter::for('api-login', fn (Request $request) => Limit::perMinute(5)
            ->by(strtolower((string) $request->input('email')).'|'.$request->ip()));

        RateLimiter::for('uploads', fn (Request $request) => Limit::perMinute(30)
            ->by($request->user()?->id ?: $request->ip()));

        // Generous for real use, tight enough to stop a runaway script from flooding the queue.
        RateLimiter::for('create-request', fn (Request $request) => Limit::perMinute(20)
            ->by($request->user()?->id ?: $request->ip()));
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
