<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Language;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = null;

        // Priority: 1) Query param, 2) User preference, 3) Session, 4) Default
        if ($request->has('lang')) {
            $locale = $request->get('lang');
        } elseif ($request->user()?->locale) {
            $locale = $request->user()->locale;
        } elseif ($request->session()->has('locale')) {
            $locale = $request->session()->get('locale');
        }

        // Validate the locale exists and is active
        if ($locale) {
            // Before installation neither the cache table nor the languages
            // table exists; treat that as "no locales" rather than crashing.
            $validLocales = rescue(fn () => Cache::remember('active_locale_codes', 3600, function () {
                return Language::active()->pluck('code')->toArray();
            }), [], false);

            if (in_array($locale, $validLocales, true)) {
                app()->setLocale($locale);
                $request->session()->put('locale', $locale);
            }
        }

        return $next($request);
    }
}
