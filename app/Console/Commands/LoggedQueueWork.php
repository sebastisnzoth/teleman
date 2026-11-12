<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\CronJobLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Queue;

class LoggedQueueWork extends Command
{
    protected $signature = 'queue:work-logged {--queue=default} {--max-jobs=100}';
    protected $description = 'Process queue jobs with logging for monitoring';

    public function handle(): int
    {
        $log = CronJobLog::create([
            'job_name' => 'queue:work',
            'status' => 'started',
            'started_at' => now(),
        ]);

        $startTime = microtime(true);
        $jobsProcessed = 0;
        $maxJobs = (int) $this->option('max-jobs');

        try {
            // Get queue size before processing
            $queueName = $this->option('queue');
            
            // Process jobs one at a time for better tracking
            while ($jobsProcessed < $maxJobs) {
                // Try to process one job
                $exitCode = \Illuminate\Support\Facades\Artisan::call('queue:work', [
                    '--once' => true,
                    '--queue' => $queueName,
                    '--tries' => 3,
                    '--timeout' => 60,
                ]);

                $output = \Illuminate\Support\Facades\Artisan::output();

                // Check if a job was processed
                if (str_contains($output, 'Processing:') || str_contains($output, 'Processed:')) {
                    $jobsProcessed++;
                    $this->info("Processed job {$jobsProcessed}/{$maxJobs}");
                } else {
                    // No more jobs in queue
                    $this->info('No more jobs in queue');
                    break;
                }

                // Small delay to prevent overwhelming
                usleep(100000); // 0.1 second
            }

            $endTime = microtime(true);
            $executionTime = round(($endTime - $startTime) * 1000);

            $log->update([
                'status' => 'completed',
                'completed_at' => now(),
                'execution_time_ms' => $executionTime,
                'jobs_processed' => $jobsProcessed,
                'output' => "Processed {$jobsProcessed} jobs successfully",
            ]);

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $endTime = microtime(true);
            $executionTime = round(($endTime - $startTime) * 1000);

            $log->update([
                'status' => 'failed',
                'completed_at' => now(),
                'execution_time_ms' => $executionTime,
                'jobs_processed' => $jobsProcessed,
                'output' => $e->getMessage(),
            ]);

            $this->error("Queue processing failed: {$e->getMessage()}");

            return Command::FAILURE;
        }
    }
}
