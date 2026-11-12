<?php

namespace App\Services;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ProfitAnalyticsService
{
    /**
     * Get overall profit summary.
     *
     * @param string|null $startDate
     * @param string|null $endDate
     * @return array
     */
    public function getOverallSummary(?string $startDate = null, ?string $endDate = null): array
    {
        $query = Transaction::withProfit();

        if ($startDate) {
            $query->where('created_at', '>=', $startDate);
        }
        if ($endDate) {
            $query->where('created_at', '<=', $endDate);
        }

        $transactions = $query->get();

        $totalRevenue = $transactions->where('type', 'debit')->sum('amount');
        $totalCost = $transactions->where('type', 'debit')->sum('actual_cost');
        $totalProfit = $transactions->sum('profit_amount');
        $avgMargin = $totalCost > 0 ? ($totalProfit / $totalCost) * 100 : 0;

        // Count transactions
        $totalTransactions = $transactions->where('type', 'debit')->count();

        return [
            'total_revenue' => round($totalRevenue, 2),
            'total_cost' => round($totalCost, 2),
            'total_profit' => round($totalProfit, 2),
            'avg_margin_percentage' => round($avgMargin, 2),
            'total_transactions' => $totalTransactions,
            'period' => [
                'start' => $startDate,
                'end' => $endDate,
            ],
        ];
    }

    /**
     * Get profit breakdown by service type.
     *
     * @param string|null $startDate
     * @param string|null $endDate
     * @return array
     */
    public function getByServiceType(?string $startDate = null, ?string $endDate = null): array
    {
        $query = Transaction::withProfit()
            ->where('type', 'debit')
            ->select('service_type', 
                DB::raw('SUM(amount) as total_revenue'),
                DB::raw('SUM(actual_cost) as total_cost'),
                DB::raw('SUM(profit_amount) as total_profit'),
                DB::raw('AVG(profit_percentage) as avg_margin'),
                DB::raw('COUNT(*) as transaction_count')
            )
            ->groupBy('service_type');

        if ($startDate) {
            $query->where('created_at', '>=', $startDate);
        }
        if ($endDate) {
            $query->where('created_at', '<=', $endDate);
        }

        return $query->get()->map(function ($item) {
            return [
                'service_type' => $item->service_type,
                'total_revenue' => round($item->total_revenue, 2),
                'total_cost' => round($item->total_cost, 2),
                'total_profit' => round($item->total_profit, 2),
                'avg_margin' => round($item->avg_margin, 2),
                'transaction_count' => $item->transaction_count,
            ];
        })->toArray();
    }

    /**
     * Get profit breakdown by country.
     *
     * @param string|null $startDate
     * @param string|null $endDate
     * @return array
     */
    public function getByCountry(?string $startDate = null, ?string $endDate = null): array
    {
        $query = Transaction::withProfit()
            ->where('type', 'debit')
            ->select(
                DB::raw("JSON_EXTRACT(metadata, '$.country_code') as country_code"),
                DB::raw('SUM(amount) as total_revenue'),
                DB::raw('SUM(actual_cost) as total_cost'),
                DB::raw('SUM(profit_amount) as total_profit'),
                DB::raw('AVG(profit_percentage) as avg_margin'),
                DB::raw('COUNT(*) as transaction_count')
            )
            ->whereNotNull('metadata')
            ->groupBy('country_code');

        if ($startDate) {
            $query->where('created_at', '>=', $startDate);
        }
        if ($endDate) {
            $query->where('created_at', '<=', $endDate);
        }

        return $query->get()->map(function ($item) {
            return [
                'country_code' => trim($item->country_code, '"') ?? 'Unknown',
                'total_revenue' => round($item->total_revenue, 2),
                'total_cost' => round($item->total_cost, 2),
                'total_profit' => round($item->total_profit, 2),
                'avg_margin' => round($item->avg_margin, 2),
                'transaction_count' => $item->transaction_count,
            ];
        })->toArray();
    }

    /**
     * Get top profitable customers.
     *
     * @param int $limit
     * @param string|null $startDate
     * @param string|null $endDate
     * @return array
     */
    public function getTopCustomers(int $limit = 10, ?string $startDate = null, ?string $endDate = null): array
    {
        $query = Transaction::withProfit()
            ->with('user:id,name,email')
            ->where('type', 'debit')
            ->select('user_id',
                DB::raw('SUM(amount) as total_revenue'),
                DB::raw('SUM(actual_cost) as total_cost'),
                DB::raw('SUM(profit_amount) as total_profit'),
                DB::raw('COUNT(*) as transaction_count')
            )
            ->groupBy('user_id')
            ->orderByDesc('total_profit')
            ->limit($limit);

        if ($startDate) {
            $query->where('created_at', '>=', $startDate);
        }
        if ($endDate) {
            $query->where('created_at', '<=', $endDate);
        }

        return $query->get()->map(function ($item) {
            return [
                'user_id' => $item->user_id,
                'user_name' => $item->user->name ?? 'Unknown',
                'user_email' => $item->user->email ?? '',
                'total_revenue' => round($item->total_revenue, 2),
                'total_cost' => round($item->total_cost, 2),
                'total_profit' => round($item->total_profit, 2),
                'transaction_count' => $item->transaction_count,
            ];
        })->toArray();
    }

    /**
     * Get daily profit trend.
     *
     * @param int $days
     * @return array
     */
    public function getDailyTrend(int $days = 30): array
    {
        $startDate = Carbon::now()->subDays($days)->startOfDay();

        $data = Transaction::withProfit()
            ->where('type', 'debit')
            ->where('created_at', '>=', $startDate)
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('SUM(amount) as revenue'),
                DB::raw('SUM(actual_cost) as cost'),
                DB::raw('SUM(profit_amount) as profit'),
                DB::raw('COUNT(*) as transactions')
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return $data->map(function ($item) {
            return [
                'date' => $item->date,
                'revenue' => round($item->revenue, 2),
                'cost' => round($item->cost, 2),
                'profit' => round($item->profit, 2),
                'transactions' => $item->transactions,
            ];
        })->toArray();
    }

    /**
     * Get monthly profit comparison.
     *
     * @param int $months
     * @return array
     */
    public function getMonthlyComparison(int $months = 6): array
    {
        $startDate = Carbon::now()->subMonths($months)->startOfMonth();

        $data = Transaction::withProfit()
            ->where('type', 'debit')
            ->where('created_at', '>=', $startDate)
            ->select(
                DB::raw('YEAR(created_at) as year'),
                DB::raw('MONTH(created_at) as month'),
                DB::raw('SUM(amount) as revenue'),
                DB::raw('SUM(actual_cost) as cost'),
                DB::raw('SUM(profit_amount) as profit'),
                DB::raw('COUNT(*) as transactions')
            )
            ->groupBy('year', 'month')
            ->orderBy('year')
            ->orderBy('month')
            ->get();

        return $data->map(function ($item) {
            return [
                'period' => Carbon::create($item->year, $item->month)->format('M Y'),
                'year' => $item->year,
                'month' => $item->month,
                'revenue' => round($item->revenue, 2),
                'cost' => round($item->cost, 2),
                'profit' => round($item->profit, 2),
                'margin' => $item->cost > 0 ? round(($item->profit / $item->cost) * 100, 2) : 0,
                'transactions' => $item->transactions,
            ];
        })->toArray();
    }

    /**
     * Get cost status breakdown.
     *
     * @return array
     */
    public function getCostStatusBreakdown(): array
    {
        return Transaction::where('type', 'debit')
            ->select('cost_status',
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(amount) as total_amount')
            )
            ->groupBy('cost_status')
            ->get()
            ->map(function ($item) {
                return [
                    'status' => $item->cost_status,
                    'count' => $item->count,
                    'total_amount' => round($item->total_amount, 2),
                ];
            })
            ->toArray();
    }

    /**
     * Get real-time stats for dashboard.
     *
     * @return array
     */
    public function getRealTimeStats(): array
    {
        $today = Carbon::today();
        $thisWeek = Carbon::now()->startOfWeek();
        $thisMonth = Carbon::now()->startOfMonth();

        return [
            'today' => $this->getOverallSummary($today->toDateString(), null),
            'this_week' => $this->getOverallSummary($thisWeek->toDateString(), null),
            'this_month' => $this->getOverallSummary($thisMonth->toDateString(), null),
            'all_time' => $this->getOverallSummary(),
        ];
    }
}
