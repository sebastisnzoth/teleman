<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;

class TunnelUrlServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Auto-detect and update APP_URL from tunnel headers
        if (app()->environment('local')) {
            $this->detectTunnelUrl();
        }
    }

    /**
     * Detect tunnel URL from request headers
     */
    private function detectTunnelUrl(): void
    {
        $request = request();
        
        // Check for common tunnel headers
        $tunnelHeaders = [
            'x-forwarded-host',      // Localtunnel, ngrok
            'x-original-host',       // Some proxies
            'cf-connecting-ip',      // Cloudflare
        ];
        
        foreach ($tunnelHeaders as $header) {
            $tunnelHost = $request->header($header);
            
            if ($tunnelHost) {
                // Build tunnel URL
                $protocol = $request->secure() ? 'https' : 'http';
                $tunnelUrl = "{$protocol}://{$tunnelHost}";
                
                // Check if it's different from current APP_URL
                $currentUrl = rtrim(config('app.url'), '/');
                
                if ($tunnelUrl !== $currentUrl) {
                    // Update APP_URL in config at runtime
                    config(['app.url' => $tunnelUrl]);
                    
                    Log::info('APP_URL automatically updated to: ' . $tunnelUrl);
                }
                
                break;
            }
        }
        
        // Also check if request host is from known tunnel services
        $host = $request->getHost();
        $knownTunnelDomains = [
            '.loca.lt',              // Localtunnel
            '.ngrok.io',             // ngrok
            '.sharedwithexpose.com', // Expose
            '.trycloudflare.com',    // Cloudflare Tunnel
        ];
        
        foreach ($knownTunnelDomains as $domain) {
            if (str_ends_with($host, $domain)) {
                $protocol = $request->secure() ? 'https' : 'http';
                $tunnelUrl = "{$protocol}://{$host}";
                
                $currentUrl = rtrim(config('app.url'), '/');
                
                if ($tunnelUrl !== $currentUrl) {
                    config(['app.url' => $tunnelUrl]);
                    Log::info('APP_URL automatically updated to: ' . $tunnelUrl);
                }
                
                break;
            }
        }
    }
}
