<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ProfitAnalyticsService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProfitDashboardController extends Controller
{
    public function __construct(
        protected ProfitAnalyticsService $profitService
    ) {}

    /**
     * Display the profit dashboard.
     */
    public function index(Request $request): Response
    {
        // Get filter parameters
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $period = $request->input('period', 'today'); // today, week, month, all

        // Set date range based on period
        if (!$startDate && !$endDate) {
            switch ($period) {
                case 'today':
                    $startDate = now()->startOfDay()->toDateString();
                    break;
                case 'week':
                    $startDate = now()->startOfWeek()->toDateString();
                    break;
                case 'month':
                    $startDate = now()->startOfMonth()->toDateString();
                    break;
            }
        }

        // Get analytics data
        $overview = $this->profitService->getOverallSummary($startDate, $endDate);
        $byService = $this->profitService->getByServiceType($startDate, $endDate);
        $byCountry = $this->profitService->getByCountry($startDate, $endDate);
        $topCustomers = $this->profitService->getTopCustomers(10, $startDate, $endDate);
        $dailyTrend = $this->profitService->getDailyTrend(30);
        $monthlyComparison = $this->profitService->getMonthlyComparison(6);
        $costStatusBreakdown = $this->profitService->getCostStatusBreakdown();
        $realTimeStats = $this->profitService->getRealTimeStats();

        return Inertia::render('Admin/ProfitDashboard/Index', [
            'overview' => $overview,
            'byService' => $byService,
            'byCountry' => $byCountry,
            'topCustomers' => $topCustomers,
            'dailyTrend' => $dailyTrend,
            'monthlyComparison' => $monthlyComparison,
            'costStatusBreakdown' => $costStatusBreakdown,
            'realTimeStats' => $realTimeStats,
            'filters' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'period' => $period,
            ],
        ]);
    }

    /**
     * Export profit report as CSV.
     */
    public function export(Request $request)
    {
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        $overview = $this->profitService->getOverallSummary($startDate, $endDate);
        $byService = $this->profitService->getByServiceType($startDate, $endDate);
        $byCountry = $this->profitService->getByCountry($startDate, $endDate);
        $topCustomers = $this->profitService->getTopCustomers(50, $startDate, $endDate);

        $filename = 'profit-report-' . now()->format('Y-m-d') . '.csv';
        
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function() use ($overview, $byService, $byCountry, $topCustomers) {
            $file = fopen('php://output', 'w');

            // Overview section
            fputcsv($file, ['PROFIT OVERVIEW']);
            fputcsv($file, ['Metric', 'Value']);
            fputcsv($file, ['Total Revenue', '$' . number_format($overview['total_revenue'], 2)]);
            fputcsv($file, ['Total Cost', '$' . number_format($overview['total_cost'], 2)]);
            fputcsv($file, ['Total Profit', '$' . number_format($overview['total_profit'], 2)]);
            fputcsv($file, ['Average Margin', $overview['avg_margin_percentage'] . '%']);
            fputcsv($file, ['Total Transactions', $overview['total_transactions']]);
            fputcsv($file, []);

            // By Service Type
            fputcsv($file, ['PROFIT BY SERVICE TYPE']);
            fputcsv($file, ['Service', 'Revenue', 'Cost', 'Profit', 'Margin %', 'Transactions']);
            foreach ($byService as $service) {
                fputcsv($file, [
                    ucfirst($service['service_type']),
                    '$' . number_format($service['total_revenue'], 2),
                    '$' . number_format($service['total_cost'], 2),
                    '$' . number_format($service['total_profit'], 2),
                    $service['avg_margin'] . '%',
                    $service['transaction_count'],
                ]);
            }
            fputcsv($file, []);

            // By Country
            fputcsv($file, ['PROFIT BY COUNTRY']);
            fputcsv($file, ['Country', 'Revenue', 'Cost', 'Profit', 'Margin %', 'Transactions']);
            foreach ($byCountry as $country) {
                fputcsv($file, [
                    $country['country_code'],
                    '$' . number_format($country['total_revenue'], 2),
                    '$' . number_format($country['total_cost'], 2),
                    '$' . number_format($country['total_profit'], 2),
                    $country['avg_margin'] . '%',
                    $country['transaction_count'],
                ]);
            }
            fputcsv($file, []);

            // Top Customers
            fputcsv($file, ['TOP PROFITABLE CUSTOMERS']);
            fputcsv($file, ['Customer', 'Email', 'Revenue', 'Cost', 'Profit', 'Transactions']);
            foreach ($topCustomers as $customer) {
                fputcsv($file, [
                    $customer['user_name'],
                    $customer['user_email'],
                    '$' . number_format($customer['total_revenue'], 2),
                    '$' . number_format($customer['total_cost'], 2),
                    '$' . number_format($customer['total_profit'], 2),
                    $customer['transaction_count'],
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Get real-time stats (AJAX endpoint).
     */
    public function realTimeStats()
    {
        return response()->json($this->profitService->getRealTimeStats());
    }

    /**
     * Get daily trend data (AJAX endpoint).
     */
    public function dailyTrend(Request $request)
    {
        $days = $request->input('days', 30);
        return response()->json($this->profitService->getDailyTrend($days));
    }

    /**
     * Get service breakdown (AJAX endpoint).
     */
    public function serviceBreakdown(Request $request)
    {
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        
        return response()->json($this->profitService->getByServiceType($startDate, $endDate));
    }
}
