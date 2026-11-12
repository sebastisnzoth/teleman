<?php

namespace App\Services\Payment;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PayPalPaymentGateway implements PaymentGatewayInterface
{
    protected string $clientId;
    protected string $clientSecret;
    protected string $webhookId;
    protected bool $testMode;
    protected float $feePercentage;
    protected float $feeFixed;

    private const SANDBOX_URL = 'https://api-m.sandbox.paypal.com';
    private const LIVE_URL    = 'https://api-m.paypal.com';

    public function __construct()
    {
        $this->clientId     = config('services.paypal.client_id') ?? '';
        $this->clientSecret = config('services.paypal.client_secret') ?? '';
        $this->webhookId    = config('services.paypal.webhook_id') ?? '';
        $this->testMode     = config('services.paypal.test_mode', true);
        $this->feePercentage = config('services.paypal.fee_percentage', 3.49);
        $this->feeFixed      = config('services.paypal.fee_fixed', 0.49);

        if (empty($this->clientId) || empty($this->clientSecret)) {
            throw new \Exception('PayPal API credentials are not configured. Please set PAYPAL_CLIENT_ID and PAYPAL_CLIENT_SECRET in your .env file.');
        }
    }

    /**
     * Base API URL depending on test/live mode.
     */
    private function baseUrl(): string
    {
        return $this->testMode ? self::SANDBOX_URL : self::LIVE_URL;
    }

    /**
     * Get or refresh an OAuth 2.0 access token (cached for 8 hours).
     */
    private function getAccessToken(): string
    {
        $cacheKey = 'paypal_access_token_' . substr(md5($this->clientId), 0, 8);

        return Cache::remember($cacheKey, 28800, function () {
            $response = Http::withBasicAuth($this->clientId, $this->clientSecret)
                ->asForm()
                ->post($this->baseUrl() . '/v1/oauth2/token', [
                    'grant_type' => 'client_credentials',
                ]);

            $data = $response->json();

            if (!$response->successful() || empty($data['access_token'])) {
                throw new \Exception('Failed to obtain PayPal access token: ' . ($data['error_description'] ?? 'Unknown error'));
            }

            return $data['access_token'];
        });
    }

    /**
     * Create a PayPal order and return the approval URL.
     */
    public function createCheckoutSession(float $amount, string $currency, array $metadata = []): array
    {
        try {
            $token = $this->getAccessToken();

            $reference = 'TLM-' . strtoupper(uniqid());

            $response = Http::withToken($token)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post($this->baseUrl() . '/v2/checkout/orders', [
                    'intent' => 'CAPTURE',
                    'purchase_units' => [
                        [
                            'reference_id'  => $reference,
                            'description'   => 'Credit Top-up',
                            'custom_id'     => (string) ($metadata['transaction_id'] ?? ''),
                            'amount'        => [
                                'currency_code' => strtoupper($currency),
                                'value'         => number_format($amount, 2, '.', ''),
                            ],
                        ],
                    ],
                    'payment_source' => [
                        'paypal' => [
                            'experience_context' => [
                                'payment_method_preference' => 'IMMEDIATE_PAYMENT_REQUIRED',
                                'brand_name'     => config('app.name', 'Teleman AI'),
                                'locale'         => 'en-US',
                                'landing_page'   => 'LOGIN',
                                'user_action'    => 'PAY_NOW',
                                'return_url'     => route('payment.success') . '?gateway=paypal&reference=' . $reference,
                                'cancel_url'     => route('payment.cancel'),
                            ],
                        ],
                    ],
                ]);

            $data = $response->json();

            if (!$response->successful() || empty($data['id'])) {
                $msg = $data['message'] ?? ($data['details'][0]['description'] ?? 'Failed to create PayPal order');
                throw new \Exception($msg);
            }

            // Find the payer-action (approval) link
            $approvalUrl = null;
            foreach ($data['links'] ?? [] as $link) {
                if ($link['rel'] === 'payer-action') {
                    $approvalUrl = $link['href'];
                    break;
                }
            }

            if (!$approvalUrl) {
                throw new \Exception('PayPal did not return an approval URL');
            }

            Log::info('PayPal order created', [
                'order_id'   => $data['id'],
                'reference'  => $reference,
                'amount'     => $amount,
                'currency'   => $currency,
            ]);

            return [
                'session_id'  => $data['id'],       // PayPal order ID
                'checkout_url'=> $approvalUrl,
                'payment_id'  => $reference,         // Our reference stored in DB
            ];
        } catch (\Exception $e) {
            // Flush cached token on auth failure so next attempt re-fetches
            if (str_contains($e->getMessage(), 'access token') || str_contains($e->getMessage(), 'UNAUTHORIZED')) {
                Cache::forget('paypal_access_token_' . substr(md5($this->clientId), 0, 8));
            }
            throw new \Exception('Failed to create PayPal checkout: ' . $e->getMessage());
        }
    }

    /**
     * Capture a PayPal order by order ID (called on return from PayPal).
     */
    public function captureOrder(string $orderId): array
    {
        $token = $this->getAccessToken();

        $response = Http::withToken($token)
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post($this->baseUrl() . "/v2/checkout/orders/{$orderId}/capture", []);

        $data = $response->json();

        if (!$response->successful()) {
            $msg = $data['message'] ?? ($data['details'][0]['description'] ?? 'Failed to capture PayPal order');
            throw new \Exception($msg);
        }

        $unit = $data['purchase_units'][0] ?? [];
        $capture = $unit['payments']['captures'][0] ?? [];

        return [
            'order_id'   => $data['id'],
            'capture_id' => $capture['id'] ?? '',
            'status'     => strtolower($data['status'] ?? 'unknown'),
            'reference'  => $unit['reference_id'] ?? '',
            'custom_id'  => $unit['custom_id'] ?? '',
            'amount'     => (float) ($capture['amount']['value'] ?? 0),
            'currency'   => strtoupper($capture['amount']['currency_code'] ?? 'USD'),
        ];
    }

    /**
     * Handle PayPal webhook events.
     * PayPal signs webhooks; we verify using the PayPal Webhook Verification API.
     */
    public function handleWebhook(string $payload, string $signature): array
    {
        // $signature parameter here carries the full set of PayPal headers as JSON
        // (packed by PaymentController before calling this method)
        $headers = json_decode($signature, true) ?? [];

        // PayPal verifies a webhook against the webhook's id; without one an
        // unverified PAYMENT.CAPTURE.COMPLETED could credit anyone.
        if (empty($this->webhookId)) {
            throw new \Exception('PAYPAL_WEBHOOK_ID is not configured, so the webhook cannot be verified');
        }

        $this->verifyWebhookSignature($payload, $headers);

        $event = json_decode($payload, true);
        $eventType = $event['event_type'] ?? '';
        $resource  = $event['resource'] ?? [];

        $eventTypeMap = [
            'PAYMENT.CAPTURE.COMPLETED'  => 'payment.success',
            'PAYMENT.CAPTURE.DENIED'     => 'payment.failed',
            'PAYMENT.CAPTURE.REVERSED'   => 'payment.failed',
            'CHECKOUT.ORDER.APPROVED'    => 'payment.pending',
            'CHECKOUT.ORDER.COMPLETED'   => 'payment.success',
            'PAYMENT.CAPTURE.REFUNDED'   => 'refund.completed',
        ];

        $mappedType = $eventTypeMap[$eventType] ?? 'unknown';

        // The custom_id we stored when creating the order is the transaction_id
        $customId  = $resource['purchase_units'][0]['custom_id']   ?? // order resource
                     $resource['custom_id']                        ?? // capture resource
                     '';

        $paymentId = $resource['supplementary_data']['related_ids']['order_id'] ?? // capture resource
                     $resource['id']                                              ?? // order resource
                     '';

        $amount   = (float) ($resource['amount']['value']                      ??
                             $resource['purchase_units'][0]['amount']['value']   ?? 0);
        $currency = strtoupper($resource['amount']['currency_code']             ??
                               $resource['purchase_units'][0]['amount']['currency_code'] ?? 'USD');

        return [
            'event_type' => $mappedType,
            'payment_id' => $paymentId,
            'custom_id'  => $customId,
            'amount'     => $amount,
            'currency'   => $currency,
            'status'     => strtolower($resource['status'] ?? 'unknown'),
            'metadata'   => $resource,
        ];
    }

    /**
     * Verify PayPal webhook signature via the PayPal API.
     */
    private function verifyWebhookSignature(string $payload, array $headers): void
    {
        $token = $this->getAccessToken();

        $response = Http::withToken($token)
            ->post($this->baseUrl() . '/v1/notifications/verify-webhook-signature', [
                'auth_algo'         => $headers['paypal-auth-algo']         ?? '',
                'cert_url'          => $headers['paypal-cert-url']          ?? '',
                'transmission_id'   => $headers['paypal-transmission-id']   ?? '',
                'transmission_sig'  => $headers['paypal-transmission-sig']  ?? '',
                'transmission_time' => $headers['paypal-transmission-time'] ?? '',
                'webhook_id'        => $this->webhookId,
                'webhook_event'     => json_decode($payload, true),
            ]);

        $data = $response->json();

        if (!$response->successful() || ($data['verification_status'] ?? '') !== 'SUCCESS') {
            throw new \Exception('Invalid PayPal webhook signature');
        }
    }

    /**
     * Refund a captured PayPal payment.
     */
    public function refund(string $transactionId, ?float $amount = null): array
    {
        try {
            $token = $this->getAccessToken();

            $body = [];
            if ($amount !== null) {
                // transactionId here should be the capture_id stored in payment_id
                // We need to find the capture — for simplicity we store capture_id as payment_id
                $body['amount'] = [
                    'value'         => number_format($amount, 2, '.', ''),
                    'currency_code' => 'USD', // Will be overridden by actual capture currency
                ];
            }

            $response = Http::withToken($token)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post($this->baseUrl() . "/v2/payments/captures/{$transactionId}/refund", $body);

            $data = $response->json();

            if (!$response->successful()) {
                $msg = $data['message'] ?? ($data['details'][0]['description'] ?? 'Failed to process PayPal refund');
                throw new \Exception($msg);
            }

            return [
                'refund_id' => $data['id'],
                'amount'    => (float) ($data['amount']['value'] ?? $amount ?? 0),
                'status'    => strtolower($data['status'] ?? 'pending') === 'completed' ? 'completed' : 'pending',
            ];
        } catch (\Exception $e) {
            throw new \Exception('Failed to process PayPal refund: ' . $e->getMessage());
        }
    }

    /**
     * Get PayPal order/capture details.
     */
    public function getTransactionDetails(string $transactionId): array
    {
        try {
            $token = $this->getAccessToken();

            // Try as capture first, then as order
            $response = Http::withToken($token)
                ->get($this->baseUrl() . "/v2/payments/captures/{$transactionId}");

            if (!$response->successful()) {
                $response = Http::withToken($token)
                    ->get($this->baseUrl() . "/v2/checkout/orders/{$transactionId}");
            }

            $data = $response->json();

            if (!$response->successful()) {
                throw new \Exception($data['message'] ?? 'Failed to retrieve PayPal transaction');
            }

            $amount   = (float) ($data['amount']['value'] ??
                                  $data['purchase_units'][0]['amount']['value'] ?? 0);
            $currency = strtoupper($data['amount']['currency_code'] ??
                                    $data['purchase_units'][0]['amount']['currency_code'] ?? 'USD');
            $status   = strtolower($data['status'] ?? 'unknown');

            return [
                'id'         => $data['id'],
                'amount'     => $amount,
                'currency'   => $currency,
                'status'     => $status,
                'created_at' => $data['create_time'] ?? now()->toDateTimeString(),
                'metadata'   => $data,
            ];
        } catch (\Exception $e) {
            throw new \Exception('Failed to retrieve PayPal transaction: ' . $e->getMessage());
        }
    }

    /**
     * PayPal supported currencies (25 currencies accepted globally).
     */
    public function getSupportedCurrencies(): array
    {
        return [
            'USD', 'EUR', 'GBP', 'AUD', 'CAD', 'JPY', 'CNY', 'INR',
            'BRL', 'MXN', 'SGD', 'HKD', 'NOK', 'SEK', 'DKK', 'PLN',
            'CHF', 'CZK', 'HUF', 'ILS', 'MYR', 'NZD', 'PHP', 'THB', 'TWD',
        ];
    }

    /**
     * Get the gateway identifier.
     */
    public function getGatewayName(): string
    {
        return 'paypal';
    }

    /**
     * Check if gateway is in sandbox/test mode.
     */
    public function isTestMode(): bool
    {
        return $this->testMode;
    }

    /**
     * Calculate PayPal fees.
     * Standard rate: 3.49% + $0.49 per transaction (international).
     */
    public function calculateFees(float $amount, string $currency): array
    {
        $percentageFee  = ($amount * $this->feePercentage) / 100;
        $fixedFee       = $this->feeFixed;
        $totalFee       = $percentageFee + $fixedFee;
        $amountAfterFees = $amount - $totalFee;

        return [
            'percentage_fee'   => round($percentageFee, 2),
            'fixed_fee'        => $fixedFee,
            'total_fee'        => round($totalFee, 2),
            'amount_after_fees'=> round($amountAfterFees, 2),
        ];
    }
}
