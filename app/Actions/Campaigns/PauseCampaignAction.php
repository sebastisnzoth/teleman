<?php

declare(strict_types=1);

namespace App\Actions\Campaigns;

use App\Models\Campaign;
use Illuminate\Support\Facades\DB;

class PauseCampaignAction
{
    /**
     * Pause a running campaign
     */
    public function execute(Campaign $campaign): Campaign
    {
        return DB::transaction(function () use ($campaign) {
            if ($campaign->status !== 'running') {
                throw new \Exception('Only running campaigns can be paused');
            }

            $campaign->update([
                'status' => 'paused',
            ]);

            // Jobs will automatically stop processing when they check campaign status
            // The ProcessCampaignJob and MakeCampaignCallJob both check if campaign is 'running'

            return $campaign->fresh();
        });
    }
}
