<?php

namespace App\Services\Payment;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PaystackPaymentGateway implements PaymentGatewayInterface
{
    protected string $secretKey;
    protected string $publicKey;
    protected bool $testMode;
    protected float $feePercentage;
    protected float $feeFixed;

    private const BASE_URL = 'https://api.paystack.co';

    public function __construct()
    {
        $this->secretKey = config('services.paystack.secret_key') ?? '';
        $this->publicKey = config('services.paystack.public_key') ?? '';
        $this->testMode = config('services.paystack.test_mode', true);
        $this->feePercentage = config('services.paystack.fee_percentage', 1.5);
        $this->feeFixed = config('services.paystack.fee_fixed', 0.0);

        if (empty($this->secretKey) || empty($this->publicKey)) {
            throw new \Exception('Paystack API credentials are not configured. Please set PAYSTACK_SECRET_KEY and PAYSTACK_PUBLIC_KEY in your .env file.');
        }
    }

    /**
     * Create a checkout session (initialize transaction).
     */
    public function createCheckoutSession(float $amount, string $currency, array $metadata = []): array
    {
        try {
            $reference = 'txn_' . uniqid() . '_' . time();

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->secretKey,
                'Content-Type' => 'application/json',
            ])->post(self::BASE_URL . '/transaction/initialize', [
                'email' => $metadata['customer_email'] ?? '',
                'amount' => (int) ($amount * 100), // Convert to smallest currency unit (kobo/pesewas/cents)
                'currency' => strtoupper($currency),
                'reference' => $reference,
                'callback_url' => route('payment.success') . '?gateway=paystack&reference=' . $reference,
                'metadata' => [
                    'user_id' => $metadata['user_id'] ?? null,
                    'transaction_id' => $metadata['transaction_id'] ?? null,
                    'custom_fields' => [
                        [
                            'display_name' => 'Credit Top-up',
                            'variable_name' => 'credit_topup',
                            'value' => $amount . ' ' . strtoupper($currency),
                        ],
                    ],
                ],
            ]);

            $data = $response->json();

            if (!$response->successful() || !($data['status'] ?? false)) {
                throw new \Exception($data['message'] ?? 'Failed to initialize Paystack transaction');
            }

            return [
                'session_id' => $reference,
                'checkout_url' => $data['data']['authorization_url'],
                'payment_id' => $reference,
            ];
        } catch (\Exception $e) {
            if (str_contains($e->getMessage(), 'Paystack')) {
                throw $e;
            }
            throw new \Exception('Failed to create Paystack checkout: ' . $e->getMessage());
        }
    }

    /**
     * Handle webhook from Paystack.
     * Paystack uses HMAC SHA512 signature verification via x-paystack-signature header.
     */
    public function handleWebhook(string $payload, string $signature): array
    {
        // Verify HMAC SHA512 signature
        $computedSignature = hash_hmac('sha512', $payload, $this->secretKey);

        if ($signature !== $computedSignature) {
            throw new \Exception('Invalid webhook signature');
        }

        $event = json_decode($payload, true);
        $eventType = $event['event'] ?? '';
        $eventData = $event['data'] ?? [];

        // Map Paystack event types to our standard event types
        $eventTypeMap = [
            'charge.success' => 'payment.success',
            'charge.failed' => 'payment.failed',
            'transfer.success' => 'payment.success',
            'transfer.failed' => 'payment.failed',
            'refund.processed' => 'refund.completed',
            'refund.failed' => 'refund.failed',
        ];

        $mappedEventType = $eventTypeMap[$eventType] ?? 'unknown';

        return [
            'event_type' => $mappedEventType,
            'payment_id' => $eventData['reference'] ?? null,
            'amount' => isset($eventData['amount']) ? $eventData['amount'] / 100 : 0,
            'currency' => strtoupper($eventData['currency'] ?? 'NGN'),
            'status' => $eventData['status'] ?? 'unknown',
            'metadata' => $eventData['metadata'] ?? [],
        ];
    }

    /**
     * Verify a transaction by reference.
     */
    public function verifyTransaction(string $reference): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->secretKey,
            ])->get(self::BASE_URL . '/transaction/verify/' . $reference);

            $data = $response->json();

            if (!$response->successful() || !($data['status'] ?? false)) {
                throw new \Exception($data['message'] ?? 'Failed to verify Paystack transaction');
            }

            $txnData = $data['data'];

            return [
                'verified' => $txnData['status'] === 'success',
                'reference' => $txnData['reference'],
                'amount' => $txnData['amount'] / 100,
                'currency' => strtoupper($txnData['currency']),
                'status' => $txnData['status'],
                'gateway_response' => $txnData['gateway_response'] ?? '',
                'paid_at' => $txnData['paid_at'] ?? null,
                'channel' => $txnData['channel'] ?? null,
                'metadata' => $txnData['metadata'] ?? [],
            ];
        } catch (\Exception $e) {
            if (str_contains($e->getMessage(), 'Paystack')) {
                throw $e;
            }
            throw new \Exception('Failed to verify Paystack transaction: ' . $e->getMessage());
        }
    }

    /**
     * Refund a transaction.
     */
    public function refund(string $transactionId, ?float $amount = null): array
    {
        try {
            $refundData = [
                'transaction' => $transactionId,
            ];

            if ($amount !== null) {
                $refundData['amount'] = (int) ($amount * 100);
            }

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->secretKey,
                'Content-Type' => 'application/json',
            ])->post(self::BASE_URL . '/refund', $refundData);

            $data = $response->json();

            if (!$response->successful() || !($data['status'] ?? false)) {
                throw new \Exception($data['message'] ?? 'Failed to process Paystack refund');
            }

            $refundInfo = $data['data'];

            return [
                'refund_id' => (string) $refundInfo['id'],
                'amount' => ($refundInfo['amount'] ?? 0) / 100,
                'status' => $refundInfo['status'] === 'processed' ? 'completed' : ($refundInfo['status'] ?? 'pending'),
            ];
        } catch (\Exception $e) {
            if (str_contains($e->getMessage(), 'Paystack')) {
                throw $e;
            }
            throw new \Exception('Failed to process Paystack refund: ' . $e->getMessage());
        }
    }

    /**
     * Get transaction details from Paystack.
     */
    public function getTransactionDetails(string $transactionId): array
    {
        try {
            // Paystack uses reference for verification
            $result = $this->verifyTransaction($transactionId);

            return [
                'id' => $result['reference'],
                'amount' => $result['amount'],
                'currency' => $result['currency'],
                'status' => $result['status'],
                'created_at' => $result['paid_at'] ?? now()->toDateTimeString(),
                'metadata' => $result['metadata'],
            ];
        } catch (\Exception $e) {
            throw new \Exception('Failed to retrieve Paystack transaction: ' . $e->getMessage());
        }
    }

    /**
     * Get list of supported currencies for Paystack.
     *
     * Priority:
     * 1. Explicit PAYSTACK_SUPPORTED_CURRENCIES env override (admin-set)
     * 2. Live detection via GET /balance (cached 24h) — returns the merchant's actual settlement currencies
     * 3. Fallback: all standard Paystack currencies
     */
    public function getSupportedCurrencies(): array
    {
        // 1. Explicit override via PAYSTACK_SUPPORTED_CURRENCIES env takes highest priority
        $configured = config('services.paystack.supported_currencies');
        if (!empty($configured)) {
            return array_values(array_filter(array_map('trim', (array) $configured)));
        }

        // 2. Dynamically detect from merchant's Paystack balance (cached 24 hours)
        $cacheKey = 'paystack_supported_currencies_' . substr(md5($this->publicKey), 0, 8);

        return Cache::remember($cacheKey, 86400, function () {
            try {
                $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $this->secretKey,
                ])->timeout(5)->get(self::BASE_URL . '/balance');

                $data = $response->json();

                if ($response->successful() && !empty($data['data'])) {
                    $currencies = array_column($data['data'], 'currency');
                    if (!empty($currencies)) {
                        Log::info('Paystack: detected merchant currencies from /balance', ['currencies' => $currencies]);
                        return $currencies;
                    }
                }
            } catch (\Exception $e) {
                Log::warning('Paystack: could not detect currencies from /balance, using fallback', ['error' => $e->getMessage()]);
            }

            // 3. Fallback to all standard Paystack currencies
            return ['NGN', 'GHS', 'ZAR', 'KES'];
        });
    }

    /**
     * Get the gateway identifier.
     */
    public function getGatewayName(): string
    {
        return 'paystack';
    }

    /**
     * Check if gateway is in test mode.
     */
    public function isTestMode(): bool
    {
        return $this->testMode;
    }

    /**
     * Calculate Paystack fees for a transaction.
     */
    public function calculateFees(float $amount, string $currency): array
    {
        $percentageFee = ($amount * $this->feePercentage) / 100;
        $fixedFee = $this->feeFixed;
        $totalFee = $percentageFee + $fixedFee;
        $amountAfterFees = $amount - $totalFee;

        return [
            'percentage_fee' => round($percentageFee, 2),
            'fixed_fee' => $fixedFee,
            'total_fee' => round($totalFee, 2),
            'amount_after_fees' => round($amountAfterFees, 2),
        ];
    }
}
