<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Installation;
use Illuminate\Http\Middleware\TrustHosts as Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Answer only to this site's own domain.
 *
 * Links — the one in a password-reset email included — are built from the
 * host a request names, and every proxy is trusted (bootstrap/app.php), so a
 * request could name any host in X-Forwarded-Host and the reset email it
 * triggered for someone else's address pointed there, token and all. A
 * request naming another host is refused (400 Bad Request).
 *
 * Trusted: APP_URL's host and its www. twin, either way round, plus the hosts
 * in APP_TRUSTED_HOSTS. Other subdomains are not: one that has come loose in
 * DNS could be claimed by somebody else.
 *
 * Nothing is enforced before the site is installed — the installer runs on
 * whatever domain it was uploaded to. Nor is it while APP_URL is still the
 * shipped http://localhost, which would take an upgraded site that never set
 * it offline; there X-Forwarded-Host is ignored instead, so a header cannot
 * choose the host links are built from, and the owner is warned to set it.
 */
class TrustHosts extends Middleware
{
    /**
     * @return list<string>
     */
    public function hosts(): array
    {
        $host = strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));
        $bare = str_starts_with($host, 'www.') ? substr($host, 4) : $host;
        $extra = array_filter(array_map('trim', explode(',', (string) config('app.trusted_hosts'))));

        return array_map(
            fn (string $trusted): string => '^'.preg_quote($trusted).'$',
            array_values(array_unique([$bare, 'www.'.$bare, ...$extra])),
        );
    }

    public function handle(Request $request, $next)
    {
        $enforce = $this->shouldSpecifyTrustedHosts();

        // Set on every request, even to nothing: the list is static, and would
        // otherwise outlive the request that set it.
        Request::setTrustedHosts($enforce ? $this->hosts() : []);

        if (! $enforce && $this->installedWithoutAnAddress()) {
            $request->headers->remove('X-Forwarded-Host');

            if (Cache::add('trust-hosts:app-url-unset-warned', true, now()->addDay())) {
                Log::warning('APP_URL is not set to this site\'s address, so requests for any host are answered. Set APP_URL in .env to the address customers use.');
            }
        }

        return $next($request);
    }

    protected function shouldSpecifyTrustedHosts(): bool
    {
        return parent::shouldSpecifyTrustedHosts() && $this->addressIsSet() && Installation::isInstalled();
    }

    private function installedWithoutAnAddress(): bool
    {
        return parent::shouldSpecifyTrustedHosts() && ! $this->addressIsSet() && Installation::isInstalled();
    }

    private function addressIsSet(): bool
    {
        return ! in_array(parse_url((string) config('app.url'), PHP_URL_HOST), [null, false, '', 'localhost', '127.0.0.1'], true);
    }
}
