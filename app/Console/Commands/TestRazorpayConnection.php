<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Payment\RazorpayPaymentGateway;
use App\Services\PaymentService;
use App\Services\CreditService;

class TestRazorpayConnection extends Command
{
    protected $signature = 'razorpay:test {--detailed : Show detailed configuration}';
    protected $description = 'Test Razorpay payment gateway configuration and connection';

    public function handle(): int
    {
        $this->info('Testing Razorpay Configuration...');
        $this->newLine();

        $apiKey = config('services.razorpay.api_key');
        $secretKey = config('services.razorpay.secret_key');
        $webhookSecret = config('services.razorpay.webhook_secret');
        $testMode = config('services.razorpay.test_mode', true);

        if (empty($apiKey)) {
            $this->error('✗ RAZORPAY_API_KEY is not configured');
            $this->warn('Please add RAZORPAY_API_KEY to your .env file');
            return self::FAILURE;
        }

        if (empty($secretKey)) {
            $this->error('✗ RAZORPAY_SECRET_KEY is not configured');
            $this->warn('Please add RAZORPAY_SECRET_KEY to your .env file');
            return self::FAILURE;
        }

        $this->info('✓ Razorpay credentials configured');
        $this->newLine();

        if ($this->option('detailed')) {
            $this->info('Configuration Details:');
            $this->table(
                ['Setting', 'Value'],
                [
                    ['Test Mode', $testMode ? 'Yes' : 'No'],
                    ['API Key', $this->maskKey($apiKey)],
                    ['Secret Key', $this->maskKey($secretKey)],
                    ['Webhook Secret', $webhookSecret ? $this->maskKey($webhookSecret) : 'Not configured'],
                ]
            );
            $this->newLine();
        }

        try {
            $gateway = new RazorpayPaymentGateway();
            $this->info('✓ Razorpay Gateway instantiated successfully');
        } catch (\Exception $e) {
            $this->error('✗ Failed to instantiate Razorpay Gateway');
            $this->error('  Error: ' . $e->getMessage());
            return self::FAILURE;
        }

        try {
            $currencies = $gateway->getSupportedCurrencies();
            $this->info('✓ Razorpay API connection successful');
            $this->line('  Supported currencies: ' . count($currencies));
            
            if ($this->option('detailed')) {
                $this->line('  Sample: ' . implode(', ', array_slice($currencies, 0, 10)));
            }
            $this->newLine();
        } catch (\Exception $e) {
            $this->error('✗ Failed to connect to Razorpay API');
            $this->error('  Error: ' . $e->getMessage());
            return self::FAILURE;
        }

        try {
            $creditService = app(CreditService::class);
            $paymentService = new PaymentService($creditService);
            $gateways = $paymentService->getAvailableGateways();
            
            $this->info('✓ Payment Service integration working');
            $this->line('  Available gateways: ' . count($gateways));
            foreach ($gateways as $gw) {
                $this->line("    • {$gw['name']} ({$gw['id']})");
            }
            $this->newLine();
        } catch (\Exception $e) {
            $this->error('✗ PaymentService integration failed');
            $this->error('  Error: ' . $e->getMessage());
            return self::FAILURE;
        }

        if (empty($webhookSecret)) {
            $this->warn('⚠ Webhook secret is not configured');
            $this->line('  Webhooks are needed for production payment processing');
            $this->line('  Set RAZORPAY_WEBHOOK_SECRET in your .env file');
            $this->newLine();
        } else {
            $this->info('✓ Webhook secret configured');
            $this->newLine();
        }

        $this->info('✅ All Razorpay tests passed!');
        $this->newLine();
        
        if ($testMode) {
            $this->warn('⚠ Razorpay is in TEST MODE');
            $this->line('  Set RAZORPAY_TEST_MODE=false for live transactions');
        } else {
            $this->info('🔴 Razorpay is in LIVE MODE');
            $this->line('  Real transactions will be processed');
        }

        return self::SUCCESS;
    }

    protected function maskKey(string $key): string
    {
        if (strlen($key) <= 8) {
            return str_repeat('*', strlen($key));
        }
        return substr($key, 0, 4) . str_repeat('*', strlen($key) - 8) . substr($key, -4);
    }
}
