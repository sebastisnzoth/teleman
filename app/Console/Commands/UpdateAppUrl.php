<?php

namespace App\Console\Commands;

use App\Support\EnvWriter;
use Illuminate\Console\Command;

class UpdateAppUrl extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:update-url {url? : The URL to set as APP_URL}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update APP_URL in .env file based on current request or provided URL';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $envPath = app()->environmentFilePath();

        if (! file_exists($envPath)) {
            $this->error('.env file not found!');

            return 1;
        }

        // Get URL from argument or detect from request
        $url = $this->argument('url');

        if (! $url) {
            $url = $this->detectUrl();
        }

        if (! $url) {
            $this->error('Could not detect URL. Please provide URL as argument.');

            return 1;
        }

        // Validate URL
        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            $this->error('Invalid URL format: '.$url);

            return 1;
        }

        // Locked + atomic write; never leaves a truncated .env behind.
        EnvWriter::make($envPath)->set(['APP_URL' => $url]);

        $this->info('APP_URL updated successfully to: '.$url);

        // Clear config cache
        $this->call('config:clear');

        return 0;
    }

    /**
     * Detect URL from current environment
     */
    protected function detectUrl(): ?string
    {
        // Try to get from current config (might be set via web request)
        if (app()->runningInConsole()) {
            // If running in console, try to detect from server
            $host = gethostname();
            $protocol = 'http';

            // Check if we're in a typical local development environment
            if (str_contains($host, 'localhost') || str_contains($host, '127.0.0.1')) {
                return 'http://localhost';
            }

            return null;
        }

        // If running via web, get from request
        return request()->getSchemeAndHttpHost();
    }
}
