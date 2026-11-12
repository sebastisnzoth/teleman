<?php

namespace App\Services\Payment;

use Razorpay\Api\Api;
use Razorpay\Api\Errors\SignatureVerificationError;

class RazorpayPaymentGateway implements PaymentGatewayInterface
{
    protected string $apiKey;
    protected string $secretKey;
    protected string $webhookSecret;
    protected bool $testMode;
    protected float $feePercentage;
    protected float $feeFixed;
    protected Api $api;

    public function __construct()
    {
        $this->apiKey = config('services.razorpay.api_key') ?? '';
        $this->secretKey = config('services.razorpay.secret_key') ?? '';
        $this->webhookSecret = config('services.razorpay.webhook_secret') ?? '';
        $this->testMode = config('services.razorpay.test_mode', true);
        $this->feePercentage = config('services.razorpay.fee_percentage', 2.0);
        $this->feeFixed = config('services.razorpay.fee_fixed', 0.0);

        if (empty($this->apiKey) || empty($this->secretKey)) {
            throw new \Exception('Razorpay API credentials are not configured. Please set RAZORPAY_API_KEY and RAZORPAY_SECRET_KEY in your .env file.');
        }

        $this->api = new Api($this->apiKey, $this->secretKey);
    }

    /**
     * Create a checkout session for payment.
     */
    public function createCheckoutSession(float $amount, string $currency, array $metadata = []): array
    {
        try {
            // Create Razorpay Order
            $order = $this->api->order->create([
                'amount' => (int) ($amount * 100), // Convert to paise (smallest currency unit)
                'currency' => strtoupper($currency),
                'receipt' => 'txn_' . time(),
                'notes' => $metadata,
            ]);

            // Return order details for frontend Razorpay Checkout integration
            // Frontend will handle the Razorpay checkout modal
            $checkoutUrl = config('app.url') . '/credit/razorpay/checkout?order_id=' . $order->id;

            return [
                'session_id' => $order->id,
                'checkout_url' => $checkoutUrl,
                'payment_id' => $order->id,
                'order_details' => [
                    'id' => $order->id,
                    'amount' => $order->amount,
                    'currency' => $order->currency,
                ],
            ];
        } catch (\Exception $e) {
            throw new \Exception('Failed to create Razorpay order: ' . $e->getMessage());
        }
    }

    /**
     * Handle webhook from Razorpay.
     */
    public function handleWebhook(string $payload, string $signature): array
    {
        try {
            // Without a secret there is nothing to verify against, and an
            // unverified "payment.captured" could credit anyone.
            if (empty($this->webhookSecret)) {
                throw new \Exception('RAZORPAY_WEBHOOK_SECRET is not configured, so the webhook cannot be verified');
            }

            $this->api->utility->verifyWebhookSignature($payload, $signature, $this->webhookSecret);

            $event = json_decode($payload, true);
            $eventType = $event['event'];
            $eventData = $event['payload']['payment']['entity'] ?? $event['payload']['order']['entity'] ?? [];

            // Map Razorpay event types to our event types
            $eventTypeMap = [
                'payment.captured' => 'payment.success',
                'payment.authorized' => 'payment.success',
                'payment.failed' => 'payment.failed',
                'order.paid' => 'payment.success',
                'refund.created' => 'refund.initiated',
                'refund.processed' => 'refund.completed',
            ];

            $mappedEventType = $eventTypeMap[$eventType] ?? 'unknown';

            // Extract payment details
            return [
                'event_type' => $mappedEventType,
                'payment_id' => $eventData['id'] ?? null,
                'order_id' => $eventData['order_id'] ?? null,
                'amount' => isset($eventData['amount']) ? $eventData['amount'] / 100 : 0,
                'currency' => strtoupper($eventData['currency'] ?? 'INR'),
                'status' => $eventData['status'] ?? 'unknown',
                'metadata' => $eventData['notes'] ?? [],
            ];
        } catch (SignatureVerificationError $e) {
            throw new \Exception('Invalid webhook signature');
        } catch (\Exception $e) {
            throw new \Exception('Failed to process webhook: ' . $e->getMessage());
        }
    }

    /**
     * Refund a transaction.
     */
    public function refund(string $transactionId, ?float $amount = null): array
    {
        try {
            $refundData = ['payment_id' => $transactionId];

            if ($amount !== null) {
                $refundData['amount'] = (int) ($amount * 100); // Convert to paise
            }

            $refund = $this->api->refund->create($refundData);

            return [
                'refund_id' => $refund->id,
                'amount' => $refund->amount / 100,
                'status' => $refund->status === 'processed' ? 'completed' : $refund->status,
            ];
        } catch (\Exception $e) {
            throw new \Exception('Failed to process refund: ' . $e->getMessage());
        }
    }

    /**
     * Verify payment signature and get payment details.
     */
    public function verifyPayment(string $orderId, string $paymentId, string $signature): array
    {
        try {
            // Verify signature
            $attributes = [
                'razorpay_order_id' => $orderId,
                'razorpay_payment_id' => $paymentId,
                'razorpay_signature' => $signature,
            ];

            $this->api->utility->verifyPaymentSignature($attributes);

            // Fetch payment details
            $payment = $this->api->payment->fetch($paymentId);

            return [
                'verified' => true,
                'payment_id' => $payment->id,
                'order_id' => $payment->order_id,
                'amount' => $payment->amount / 100,
                'currency' => strtoupper($payment->currency),
                'status' => $payment->status,
                'method' => $payment->method,
                'captured' => $payment->captured,
            ];
        } catch (SignatureVerificationError $e) {
            throw new \Exception('Payment signature verification failed');
        } catch (\Exception $e) {
            throw new \Exception('Failed to verify payment: ' . $e->getMessage());
        }
    }

    /**
     * Get transaction details from Razorpay.
     */
    public function getTransactionDetails(string $transactionId): array
    {
        try {
            $payment = $this->api->payment->fetch($transactionId);

            return [
                'id' => $payment->id,
                'order_id' => $payment->order_id,
                'amount' => $payment->amount / 100,
                'currency' => strtoupper($payment->currency),
                'status' => $payment->status,
                'created_at' => date('Y-m-d H:i:s', $payment->created_at),
                'metadata' => $payment->notes->toArray(),
            ];
        } catch (\Exception $e) {
            throw new \Exception('Failed to retrieve transaction: ' . $e->getMessage());
        }
    }

    /**
     * Get list of supported currencies for Razorpay.
     */
    public function getSupportedCurrencies(): array
    {
        return [
            'INR', 'USD', 'EUR', 'GBP', 'AUD', 'CAD', 'SGD', 'AED', 'MYR', 'THB',
            'PHP', 'IDR', 'HKD', 'NZD', 'JPY', 'ZAR', 'SAR', 'QAR', 'KWD', 'OMR',
        ];
    }

    /**
     * Get the gateway identifier.
     */
    public function getGatewayName(): string
    {
        return 'razorpay';
    }

    /**
     * Check if gateway is in test mode.
     */
    public function isTestMode(): bool
    {
        return $this->testMode;
    }

    /**
     * Calculate Razorpay fees for a transaction.
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
