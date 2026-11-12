<?php

namespace App\Jobs;

use App\Models\UserSipTrunk;
use App\Services\TrunkAutoSetupService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SetupSipTrunkJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300; // 5 minutes max
    public $tries = 1; // Don't retry — let user manually retry via UI

    /**
     * Create a new job instance.
     */
    public function __construct(
        public UserSipTrunk $trunk
    ) {}

    /**
     * Execute the job.
     */
    public function handle(TrunkAutoSetupService $setupService): void
    {
        Log::info("SetupSipTrunkJob started", ['trunk_id' => $this->trunk->id]);

        $setupService->runSetup($this->trunk);
    }

    /**
     * Handle a job failure.
     */
    public function failed(?\Throwable $exception): void
    {
        Log::error("SetupSipTrunkJob failed", [
            'trunk_id' => $this->trunk->id,
            'error' => $exception?->getMessage(),
        ]);

        $this->trunk->update([
            'status' => 'error',
            'last_error' => 'Setup job failed: ' . ($exception?->getMessage() ?? 'Unknown error'),
        ]);
    }
}
