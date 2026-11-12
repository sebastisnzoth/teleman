<?php

namespace App\Services\Payment;

interface PaymentGatewayInterface
{
    /**
     * Create a checkout session for payment.
     *
     * @param float $amount Amount to charge
     * @param string $currency Currency code (USD, EUR, etc)
     * @param array $metadata Additional data (user_id, description, etc)
     * @return array [
     *   'session_id' => string,
     *   'checkout_url' => string,
     *   'payment_id' => string|null
     * ]
     */
    public function createCheckoutSession(float $amount, string $currency, array $metadata = []): array;

    /**
     * Handle webhook from payment gateway.
     *
     * @param string $payload Raw webhook payload
     * @param string $signature Webhook signature for verification
     * @return array [
     *   'event_type' => string (payment.success, payment.failed, refund.completed, etc),
     *   'payment_id' => string,
     *   'amount' => float,
     *   'currency' => string,
     *   'status' => string,
     *   'metadata' => array
     * ]
     * @throws \Exception If signature verification fails
     */
    public function handleWebhook(string $payload, string $signature): array;

    /**
     * Process a refund for a transaction.
     *
     * @param string $transactionId Payment gateway transaction ID
     * @param float|null $amount Amount to refund (null for full refund)
     * @return array [
     *   'refund_id' => string,
     *   'amount' => float,
     *   'status' => string (pending, completed, failed)
     * ]
     * @throws \Exception If refund fails
     */
    public function refund(string $transactionId, ?float $amount = null): array;

    /**
     * Get transaction details from payment gateway.
     *
     * @param string $transactionId Payment gateway transaction ID
     * @return array [
     *   'id' => string,
     *   'amount' => float,
     *   'currency' => string,
     *   'status' => string,
     *   'created_at' => string,
     *   'metadata' => array
     * ]
     * @throws \Exception If transaction not found
     */
    public function getTransactionDetails(string $transactionId): array;

    /**
     * Get list of supported currencies for this gateway.
     *
     * @return array ['USD', 'EUR', 'GBP', ...]
     */
    public function getSupportedCurrencies(): array;

    /**
     * Get the gateway identifier.
     *
     * @return string (stripe, paypal, razorpay, etc)
     */
    public function getGatewayName(): string;

    /**
     * Check if gateway is in test mode.
     *
     * @return bool
     */
    public function isTestMode(): bool;

    /**
     * Calculate gateway fees for a transaction.
     *
     * @param float $amount Transaction amount
     * @param string $currency Currency code
     * @return array [
     *   'percentage_fee' => float,
     *   'fixed_fee' => float,
     *   'total_fee' => float,
     *   'amount_after_fees' => float
     * ]
     */
    public function calculateFees(float $amount, string $currency): array;
}
