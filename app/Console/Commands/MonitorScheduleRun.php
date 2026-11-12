<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\CronJobLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class MonitorScheduleRun extends Command
{
    protected $signature = 'monitor:schedule';
    protected $description = 'Run scheduled tasks with monitoring';

    public function handle(): int
    {
        $log = CronJobLog::create([
            'job_name' => 'schedule:run',
            'status' => 'started',
            'started_at' => now(),
        ]);

        $startTime = microtime(true);

        try {
            // Run the actual schedule:run command
            $exitCode = Artisan::call('schedule:run');
            $output = Artisan::output();

            $endTime = microtime(true);
            $executionTime = round(($endTime - $startTime) * 1000); // Convert to milliseconds

            // Count how many tasks ran
            $jobsProcessed = substr_count($output, 'Running scheduled command');

            $log->update([
                'status' => $exitCode === 0 ? 'completed' : 'failed',
                'completed_at' => now(),
                'execution_time_ms' => $executionTime,
                'jobs_processed' => $jobsProcessed,
                'output' => $output,
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
