<?php

namespace App\Services\Payment;

use Cryptomus\Api\Client;
use Illuminate\Support\Facades\Log;

class CryptomusPaymentGateway implements PaymentGatewayInterface
{
    protected string $merchantUuid;
    protected string $paymentKey;
    protected bool $testMode;

    public function __construct()
    {
        $this->merchantUuid = config('services.cryptomus.merchant_uuid') ?? '';
        $this->paymentKey = config('services.cryptomus.payment_key') ?? '';
        $this->testMode = config('services.cryptomus.test_mode', true);

        if (empty($this->merchantUuid) || empty($this->paymentKey)) {
            throw new \Exception('Cryptomus API credentials are not configured. Please set CRYPTOMUS_MERCHANT_UUID and CRYPTOMUS_PAYMENT_KEY in your .env file.');
        }
    }

    /**
     * Create a checkout session (invoice) via Cryptomus API.
     */
    public function createCheckoutSession(float $amount, string $currency, array $metadata = []): array
    {
        try {
            $orderId = 'txn_' . uniqid() . '_' . time();

            $payment = Client::payment($this->paymentKey, $this->merchantUuid);

            $result = $payment->create([
                'amount' => (string) number_format($amount, 2, '.', ''),
                'currency' => strtoupper($currency),
                'order_id' => $orderId,
                'url_return' => route('payment.success') . '?gateway=cryptomus&reference=' . $orderId,
                'url_success' => route('payment.success') . '?gateway=cryptomus&reference=' . $orderId,
                'url_callback' => url('/webhooks/payment/cryptomus'),
                'is_payment_multiple' => false,
                'lifetime' => '7200', // 2 hours
            ]);

            if (empty($result['url'])) {
                throw new \Exception('Cryptomus did not return a payment URL');
            }

            return [
                'session_id' => $orderId,
                'checkout_url' => $result['url'],
                'payment_id' => $orderId,
            ];
        } catch (\Exception $e) {
            if (str_contains($e->getMessage(), 'Cryptomus')) {
                throw $e;
            }
            throw new \Exception('Failed to create Cryptomus checkout: ' . $e->getMessage());
        }
    }

    /**
     * Handle webhook from Cryptomus.
     * Verifies signature by removing 'sign' from payload, then md5(base64_encode(json_encode(data)) . paymentKey).
     */
    public function handleWebhook(string $payload, string $signature): array
    {
        $data = json_decode($payload, true);

        if (!$data) {
            throw new \Exception('Invalid webhook payload');
        }

        // Extract and verify signature
        $receivedSign = $data['sign'] ?? '';
        unset($data['sign']);

        // Sort data by keys for consistent signature
        ksort($data);

        $computedSign = md5(base64_encode(json_encode($data, JSON_UNESCAPED_UNICODE)) . $this->paymentKey);

        if ($receivedSign !== $computedSign) {
            throw new \Exception('Invalid webhook signature');
        }

        $status = $data['status'] ?? '';
        $orderId = $data['order_id'] ?? '';

        // Map Cryptomus statuses to our standard event types
        $eventType = match ($status) {
            'paid', 'paid_over' => 'payment.success',
            'cancel', 'fail', 'system_fail', 'wrong_amount' => 'payment.failed',
            default => 'unknown',
        };

        // Convert amount from string
        $amount = isset($data['amount']) ? (float) $data['amount'] : 0;

        return [
            'event_type' => $eventType,
            'payment_id' => $orderId,
            'amount' => $amount,
            'currency' => strtoupper($data['currency'] ?? 'USD'),
            'status' => $status,
            'metadata' => [
                'uuid' => $data['uuid'] ?? null,
                'network' => $data['network'] ?? null,
                'txid' => $data['txid'] ?? null,
                'payer_currency' => $data['payer_currency'] ?? null,
                'payer_amount' => $data['payer_amount'] ?? null,
            ],
        ];
    }

    /**
     * Verify a payment by order_id using the Cryptomus API.
     */
    public function verifyPayment(string $orderId): array
    {
        try {
            $payment = Client::payment($this->paymentKey, $this->merchantUuid);
            $result = $payment->info(['order_id' => $orderId]);

            $status = $result['payment_status'] ?? $result['status'] ?? 'unknown';
            $verified = in_array($status, ['paid', 'paid_over']);

            return [
                'verified' => $verified,
                'order_id' => $orderId,
                'uuid' => $result['uuid'] ?? null,
                'amount' => isset($result['amount']) ? (float) $result['amount'] : 0,
                'currency' => strtoupper($result['currency'] ?? 'USD'),
                'status' => $status,
                'network' => $result['network'] ?? null,
            ];
        } catch (\Exception $e) {
            throw new \Exception('Failed to verify Cryptomus payment: ' . $e->getMessage());
        }
    }

    /**
     * Refund not supported for cryptocurrency payments.
     */
    public function refund(string $transactionId, ?float $amount = null): array
    {
        throw new \Exception('Refunds are not supported for cryptocurrency payments via Cryptomus. Please process refunds manually through your Cryptomus dashboard.');
    }

    /**
     * Get transaction details from Cryptomus.
     */
    public function getTransactionDetails(string $transactionId): array
    {
        try {
            $result = $this->verifyPayment($transactionId);

            return [
                'id' => $result['order_id'],
                'amount' => $result['amount'],
                'currency' => $result['currency'],
                'status' => $result['status'],
                'created_at' => now()->toDateTimeString(),
                'metadata' => [
                    'uuid' => $result['uuid'],
                    'network' => $result['network'],
                ],
            ];
        } catch (\Exception $e) {
            throw new \Exception('Failed to retrieve Cryptomus transaction: ' . $e->getMessage());
        }
    }

    /**
     * Get supported currencies.
     * Cryptomus accepts fiat currencies which get converted to crypto at checkout.
     */
    public function getSupportedCurrencies(): array
    {
        return ['USD', 'EUR', 'GBP', 'CAD', 'AUD', 'JPY', 'CHF', 'CNY', 'INR', 'BRL', 'RUB', 'KRW', 'TRY', 'AED', 'SAR', 'NGN'];
    }

    /**
     * Get the gateway identifier.
     */
    public function getGatewayName(): string
    {
        return 'cryptomus';
    }

    /**
     * Check if gateway is in test mode.
     */
    public function isTestMode(): bool
    {
        return $this->testMode;
    }

    /**
     * Calculate fees — Cryptomus fees are handled on their side.
     */
    public function calculateFees(float $amount, string $currency): array
    {
        return [
            'percentage_fee' => 0,
            'fixed_fee' => 0,
            'total_fee' => 0,
            'amount_after_fees' => $amount,
        ];
    }
}
