<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

/** Locale scaffolding: user preference → session → Accept-Language → default. Only enabled locales are honoured. */
class SetLocale
{
    public function handle(Request $request, Closure $next)
    {
        $enabled = collect(config('shared.locales'))->where('enabled', true)->pluck('code')->all();
        $candidates = [
            $request->user()?->locale,
            $request->hasSession() ? $request->session()->get('locale') : null,
            substr((string) $request->header('Accept-Language'), 0, 2),
        ];
        $locale = collect($candidates)->first(fn ($l) => $l && in_array($l, $enabled, true)) ?? config('shared.defaultLocale');
        App::setLocale($locale);

        return $next($request);
    }
}
