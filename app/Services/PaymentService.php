<?php

namespace App\Services;

use App\Models\User;
use App\Models\Transaction;
use App\Services\Payment\PaymentGatewayInterface;
use App\Services\Payment\StripePaymentGateway;
use App\Services\Payment\RazorpayPaymentGateway;
use App\Services\Payment\PaystackPaymentGateway;
use App\Services\Payment\PayPalPaymentGateway;
use App\Services\Payment\FlutterwavePaymentGateway;
use App\Services\Payment\CryptomusPaymentGateway;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentService
{
    protected CreditService $creditService;
    protected ExchangeRateService $exchangeRateService;

    public function __construct(CreditService $creditService, ExchangeRateService $exchangeRateService)
    {
        $this->creditService = $creditService;
        $this->exchangeRateService = $exchangeRateService;
    }

    /**
     * Get payment gateway instance.
     *
     * Resolved through the container (a fresh instance each call, exactly as
     * `new` gave) so tests can stand a fake in for the gateway's network calls.
     */
    public function getGateway(string $gateway = 'stripe'): PaymentGatewayInterface
    {
        return match($gateway) {
            'stripe'      => app(StripePaymentGateway::class),
            'razorpay'    => app(RazorpayPaymentGateway::class),
            'paystack'    => app(PaystackPaymentGateway::class),
            'paypal'      => app(PayPalPaymentGateway::class),
            'flutterwave' => app(FlutterwavePaymentGateway::class),
            'cryptomus'   => app(CryptomusPaymentGateway::class),
            default       => throw new \Exception('Unsupported payment gateway: ' . $gateway),
        };
    }

    /**
     * Create a checkout session for top-up.
     *
     * @param User $user
     * @param float $amount
     * @param string $currency
     * @param string $gateway
     * @return array
     */
    public function createTopUpCheckout(User $user, float $amount, string $currency = 'USD', string $gateway = 'stripe'): array
    {
        // Validate minimum amount
        $minAmount = 5.00;
        if ($amount < $minAmount) {
            throw new \Exception("Minimum top-up amount is $minAmount $currency");
        }

        $gatewayInstance = $this->getGateway($gateway);

        // Verify currency is supported
        if (!in_array($currency, $gatewayInstance->getSupportedCurrencies())) {
            throw new \Exception("Currency $currency is not supported by $gateway");
        }

        // Multi-currency conversion: convert to base currency if needed
        $baseCurrency = $this->exchangeRateService->getBaseCurrency();
        $convertedAmount = $amount;
        $originalAmount = null;
        $originalCurrency = null;
        $exchangeRate = null;

        if (strtoupper($currency) !== strtoupper($baseCurrency)) {
            try {
                $conversion = $this->exchangeRateService->convert($amount, $currency, $baseCurrency);
                $convertedAmount = $conversion['converted_amount'];
                $originalAmount = $amount;
                $originalCurrency = strtoupper($currency);
                $exchangeRate = $conversion['rate'];
            } catch (\Exception $e) {
                Log::warning('Top-up refused: currency conversion failed', [
                    'from' => $currency,
                    'to' => $baseCurrency,
                    'error' => $e->getMessage(),
                ]);

                // Crediting the unconverted figure would turn 5,000 rupees
                // into 5,000 dollars of credit. Refuse the top-up instead.
                throw new \Exception("We could not convert {$currency} to {$baseCurrency} right now. Please try again shortly, or pay in {$baseCurrency}.");
            }
        }

        // Create pending transaction
        $transaction = Transaction::create([
            'user_id' => $user->id,
            'type' => 'credit',
            'amount' => $convertedAmount,
            'currency' => $baseCurrency,
            'original_amount' => $originalAmount,
            'original_currency' => $originalCurrency,
            'exchange_rate' => $exchangeRate,
            'base_currency' => $baseCurrency,
            'balance_before' => $user->credit_balance,
            'balance_after' => $user->credit_balance, // Will be updated when payment completes
            'description' => 'Credit top-up',
            'reference_type' => 'TopUp',
            'payment_gateway' => $gateway,
            'status' => 'pending',
            'metadata' => [
                'initiated_at' => now()->toDateTimeString(),
                'payment_currency' => strtoupper($currency),
                'payment_amount' => $amount,
            ],
        ]);

        // Create checkout session
        $checkout = $gatewayInstance->createCheckoutSession($amount, $currency, [
            'user_id' => $user->id,
            'transaction_id' => $transaction->id,
            'customer_email' => $user->email,
        ]);

        // Update transaction with payment ID
        $transaction->update([
            'payment_id' => $checkout['payment_id'] ?? $checkout['session_id'],
            'metadata' => array_merge($transaction->metadata ?? [], [
                'session_id' => $checkout['session_id'],
            ]),
        ]);

        Log::info('Checkout session created', [
            'user_id' => $user->id,
            'amount' => $amount,
            'currency' => $currency,
            'gateway' => $gateway,
            'transaction_id' => $transaction->id,
        ]);

        return [
            'transaction_id' => $transaction->id,
            'checkout_url' => $checkout['checkout_url'],
            'session_id' => $checkout['session_id'],
        ];
    }

    /**
     * Complete a transaction manually (e.g., from success callback).
     */
    public function completeTransaction(Transaction $transaction, string $paymentId): void
    {
        $this->creditTopUpOnce(
            $transaction,
            $paymentId,
            ['completion_method' => 'callback'],
            'Transaction completed via callback'
        );
    }

    /**
     * Complete a Razorpay top-up from the browser's return.
     *
     * The return URL names an order and a payment, both chosen by whoever
     * loads it. The payment is fetched from Razorpay and completes this
     * top-up only if it was made against this top-up's order, in full —
     * otherwise one small captured payment could complete any number of
     * pending top-ups.
     */
    public function completeRazorpayReturn(Transaction $transaction, string $paymentId): bool
    {
        $payment = $this->getGateway('razorpay')->getTransactionDetails($paymentId);

        if (!in_array($payment['status'] ?? null, ['captured', 'authorized'], true)) {
            return false;
        }

        if (($payment['order_id'] ?? null) !== $transaction->payment_id
            || !$this->paidInFull($transaction, (float) $payment['amount'], (string) $payment['currency'])) {
            Log::warning('Razorpay payment does not match the top-up it was returned against', [
                'transaction_id' => $transaction->id,
                'payment_id' => $paymentId,
                'payment_order_id' => $payment['order_id'] ?? null,
                'amount' => $payment['amount'] ?? null,
                'currency' => $payment['currency'] ?? null,
            ]);

            return false;
        }

        $this->completeTransaction($transaction, $paymentId);

        return true;
    }

    /**
     * Capture a PayPal order on the buyer's return and credit the top-up the
     * order was created for. The reference in the return URL gets no say —
     * PayPal's record of the order does.
     */
    public function completePayPalReturn(string $orderId): ?Transaction
    {
        $capture = $this->getGateway('paypal')->captureOrder($orderId);

        if (!in_array($capture['status'] ?? null, ['completed', 'approved'], true)) {
            return null;
        }

        $customId = (string) ($capture['custom_id'] ?? '');

        $transaction = Transaction::where('payment_gateway', 'paypal')
            ->when(
                $customId !== '',
                fn ($query) => $query->whereKey($customId),
                fn ($query) => $query->where('payment_id', (string) ($capture['reference'] ?? '')),
            )
            ->first();

        if (!$transaction || !$this->paidInFull($transaction, (float) $capture['amount'], (string) $capture['currency'])) {
            Log::warning('PayPal capture does not match a top-up', [
                'paypal_order' => $orderId,
                'custom_id' => $customId,
                'reference' => $capture['reference'] ?? null,
                'amount' => $capture['amount'] ?? null,
                'currency' => $capture['currency'] ?? null,
            ]);

            return null;
        }

        // Store the capture id as the definitive payment_id — refunds need it.
        $this->completeTransaction($transaction, ($capture['capture_id'] ?? '') ?: $orderId);

        return $transaction->refresh();
    }

    /**
     * Whether a gateway-reported payment covers the top-up: the currency the
     * buyer was asked to pay in, and the amount to the cent (gateways round to
     * their smallest unit).
     */
    private function paidInFull(Transaction $transaction, float $amount, string $currency): bool
    {
        $expectedAmount = (float) ($transaction->metadata['payment_amount'] ?? $transaction->amount);
        $expectedCurrency = strtoupper((string) ($transaction->metadata['payment_currency'] ?? $transaction->currency));

        return strtoupper($currency) === $expectedCurrency
            && abs($amount - $expectedAmount) <= 0.01 + PHP_FLOAT_EPSILON;
    }

    /**
     * Credit a pending top-up exactly once.
     *
     * The row is re-read under a lock: the browser's return and the gateway's
     * webhook routinely arrive together, and each used to see 'pending' on its
     * own copy and credit the top-up twice. A refunded top-up is never
     * credited again.
     */
    private function creditTopUpOnce(Transaction $transaction, ?string $paymentId, array $completion, string $logMessage): void
    {
        DB::transaction(function () use ($transaction, $paymentId, $completion, $logMessage) {
            $locked = Transaction::whereKey($transaction->getKey())->lockForUpdate()->firstOrFail();

            if (in_array($locked->status, ['completed', 'refunded'], true)) {
                Log::info('Top-up already settled', [
                    'transaction_id' => $locked->id,
                    'status' => $locked->status,
                ]);

                return;
            }

            $user = $locked->user;
            $paymentId ??= $locked->payment_id;
            $completedAt = now()->toDateTimeString();

            $this->creditService->addCredit(
                $user,
                $locked->amount,
                'Credit top-up completed',
                [
                    'currency' => $locked->currency,
                    'reference_type' => 'TopUp',
                    'reference_id' => $locked->id,
                    'payment_gateway' => $locked->payment_gateway,
                    'payment_id' => $paymentId,
                    'metadata' => array_merge([
                        'original_transaction_id' => $locked->id,
                        'completed_at' => $completedAt,
                    ], $completion),
                ]
            );

            // Update original pending transaction
            $locked->update([
                'status' => 'completed',
                'payment_id' => $paymentId,
                'balance_after' => $user->fresh()->credit_balance,
                'metadata' => array_merge($locked->metadata ?? [], ['completed_at' => $completedAt], $completion),
            ]);

            Log::info($logMessage, [
                'transaction_id' => $locked->id,
                'user_id' => $user->id,
                'amount' => $locked->amount,
                'payment_id' => $paymentId,
            ]);
        });
    }

    /**
     * Handle payment webhook.
     *
     * @param string $gateway
     * @param string $payload
     * @param string $signature
     * @return void
     */
    public function handleWebhook(string $gateway, string $payload, string $signature): void
    {
        $gatewayInstance = $this->getGateway($gateway);

        try {
            $event = $gatewayInstance->handleWebhook($payload, $signature);

            Log::info('Webhook received', [
                'gateway' => $gateway,
                'event_type' => $event['event_type'],
                'payment_id' => $event['payment_id'],
            ]);

            // Find transaction by payment ID
            // PayPal stores our transaction_id in custom_id; try that first for PayPal
            $transaction = null;

            if ($gateway === 'paypal' && !empty($event['custom_id'])) {
                $transaction = Transaction::where('id', $event['custom_id'])
                    ->where('payment_gateway', 'paypal')
                    ->first();
            }

            if (!$transaction) {
                $transaction = Transaction::where('payment_id', $event['payment_id'])
                    ->where('payment_gateway', $gateway)
                    ->first();
            }

            // Razorpay names the payment (pay_…), but the top-up was stored
            // under the order created for it (order_…).
            if (!$transaction && $gateway === 'razorpay' && !empty($event['order_id'])) {
                $transaction = Transaction::where('payment_id', $event['order_id'])
                    ->where('payment_gateway', 'razorpay')
                    ->first();
            }

            // For Stripe: payment_id may be stored as cs_... (session) but webhook sends pi_... (payment intent).
            // Fall back to matching by session_id stored in metadata.
            if (!$transaction && $gateway === 'stripe' && !empty($event['session_id'])) {
                $transaction = Transaction::where('payment_gateway', 'stripe')
                    ->whereJsonContains('metadata->session_id', $event['session_id'])
                    ->first();
            }

            if (!$transaction) {
                Log::warning('Transaction not found for webhook', [
                    'payment_id' => $event['payment_id'],
                    'gateway'    => $gateway,
                    'custom_id'  => $event['custom_id'] ?? null,
                ]);
                return;
            }

            // Handle different event types
            match($event['event_type']) {
                'payment.success' => $this->handlePaymentSuccess($transaction, $event),
                'payment.failed' => $this->handlePaymentFailed($transaction, $event),
                'refund.completed' => $this->handleRefundCompleted($transaction, $event),
                default => Log::info('Unhandled webhook event', ['event_type' => $event['event_type']]),
            };

        } catch (\Exception $e) {
            Log::error('Webhook handling failed', [
                'gateway' => $gateway,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Handle successful payment.
     * Uses DB transaction + idempotency to prevent double-crediting.
     */
    protected function handlePaymentSuccess(Transaction $transaction, array $event): void
    {
        // Idempotency: check if this payment event was already processed
        $eventId = $event['payment_id'] ?? $transaction->payment_id ?? (string) $transaction->id;
        if (!\App\Models\ProcessedWebhook::markProcessed($transaction->payment_gateway, $eventId, 'payment.success', ['transaction_id' => $transaction->id])) {
            Log::info('Duplicate payment webhook ignored', ['transaction_id' => $transaction->id]);
            return;
        }

        $this->creditTopUpOnce(
            $transaction,
            null,
            ['webhook_event' => $event['event_type']],
            'Payment success processed'
        );
    }

    /**
     * Handle failed payment.
     */
    protected function handlePaymentFailed(Transaction $transaction, array $event): void
    {
        $transaction->update([
            'status' => 'failed',
            'metadata' => array_merge($transaction->metadata ?? [], [
                'failed_at' => now()->toDateTimeString(),
                'error_message' => $event['error_message'] ?? 'Payment failed',
                'webhook_event' => $event['event_type'],
            ]),
        ]);

        // Send notification (implement later)
        // Notification::send($transaction->user, new PaymentFailedNotification($transaction));

        Log::warning('Payment failed', [
            'transaction_id' => $transaction->id,
            'user_id' => $transaction->user_id,
            'error' => $event['error_message'] ?? 'Unknown error',
        ]);
    }

    /**
     * Handle completed refund.
     */
    protected function handleRefundCompleted(Transaction $transaction, array $event): void
    {
        $user = $transaction->user;

        // Deduct refunded amount from user balance
        try {
            $this->creditService->deductCredit(
                $user,
                $event['amount'],
                'Refund processed',
                [
                    'currency' => $transaction->currency,
                    'reference_type' => 'Refund',
                    'reference_id' => $transaction->id,
                    'payment_gateway' => $transaction->payment_gateway,
                    'payment_id' => $transaction->payment_id,
                    'metadata' => [
                        'original_transaction_id' => $transaction->id,
                        'webhook_event' => $event['event_type'],
                        'refunded_at' => now()->toDateTimeString(),
                    ],
                ]
            );

            $transaction->update([
                'status' => 'refunded',
                'metadata' => array_merge($transaction->metadata ?? [], [
                    'refunded_at' => now()->toDateTimeString(),
                    'webhook_event' => $event['event_type'],
                ]),
            ]);

            Log::info('Refund processed', [
                'transaction_id' => $transaction->id,
                'user_id' => $user->id,
                'amount' => $event['amount'],
            ]);

        } catch (\Exception $e) {
            Log::error('Refund processing failed', [
                'transaction_id' => $transaction->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Process a refund.
     *
     * @param Transaction $transaction
     * @param float|null $amount
     * @return array
     */
    public function processRefund(Transaction $transaction, ?float $amount = null): array
    {
        if ($transaction->status !== 'completed') {
            throw new \Exception('Can only refund completed transactions');
        }

        if ($transaction->type !== 'credit') {
            throw new \Exception('Can only refund credit transactions');
        }

        $gatewayInstance = $this->getGateway($transaction->payment_gateway);

        $refund = $gatewayInstance->refund($transaction->payment_id, $amount);

        Log::info('Refund initiated', [
            'transaction_id' => $transaction->id,
            'refund_id' => $refund['refund_id'],
            'amount' => $refund['amount'],
        ]);

        return $refund;
    }

    /**
     * Get supported currencies.
     */
    public function getSupportedCurrencies(string $gateway = 'stripe'): array
    {
        $gatewayInstance = $this->getGateway($gateway);
        return $gatewayInstance->getSupportedCurrencies();
    }

    /**
     * Get available payment gateways.
     */
    public function getAvailableGateways(): array
    {
        $gateways = [];
        
        // Check if Stripe is configured
        if ($this->isGatewayConfigured('stripe')) {
            try {
                $gateways[] = [
                    'id' => 'stripe',
                    'name' => 'Stripe',
                    'icon' => '/images/stripe-icon.png',
                    'supported_currencies' => $this->getSupportedCurrencies('stripe'),
                ];
            } catch (\Exception $e) {
                Log::error('Failed to get Stripe currencies', ['error' => $e->getMessage()]);
            }
        }
        
        // Check if Razorpay is configured
        if ($this->isGatewayConfigured('razorpay')) {
            try {
                $gateways[] = [
                    'id' => 'razorpay',
                    'name' => 'Razorpay',
                    'icon' => '/images/razorpay-icon.png',
                    'supported_currencies' => $this->getSupportedCurrencies('razorpay'),
                ];
            } catch (\Exception $e) {
                Log::error('Failed to get Razorpay currencies', ['error' => $e->getMessage()]);
            }
        }

        // Check if Paystack is configured
        if ($this->isGatewayConfigured('paystack')) {
            try {
                $gateways[] = [
                    'id' => 'paystack',
                    'name' => 'Paystack',
                    'icon' => '/images/paystack-icon.png',
                    'supported_currencies' => $this->getSupportedCurrencies('paystack'),
                ];
            } catch (\Exception $e) {
                Log::error('Failed to get Paystack currencies', ['error' => $e->getMessage()]);
            }
        }

        // Check if PayPal is configured
        if ($this->isGatewayConfigured('paypal')) {
            try {
                $gateways[] = [
                    'id' => 'paypal',
                    'name' => 'PayPal',
                    'icon' => '/images/paypal-icon.png',
                    'supported_currencies' => $this->getSupportedCurrencies('paypal'),
                ];
            } catch (\Exception $e) {
                Log::error('Failed to get PayPal currencies', ['error' => $e->getMessage()]);
            }
        }

        // Check if Flutterwave is configured
        if ($this->isGatewayConfigured('flutterwave')) {
            try {
                $gateways[] = [
                    'id' => 'flutterwave',
                    'name' => 'Flutterwave',
                    'icon' => '/images/flutterwave-icon.png',
                    'supported_currencies' => $this->getSupportedCurrencies('flutterwave'),
                ];
            } catch (\Exception $e) {
                Log::error('Failed to get Flutterwave currencies', ['error' => $e->getMessage()]);
            }
        }

        // Check if Cryptomus is configured
        if ($this->isGatewayConfigured('cryptomus')) {
            try {
                $gateways[] = [
                    'id' => 'cryptomus',
                    'name' => 'Cryptomus',
                    'icon' => '/images/cryptomus-icon.png',
                    'supported_currencies' => $this->getSupportedCurrencies('cryptomus'),
                ];
            } catch (\Exception $e) {
                Log::error('Failed to get Cryptomus currencies', ['error' => $e->getMessage()]);
            }
        }

        return $gateways;
    }
    
    /**
     * Check if a payment gateway is configured.
     */
    public function isGatewayConfigured(string $gateway): bool
    {
        return match($gateway) {
            'stripe'      => !empty(config('services.stripe.secret')) && !empty(config('services.stripe.public')),
            'razorpay'    => !empty(config('services.razorpay.api_key')) && !empty(config('services.razorpay.secret_key')),
            'paystack'    => !empty(config('services.paystack.secret_key')) && !empty(config('services.paystack.public_key')),
            'paypal'      => !empty(config('services.paypal.client_id')) && !empty(config('services.paypal.client_secret')),
            'flutterwave' => !empty(config('services.flutterwave.secret_key')) && !empty(config('services.flutterwave.public_key')),
            'cryptomus'   => !empty(config('services.cryptomus.merchant_uuid')) && !empty(config('services.cryptomus.payment_key')),
            default       => false,
        };
    }
}
