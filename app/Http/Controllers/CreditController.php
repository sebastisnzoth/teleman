<?php

namespace App\Http\Controllers;

use App\Services\CreditService;
use App\Services\ExchangeRateService;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CreditController extends Controller
{
    protected CreditService $creditService;
    protected PaymentService $paymentService;
    protected ExchangeRateService $exchangeRateService;

    public function __construct(CreditService $creditService, PaymentService $paymentService, ExchangeRateService $exchangeRateService)
    {
        $this->creditService = $creditService;
        $this->paymentService = $paymentService;
        $this->exchangeRateService = $exchangeRateService;
    }

    /**
     * Show credit balance and recent transactions.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $transactions = $this->creditService->getTransactionHistory($user, 10);
        $spendingSummary = $this->creditService->getSpendingSummary($user, 'month');

        return Inertia::render('Credit/Index', [
            'creditBalance' => (float) $user->credit_balance ?? 0,
            'currency' => $user->preferred_currency ?? 'USD',
            'lowBalanceThreshold' => $user->low_balance_threshold,
            'lowBalanceAlertEnabled' => $user->low_balance_alert_enabled,
            'transactions' => $transactions,
            'spendingSummary' => $spendingSummary,
        ]);
    }

    /**
     * Show top-up page.
     */
    public function topUp(Request $request)
    {
        $user = $request->user();
        
        $availableGateways = $this->paymentService->getAvailableGateways();
        
        // If no payment gateways are configured
        if (empty($availableGateways)) {
            return Inertia::render('Credit/TopUp', [
                'currentBalance' => (float) $user->credit_balance ?? 0,
                'currency' => $user->preferred_currency ?? 'USD',
                'presetAmounts' => [10, 25, 50, 100, 250, 500],
                'availableGateways' => [],
                'supportedCurrencies' => ['USD'],
                'configError' => 'Payment system is currently unavailable. Please contact support or try again later.',
            ]);
        }
        
        try {
            $supportedCurrencies = $this->paymentService->getSupportedCurrencies();
        } catch (\Exception $e) {
            $supportedCurrencies = ['USD'];
        }

        $presetAmounts = [5, 10, 20, 50, 100, 200];

        return Inertia::render('Credit/TopUp', [
            'currentBalance' => (float) $user->credit_balance ?? 0,
            'currency' => $user->preferred_currency ?? 'USD',
            'presetAmounts' => $presetAmounts,
            'availableGateways' => $availableGateways,
            'supportedCurrencies' => $supportedCurrencies,
        ]);
    }

    /**
     * Show Razorpay checkout page.
     */
    public function razorpayCheckout(Request $request)
    {
        $orderId = $request->query('order_id');
        
        if (!$orderId) {
            return redirect()->route('credit.top-up')
                ->with('error', 'Invalid payment session');
        }

        // Get transaction by order ID
        $transaction = \App\Models\Transaction::where('payment_id', $orderId)
            ->where('payment_gateway', 'razorpay')
            ->where('status', 'pending')
            ->first();

        if (!$transaction) {
            return redirect()->route('credit.top-up')
                ->with('error', 'Payment session not found or expired');
        }

        return Inertia::render('Credit/RazorpayCheckout', [
            'orderId' => $orderId,
            'amount' => (float) $transaction->amount,
            'currency' => $transaction->currency,
            'razorpayKey' => config('services.razorpay.api_key'),
            'userEmail' => $request->user()->email,
            'userName' => $request->user()->name,
        ]);
    }

    /**
     * Show transaction history.
     */
    public function history(Request $request)
    {
        $user = $request->user();

        $query = $user->transactions()
            ->where('status', 'completed') // Only show completed transactions
            ->orderBy('created_at', 'desc');

        // Apply filters
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('from_date')) {
            $query->where('created_at', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->where('created_at', '<=', $request->to_date);
        }

        if ($request->filled('reference_type')) {
            $query->where('reference_type', $request->reference_type);
        }

        $transactions = $query->paginate(25);

        return Inertia::render('Credit/History', [
            'transactions' => $transactions->through(function ($transaction) {
                return [
                    'id' => $transaction->id,
                    'type' => $transaction->type,
                    'amount' => (float) $transaction->amount,
                    'description' => $transaction->description,
                    'balance_after' => (float) $transaction->balance_after,
                    'created_at' => $transaction->created_at,
                    'reference_type' => $transaction->reference_type,
                    'payment_gateway' => $transaction->payment_gateway,
                    'payment_id' => $transaction->payment_id,
                    'status' => $transaction->status,
                ];
            }),
            'currentBalance' => (float) $user->credit_balance ?? 0,
            'filters' => $request->only(['type', 'from_date', 'to_date', 'reference_type']),
        ]);
    }

    /**
     * Update credit settings.
     */
    public function updateSettings(Request $request)
    {
        $validated = $request->validate([
            'preferred_currency' => 'required|string|size:3',
            'low_balance_alert_enabled' => 'required|boolean',
            'low_balance_threshold' => 'required|numeric|min:0|max:1000',
        ]);

        $user = $request->user();
        $user->update($validated);

        return redirect()->back()->with('success', 'Credit settings updated successfully');
    }

    /**
     * Export transaction history as CSV.
     */
    public function export(Request $request)
    {
        $user = $request->user();

        $query = $user->transactions()->orderBy('created_at', 'desc');

        // Apply same filters as history
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('from_date')) {
            $query->where('created_at', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->where('created_at', '<=', $request->to_date);
        }

        $transactions = $query->get();

        $filename = 'transactions_' . now()->format('Y-m-d_His') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function() use ($transactions) {
            $file = fopen('php://output', 'w');

            // Header row
            fputcsv($file, [
                'Date', 'Type', 'Amount', 'Currency', 'Balance Before', 
                'Balance After', 'Description', 'Reference Type', 
                'Status', 'Payment Gateway'
            ]);

            // Data rows
            foreach ($transactions as $transaction) {
                fputcsv($file, [
                    $transaction->created_at->format('Y-m-d H:i:s'),
                    ucfirst($transaction->type),
                    $transaction->amount,
                    $transaction->currency,
                    $transaction->balance_before,
                    $transaction->balance_after,
                    $transaction->description,
                    $transaction->reference_type ?? 'N/A',
                    ucfirst($transaction->status),
                    $transaction->payment_gateway ?? 'N/A',
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Get exchange rate and conversion preview.
     */
    public function exchangeRate(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'from' => 'required|string|size:3',
        ]);

        $baseCurrency = $this->exchangeRateService->getBaseCurrency();

        try {
            $conversion = $this->exchangeRateService->convert(
                (float) $request->amount,
                $request->from,
                $baseCurrency
            );

            return response()->json([
                'success' => true,
                'from' => strtoupper($request->from),
                'to' => $baseCurrency,
                'amount' => (float) $request->amount,
                'converted_amount' => $conversion['converted_amount'],
                'rate' => $conversion['rate'],
                'base_currency' => $baseCurrency,
                'base_currency_symbol' => $this->exchangeRateService->getCurrencySymbol($baseCurrency),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to fetch exchange rate. Please try again.',
            ], 500);
        }
    }
}

