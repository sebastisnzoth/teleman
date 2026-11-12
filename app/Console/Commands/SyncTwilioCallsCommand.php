<?php

namespace App\Console\Commands;

use App\Models\AiAgentCall;
use App\Models\TwilioGlobalConfig;
use App\Services\TwilioService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncTwilioCallsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'twilio:sync-calls
                            {--call-sid= : Sync a specific call by Twilio SID}
                            {--status= : Only sync calls with specific status (in-progress, completed, etc.)}
                            {--recent : Only sync calls from last 24 hours}
                            {--all : Sync all calls (use with caution)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync call data from Twilio to update false information in the database

Examples:
  php artisan twilio:sync-calls                          # Sync in-progress and recent calls (last 6 hours)
  php artisan twilio:sync-calls --recent                 # Sync calls from last 24 hours
  php artisan twilio:sync-calls --status=in-progress     # Sync all in-progress calls
  php artisan twilio:sync-calls --call-sid=CA123...      # Sync a specific call by Twilio SID
  php artisan twilio:sync-calls --all                    # Sync all calls (with confirmation)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting Twilio call data sync...');

        $query = AiAgentCall::query()->whereNotNull('call_sid');

        // Filter by specific call SID
        if ($callSid = $this->option('call-sid')) {
            $query->where('call_sid', $callSid);
            $this->info("Syncing specific call: {$callSid}");
        }
        // Filter by status
        elseif ($status = $this->option('status')) {
            $query->where('status', $status);
            $this->info("Syncing calls with status: {$status}");
        }
        // Filter by recent calls (last 24 hours)
        elseif ($this->option('recent')) {
            $query->where('created_at', '>=', now()->subDay());
            $this->info('Syncing calls from last 24 hours...');
        }
        // Sync all calls
        elseif ($this->option('all')) {
            if (!$this->confirm('Are you sure you want to sync ALL calls? This may take a while.')) {
                $this->info('Sync cancelled.');
                return 0;
            }
            $this->info('Syncing all calls...');
        }
        // Default: sync in-progress and recent calls
        else {
            $query->where(function ($q) {
                $q->where('status', 'in-progress')
                    ->orWhere('status', 'initiated')
                    ->orWhere('status', 'ringing')
                    ->orWhere('created_at', '>=', now()->subHours(6));
            });
            $this->info('Syncing in-progress and recent calls (last 6 hours)...');
        }

        $calls = $query->get();
        $totalCalls = $calls->count();

        if ($totalCalls === 0) {
            $this->warn('No calls found to sync.');
            return 0;
        }

        $this->info("Found {$totalCalls} calls to sync.");

        $progressBar = $this->output->createProgressBar($totalCalls);
        $progressBar->start();

        $synced = 0;
        $failed = 0;
        $skipped = 0;

        foreach ($calls as $call) {
            try {
                $globalConfig = TwilioGlobalConfig::active();
                if (!$globalConfig) {
                    $this->newLine();
                    $this->error('No active Twilio configuration found. Please configure Twilio in Settings.');
                    return self::FAILURE;
                }
                
                // Initialize Twilio service with global credentials
                $twilioService = new TwilioService();
                $credential = new \App\Models\TwilioCredential([
                    'account_sid' => $globalConfig->account_sid,
                    'auth_token' => $globalConfig->getDecryptedAuthToken(),
                ]);
                $twilioService->initialize($credential);

                // Fetch call details from Twilio
                $twilioData = $twilioService->fetchCallDetails($call->call_sid);

                if (!$twilioData) {
                    $this->newLine();
                    $this->warn("Call ID {$call->id} (SID: {$call->call_sid}): Could not fetch from Twilio. Skipping...");
                    $skipped++;
                    $progressBar->advance();
                    continue;
                }

                // Update call with Twilio data
                $oldStatus = $call->status;
                $oldDuration = $call->duration;

                // Map Twilio status to our internal status
                $status = $this->mapTwilioStatus($twilioData['status']);

                $call->update([
                    'status' => $status,
                    'duration_seconds' => $twilioData['duration'] ?? $call->duration_seconds,
                    'cost_estimate' => $twilioData['price'] ? abs((float) $twilioData['price']) : $call->cost_estimate,
                    'ended_at' => $twilioData['end_time'] ?? $call->ended_at,
                    'recording_url' => $twilioData['recording_url'] ?? $call->recording_url,
                ]);

                $synced++;

                // Log significant changes
                if ($oldStatus !== $status || ($oldDuration != $twilioData['duration'] && $twilioData['duration'])) {
                    $this->newLine();
                    $this->info("Call ID {$call->id}: Updated status [{$oldStatus} → {$status}], duration [{$oldDuration} → {$twilioData['duration']}]");
                }

                $progressBar->advance();
            } catch (\Exception $e) {
                $failed++;
                $this->newLine();
                $this->error("Call ID {$call->id}: Failed to sync - {$e->getMessage()}");
                Log::error("Twilio sync failed for call {$call->id}", [
                    'error' => $e->getMessage(),
                    'call_sid' => $call->call_sid,
                ]);
                $progressBar->advance();
            }
        }

        $progressBar->finish();
        $this->newLine(2);

        // Display summary
        $this->info('=== Sync Summary ===');
        $this->info("Total calls processed: {$totalCalls}");
        $this->info("Successfully synced: {$synced}");
        
        if ($skipped > 0) {
            $this->warn("Skipped: {$skipped}");
        }
        
        if ($failed > 0) {
            $this->error("Failed: {$failed}");
        }

        $this->newLine();
        $this->info('Twilio call sync completed!');

        return 0;
    }

    /**
     * Map Twilio call status to internal status
     */
    private function mapTwilioStatus(string $twilioStatus): string
    {
        return match (strtolower($twilioStatus)) {
            'queued', 'initiated' => 'initiated',
            'ringing' => 'ringing',
            'in-progress', 'answered' => 'in-progress',
            'completed' => 'completed',
            'busy', 'failed', 'no-answer', 'canceled' => 'failed',
            default => $twilioStatus,
        };
    }
}
