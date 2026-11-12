<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ProfitAnalyticsController extends Controller
{
    /**
     * Display comprehensive profit analytics dashboard.
     */
    public function index(Request $request): Response
    {
        // Only admins can view profit analytics
        abort_unless($request->user()->isAdmin(), 403);

        $period = $request->get('period', 'today'); // today, week, month, year, custom
        $startDate = $this->getStartDate($period, $request->get('start_date'));
        $endDate = $request->get('end_date', now());

        // Overall Profit Summary
        $summary = Transaction::where('type', 'debit')
            ->where('status', 'completed')
            ->where('created_at', '>=', $startDate)
            ->where('created_at', '<=', $endDate)
            ->select([
                DB::raw('SUM(amount) as total_revenue'),
                DB::raw('SUM(actual_cost) as total_cost'),
                DB::raw('SUM(profit_amount) as total_profit'),
                DB::raw('AVG(profit_percentage) as avg_margin'),
                DB::raw('COUNT(*) as total_transactions'),
            ])
            ->first();

        // Profit by Service Type
        $profitByService = Transaction::where('type', 'debit')
            ->where('status', 'completed')
            ->where('created_at', '>=', $startDate)
            ->where('created_at', '<=', $endDate)
            ->whereNotNull('service_type')
            ->select([
                'service_type',
                DB::raw('SUM(amount) as revenue'),
                DB::raw('SUM(actual_cost) as cost'),
                DB::raw('SUM(profit_amount) as profit'),
                DB::raw('AVG(profit_percentage) as margin'),
                DB::raw('COUNT(*) as count'),
            ])
            ->groupBy('service_type')
            ->get()
            ->map(function ($item) {
                $item->service_label = ucfirst(str_replace('_', ' ', $item->service_type));
                return $item;
            });

        // Profit Trends (Daily/Weekly/Monthly based on period)
        $trends = $this->getProfitTrends($startDate, $endDate, $period);

        // Top Profitable Customers
        $topCustomers = Transaction::where('type', 'debit')
            ->where('status', 'completed')
            ->where('created_at', '>=', $startDate)
            ->where('created_at', '<=', $endDate)
            ->select([
                'user_id',
                DB::raw('SUM(amount) as revenue'),
                DB::raw('SUM(actual_cost) as cost'),
                DB::raw('SUM(profit_amount) as profit'),
                DB::raw('COUNT(*) as transactions'),
            ])
            ->groupBy('user_id')
            ->orderByDesc('profit')
            ->limit(10)
            ->get()
            ->load('user:id,name,email');

        // Profit by Country (from metadata)
        $profitByCountry = Transaction::where('type', 'debit')
            ->where('status', 'completed')
            ->where('created_at', '>=', $startDate)
            ->where('created_at', '<=', $endDate)
            ->whereNotNull('metadata')
            ->get()
            ->filter(function ($transaction) {
                return isset($transaction->metadata['country_code']);
            })
            ->groupBy('metadata.country_code')
            ->map(function ($transactions, $country) {
                return [
                    'country' => $country,
                    'revenue' => $transactions->sum('amount'),
                    'cost' => $transactions->sum('actual_cost'),
                    'profit' => $transactions->sum('profit_amount'),
                    'margin' => $transactions->avg('profit_percentage'),
                    'count' => $transactions->count(),
                ];
            })
            ->sortByDesc('profit')
            ->values()
            ->take(15);

        // Cost Accuracy Metrics (estimated vs actual)
        $costAccuracy = Transaction::where('type', 'debit')
            ->where('status', 'completed')
            ->where('created_at', '>=', $startDate)
            ->where('created_at', '<=', $endDate)
            ->whereNotNull('actual_cost')
            ->get()
            ->map(function ($transaction) {
                $estimated = $transaction->metadata['estimated_cost'] ?? $transaction->actual_cost;
                $actual = $transaction->actual_cost;
                $difference = abs($estimated - $actual);
                $accuracy = $actual > 0 ? (1 - ($difference / $actual)) * 100 : 100;
                return [
                    'estimated' => $estimated,
                    'actual' => $actual,
                    'difference' => $difference,
                    'accuracy' => $accuracy,
                ];
            });

        $accuracyMetrics = [
            'avg_accuracy' => $costAccuracy->avg('accuracy'),
            'total_overcharged' => $costAccuracy->where('difference', '>', 0)->sum('difference'),
            'total_undercharged' => $costAccuracy->where('difference', '<', 0)->sum('difference'),
            'refunds_issued' => Transaction::where('type', 'credit')
                ->where('description', 'LIKE', '%refund%')
                ->where('created_at', '>=', $startDate)
                ->where('created_at', '<=', $endDate)
                ->sum('amount'),
        ];

        // Recent High-Value Transactions
        $recentTransactions = Transaction::where('type', 'debit')
            ->where('status', 'completed')
            ->where('created_at', '>=', $startDate)
            ->where('created_at', '<=', $endDate)
            ->orderByDesc('profit_amount')
            ->limit(20)
            ->with('user:id,name,email')
            ->get();

        return Inertia::render('Admin/ProfitAnalytics/Index', [
            'summary' => [
                'total_revenue' => (float) ($summary->total_revenue ?? 0),
                'total_cost' => (float) ($summary->total_cost ?? 0),
                'total_profit' => (float) ($summary->total_profit ?? 0),
                'avg_margin' => (float) ($summary->avg_margin ?? 0),
                'total_transactions' => (int) ($summary->total_transactions ?? 0),
            ],
            'profitByService' => $profitByService,
            'trends' => $trends,
            'topCustomers' => $topCustomers,
            'profitByCountry' => $profitByCountry,
            'accuracyMetrics' => $accuracyMetrics,
            'recentTransactions' => $recentTransactions,
            'filters' => [
                'period' => $period,
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => is_string($endDate) ? $endDate : $endDate->format('Y-m-d'),
            ],
        ]);
    }

    /**
     * Export profit report as CSV.
     */
    public function export(Request $request)
    {
        abort_unless($request->user()->isAdmin(), 403);

        $period = $request->get('period', 'today');
        $startDate = $this->getStartDate($period, $request->get('start_date'));
        $endDate = $request->get('end_date', now());

        $transactions = Transaction::where('type', 'debit')
            ->where('status', 'completed')
            ->where('created_at', '>=', $startDate)
            ->where('created_at', '<=', $endDate)
            ->with('user:id,name,email')
            ->orderBy('created_at', 'desc')
            ->get();

        $filename = 'profit_report_' . $startDate->format('Y-m-d') . '_to_' . (is_string($endDate) ? $endDate : $endDate->format('Y-m-d')) . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        $callback = function () use ($transactions) {
            $file = fopen('php://output', 'w');

            // CSV Headers
            fputcsv($file, [
                'Date',
                'User',
                'Service Type',
                'Description',
                'Revenue',
                'Cost',
                'Profit',
                'Margin %',
                'Currency',
                'Country',
                'Status',
            ]);

            // CSV Data
            foreach ($transactions as $transaction) {
                fputcsv($file, [
                    $transaction->created_at->format('Y-m-d H:i:s'),
                    $transaction->user->name ?? 'N/A',
                    ucfirst(str_replace('_', ' ', $transaction->service_type ?? 'N/A')),
                    $transaction->description,
                    number_format($transaction->amount, 2),
                    number_format($transaction->actual_cost ?? 0, 2),
                    number_format($transaction->profit_amount ?? 0, 2),
                    number_format($transaction->profit_percentage ?? 0, 2),
                    $transaction->currency,
                    $transaction->metadata['country_code'] ?? 'N/A',
                    $transaction->status,
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Get profit trends based on period.
     */
    protected function getProfitTrends($startDate, $endDate, $period)
    {
        $groupByField = match ($period) {
            'today' => 'HOUR(created_at)',
            'week', 'month' => 'DATE(created_at)',
            'year' => 'DATE_FORMAT(created_at, "%Y-%m")',
            default => 'DATE(created_at)',
        };

        $labelFormat = match ($period) {
            'today' => 'H:00',
            'week', 'month' => 'M d',
            'year' => 'M Y',
            default => 'M d',
        };

        return Transaction::where('type', 'debit')
            ->where('status', 'completed')
            ->where('created_at', '>=', $startDate)
            ->where('created_at', '<=', $endDate)
            ->select([
                DB::raw($groupByField . ' as period'),
                DB::raw('SUM(amount) as revenue'),
                DB::raw('SUM(actual_cost) as cost'),
                DB::raw('SUM(profit_amount) as profit'),
            ])
            ->groupBy('period')
            ->orderBy('period')
            ->get()
            ->map(function ($item) use ($period, $labelFormat) {
                if ($period === 'today') {
                    $item->label = str_pad($item->period, 2, '0', STR_PAD_LEFT) . ':00';
                } else {
                    $item->label = $item->period;
                }
                return $item;
            });
    }

    /**
     * Get start date based on period.
     */
    protected function getStartDate($period, $customStart = null)
    {
        return match ($period) {
            'today' => now()->startOfDay(),
            'week' => now()->subWeek(),
            'month' => now()->subMonth(),
            'year' => now()->subYear(),
            'custom' => $customStart ? \Carbon\Carbon::parse($customStart) : now()->subMonth(),
            default => now()->startOfDay(),
        };
    }
}
