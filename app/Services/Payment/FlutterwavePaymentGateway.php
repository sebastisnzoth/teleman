<?php

namespace App\Services\Payment;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FlutterwavePaymentGateway implements PaymentGatewayInterface
{
    protected string $secretKey;
    protected string $publicKey;
    protected string $encryptionKey;
    protected string $secretHash;
    protected bool $testMode;
    protected float $feePercentage;
    protected float $feeFixed;

    private const BASE_URL = 'https://api.flutterwave.com/v3';

    public function __construct()
    {
        $this->secretKey = config('services.flutterwave.secret_key') ?? '';
        $this->publicKey = config('services.flutterwave.public_key') ?? '';
        $this->encryptionKey = config('services.flutterwave.encryption_key') ?? '';
        $this->secretHash = config('services.flutterwave.secret_hash') ?? '';
        $this->testMode = config('services.flutterwave.test_mode', true);
        $this->feePercentage = config('services.flutterwave.fee_percentage', 1.4);
        $this->feeFixed = config('services.flutterwave.fee_fixed', 0.0);

        if (empty($this->secretKey) || empty($this->publicKey)) {
            throw new \Exception('Flutterwave API credentials are not configured. Please set FLUTTERWAVE_SECRET_KEY and FLUTTERWAVE_PUBLIC_KEY in your .env file.');
        }
    }

    /**
     * Create a checkout session (Flutterwave Standard).
     * POST /v3/payments → returns hosted payment link.
     */
    public function createCheckoutSession(float $amount, string $currency, array $metadata = []): array
    {
        try {
            $txRef = 'flw_' . uniqid() . '_' . time();

            $payload = [
                'tx_ref' => $txRef,
                'amount' => number_format($amount, 2, '.', ''),
                'currency' => strtoupper($currency),
                'redirect_url' => route('payment.success') . '?gateway=flutterwave&reference=' . $txRef,
                'customer' => [
                    'email' => $metadata['customer_email'] ?? '',
                ],
                'customizations' => [
                    'title' => 'Credit Top-up',
                    'description' => 'Add credits to your account',
                ],
                'meta' => [
                    'user_id' => $metadata['user_id'] ?? null,
                    'transaction_id' => $metadata['transaction_id'] ?? null,
                ],
            ];

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->secretKey,
                'Content-Type' => 'application/json',
            ])->post(self::BASE_URL . '/payments', $payload);

            $data = $response->json();

            if (!$response->successful() || ($data['status'] ?? '') !== 'success') {
                throw new \Exception($data['message'] ?? 'Failed to initialize Flutterwave payment');
            }

            return [
                'session_id' => $txRef,
                'checkout_url' => $data['data']['link'],
                'payment_id' => $txRef,
            ];
        } catch (\Exception $e) {
            if (str_contains($e->getMessage(), 'Flutterwave')) {
                throw $e;
            }
            throw new \Exception('Failed to create Flutterwave checkout: ' . $e->getMessage());
        }
    }

    /**
     * Handle webhook from Flutterwave.
     * Flutterwave sends a verif-hash header containing the secret hash for verification.
     * Event types: charge.completed, transfer.completed, etc.
     */
    public function handleWebhook(string $payload, string $signature): array
    {
        // Verify the webhook using the secret hash (verif-hash header). With no
        // hash configured nothing can be verified, so nothing is accepted.
        if (empty($this->secretHash) || !hash_equals($this->secretHash, $signature)) {
            throw new \Exception('Invalid webhook signature');
        }

        $event = json_decode($payload, true);
        $eventType = $event['event'] ?? '';
        $eventData = $event['data'] ?? [];

        // Map Flutterwave event types to our standard event types
        $eventTypeMap = [
            'charge.completed' => 'payment.success',
            'charge.failed' => 'payment.failed',
            'transfer.completed' => 'payment.success',
            'transfer.failed' => 'payment.failed',
            'refund.completed' => 'refund.completed',
        ];

        $mappedEventType = $eventTypeMap[$eventType] ?? 'unknown';

        // For successful charges, verify the transaction status
        if ($mappedEventType === 'payment.success' && ($eventData['status'] ?? '') !== 'successful') {
            $mappedEventType = 'payment.failed';
        }

        return [
            'event_type' => $mappedEventType,
            'payment_id' => $eventData['tx_ref'] ?? null,
            'amount' => (float) ($eventData['amount'] ?? 0),
            'currency' => strtoupper($eventData['currency'] ?? 'USD'),
            'status' => $eventData['status'] ?? 'unknown',
            'metadata' => $eventData['meta'] ?? [],
        ];
    }

    /**
     * Verify a transaction by its ID.
     * GET /v3/transactions/{id}/verify
     */
    public function verifyTransaction(string $transactionId): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->secretKey,
            ])->get(self::BASE_URL . '/transactions/' . $transactionId . '/verify');

            $data = $response->json();

            if (!$response->successful() || ($data['status'] ?? '') !== 'success') {
                throw new \Exception($data['message'] ?? 'Failed to verify Flutterwave transaction');
            }

            $txnData = $data['data'];

            return [
                'verified' => $txnData['status'] === 'successful',
                'id' => (string) $txnData['id'],
                'tx_ref' => $txnData['tx_ref'],
                'flw_ref' => $txnData['flw_ref'] ?? null,
                'amount' => (float) $txnData['amount'],
                'currency' => strtoupper($txnData['currency']),
                'status' => $txnData['status'],
                'payment_type' => $txnData['payment_type'] ?? null,
                'created_at' => $txnData['created_at'] ?? null,
                'metadata' => $txnData['meta'] ?? [],
            ];
        } catch (\Exception $e) {
            if (str_contains($e->getMessage(), 'Flutterwave')) {
                throw $e;
            }
            throw new \Exception('Failed to verify Flutterwave transaction: ' . $e->getMessage());
        }
    }

    /**
     * Verify a transaction by its tx_ref (our reference).
     * GET /v3/transactions/verify_by_reference?tx_ref={ref}
     */
    public function verifyTransactionByReference(string $txRef): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->secretKey,
            ])->get(self::BASE_URL . '/transactions/verify_by_reference', [
                'tx_ref' => $txRef,
            ]);

            $data = $response->json();

            if (!$response->successful() || ($data['status'] ?? '') !== 'success') {
                throw new \Exception($data['message'] ?? 'Failed to verify Flutterwave transaction');
            }

            $txnData = $data['data'];

            return [
                'verified' => $txnData['status'] === 'successful',
                'id' => (string) $txnData['id'],
                'tx_ref' => $txnData['tx_ref'],
                'flw_ref' => $txnData['flw_ref'] ?? null,
                'amount' => (float) $txnData['amount'],
                'currency' => strtoupper($txnData['currency']),
                'status' => $txnData['status'],
                'payment_type' => $txnData['payment_type'] ?? null,
                'created_at' => $txnData['created_at'] ?? null,
                'metadata' => $txnData['meta'] ?? [],
            ];
        } catch (\Exception $e) {
            if (str_contains($e->getMessage(), 'Flutterwave')) {
                throw $e;
            }
            throw new \Exception('Failed to verify Flutterwave transaction by reference: ' . $e->getMessage());
        }
    }

    /**
     * Refund a transaction.
     * POST /v3/transactions/{id}/refund
     */
    public function refund(string $transactionId, ?float $amount = null): array
    {
        try {
            $refundData = [];

            if ($amount !== null) {
                $refundData['amount'] = $amount;
            }

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->secretKey,
                'Content-Type' => 'application/json',
            ])->post(self::BASE_URL . '/transactions/' . $transactionId . '/refund', $refundData);

            $data = $response->json();

            if (!$response->successful() || ($data['status'] ?? '') !== 'success') {
                throw new \Exception($data['message'] ?? 'Failed to process Flutterwave refund');
            }

            $refundInfo = $data['data'];

            return [
                'refund_id' => (string) ($refundInfo['id'] ?? $transactionId),
                'amount' => (float) ($refundInfo['amount_refunded'] ?? $amount ?? 0),
                'status' => ($refundInfo['status'] ?? '') === 'completed' ? 'completed' : 'pending',
            ];
        } catch (\Exception $e) {
            if (str_contains($e->getMessage(), 'Flutterwave')) {
                throw $e;
            }
            throw new \Exception('Failed to process Flutterwave refund: ' . $e->getMessage());
        }
    }

    /**
     * Get transaction details from Flutterwave.
     */
    public function getTransactionDetails(string $transactionId): array
    {
        try {
            $result = $this->verifyTransaction($transactionId);

            return [
                'id' => $result['id'],
                'amount' => $result['amount'],
                'currency' => $result['currency'],
                'status' => $result['status'],
                'created_at' => $result['created_at'] ?? now()->toDateTimeString(),
                'metadata' => $result['metadata'],
            ];
        } catch (\Exception $e) {
            throw new \Exception('Failed to retrieve Flutterwave transaction: ' . $e->getMessage());
        }
    }

    /**
     * Get list of supported currencies for Flutterwave.
     * Flutterwave supports 150+ currencies across Africa, Europe, Asia, and the Americas.
     */
    public function getSupportedCurrencies(): array
    {
        return [
            'USD', 'EUR', 'GBP', 'NGN', 'GHS', 'KES', 'ZAR', 'UGX', 'TZS',
            'XOF', 'XAF', 'RWF', 'EGP', 'CAD', 'AUD', 'INR', 'JPY', 'CNY',
            'BRL', 'MXN', 'AED', 'MUR', 'ETB', 'SLL', 'MWK', 'ZMW',
        ];
    }

    /**
     * Get the gateway identifier.
     */
    public function getGatewayName(): string
    {
        return 'flutterwave';
    }

    /**
     * Check if gateway is in test mode.
     */
    public function isTestMode(): bool
    {
        return $this->testMode;
    }

    /**
     * Calculate Flutterwave fees for a transaction.
     * Default: 1.4% for local, 3.8% for international. We use the configured rate.
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
