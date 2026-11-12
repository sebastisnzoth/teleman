<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Call;
use App\Models\Campaign;
use App\Models\Contact;
use App\Models\NumberRequest;
use App\Models\PhoneNumber;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Display the dashboard with role-based data
     */
    public function index(Request $request): Response
    {
        $user = Auth::user();

        // Get role-based statistics
        $stats = $this->getStats($user);

        // Get recent activity
        $recentCampaigns = $this->getRecentCampaigns($user);
        $recentCalls = $this->getRecentCalls($user);

        // Get chart data
        $callsChart = $this->getCallsChartData($user);
        $campaignsChart = $this->getCampaignsChartData($user);

        return Inertia::render('Dashboard/Index', [
            'stats' => $stats,
            'recentCampaigns' => $recentCampaigns,
            'recentCalls' => $recentCalls,
            'callsChart' => $callsChart,
            'campaignsChart' => $campaignsChart,
        ]);
    }

    /**
     * Get statistics based on user role
     */
    private function getStats($user): array
    {
        $query = $this->buildBaseQuery($user);

        if ($user->isAdmin()) {
            // Admin sees system-wide stats
            return [
                'total_users' => User::count(),
                'total_customers' => User::whereHas('roles', fn ($q) => $q->where('slug', 'customer'))->count(),
                'total_agents' => User::whereHas('roles', fn ($q) => $q->where('slug', 'agent'))->count(),
                'total_campaigns' => Campaign::count(),
                'active_campaigns' => Campaign::where('status', 'active')->count(),
                'total_contacts' => Contact::count(),
                'total_calls' => Call::count(),
                'calls_today' => Call::whereDate('created_at', today())->count(),
                'calls_this_month' => Call::whereMonth('created_at', now()->month)
                    ->whereYear('created_at', now()->year)
                    ->count(),
                // Phone number stats
                'total_phone_numbers' => PhoneNumber::count(),
                'purchased_phone_numbers' => PhoneNumber::provisioned()->count(),
                'available_phone_numbers' => PhoneNumber::available()->count(),
                'assigned_phone_numbers' => PhoneNumber::assigned()->count(),
                'pending_requests' => NumberRequest::pending()->count(),
                'total_phone_cost' => number_format((float) PhoneNumber::provisioned()->sum('monthly_cost'), 2),
            ];
        } elseif ($user->isCustomer()) {
            // Customer sees their own stats
            return [
                'total_agents' => User::where('parent_user_id', $user->id)->count(),
                'total_campaigns' => Campaign::where('user_id', $user->id)->count(),
                'active_campaigns' => Campaign::where('user_id', $user->id)
                    ->where('status', 'active')
                    ->count(),
                'total_contacts' => Contact::where('user_id', $user->id)->count(),
                'active_contacts' => Contact::where('user_id', $user->id)
                    ->where('status', 'active')
                    ->count(),
                'total_calls' => Call::where('user_id', $user->id)->count(),
                'calls_today' => Call::where('user_id', $user->id)
                    ->whereDate('created_at', today())
                    ->count(),
                'calls_this_week' => Call::where('user_id', $user->id)
                    ->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])
                    ->count(),
                'calls_this_month' => Call::where('user_id', $user->id)
                    ->whereMonth('created_at', now()->month)
                    ->whereYear('created_at', now()->year)
                    ->count(),
                // Phone number stats
                'my_phone_numbers' => PhoneNumber::where('user_id', $user->id)->count(),
                'my_pending_requests' => NumberRequest::where('customer_id', $user->id)
                    ->pending()
                    ->count(),
            ];
        } else {
            // Agent sees parent's campaigns/contacts but their own call stats
            $parentId = $user->parent_user_id;

            return [
                'total_campaigns' => Campaign::where('user_id', $parentId)->count(),
                'active_campaigns' => Campaign::where('user_id', $parentId)
                    ->where('status', 'active')
                    ->count(),
                'total_contacts' => Contact::where('user_id', $parentId)->count(),
                'my_calls_today' => Call::where('user_id', $user->id)
                    ->whereDate('created_at', today())
                    ->count(),
                'my_calls_this_week' => Call::where('user_id', $user->id)
                    ->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])
                    ->count(),
            ];
        }
    }

    /**
     * Get recent campaigns based on user role
     */
    private function getRecentCampaigns($user)
    {
        $query = Campaign::query();

        if ($user->isAdmin()) {
            // Admin sees all campaigns
            $query->with('user:id,name');
        } elseif ($user->isCustomer()) {
            // Customer sees their campaigns
            $query->where('user_id', $user->id);
        } else {
            // Agent sees parent's campaigns
            $query->where('user_id', $user->parent_user_id);
        }

        return $query->latest()
            ->limit(5)
            ->get()
            ->map(function ($campaign) use ($user) {
                return [
                    'id' => $campaign->id,
                    'name' => $campaign->name,
                    'status' => $campaign->status,
                    'total_contacts' => $campaign->total_contacts,
                    'contacted' => $campaign->contacted_count,
                    'created_at' => $campaign->created_at->toISOString(),
                    'owner' => $user->isAdmin() ? $campaign->user?->name : null,
                ];
            });
    }

    /**
     * Get recent calls based on user role
     */
    private function getRecentCalls($user)
    {
        $query = Call::query();

        if ($user->isAdmin()) {
            // Admin sees all calls
            $query->with('user:id,name');
        } elseif ($user->isCustomer()) {
            // Customer sees their calls
            $query->where('user_id', $user->id);
        } else {
            // Agent sees parent's calls
            $query->where('user_id', $user->parent_user_id);
        }

        return $query->latest()
            ->limit(10)
            ->get()
            ->map(function ($call) use ($user) {
                return [
                    'id' => $call->id,
                    'to_number' => $call->to_number,
                    'status' => $call->status,
                    'duration_seconds' => $call->duration_seconds,
                    'created_at' => $call->created_at->toISOString(),
                    'owner' => $user->isAdmin() ? $call->user?->name : null,
                ];
            });
    }

    /**
     * Get calls chart data (last 7 days)
     */
    private function getCallsChartData($user): array
    {
        $query = Call::query();

        if ($user->isAdmin()) {
            // Admin sees all calls
        } elseif ($user->isCustomer()) {
            $query->where('user_id', $user->id);
        } else {
            $query->where('user_id', $user->parent_user_id);
        }

        $data = $query->whereBetween('created_at', [now()->subDays(6)->startOfDay(), now()->endOfDay()])
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as completed'),
                DB::raw('SUM(CASE WHEN status IN ("failed", "busy", "no-answer") THEN 1 ELSE 0 END) as failed')
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Fill in missing dates
        $result = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $dayData = $data->firstWhere('date', $date);

            $result[] = [
                'date' => $date,
                'label' => now()->subDays($i)->format('M d'),
                'total' => $dayData->total ?? 0,
                'completed' => $dayData->completed ?? 0,
                'failed' => $dayData->failed ?? 0,
            ];
        }

        return $result;
    }

    /**
     * Get campaigns chart data (by status)
     */
    private function getCampaignsChartData($user): array
    {
        $query = Campaign::query();

        if ($user->isAdmin()) {
            // Admin sees all campaigns
        } elseif ($user->isCustomer()) {
            $query->where('user_id', $user->id);
        } else {
            $query->where('user_id', $user->parent_user_id);
        }

        $data = $query->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->get()
            ->pluck('count', 'status')
            ->toArray();

        return [
            'draft' => $data['draft'] ?? 0,
            'active' => $data['active'] ?? 0,
            'paused' => $data['paused'] ?? 0,
            'completed' => $data['completed'] ?? 0,
        ];
    }

    /**
     * Build base query based on user role
     */
    private function buildBaseQuery($user)
    {
        if ($user->isAdmin()) {
            return null; // Admin has no query restrictions
        } elseif ($user->isCustomer()) {
            return ['user_id' => $user->id];
        } else {
            return ['user_id' => $user->parent_user_id];
        }
    }
}
