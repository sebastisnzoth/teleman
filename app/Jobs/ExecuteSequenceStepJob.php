<?php

namespace App\Jobs;

use App\Models\SequenceEnrollment;
use App\Services\SequenceEngine;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ExecuteSequenceStepJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;
    public int $tries = 3;
    public int $backoff = 60;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public SequenceEnrollment $enrollment
    ) {
        //
    }

    /**
     * Execute the job - Execute a single sequence step
     */
    public function handle(SequenceEngine $sequenceEngine): void
    {
        try {
            // Refresh enrollment to get latest state
            $this->enrollment->refresh();

            // Verify enrollment is still active
            if ($this->enrollment->status !== 'active') {
                Log::info('Skipping execution - enrollment not active', [
                    'enrollment_id' => $this->enrollment->id,
                    'status' => $this->enrollment->status,
                ]);
                return;
            }

            // Verify step is due
            if ($this->enrollment->next_step_at && $this->enrollment->next_step_at->isFuture()) {
                Log::info('Skipping execution - step not yet due', [
                    'enrollment_id' => $this->enrollment->id,
                    'next_step_at' => $this->enrollment->next_step_at,
                ]);
                return;
            }

            // Get current step
            $step = $this->enrollment->getCurrentStep();
            if (!$step) {
                // If no current step, schedule the first step
                $sequenceEngine->scheduleNextStep($this->enrollment);
                return;
            }

            // Execute the step
            $success = $sequenceEngine->executeStep($this->enrollment, $step);

            if (!$success) {
                Log::warning('Step execution failed', [
                    'enrollment_id' => $this->enrollment->id,
                    'step_id' => $step->id,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to execute sequence step job', [
                'enrollment_id' => $this->enrollment->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e; // Re-throw to trigger retry
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('ExecuteSequenceStepJob permanently failed', [
            'enrollment_id' => $this->enrollment->id,
            'error' => $exception->getMessage(),
        ]);

        // Optionally: Mark enrollment as stopped or send notification
    }
}
