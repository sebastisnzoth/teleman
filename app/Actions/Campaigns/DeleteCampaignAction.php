<?php

declare(strict_types=1);

namespace App\Actions\Campaigns;

use App\Models\Campaign;
use Illuminate\Support\Facades\DB;

class DeleteCampaignAction
{
    /**
     * Delete a campaign
     */
    public function execute(Campaign $campaign): bool
    {
        return DB::transaction(function () use ($campaign) {
            // Only allow deletion if campaign is in draft, completed, or failed status
            if (!in_array($campaign->status, ['draft', 'completed', 'failed'])) {
                throw new \Exception('Cannot delete campaign in ' . $campaign->status . ' status. Please pause it first.');
            }

            // Delete related contacts and calls (cascade will handle this if FK is set)
            return $campaign->delete();
        });
    }
}
