<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CronJobLog;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class CronMonitorService
{
    private const QUEUE_HEARTBEAT_KEY = 'cron-monitor:heartbeat:queue:work';
    private const QUEUE_START_PREFIX = 'cron-monitor:queue-start:';
    private const QUEUE_CACHE_TTL_MINUTES = 15;
    private const QUEUE_START_TTL_HOURS = 2;

    public function recordQueueHeartbeat(string $connectionName, ?string $queue = null): void
    {
        $message = trim(collect([
            'Queue worker heartbeat',
            $connectionName !== '' ? "connection={$connectionName}" : null,
            $queue ? "queue={$queue}" : null,
        ])->filter()->implode(' | '));

        Cache::put(self::QUEUE_HEARTBEAT_KEY, [
            'completed_at' => now()->toIso8601String(),
            'execution_time_ms' => 0,
            'jobs_processed' => 0,
            'output' => $message,
        ], now()->addMinutes(self::QUEUE_CACHE_TTL_MINUTES));
    }

    public function recordQueueJobStarted(Job $job): void
    {
        Cache::put(
            $this->queueStartKey($job),
            now()->toIso8601String(),
            now()->addHours(self::QUEUE_START_TTL_HOURS)
        );

        $this->recordQueueHeartbeat($job->getConnectionName(), $job->getQueue());
    }

    public function recordQueueJobFinished(Job $job, bool $successful, ?string $output = null): void
    {
        $completedAt = now();
        $startedAt = $this->pullQueueJobStartedAt($job, $completedAt);
        $executionTime = max($completedAt->diffInMilliseconds($startedAt), 0);
        $jobName = $job->resolveName();

        CronJobLog::create([
            'job_name' => 'queue:work',
            'status' => $successful ? 'completed' : 'failed',
            'output' => $output ?? $jobName,
            'jobs_processed' => $successful ? 1 : 0,
            'started_at' => $startedAt,
            'completed_at' => $completedAt,
            'execution_time_ms' => $executionTime,
        ]);

        $this->recordQueueHeartbeat($job->getConnectionName(), $job->getQueue());
    }

    public function recordScheduleHeartbeat(): void
    {
        $now = now();

        CronJobLog::create([
            'job_name' => 'schedule:run',
            'status' => 'completed',
            'output' => 'Scheduler heartbeat',
            'jobs_processed' => 0,
            'started_at' => $now,
            'completed_at' => $now,
            'execution_time_ms' => 0,
        ]);
    }

    public function getHeartbeat(string $jobName): ?array
    {
        if ($jobName !== 'queue:work') {
            return null;
        }

        $heartbeat = Cache::get(self::QUEUE_HEARTBEAT_KEY);

        if (! is_array($heartbeat) || empty($heartbeat['completed_at'])) {
            return null;
        }

        return [
            'completed_at' => Carbon::parse((string) $heartbeat['completed_at']),
            'execution_time_ms' => (int) ($heartbeat['execution_time_ms'] ?? 0),
            'jobs_processed' => (int) ($heartbeat['jobs_processed'] ?? 0),
            'output' => (string) ($heartbeat['output'] ?? ''),
        ];
    }

    public function isHeartbeatHealthy(string $jobName, int $minutesThreshold = 2): bool
    {
        $heartbeat = $this->getHeartbeat($jobName);

        if ($heartbeat === null) {
            return false;
        }

        return $heartbeat['completed_at']->greaterThan(now()->subMinutes($minutesThreshold));
    }

    public function lastRunPayload(?CronJobLog $log, ?array $heartbeat = null): ?array
    {
        $heartbeatCompletedAt = $heartbeat['completed_at'] ?? null;
        $logCompletedAt = $log?->completed_at;

        if (! $logCompletedAt && ! $heartbeatCompletedAt) {
            return null;
        }

        if ($heartbeatCompletedAt instanceof CarbonInterface
            && (! $logCompletedAt || $heartbeatCompletedAt->greaterThan($logCompletedAt))) {
            return [
                'completed_at' => $heartbeatCompletedAt->toDateTimeString(),
                'completed_at_human' => $heartbeatCompletedAt->diffForHumans(),
                'execution_time_ms' => (int) ($heartbeat['execution_time_ms'] ?? 0),
                'jobs_processed' => (int) ($heartbeat['jobs_processed'] ?? 0),
            ];
        }

        return $log ? [
            'completed_at' => $log->completed_at?->toDateTimeString(),
            'completed_at_human' => $log->completed_at?->diffForHumans(),
            'execution_time_ms' => $log->execution_time_ms ?? 0,
            'jobs_processed' => $log->jobs_processed ?? 0,
        ] : null;
    }

    private function pullQueueJobStartedAt(Job $job, CarbonInterface $fallback): CarbonInterface
    {
        $startedAt = Cache::pull($this->queueStartKey($job));

        return is_string($startedAt) ? Carbon::parse($startedAt) : $fallback;
    }

    private function queueStartKey(Job $job): string
    {
        return self::QUEUE_START_PREFIX.$job->uuid();
    }
}