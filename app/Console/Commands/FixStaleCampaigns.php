<?php

namespace App\Console\Commands;

use App\Jobs\ProcessCampaignJob;
use App\Models\Campaign;
use Illuminate\Console\Command;

class FixStaleCampaigns extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'campaigns:fix-stale {--campaign= : Specific campaign ID to fix}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fix stale campaign contacts stuck in in_progress status';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $campaignId = $this->option('campaign');

        if ($campaignId) {
            $campaigns = Campaign::where('id', $campaignId)->where('status', 'running')->get();
        } else {
            $campaigns = Campaign::where('status', 'running')->get();
        }

        if ($campaigns->isEmpty()) {
            $this->info('No running campaigns found.');

            return 0;
        }

        foreach ($campaigns as $campaign) {
            $this->info("Processing campaign {$campaign->id}: {$campaign->name}");

            // Find stale contacts
            $staleContacts = $campaign->campaignContacts()
                ->where('status', 'in_progress')
                ->where('last_call_at', '<', now()->subMinutes(5))
                ->get();

            if ($staleContacts->isEmpty()) {
                $this->info('  No stale contacts found.');

                continue;
            }

            $this->info("  Found {$staleContacts->count()} stale contacts");

            foreach ($staleContacts as $contact) {
                // Check the actual call status
                $lastCall = $contact->calls()->latest()->first();

                if (! $lastCall) {
                    // No call found, reset to pending
                    $contact->update(['status' => 'pending']);
                    $this->warn("  Contact {$contact->id}: No call found, reset to pending");

                    continue;
                }

                // If call is in a final state, update contact accordingly
                if (in_array($lastCall->status, ['completed', 'busy', 'no-answer', 'failed', 'canceled'])) {
                    // Call finished but contact wasn't updated
                    $status = $lastCall->status === 'completed' ? 'completed' : 'failed';
                    $contact->update(['status' => $status]);
                    $this->info("  Contact {$contact->id}: Call {$lastCall->status}, updated to {$status}");
                } elseif (in_array($lastCall->status, ['initiated', 'ringing'])) {
                    // Call is still waiting after 5 minutes - likely failed.
                    //
                    // A connected call is deliberately not in this list.
                    // last_call_at is stamped once, when the call is
                    // placed, so "stale" here only means "dialled more
                    // than five minutes ago" — which a conversation that
                    // is still going says nothing about. Hanging one up
                    // and redialling calls the person twice, bills the
                    // customer twice, and leaves one contact counted as
                    // both failed and completed once the real call
                    // reports in. ProcessCampaignJob's copy of this sweep
                    // has always omitted it.
                    $lastCall->update(['status' => 'failed']);

                    // Retry if attempts left
                    if ($contact->call_attempts < $campaign->retry_attempts) {
                        $contact->update(['status' => 'pending']);
                        $this->info("  Contact {$contact->id}: Reset to pending for retry (attempt {$contact->call_attempts}/{$campaign->retry_attempts})");
                    } else {
                        $contact->update(['status' => 'failed']);
                        $this->warn("  Contact {$contact->id}: Marked as failed (max retries reached)");
                    }
                }
            }

            // Dispatch ProcessCampaignJob to continue processing
            ProcessCampaignJob::dispatch($campaign);
            $this->info("  Dispatched ProcessCampaignJob for campaign {$campaign->id}");
        }

        $this->info('Done!');

        return 0;
    }
}
