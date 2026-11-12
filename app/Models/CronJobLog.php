<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CronJobLog extends Model
{
    protected $fillable = [
        'job_name',
        'status',
        'output',
        'jobs_processed',
        'started_at',
        'completed_at',
        'execution_time_ms',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'jobs_processed' => 'integer',
        'execution_time_ms' => 'integer',
    ];

    /**
     * Get the last successful run for a job
     */
    public static function getLastSuccessfulRun(string $jobName): ?self
    {
        return self::where('job_name', $jobName)
            ->where('status', 'completed')
            ->latest('completed_at')
            ->first();
    }

    /**
     * Get recent failures for a job
     */
    public static function getRecentFailures(string $jobName, int $limit = 5): \Illuminate\Database\Eloquent\Collection
    {
        return self::where('job_name', $jobName)
            ->where('status', 'failed')
            ->latest('started_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Check if job is healthy (ran in last X minutes)
     */
    public static function isHealthy(string $jobName, int $minutesThreshold = 2): bool
    {
        $lastRun = self::where('job_name', $jobName)
            ->where('status', 'completed')
            ->latest('completed_at')
            ->first();

        if (!$lastRun) {
            return false;
        }

        return $lastRun->completed_at->greaterThan(now()->subMinutes($minutesThreshold));
    }

    /**
     * Get job statistics
     */
    public static function getStats(string $jobName, int $hours = 24): array
    {
        $since = now()->subHours($hours);

        $total = self::where('job_name', $jobName)
            ->where('started_at', '>=', $since)
            ->count();

        $completed = self::where('job_name', $jobName)
            ->where('status', 'completed')
            ->where('started_at', '>=', $since)
            ->count();

        $failed = self::where('job_name', $jobName)
            ->where('status', 'failed')
            ->where('started_at', '>=', $since)
            ->count();

        $avgExecutionTime = self::where('job_name', $jobName)
            ->where('status', 'completed')
            ->where('started_at', '>=', $since)
            ->avg('execution_time_ms');

        $totalJobsProcessed = self::where('job_name', $jobName)
            ->where('status', 'completed')
            ->where('started_at', '>=', $since)
            ->sum('jobs_processed');

        return [
            'total_runs' => $total,
            'completed' => $completed,
            'failed' => $failed,
            'success_rate' => $total > 0 ? round(($completed / $total) * 100, 2) : 0,
            'avg_execution_time_ms' => $avgExecutionTime ? (int) round((float) $avgExecutionTime) : 0,
            'total_jobs_processed' => (int) ($totalJobsProcessed ?? 0),
        ];
    }

    /**
     * Clean up old logs (keep last 7 days)
     */
    public static function cleanup(int $daysToKeep = 7): int
    {
        return self::where('created_at', '<', now()->subDays($daysToKeep))->delete();
    }
}
