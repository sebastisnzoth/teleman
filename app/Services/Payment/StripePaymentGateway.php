<?php

namespace App\Services\Payment;

use Stripe\Stripe;
use Stripe\Checkout\Session;
use Stripe\Webhook;
use Stripe\PaymentIntent;
use Stripe\Refund;
use Stripe\Exception\SignatureVerificationException;

class StripePaymentGateway implements PaymentGatewayInterface
{
    protected string $secretKey;
    protected string $webhookSecret;
    protected bool $testMode;
    protected float $feePercentage;
    protected float $feeFixed;

    public function __construct()
    {
        $this->secretKey = config('services.stripe.secret') ?? '';
        $this->webhookSecret = config('services.stripe.webhook_secret') ?? '';
        $this->testMode = config('services.stripe.test_mode', true);
        $this->feePercentage = config('services.stripe.fee_percentage', 2.9);
        $this->feeFixed = config('services.stripe.fee_fixed', 0.30);

        if (empty($this->secretKey)) {
            throw new \Exception('Stripe secret key is not configured. Please set STRIPE_SECRET_KEY in your .env file.');
        }

        Stripe::setApiKey($this->secretKey);
    }

    /**
     * Create a checkout session for payment.
     */
    public function createCheckoutSession(float $amount, string $currency, array $metadata = []): array
    {
        try {
            $session = Session::create([
                'payment_method_types' => ['card'],
                'line_items' => [[
                    'price_data' => [
                        'currency' => strtolower($currency),
                        'unit_amount' => (int) ($amount * 100), // Convert to cents
                        'product_data' => [
                            'name' => 'Credit Top-up',
                            'description' => 'Add credits to your account',
                        ],
                    ],
                    'quantity' => 1,
                ]],
                'mode' => 'payment',
                'success_url' => route('payment.success') . '?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => route('payment.cancel'),
                'metadata' => $metadata,
                'customer_email' => $metadata['customer_email'] ?? null,
            ]);

            return [
                'session_id' => $session->id,
                'checkout_url' => $session->url,
                'payment_id' => $session->payment_intent,
            ];
        } catch (\Exception $e) {
            throw new \Exception('Failed to create checkout session: ' . $e->getMessage());
        }
    }

    /**
     * Handle webhook from Stripe.
     */
    public function handleWebhook(string $payload, string $signature): array
    {
        // An empty signing secret is still a key — one anybody can sign with.
        if (empty($this->webhookSecret)) {
            throw new \Exception('STRIPE_WEBHOOK_SECRET is not configured, so the webhook cannot be verified');
        }

        try {
            $event = Webhook::constructEvent(
                $payload,
                $signature,
                $this->webhookSecret
            );
        } catch (SignatureVerificationException $e) {
            throw new \Exception('Invalid webhook signature');
        }

        $eventType = $event->type;
        $eventData = $event->data->object;

        // Map Stripe event types to our event types
        $eventTypeMap = [
            'checkout.session.completed' => 'payment.success',
            'payment_intent.succeeded' => 'payment.success',
            'payment_intent.payment_failed' => 'payment.failed',
            'charge.refunded' => 'refund.completed',
        ];

        $mappedEventType = $eventTypeMap[$eventType] ?? 'unknown';

        // Extract payment details based on event type
        if ($eventType === 'checkout.session.completed') {
            return [
                'event_type'     => $mappedEventType,
                'payment_id'     => $eventData->payment_intent,
                'session_id'     => $eventData->id,  // cs_... for fallback transaction lookup
                'amount'         => $eventData->amount_total / 100,
                'currency'       => strtoupper($eventData->currency),
                'status'         => 'completed',
                'metadata'       => $eventData->metadata->toArray(),
                'customer_email' => $eventData->customer_email,
            ];
        } elseif ($eventType === 'payment_intent.succeeded') {
            return [
                'event_type' => $mappedEventType,
                'payment_id' => $eventData->id,
                'amount' => $eventData->amount / 100,
                'currency' => strtoupper($eventData->currency),
                'status' => 'completed',
                'metadata' => $eventData->metadata->toArray(),
            ];
        } elseif ($eventType === 'payment_intent.payment_failed') {
            return [
                'event_type' => $mappedEventType,
                'payment_id' => $eventData->id,
                'amount' => $eventData->amount / 100,
                'currency' => strtoupper($eventData->currency),
                'status' => 'failed',
                'metadata' => $eventData->metadata->toArray(),
                'error_message' => $eventData->last_payment_error->message ?? 'Payment failed',
            ];
        } elseif ($eventType === 'charge.refunded') {
            return [
                'event_type' => $mappedEventType,
                'payment_id' => $eventData->payment_intent,
                'amount' => $eventData->amount_refunded / 100,
                'currency' => strtoupper($eventData->currency),
                'status' => 'refunded',
                'metadata' => $eventData->metadata->toArray(),
            ];
        }

        throw new \Exception('Unhandled webhook event type: ' . $eventType);
    }

    /**
     * Process a refund for a transaction.
     */
    public function refund(string $transactionId, ?float $amount = null): array
    {
        try {
            $refundData = ['payment_intent' => $transactionId];

            if ($amount !== null) {
                $refundData['amount'] = (int) ($amount * 100); // Convert to cents
            }

            $refund = Refund::create($refundData);

            return [
                'refund_id' => $refund->id,
                'amount' => $refund->amount / 100,
                'status' => $refund->status === 'succeeded' ? 'completed' : $refund->status,
            ];
        } catch (\Exception $e) {
            throw new \Exception('Failed to process refund: ' . $e->getMessage());
        }
    }

    /**
     * Get Stripe Checkout Session details (used to verify payment on success callback).
     */
    public function getSessionDetails(string $sessionId): array
    {
        try {
            $session = Session::retrieve($sessionId);

            return [
                'id'             => $session->id,
                'payment_intent' => $session->payment_intent,
                'status'         => $session->status,          // 'open', 'complete', 'expired'
                'payment_status' => $session->payment_status,  // 'paid', 'unpaid', 'no_payment_required'
                'amount_total'   => $session->amount_total / 100,
                'currency'       => strtoupper($session->currency),
            ];
        } catch (\Exception $e) {
            throw new \Exception('Failed to retrieve Stripe session: ' . $e->getMessage());
        }
    }

    /**
     * Get transaction details from Stripe.
     */
    public function getTransactionDetails(string $transactionId): array
    {
        try {
            $paymentIntent = PaymentIntent::retrieve($transactionId);

            return [
                'id' => $paymentIntent->id,
                'amount' => $paymentIntent->amount / 100,
                'currency' => strtoupper($paymentIntent->currency),
                'status' => $paymentIntent->status,
                'created_at' => date('Y-m-d H:i:s', $paymentIntent->created),
                'metadata' => $paymentIntent->metadata->toArray(),
            ];
        } catch (\Exception $e) {
            throw new \Exception('Failed to retrieve transaction: ' . $e->getMessage());
        }
    }

    /**
     * Get list of supported currencies for Stripe.
     */
    public function getSupportedCurrencies(): array
    {
        return [
            'USD', 'EUR', 'GBP', 'AUD', 'CAD', 'JPY', 'INR', 'SGD', 'HKD', 'NZD',
            'SEK', 'NOK', 'DKK', 'CHF', 'MXN', 'BRL', 'ZAR', 'PLN', 'CZK', 'HUF',
        ];
    }

    /**
     * Get the gateway identifier.
     */
    public function getGatewayName(): string
    {
        return 'stripe';
    }

    /**
     * Check if gateway is in test mode.
     */
    public function isTestMode(): bool
    {
        return $this->testMode;
    }

    /**
     * Calculate Stripe fees for a transaction.
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
