<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Picks the UI language.
 *
 *  Web:  the signed-in user's saved choice, else the "locale" cookie (set from the login screen), else the app default.
 *  API:  an explicit Accept-Language header wins (a client asking for a language means it), then the user's saved choice.
 */
class SetLocale
{
    public const SUPPORTED = ['id', 'en'];

    public function handle(Request $request, Closure $next): Response
    {
        app()->setLocale($this->resolve($request));

        return $next($request);
    }

    private function resolve(Request $request): string
    {
        $candidates = $request->is('api/*')
            ? [$this->fromAcceptLanguage($request), $request->user()?->locale, $request->cookie('locale')]
            : [$request->user()?->locale, $request->cookie('locale')];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && in_array($candidate, self::SUPPORTED, true)) {
                return $candidate;
            }
        }

        return config('app.locale');
    }

    /** First supported language the client asked for, or null (an unsupported header must not force a default). */
    private function fromAcceptLanguage(Request $request): ?string
    {
        foreach ($request->getLanguages() as $language) {
            $primary = strtolower(substr($language, 0, 2));

            if (in_array($primary, self::SUPPORTED, true)) {
                return $primary;
            }
        }

        return null;
    }
}
