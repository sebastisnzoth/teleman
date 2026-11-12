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

class ProcessSequenceStepsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;
    public int $tries = 1;

    /**
     * Execute the job - Find and execute all due sequence steps
     */
    public function handle(SequenceEngine $sequenceEngine): void
    {
        try {
            // Find all enrollments that are due for execution
            $dueEnrollments = SequenceEnrollment::dueForExecution()
                ->with(['sequence', 'contact', 'campaignContact', 'campaign'])
                ->limit(100) // Process 100 at a time to avoid overload
                ->get();

            Log::info('Processing sequence steps', [
                'due_count' => $dueEnrollments->count(),
            ]);

            foreach ($dueEnrollments as $enrollment) {
                // Dispatch individual step execution job
                ExecuteSequenceStepJob::dispatch($enrollment)
                    ->onQueue('default');
            }

            // If there are more enrollments to process, reschedule this job
            if ($dueEnrollments->count() >= 100) {
                self::dispatch()
                    ->delay(now()->addSeconds(10))
                    ->onQueue('default');
            }
        } catch (\Exception $e) {
            Log::error('Failed to process sequence steps', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
