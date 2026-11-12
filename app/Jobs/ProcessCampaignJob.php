<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Campaign;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessCampaignJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 3600; // 1 hour
    public int $maxExceptions = 3; // Max exceptions before failing

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Campaign $campaign
    ) {
        // Use dedicated queue for campaign processing
        $this->queue = 'campaigns';
    }
    
    /**
     * Get the unique ID for the job.
     */
    public function uniqueId(): string
    {
        return 'process-campaign-' . $this->campaign->id;
    }
    
    /**
     * Get the middleware the job should pass through.
     */
    public function middleware(): array
    {
        // Rate limit: Process max 10 campaigns per minute
        return [
            new \Illuminate\Queue\Middleware\RateLimited('process-campaigns'),
        ];
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Check if campaign is still in running status
        $this->campaign->refresh();

        if ($this->campaign->status !== 'running') {
            Log::info("Campaign {$this->campaign->id} is not running, stopping job");
            return;
        }

        Log::info("Processing campaign: {$this->campaign->id}");

        // First, check for stale in_progress contacts (calls that may have failed to update)
        $this->checkStaleInProgressContacts();

        // Get pending contacts with optimized query (use index)
        $pendingContacts = $this->campaign->campaignContacts()
            ->where('status', 'pending')
            ->orderBy('id') // Use primary key for faster ordering
            ->limit($this->campaign->max_concurrent_calls * 10) // Fetch batch
            ->get(['id', 'campaign_id', 'phone_number', 'call_attempts', 'status']);

        if ($pendingContacts->isEmpty()) {
            // Check if all contacts are completed
            $this->checkCampaignCompletion();
            return;
        }

        // Dispatch individual call jobs with concurrency control (use index)
        $activeCallsCount = $this->campaign->calls()
            ->whereIn('status', ['initiated', 'ringing', 'in-progress'])
            ->where('call_type', 'campaign') // Use index
            ->count();

        $slotsAvailable = $this->campaign->max_concurrent_calls - $activeCallsCount;

        if ($slotsAvailable <= 0) {
            // No slots available, re-queue this job to try later
            Log::info("No slots available for campaign {$this->campaign->id}, re-queuing");
            ProcessCampaignJob::dispatch($this->campaign)
                ->delay(now()->addSeconds(10))
                ->onQueue('campaigns');
            return;
        }

        // Dispatch call jobs for available slots
        $contactsToCall = $pendingContacts->take($slotsAvailable);

        foreach ($contactsToCall as $contact) {
            // Mark contact as queued (bulk update would be better but this ensures atomicity)
            $contact->update(['status' => 'queued']);

            // Dispatch call job to dedicated queue
            MakeCampaignCallJob::dispatch($this->campaign, $contact)
                ->onQueue('campaign-calls');
        }

        // Re-queue this job to continue processing
        if ($pendingContacts->count() > $slotsAvailable) {
            ProcessCampaignJob::dispatch($this->campaign)->delay(now()->addSeconds(5));
        }
    }

    /**
     * Check for stale in_progress contacts
     */
    private function checkStaleInProgressContacts(): void
    {
        // Find contacts stuck in in_progress for more than 5 minutes
        $staleContacts = $this->campaign->campaignContacts()
            ->where('status', 'in_progress')
            ->where('last_call_at', '<', now()->subMinutes(5))
            ->get();

        foreach ($staleContacts as $contact) {
            // Check the actual call status
            $lastCall = $contact->calls()->latest()->first();
            
            if (!$lastCall) {
                // No call found, reset to pending
                $contact->update(['status' => 'pending']);
                Log::warning("Contact {$contact->id} had no call, reset to pending");
                continue;
            }

            // If call is in a final state, update contact accordingly
            if (in_array($lastCall->status, ['completed', 'busy', 'no-answer', 'failed', 'canceled'])) {
                // Call finished but contact wasn't updated
                $status = $lastCall->status === 'completed' ? 'completed' : 'failed';
                $contact->update(['status' => $status]);
                Log::info("Updated stale contact {$contact->id} from in_progress to {$status}");
            } elseif (in_array($lastCall->status, ['initiated', 'ringing'])) {
                // Call is still waiting after 5 minutes - likely failed
                $lastCall->update(['status' => 'failed']);
                
                // Retry if attempts left
                if ($contact->call_attempts < $this->campaign->retry_attempts) {
                    $contact->update(['status' => 'pending']);
                    Log::info("Reset stale contact {$contact->id} to pending for retry");
                } else {
                    $contact->update(['status' => 'failed']);
                    Log::info("Marked stale contact {$contact->id} as failed (max retries)");
                }
            }
        }
    }

    /**
     * Check if campaign is completed
     */
    private function checkCampaignCompletion(): void
    {
        $pendingCount = $this->campaign->campaignContacts()
            ->whereIn('status', ['pending', 'queued'])
            ->count();

        $inProgressCount = $this->campaign->calls()
            ->whereIn('status', ['initiated', 'ringing', 'in-progress'])
            ->count();

        if ($pendingCount === 0 && $inProgressCount === 0) {
            // Campaign completed
            $this->campaign->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);

            Log::info("Campaign {$this->campaign->id} completed");

            // Dispatch completion event
            event(new \App\Events\CampaignCompletedEvent($this->campaign));
        } else {
            // Still processing, re-queue
            ProcessCampaignJob::dispatch($this->campaign)->delay(now()->addSeconds(10));
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("ProcessCampaignJob failed for campaign {$this->campaign->id}: {$exception->getMessage()}");

        $this->campaign->update([
            'status' => 'failed',
        ]);
    }
}
