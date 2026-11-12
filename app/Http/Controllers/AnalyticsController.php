<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Services\AnalyticsService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class AnalyticsController extends Controller
{
    public function __construct(
        private AnalyticsService $analyticsService
    ) {}

    /**
     * Show campaign analytics page
     */
    public function campaignAnalytics(Request $request, Campaign $campaign): InertiaResponse
    {
        $this->authorizeCampaignAccess($request->user(), $campaign);

        $analytics = $this->analyticsService->getCampaignAnalytics($campaign);

        return Inertia::render('analytics/campaign', [
            'campaign' => $campaign,
            'analytics' => $analytics,
        ]);
    }

    /**
     * Get campaign analytics data (API endpoint)
     */
    public function getCampaignAnalyticsData(Request $request, Campaign $campaign): array
    {
        $this->authorizeCampaignAccess($request->user(), $campaign);

        return $this->analyticsService->getCampaignAnalytics($campaign);
    }

    /**
     * Show user analytics dashboard
     */
    public function dashboard(Request $request): InertiaResponse
    {
        $user = $request->user();

        // Agents should see their parent's analytics data
        $dataOwnerId = ($user->isAgent() && $user->parent_user_id)
            ? $user->parent_user_id
            : $user->id;

        $analytics = $this->analyticsService->getUserAnalytics($dataOwnerId);

        $campaigns = Campaign::where('user_id', $dataOwnerId)
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        return Inertia::render('analytics/dashboard', [
            'analytics' => $analytics,
            'recentCampaigns' => $campaigns,
        ]);
    }

    /**
     * Check if user can access a campaign's data.
     * Allows: campaign owner, admin, or agent of the campaign owner.
     */
    private function authorizeCampaignAccess($user, Campaign $campaign): void
    {
        if ($user->isAdmin()) {
            return;
        }

        // Campaign owner
        if ($campaign->user_id === $user->id) {
            return;
        }

        // Agent whose parent owns the campaign
        if ($user->isAgent() && $campaign->user_id === $user->parent_user_id) {
            return;
        }

        abort(403);
    }

    /**
     * Export campaign calls to CSV
     */
    public function exportCalls(Request $request, Campaign $campaign): Response
    {
        $this->authorizeCampaignAccess($request->user(), $campaign);

        $csv = $this->analyticsService->exportCampaignCalls($campaign);

        $filename = sprintf(
            'campaign-%s-calls-%s.csv',
            $campaign->id,
            now()->format('Y-m-d-His')
        );

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * Export campaign contacts to CSV
     */
    public function exportContacts(Request $request, Campaign $campaign): Response
    {
        $this->authorizeCampaignAccess($request->user(), $campaign);

        $csv = $this->analyticsService->exportCampaignContacts($campaign);

        $filename = sprintf(
            'campaign-%s-contacts-%s.csv',
            $campaign->id,
            now()->format('Y-m-d-His')
        );

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }
}
