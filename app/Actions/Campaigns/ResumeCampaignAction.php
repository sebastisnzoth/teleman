<?php

declare(strict_types=1);

namespace App\Actions\Campaigns;

use App\Models\Campaign;
use Illuminate\Support\Facades\DB;

class ResumeCampaignAction
{
    /**
     * Resume a paused campaign
     */
    public function execute(Campaign $campaign): Campaign
    {
        return DB::transaction(function () use ($campaign) {
            if ($campaign->status !== 'paused') {
                throw new \Exception('Only paused campaigns can be resumed');
            }

            $campaign->update([
                'status' => 'running',
            ]);

            // Resume processing campaign queue jobs
            \App\Jobs\ProcessCampaignJob::dispatch($campaign);

            return $campaign->fresh();
        });
    }
}
