<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Installation;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks the installer once the app is installed.
 *
 * This sits on the controller itself rather than only in the global
 * middleware stack: the installer writes database credentials into .env and
 * creates an admin account with no authentication, so it must refuse to run
 * on its own terms even if the routing layer is misconfigured or the marker
 * file is missing.
 */
class EnsureNotInstalled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Installation::isInstalled()) {
            // 404 rather than 403: don't advertise that an installer exists.
            abort(404);
        }

        return $next($request);
    }
}
