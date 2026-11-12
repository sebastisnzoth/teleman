<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Campaigns\LaunchCampaignAction;
use App\Models\Campaign;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessScheduledCampaigns extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'campaigns:process-scheduled';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process and launch campaigns that are scheduled to start';

    /**
     * Execute the console command.
     */
    public function handle(LaunchCampaignAction $launchAction): int
    {
        // Find all campaigns scheduled to start now or in the past
        $campaigns = Campaign::where('status', 'scheduled')
            ->where('scheduled_at', '<=', now())
            ->get();

        if ($campaigns->isEmpty()) {
            $this->info('No scheduled campaigns found.');
            return self::SUCCESS;
        }

        $this->info("Found {$campaigns->count()} scheduled campaign(s) to process.");

        $launched = 0;
        $failed = 0;

        foreach ($campaigns as $campaign) {
            try {
                $this->info("Launching campaign {$campaign->id}: {$campaign->name}");
                
                $launchAction->execute($campaign);
                
                $launched++;
                $this->info("✓ Campaign {$campaign->id} launched successfully");
                
                Log::info("Scheduled campaign launched", [
                    'campaign_id' => $campaign->id,
                    'campaign_name' => $campaign->name,
                    'scheduled_at' => $campaign->scheduled_at,
                ]);
                
            } catch (\Exception $e) {
                $failed++;
                $this->error("✗ Failed to launch campaign {$campaign->id}: {$e->getMessage()}");
                
                Log::error("Failed to launch scheduled campaign", [
                    'campaign_id' => $campaign->id,
                    'campaign_name' => $campaign->name,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info("\nSummary:");
        $this->info("Launched: {$launched}");
        if ($failed > 0) {
            $this->warn("Failed: {$failed}");
        }

        return self::SUCCESS;
    }
}
