<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\CronJobLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class MonitorQueueWork extends Command
{
    protected $signature = 'monitor:queue {--queue=} {--timeout=60} {--tries=3}';
    protected $description = 'Run queue worker with monitoring (single iteration)';

    public function handle(): int
    {
        $log = CronJobLog::create([
            'job_name' => 'queue:work',
            'status' => 'started',
            'started_at' => now(),
        ]);

        $startTime = microtime(true);

        try {
            // Build queue:work command with options
            $queueCommand = 'queue:work';
            $options = [
                '--once' => true, // Process one job and exit (for monitoring)
                '--tries' => $this->option('tries') ?: 3,
                '--timeout' => $this->option('timeout') ?: 60,
            ];

            if ($this->option('queue')) {
                $options['--queue'] = $this->option('queue');
            }

            // Run the actual queue:work command
            $exitCode = Artisan::call($queueCommand, $options);
            $output = Artisan::output();

            $endTime = microtime(true);
            $executionTime = round(($endTime - $startTime) * 1000); // Convert to milliseconds

            // Parse output to count processed jobs
            $jobsProcessed = 0;
            if (str_contains($output, 'Processing:') || str_contains($output, 'Processed:')) {
                $jobsProcessed = 1;
            }

            $log->update([
                'status' => $exitCode === 0 ? 'completed' : 'failed',
                'completed_at' => now(),
                'execution_time_ms' => $executionTime,
                'jobs_processed' => $jobsProcessed,
                'output' => trim($output),
            ]);

            return $exitCode;
        } catch (\Exception $e) {
            $endTime = microtime(true);
            $executionTime = round(($endTime - $startTime) * 1000);

            $log->update([
                'status' => 'failed',
                'completed_at' => now(),
                'execution_time_ms' => $executionTime,
                'output' => $e->getMessage(),
            ]);

            return Command::FAILURE;
        }
    }
}
