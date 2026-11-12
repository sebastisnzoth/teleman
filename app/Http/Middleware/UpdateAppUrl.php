<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps generated URLs consistent with the host the request actually came in
 * on (www vs non-www, or a preview domain), without persisting anything.
 *
 * This middleware used to rewrite APP_URL inside .env on every request. When
 * the browsed host differed from APP_URL — behind a proxy, or simply www vs
 * non-www — that meant an unsynchronised read/modify/write of .env on *every*
 * request, and concurrent requests could interleave and leave the file
 * truncated or completely empty. .env is configuration, not runtime state: it
 * is now only ever changed deliberately (installer / admin settings) through
 * App\Support\EnvWriter.
 */
class UpdateAppUrl
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $currentUrl = $request->getSchemeAndHttpHost();

        if ($currentUrl !== config('app.url') && ! app()->environment('testing')) {
            // In-memory only, for this request. Nothing is written to disk.
            config(['app.url' => $currentUrl]);
            url()->forceRootUrl($currentUrl);
        }

        return $next($request);
    }
}
