<?php

namespace App\Http\Controllers;

use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class PaymentController extends Controller
{
    protected PaymentService $paymentService;

    public function __construct(PaymentService $paymentService)
    {
        $this->paymentService = $paymentService;
    }

    /**
     * Create a checkout session.
     */
    public function createCheckout(Request $request)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:5|max:10000',
            'currency' => 'required|string|size:3',
            'gateway' => 'nullable|string|in:stripe,razorpay,paystack,paypal,flutterwave,cryptomus',
        ]);

        $user = $request->user();
        $gateway = $validated['gateway'] ?? 'stripe';

        try {
            $checkout = $this->paymentService->createTopUpCheckout(
                $user,
                $validated['amount'],
                $validated['currency'],
                $gateway
            );

            return response()->json([
                'success' => true,
                'checkout_url' => $checkout['checkout_url'],
                'transaction_id' => $checkout['transaction_id'],
            ]);

        } catch (\Exception $e) {
            Log::error('Checkout creation failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $errorMsg = $e->getMessage();

            // Check for specific known error types and return friendly messages
            if (str_contains($errorMsg, 'not configured') || str_contains($errorMsg, 'credentials are not configured')) {
                $message = 'Payment processing is not yet configured. Please contact support for assistance.';
            } elseif (str_contains($errorMsg, 'not supported by') || str_contains($errorMsg, 'Currency not supported')) {
                $message = $errorMsg;
            } elseif (str_contains($errorMsg, 'Minimum top-up amount')) {
                $message = $errorMsg;
            } else {
                $message = 'Payment system is currently unavailable. Please try again later or contact support.';
            }

            return response()->json([
                'success' => false,
                'message' => $message,
            ], 400);
        }
    }

    /**
     * Handle payment webhook.
     */
    public function handleWebhook(Request $request, string $gateway)
    {
        try {
            $payload = $request->getContent();

            // Get the correct signature header based on the gateway
            // PayPal uses multiple headers; pack them as JSON for the gateway to unpack
            $signature = match($gateway) {
                'stripe'      => $request->header('Stripe-Signature') ?? '',
                'paystack'    => $request->header('x-paystack-signature') ?? '',
                'razorpay'    => $request->header('x-razorpay-signature') ?? '',
                'flutterwave' => $request->header('verif-hash') ?? '',
                'cryptomus'   => '', // Cryptomus signature is in the JSON body, handled by gateway
                'paypal'      => json_encode([
                    'paypal-auth-algo'         => $request->header('paypal-auth-algo') ?? '',
                    'paypal-cert-url'          => $request->header('paypal-cert-url') ?? '',
                    'paypal-transmission-id'   => $request->header('paypal-transmission-id') ?? '',
                    'paypal-transmission-sig'  => $request->header('paypal-transmission-sig') ?? '',
                    'paypal-transmission-time' => $request->header('paypal-transmission-time') ?? '',
                ]),
                default       => '',
            };

            $this->paymentService->handleWebhook($gateway, $payload, $signature);

            return response()->json(['success' => true]);

        } catch (\Exception $e) {
            Log::error('Webhook handling failed', [
                'gateway' => $gateway,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Payment success page.
     */
    public function success(Request $request)
    {
        $sessionId = $request->query('session_id');
        $paymentId = $request->query('payment_id');
        $signature = $request->query('signature');
        $gatewayName = $request->query('gateway');
        $reference = $request->query('reference');

        // Handle PayPal return after buyer approval
        // PayPal sends: ?gateway=paypal&reference={our_reference}&token={order_id}&PayerID={payer_id}
        $paypalOrderId = $request->query('token');
        if ($gatewayName === 'paypal' && $reference && $paypalOrderId) {
            try {
                $transaction = \App\Models\Transaction::where('payment_id', $reference)
                    ->where('payment_gateway', 'paypal')
                    ->where('status', 'pending')
                    ->first();

                if ($transaction) {
                    // Credits the top-up the PayPal order was created for,
                    // which is not necessarily the reference in this URL.
                    $completed = $this->paymentService->completePayPalReturn($paypalOrderId);

                    if ($completed) {
                        return Inertia::render('Credit/PaymentSuccess', [
                            'amount'        => $completed->amount,
                            'transactionId' => $completed->id,
                            'paymentId'     => $completed->payment_id,
                            'gateway'       => 'paypal',
                        ]);
                    }
                }
            } catch (\Exception $e) {
                Log::error('PayPal payment capture failed', [
                    'reference'    => $reference,
                    'paypal_order' => $paypalOrderId,
                    'error'        => $e->getMessage(),
                ]);
            }
        }

        // Handle Paystack callback verification
        if ($gatewayName === 'paystack' && $reference) {
            try {
                $transaction = \App\Models\Transaction::where('payment_id', $reference)
                    ->where('payment_gateway', 'paystack')
                    ->where('status', 'pending')
                    ->first();

                if ($transaction) {
                    $gateway = $this->paymentService->getGateway('paystack');
                    $verification = $gateway->verifyTransaction($reference);

                    if ($verification['verified']) {
                        $this->paymentService->completeTransaction($transaction, $reference);
                        $transaction->refresh();

                        return Inertia::render('Credit/PaymentSuccess', [
                            'amount' => $transaction->amount,
                            'transactionId' => $transaction->id,
                            'paymentId' => $reference,
                            'gateway' => 'paystack',
                        ]);
                    }
                }
            } catch (\Exception $e) {
                Log::error('Paystack payment verification failed', [
                    'reference' => $reference,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Handle Flutterwave callback verification
        // Flutterwave redirects: ?gateway=flutterwave&reference={tx_ref}&status=successful&transaction_id={flw_id}
        if ($gatewayName === 'flutterwave' && $reference) {
            try {
                $transaction = \App\Models\Transaction::where('payment_id', $reference)
                    ->where('payment_gateway', 'flutterwave')
                    ->where('status', 'pending')
                    ->first();

                if ($transaction) {
                    /** @var \App\Services\Payment\FlutterwavePaymentGateway $gateway */
                    $gateway = $this->paymentService->getGateway('flutterwave');

                    // Flutterwave may pass the transaction_id in the URL for direct verification
                    $flwTransactionId = $request->query('transaction_id');
                    if ($flwTransactionId) {
                        $verification = $gateway->verifyTransaction($flwTransactionId);
                    } else {
                        $verification = $gateway->verifyTransactionByReference($reference);
                    }

                    if ($verification['verified'] && $verification['tx_ref'] === $reference) {
                        $this->paymentService->completeTransaction($transaction, (string) $verification['id']);
                        $transaction->refresh();

                        return Inertia::render('Credit/PaymentSuccess', [
                            'amount' => $transaction->amount,
                            'transactionId' => $transaction->id,
                            'paymentId' => (string) $verification['id'],
                            'gateway' => 'flutterwave',
                        ]);
                    }
                }
            } catch (\Exception $e) {
                Log::error('Flutterwave payment verification failed', [
                    'reference' => $reference,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Handle Cryptomus callback verification
        if ($gatewayName === 'cryptomus' && $reference) {
            try {
                $transaction = \App\Models\Transaction::where('payment_id', $reference)
                    ->where('payment_gateway', 'cryptomus')
                    ->where('status', 'pending')
                    ->first();

                if ($transaction) {
                    /** @var \App\Services\Payment\CryptomusPaymentGateway $gateway */
                    $gateway = $this->paymentService->getGateway('cryptomus');
                    $verification = $gateway->verifyPayment($reference);

                    if ($verification['verified']) {
                        $this->paymentService->completeTransaction($transaction, $reference);
                        $transaction->refresh();

                        return Inertia::render('Credit/PaymentSuccess', [
                            'amount' => $transaction->amount,
                            'transactionId' => $transaction->id,
                            'paymentId' => $reference,
                            'gateway' => 'cryptomus',
                        ]);
                    }
                }
            } catch (\Exception $e) {
                Log::error('Cryptomus payment verification failed', [
                    'reference' => $reference,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Handle Stripe checkout session success callback
        if ($sessionId && !$gatewayName) {
            try {
                // Transaction payment_id may be the session_id (cs_...) or the payment_intent (pi_...)
                $transaction = \App\Models\Transaction::where('payment_gateway', 'stripe')
                    ->where('status', 'pending')
                    ->where(function ($q) use ($sessionId) {
                        $q->where('payment_id', $sessionId)
                          ->orWhereJsonContains('metadata->session_id', $sessionId);
                    })
                    ->first();

                if ($transaction) {
                    /** @var \App\Services\Payment\StripePaymentGateway $gateway */
                    $gateway = $this->paymentService->getGateway('stripe');
                    $sessionDetails = $gateway->getSessionDetails($sessionId);

                    if (in_array($sessionDetails['status'], ['complete', 'paid'])) {
                        $this->paymentService->completeTransaction(
                            $transaction,
                            $sessionDetails['payment_intent'] ?? $sessionId
                        );
                        $transaction->refresh();

                        return Inertia::render('Credit/PaymentSuccess', [
                            'amount'        => $transaction->amount,
                            'transactionId' => $transaction->id,
                            'paymentId'     => $sessionDetails['payment_intent'] ?? $sessionId,
                            'gateway'       => 'stripe',
                        ]);
                    }
                }
            } catch (\Exception $e) {
                Log::error('Stripe payment verification failed', [
                    'session_id' => $sessionId,
                    'error'      => $e->getMessage(),
                ]);
            }
        }

        // Handle Razorpay payment details verification
        if ($paymentId && $sessionId) {
            try {
                $transaction = \App\Models\Transaction::where('payment_id', $sessionId)
                    ->where('payment_gateway', 'razorpay')
                    ->where('status', 'pending')
                    ->first();

                if ($transaction) {
                    // The payment must belong to this top-up's order, in full.
                    if ($this->paymentService->completeRazorpayReturn($transaction, $paymentId)) {
                        $transaction->refresh();

                        return Inertia::render('Credit/PaymentSuccess', [
                            'amount' => $transaction->amount,
                            'transactionId' => $transaction->id,
                            'paymentId' => $paymentId,
                            'gateway' => 'razorpay',
                        ]);
                    }
                }
            } catch (\Exception $e) {
                Log::error('Razorpay payment verification failed', [
                    'payment_id' => $paymentId,
                    'order_id' => $sessionId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return Inertia::render('Credit/PaymentSuccess', [
            'sessionId' => $sessionId,
        ]);
    }

    /**
     * Payment cancel page.
     */
    public function cancel(Request $request)
    {
        return Inertia::render('Credit/PaymentCancel');
    }

    /**
     * Process a refund (admin only).
     */
    public function refund(Request $request, int $transactionId)
    {
        $validated = $request->validate([
            'amount' => 'nullable|numeric|min:0.01',
        ]);

        try {
            $transaction = \App\Models\Transaction::findOrFail($transactionId);

            // Check permissions (should be admin only)
            if (!$request->user()->hasRole('admin')) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            $refund = $this->paymentService->processRefund(
                $transaction,
                $validated['amount'] ?? null
            );

            return response()->json([
                'success' => true,
                'refund' => $refund,
            ]);

        } catch (\Exception $e) {
            Log::error('Refund processing failed', [
                'transaction_id' => $transactionId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}

