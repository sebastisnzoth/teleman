<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Transaction;
use App\Services\CreditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class CreditManagementController extends Controller
{
    protected CreditService $creditService;

    public function __construct(CreditService $creditService)
    {
        $this->creditService = $creditService;
    }

    /**
     * Show credit management dashboard.
     */
    public function index(Request $request)
    {
        $query = User::query();

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Filter by low balance
        if ($request->boolean('low_balance_only')) {
            $query->whereColumn('credit_balance', '<', 'low_balance_threshold');
        }

        // Sort
        $sortBy = $request->input('sort_by', 'credit_balance');
        $sortOrder = $request->input('sort_order', 'asc');
        $query->orderBy($sortBy, $sortOrder);

        $users = $query->paginate(25);

        // Overall statistics
        $stats = [
            'total_users' => User::count(),
            'total_credit_balance' => User::sum('credit_balance'),
            'low_balance_users' => User::whereColumn('credit_balance', '<', 'low_balance_threshold')->count(),
            'recent_transactions' => Transaction::where('created_at', '>=', now()->subDays(7))->count(),
        ];

        return Inertia::render('Admin/CreditManagement/Index', [
            'users' => $users,
            'stats' => $stats,
            'filters' => $request->only(['search', 'low_balance_only', 'sort_by', 'sort_order']),
        ]);
    }

    /**
     * Show user's credit details.
     */
    public function show(User $user)
    {
        $transactions = Transaction::where('user_id', $user->id)
            ->with(['admin'])
            ->orderBy('created_at', 'desc')
            ->paginate(50);

        $spendingSummary = $this->creditService->getSpendingSummary($user, 'month');

        return Inertia::render('Admin/CreditManagement/Show', [
            'user' => $user,
            'transactions' => $transactions,
            'spendingSummary' => $spendingSummary,
        ]);
    }

    /**
     * Manual credit adjustment.
     */
    public function adjustCredit(Request $request, User $user)
    {
        $validated = $request->validate([
            'type' => 'required|in:credit,debit',
            'amount' => 'required|numeric|min:0.01|max:10000',
            'reason' => 'required|string|max:500',
        ]);

        try {
            $admin = $request->user();

            if ($validated['type'] === 'credit') {
                $transaction = $this->creditService->addCredit(
                    $user,
                    $validated['amount'],
                    'Manual adjustment: ' . $validated['reason'],
                    [
                        'currency' => $user->preferred_currency ?? 'USD',
                        'reference_type' => 'AdminAdjustment',
                        'admin_id' => $admin->id,
                        'metadata' => [
                            'adjustment_reason' => $validated['reason'],
                            'adjusted_by' => $admin->name,
                        ],
                    ]
                );
            } else {
                $transaction = $this->creditService->deductCredit(
                    $user,
                    $validated['amount'],
                    'Manual adjustment: ' . $validated['reason'],
                    [
                        'currency' => $user->preferred_currency ?? 'USD',
                        'reference_type' => 'AdminAdjustment',
                        'admin_id' => $admin->id,
                        'metadata' => [
                            'adjustment_reason' => $validated['reason'],
                            'adjusted_by' => $admin->name,
                        ],
                    ]
                );
            }

            return redirect()->back()->with('success', 'Credit adjusted successfully');

        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * View all transactions.
     */
    public function allTransactions(Request $request)
    {
        $query = Transaction::with(['user', 'admin']);

        // Filters
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('reference_type')) {
            $query->where('reference_type', $request->reference_type);
        }

        if ($request->filled('from_date')) {
            $query->where('created_at', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->where('created_at', '<=', $request->to_date);
        }

        $transactions = $query->orderBy('created_at', 'desc')->paginate(50);
        
        // Calculate profit statistics
        $profitStats = [
            'total_revenue' => Transaction::where('type', 'credit')
                ->where('reference_type', 'TopUp')
                ->where('status', 'completed')
                ->sum('amount'),
            'total_costs' => Transaction::where('type', 'debit')
                ->where('status', 'completed')
                ->sum('actual_cost'),
            'total_profit' => Transaction::where('type', 'debit')
                ->where('status', 'completed')
                ->sum('profit_amount'),
            'average_profit_margin' => Transaction::where('type', 'debit')
                ->where('status', 'completed')
                ->whereNotNull('profit_percentage')
                ->avg('profit_percentage'),
        ];

        return Inertia::render('Admin/CreditManagement/Transactions', [
            'transactions' => $transactions->through(function ($transaction) {
                return [
                    'id' => $transaction->id,
                    'user' => $transaction->user ? [
                        'id' => $transaction->user->id,
                        'name' => $transaction->user->name,
                        'email' => $transaction->user->email,
                    ] : null,
                    'type' => $transaction->type,
                    'amount' => (float) $transaction->amount,
                    'description' => $transaction->description,
                    'balance_after' => (float) $transaction->balance_after,
                    'created_at' => $transaction->created_at,
                    'reference_type' => $transaction->reference_type,
                    'payment_gateway' => $transaction->payment_gateway,
                    'payment_id' => $transaction->payment_id,
                    'status' => $transaction->status,
                    'actual_cost' => $transaction->actual_cost ? (float) $transaction->actual_cost : null,
                    'profit_amount' => $transaction->profit_amount ? (float) $transaction->profit_amount : null,
                    'profit_percentage' => $transaction->profit_percentage ? (float) $transaction->profit_percentage : null,
                    'service_type' => $transaction->service_type,
                    'admin' => $transaction->admin ? [
                        'id' => $transaction->admin->id,
                        'name' => $transaction->admin->name,
                    ] : null,
                ];
            }),
            'filters' => $request->only(['user_id', 'type', 'status', 'reference_type', 'from_date', 'to_date']),
            'profitStats' => $profitStats,
        ]);
    }

    /**
     * Financial reports.
     */
    public function reports(Request $request)
    {
        $period = $request->input('period', 'month');

        $startDate = match($period) {
            'day' => now()->startOfDay(),
            'week' => now()->startOfWeek(),
            'month' => now()->startOfMonth(),
            'year' => now()->startOfYear(),
            default => now()->startOfMonth(),
        };

        // Revenue (top-ups)
        $revenue = Transaction::where('type', 'credit')
            ->where('reference_type', 'TopUp')
            ->where('status', 'completed')
            ->where('created_at', '>=', $startDate)
            ->sum('amount');

        // Spending (debits)
        $spending = Transaction::where('type', 'debit')
            ->where('status', 'completed')
            ->where('created_at', '>=', $startDate)
            ->sum('amount');

        // Breakdown by reference type
        $spendingBreakdown = Transaction::select('reference_type', DB::raw('SUM(amount) as total'))
            ->where('type', 'debit')
            ->where('status', 'completed')
            ->where('created_at', '>=', $startDate)
            ->groupBy('reference_type')
            ->get();

        // Top spenders
        $topSpenders = User::select('users.*', DB::raw('SUM(transactions.amount) as total_spent'))
            ->join('transactions', 'users.id', '=', 'transactions.user_id')
            ->where('transactions.type', 'debit')
            ->where('transactions.created_at', '>=', $startDate)
            ->groupBy('users.id')
            ->orderByDesc('total_spent')
            ->limit(10)
            ->get();

        // Top revenue contributors
        $topContributors = User::select('users.*', DB::raw('SUM(transactions.amount) as total_contributed'))
            ->join('transactions', 'users.id', '=', 'transactions.user_id')
            ->where('transactions.type', 'credit')
            ->where('transactions.reference_type', 'TopUp')
            ->where('transactions.created_at', '>=', $startDate)
            ->groupBy('users.id')
            ->orderByDesc('total_contributed')
            ->limit(10)
            ->get();

        return Inertia::render('Admin/CreditManagement/Reports', [
            'period' => $period,
            'revenue' => round($revenue, 2),
            'spending' => round($spending, 2),
            'netRevenue' => round($revenue - $spending, 2),
            'spendingBreakdown' => $spendingBreakdown,
            'topSpenders' => $topSpenders,
            'topContributors' => $topContributors,
        ]);
    }

    /**
     * Export financial report as CSV.
     */
    public function exportReport(Request $request)
    {
        $period = $request->input('period', 'month');

        $startDate = match($period) {
            'day' => now()->startOfDay(),
            'week' => now()->startOfWeek(),
            'month' => now()->startOfMonth(),
            'year' => now()->startOfYear(),
            default => now()->startOfMonth(),
        };

        $transactions = Transaction::with('user')
            ->where('created_at', '>=', $startDate)
            ->where('status', 'completed')
            ->orderBy('created_at', 'desc')
            ->get();

        $filename = 'financial_report_' . $period . '_' . now()->format('Y-m-d_His') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function() use ($transactions) {
            $file = fopen('php://output', 'w');

            // Header row
            fputcsv($file, [
                'Date', 'User', 'Email', 'Type', 'Amount', 'Currency',
                'Description', 'Reference Type', 'Payment Gateway', 'Status'
            ]);

            // Data rows
            foreach ($transactions as $transaction) {
                fputcsv($file, [
                    $transaction->created_at->format('Y-m-d H:i:s'),
                    $transaction->user->name,
                    $transaction->user->email,
                    ucfirst($transaction->type),
                    $transaction->amount,
                    $transaction->currency,
                    $transaction->description,
                    $transaction->reference_type ?? 'N/A',
                    $transaction->payment_gateway ?? 'N/A',
                    ucfirst($transaction->status),
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}

