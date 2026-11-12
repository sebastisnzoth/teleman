<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Payment\StripePaymentGateway;
use App\Services\PaymentService;
use App\Services\CreditService;

class TestStripeConnection extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'stripe:test
                            {--detailed : Show detailed configuration}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test Stripe payment gateway configuration and connection';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Testing Stripe Configuration...');
        $this->newLine();

        // Check if Stripe credentials are set
        $secretKey = config('services.stripe.secret');
        $publicKey = config('services.stripe.public');
        $webhookSecret = config('services.stripe.webhook_secret');
        $testMode = config('services.stripe.test_mode', true);

        if (empty($secretKey)) {
            $this->error('✗ STRIPE_SECRET_KEY is not configured');
            $this->warn('Please add STRIPE_SECRET_KEY to your .env file');
            $this->newLine();
            $this->line('Get your Stripe keys from: https://dashboard.stripe.com/apikeys');
            return self::FAILURE;
        }

        if (empty($publicKey)) {
            $this->error('✗ STRIPE_PUBLIC_KEY is not configured');
            $this->warn('Please add STRIPE_PUBLIC_KEY to your .env file');
            return self::FAILURE;
        }

        // Validate key format
        $isTestSecret = str_starts_with($secretKey, 'sk_test_');
        $isLiveSecret = str_starts_with($secretKey, 'sk_live_');
        $isTestPublic = str_starts_with($publicKey, 'pk_test_');
        $isLivePublic = str_starts_with($publicKey, 'pk_live_');

        if (!$isTestSecret && !$isLiveSecret) {
            $this->error('✗ STRIPE_SECRET_KEY does not appear to be valid');
            $this->warn('Secret keys should start with sk_test_ or sk_live_');
            return self::FAILURE;
        }

        if (!$isTestPublic && !$isLivePublic) {
            $this->error('✗ STRIPE_PUBLIC_KEY does not appear to be valid');
            $this->warn('Public keys should start with pk_test_ or pk_live_');
            return self::FAILURE;
        }

        // Check if test/live keys match
        if (($isTestSecret && !$isTestPublic) || ($isLiveSecret && !$isLivePublic)) {
            $this->error('✗ Stripe key mismatch detected!');
            $this->warn('Your secret and public keys are from different modes (test vs live)');
            return self::FAILURE;
        }

        $this->info('✓ Stripe credentials configured');
        $mode = $isTestSecret ? 'TEST' : 'LIVE';
        $this->line("  Mode: <fg=cyan>{$mode}</>");
        $this->newLine();

        // Show detailed configuration if requested
        if ($this->option('detailed')) {
            $this->info('Configuration Details:');
            $this->table(
                ['Setting', 'Value'],
                [
                    ['Test Mode', $testMode ? 'Yes' : 'No'],
                    ['Secret Key', $this->maskKey($secretKey)],
                    ['Public Key', $this->maskKey($publicKey)],
                    ['Webhook Secret', $webhookSecret ? $this->maskKey($webhookSecret) : 'Not configured'],
                    ['Fee Percentage', config('services.stripe.fee_percentage', 2.9) . '%'],
                    ['Fixed Fee', '$' . config('services.stripe.fee_fixed', 0.30)],
                ]
            );
            $this->newLine();
        }

        // Test instantiation
        try {
            $gateway = new StripePaymentGateway();
            $this->info('✓ Stripe Gateway instantiated successfully');
        } catch (\Exception $e) {
            $this->error('✗ Failed to instantiate Stripe Gateway');
            $this->error('  Error: ' . $e->getMessage());
            return self::FAILURE;
        }

        // Test API connection by getting supported currencies
        try {
            $currencies = $gateway->getSupportedCurrencies();
            $this->info('✓ Stripe API connection successful');
            $this->line('  Supported currencies: ' . count($currencies));
            
            if ($this->option('detailed')) {
                $this->line('  Sample: ' . implode(', ', array_slice($currencies, 0, 15)));
            }
            $this->newLine();
        } catch (\Exception $e) {
            $this->error('✗ Failed to connect to Stripe API');
            $this->error('  Error: ' . $e->getMessage());
            $this->newLine();
            $this->warn('This could mean:');
            $this->line('  - Your API key is invalid');
            $this->line('  - Network connectivity issues');
            $this->line('  - Stripe API is down');
            return self::FAILURE;
        }

        // Test PaymentService integration
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

        // Webhook check
        if (empty($webhookSecret)) {
            $this->warn('⚠ Webhook secret is not configured');
            $this->line('  Webhooks are needed for production payment processing');
            $this->line('  Set STRIPE_WEBHOOK_SECRET in your .env file');
            $this->newLine();
        } else {
            if (str_starts_with($webhookSecret, 'whsec_')) {
                $this->info('✓ Webhook secret configured');
            } else {
                $this->warn('⚠ Webhook secret may be invalid (should start with whsec_)');
            }
            $this->newLine();
        }

        // Final summary
        $this->info('═══════════════════════════════════════');
        $this->info('  Stripe Configuration: READY ✓');
        $this->info('  Mode: ' . $mode);
        $this->info('═══════════════════════════════════════');
        $this->newLine();

        if ($mode === 'TEST') {
            $this->comment('You are in TEST mode. Use test card numbers:');
            $this->line('  Success: 4242 4242 4242 4242');
            $this->line('  Decline: 4000 0000 0000 0002');
            $this->line('  More: https://stripe.com/docs/testing');
        } else {
            $this->warn('⚠ You are in LIVE mode. Real charges will be made!');
        }

        $this->newLine();
        return self::SUCCESS;
    }

    /**
     * Mask sensitive key for display.
     */
    protected function maskKey(string $key): string
    {
        if (strlen($key) <= 12) {
            return str_repeat('*', strlen($key));
        }

        return substr($key, 0, 8) . str_repeat('*', strlen($key) - 12) . substr($key, -4);
    }
}
