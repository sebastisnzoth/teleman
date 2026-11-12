<?php

namespace App\Services;

use App\Jobs\MakeCampaignCallJob;
use App\Models\Call;
use App\Models\CampaignContact;
use App\Models\Contact;
use App\Models\Sequence;
use App\Models\SequenceEnrollment;
use App\Models\SequenceStep;
use App\Models\SequenceStepExecution;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SequenceEngine
{
    protected CallSchedulingOptimizer $schedulingOptimizer;

    protected CallSentimentAnalyzer $sentimentAnalyzer;

    public function __construct(
        CallSchedulingOptimizer $schedulingOptimizer,
        CallSentimentAnalyzer $sentimentAnalyzer
    ) {
        $this->schedulingOptimizer = $schedulingOptimizer;
        $this->sentimentAnalyzer = $sentimentAnalyzer;
    }

    /**
     * Determine and enroll a contact in appropriate sequences based on call outcome
     */
    public function determineAndEnrollSequence(Call $call): void
    {
        try {
            $triggerType = $this->determineTriggerType($call);

            if (! $triggerType) {
                return;
            }

            $campaignContact = $call->campaignContact;
            if (! $campaignContact) {
                Log::warning('Call has no campaign contact', ['call_id' => $call->id]);

                return;
            }

            $contact = $campaignContact->contact;
            if (! $contact) {
                Log::warning('Campaign contact has no contact', ['campaign_contact_id' => $campaignContact->id]);

                return;
            }

            // Find matching sequences
            $sequences = $this->findMatchingSequences($call, $triggerType);

            foreach ($sequences as $sequence) {
                $this->enrollContactInSequence($contact, $campaignContact, $sequence, [
                    'trigger_call_id' => $call->id,
                    'trigger_type' => $triggerType,
                    'trigger_status' => $call->status,
                    'trigger_sentiment' => $call->sentiment,
                    'trigger_lead_quality' => $call->lead_quality,
                    'enrolled_by' => 'auto',
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to determine and enroll sequence', [
                'call_id' => $call->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Determine trigger type based on call outcome and sentiment
     */
    protected function determineTriggerType(Call $call): ?string
    {
        // Check for no answer
        if (in_array($call->status, ['no-answer', 'busy', 'failed'])) {
            return 'no_answer';
        }

        // Check for completed call with sentiment analysis
        if ($call->status === 'completed') {
            // Check DTMF responses for callback request
            if ($call->dtmfResponses()->where('digit', '1')->exists()) {
                return 'callback_requested';
            }

            // Use sentiment analysis
            if ($call->sentiment) {
                if ($call->sentiment === 'positive' || $call->lead_quality === 'hot') {
                    return 'interested';
                } elseif ($call->sentiment === 'negative') {
                    return 'not_interested';
                }
            }

            // Default to completed if no specific sentiment
            return 'completed';
        }

        return null;
    }

    /**
     * Find sequences that match the call's trigger conditions
     */
    protected function findMatchingSequences(Call $call, string $triggerType): array
    {
        $campaignContact = $call->campaignContact;
        $campaign = $campaignContact->campaign;

        $sequences = Sequence::active()
            ->forUser($campaign->user_id)
            ->forTriggerType($triggerType)
            ->hasCapacity()
            ->byPriority()
            ->get();

        return $sequences->filter(function ($sequence) use ($call, $campaignContact) {
            // Check if already enrolled
            $existing = SequenceEnrollment::where('sequence_id', $sequence->id)
                ->where('campaign_contact_id', $campaignContact->id)
                ->exists();

            if ($existing) {
                return false;
            }

            // Evaluate trigger conditions
            return $this->evaluateTriggerConditions($sequence, $call);
        })->all();
    }

    /**
     * Evaluate if call meets sequence trigger conditions
     */
    protected function evaluateTriggerConditions(Sequence $sequence, Call $call): bool
    {
        if (empty($sequence->trigger_conditions)) {
            return true;
        }

        foreach ($sequence->trigger_conditions as $condition) {
            $field = $condition['field'] ?? null;
            $operator = $condition['operator'] ?? null;
            $value = $condition['value'] ?? null;

            if (! $field || ! $operator) {
                continue;
            }

            // Get field value from call
            $fieldValue = match ($field) {
                'sentiment' => $call->sentiment,
                'lead_quality' => $call->lead_quality,
                'duration' => $call->duration,
                'call_attempts' => $call->campaignContact->call_attempts,
                'campaign_id' => $call->campaign_id,
                default => null,
            };

            $passes = match ($operator) {
                'equals' => $fieldValue == $value,
                'not_equals' => $fieldValue != $value,
                'greater_than' => $fieldValue > $value,
                'less_than' => $fieldValue < $value,
                'greater_than_or_equal' => $fieldValue >= $value,
                'less_than_or_equal' => $fieldValue <= $value,
                'in' => in_array($fieldValue, (array) $value),
                'not_in' => ! in_array($fieldValue, (array) $value),
                default => true,
            };

            if (! $passes) {
                return false;
            }
        }

        return true;
    }

    /**
     * Enroll a contact in a sequence
     */
    public function enrollContactInSequence(
        Contact $contact,
        CampaignContact $campaignContact,
        Sequence $sequence,
        array $metadata = []
    ): ?SequenceEnrollment {
        try {
            DB::beginTransaction();

            $enrollment = $sequence->enroll($contact, $campaignContact, $metadata);

            if (! $enrollment) {
                DB::rollBack();

                return null;
            }

            // Schedule first step
            $this->scheduleNextStep($enrollment);

            DB::commit();

            Log::info('Contact enrolled in sequence', [
                'contact_id' => $contact->id,
                'sequence_id' => $sequence->id,
                'enrollment_id' => $enrollment->id,
            ]);

            return $enrollment;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to enroll contact in sequence', [
                'contact_id' => $contact->id,
                'sequence_id' => $sequence->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Schedule the next step for an enrollment
     */
    public function scheduleNextStep(SequenceEnrollment $enrollment): void
    {
        $nextStep = $enrollment->getNextStep();

        if (! $nextStep) {
            $enrollment->complete();

            return;
        }

        // Check if step can be executed
        if (! $nextStep->canExecute($enrollment)) {
            Log::info('Skipping step due to conditions', [
                'enrollment_id' => $enrollment->id,
                'step_id' => $nextStep->id,
            ]);
            $enrollment->moveToNextStep(0); // Try next step immediately

            return;
        }

        // Calculate next execution time
        $nextExecutionTime = $this->calculateNextExecutionTime($enrollment, $nextStep);

        $enrollment->current_step = $nextStep->step_number;
        $enrollment->next_step_at = $nextExecutionTime;
        $enrollment->save();

        Log::info('Scheduled next step', [
            'enrollment_id' => $enrollment->id,
            'step_number' => $nextStep->step_number,
            'scheduled_at' => $nextExecutionTime,
        ]);
    }

    /**
     * Calculate the next execution time for a step
     */
    protected function calculateNextExecutionTime(SequenceEnrollment $enrollment, SequenceStep $step): \DateTime
    {
        $baseTime = now();

        // If using smart timing and action is a call, get optimal time
        if ($enrollment->sequence->use_smart_timing && $step->action_type === 'call') {
            try {
                $contact = $enrollment->contact;
                $campaign = $enrollment->campaign;

                $optimalTime = $this->schedulingOptimizer->getOptimalCallTime($contact, $campaign);

                if ($optimalTime) {
                    // Add the step delay to the optimal time
                    $delayMinutes = $step->getDelayInMinutes();

                    return $optimalTime->addMinutes($delayMinutes);
                }
            } catch (\Exception $e) {
                Log::warning('Failed to get optimal call time, using default delay', [
                    'enrollment_id' => $enrollment->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Use fixed delay
        return $baseTime->addMinutes($step->getDelayInMinutes());
    }

    /**
     * Execute a sequence step
     */
    public function executeStep(SequenceEnrollment $enrollment, SequenceStep $step): bool
    {
        try {
            DB::beginTransaction();

            // Create execution record
            $execution = SequenceStepExecution::create([
                'enrollment_id' => $enrollment->id,
                'step_id' => $step->id,
                'status' => 'executing',
                'scheduled_at' => $enrollment->next_step_at,
                'executed_at' => now(),
            ]);

            // Execute based on action type
            $result = match ($step->action_type) {
                'call' => $this->executeCallAction($enrollment, $step, $execution),
                'sms' => $this->executeSmsAction($enrollment, $step, $execution),
                'email' => $this->executeEmailAction($enrollment, $step, $execution),
                'wait' => $this->executeWaitAction($enrollment, $step, $execution),
                'webhook' => $this->executeWebhookAction($enrollment, $step, $execution),
                default => throw new \Exception("Unknown action type: {$step->action_type}"),
            };

            // Check if sequence should stop
            if ($step->shouldStop($enrollment, $result)) {
                $enrollment->stop('Step condition met');
                $execution->markAsCompleted($result);
                DB::commit();

                return true;
            }

            // Record successful execution
            $enrollment->recordExecution($step, 'completed', $result);
            $execution->markAsCompleted($result);

            // Move to next step
            $enrollment->moveToNextStep();

            // Check stop conditions
            if ($this->shouldStopSequence($enrollment)) {
                $enrollment->complete();
            } else {
                $this->scheduleNextStep($enrollment);
            }

            DB::commit();

            Log::info('Step executed successfully', [
                'enrollment_id' => $enrollment->id,
                'step_id' => $step->id,
                'result' => $result,
            ]);

            return true;
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to execute step', [
                'enrollment_id' => $enrollment->id,
                'step_id' => $step->id,
                'error' => $e->getMessage(),
            ]);

            if (isset($execution)) {
                $execution->markAsFailed($e->getMessage());
            }

            return false;
        }
    }

    /**
     * Execute a call action
     */
    protected function executeCallAction(SequenceEnrollment $enrollment, SequenceStep $step, SequenceStepExecution $execution): string
    {
        $campaignContact = $enrollment->campaignContact;
        $campaign = $enrollment->campaign;

        // Get call configuration from step
        $config = $step->action_config ?? [];
        $audioFileId = $config['audio_file_id'] ?? $campaign->audio_file_id;
        $maxDuration = $config['max_duration'] ?? $campaign->max_call_duration;

        // Dispatch call job
        MakeCampaignCallJob::dispatch($campaignContact, $audioFileId, $maxDuration)
            ->onQueue('campaign-calls');

        return 'call_queued';
    }

    /**
     * Execute an SMS action
     */
    protected function executeSmsAction(SequenceEnrollment $enrollment, SequenceStep $step, SequenceStepExecution $execution): string
    {
        $config = $step->action_config ?? [];
        $message = $config['message'] ?? 'Follow-up message from your telemarketing campaign.';

        // TODO: Implement SMS sending via Twilio
        Log::info('SMS action placeholder', [
            'enrollment_id' => $enrollment->id,
            'message' => $message,
        ]);

        return 'sms_sent';
    }

    /**
     * Execute an email action
     */
    protected function executeEmailAction(SequenceEnrollment $enrollment, SequenceStep $step, SequenceStepExecution $execution): string
    {
        $config = $step->action_config ?? [];
        $subject = $config['subject'] ?? 'Follow-up from your call';
        $body = $config['body'] ?? 'Thank you for your time.';

        // TODO: Implement email sending
        Log::info('Email action placeholder', [
            'enrollment_id' => $enrollment->id,
            'subject' => $subject,
        ]);

        return 'email_sent';
    }

    /**
     * Execute a wait action
     */
    protected function executeWaitAction(SequenceEnrollment $enrollment, SequenceStep $step, SequenceStepExecution $execution): string
    {
        // Wait action just schedules the next step
        return 'wait_completed';
    }

    /**
     * Execute a webhook action
     */
    protected function executeWebhookAction(SequenceEnrollment $enrollment, SequenceStep $step, SequenceStepExecution $execution): string
    {
        $config = $step->action_config ?? [];
        $url = $config['url'] ?? null;
        $method = $config['method'] ?? 'POST';
        $payload = $config['payload'] ?? [];

        if (! $url) {
            throw new \Exception('Webhook URL not configured');
        }

        // TODO: Implement webhook trigger
        Log::info('Webhook action placeholder', [
            'enrollment_id' => $enrollment->id,
            'url' => $url,
            'method' => $method,
        ]);

        return 'webhook_triggered';
    }

    /**
     * Check if sequence should stop based on stop conditions
     */
    protected function shouldStopSequence(SequenceEnrollment $enrollment): bool
    {
        $stopConditions = $enrollment->sequence->stop_conditions ?? [];

        if (empty($stopConditions)) {
            return false;
        }

        foreach ($stopConditions as $condition) {
            $type = $condition['type'] ?? null;
            $value = $condition['value'] ?? null;

            $shouldStop = match ($type) {
                'max_steps' => $enrollment->steps_completed >= $value,
                'contact_responded' => ! empty($enrollment->metadata['responded']),
                'contact_converted' => $enrollment->status === 'converted',
                'campaign_completed' => $enrollment->campaign->status === 'completed',
                default => false,
            };

            if ($shouldStop) {
                return true;
            }
        }

        return false;
    }

    /**
     * Mark enrollment as converted (successful outcome)
     */
    public function markAsConverted(SequenceEnrollment $enrollment, ?string $reason = null): void
    {
        $enrollment->complete(true);

        if ($reason) {
            $enrollment->updateMetadata(['conversion_reason' => $reason]);
        }

        Log::info('Enrollment marked as converted', [
            'enrollment_id' => $enrollment->id,
            'sequence_id' => $enrollment->sequence_id,
        ]);
    }

    /**
     * Get analytics for a sequence
     */
    public function getSequenceAnalytics(Sequence $sequence): array
    {
        // A fresh query per count. Reusing one builder stacked the where()
        // clauses, so after 'active' every remaining status counted rows that
        // had to be active AND completed AND converted — always zero.
        $countByStatus = fn (string $status): int => $sequence->enrollments()
            ->where('status', $status)
            ->count();

        return [
            'total_enrolled' => $sequence->enrollments()->count(),
            'active' => $countByStatus('active'),
            'completed' => $countByStatus('completed'),
            'converted' => $countByStatus('converted'),
            'stopped' => $countByStatus('stopped'),
            'conversion_rate' => $sequence->conversion_rate,
            'success_rate' => $sequence->getSuccessRate(),
            'avg_completion_time' => $sequence->getAverageCompletionTime(),
            'steps_performance' => $this->getStepsPerformance($sequence),
        ];
    }

    /**
     * Get performance metrics for each step
     */
    protected function getStepsPerformance(Sequence $sequence): array
    {
        return $sequence->steps->map(function ($step) {
            return [
                'step_number' => $step->step_number,
                'step_name' => $step->step_name,
                'action_type' => $step->action_type,
                'total_executed' => $step->total_executed,
                'total_successful' => $step->total_successful,
                'success_rate' => $step->success_rate,
            ];
        })->toArray();
    }
}
