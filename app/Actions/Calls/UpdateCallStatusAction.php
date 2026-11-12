<?php

declare(strict_types=1);

namespace App\Actions\Calls;

use App\Actions\AiAgent\CloseAiAgentCallAction;
use App\Events\CRM\CallCompletedEvent;
use App\Events\CRM\LeadQualifiedEvent;
use App\Models\Call;
use App\Models\Transaction;
use App\Services\CallSentimentAnalyzer;
use App\Services\CreditService;
use App\Services\PricingService;
use App\Services\SequenceEngine;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UpdateCallStatusAction
{
    public function __construct(
        protected CreditService $creditService,
        protected PricingService $pricingService,
        protected CallSentimentAnalyzer $sentimentAnalyzer,
        protected SequenceEngine $sequenceEngine,
        protected CloseAiAgentCallAction $closeAiAgentCall
    ) {}

    /**
     * Update call status from webhook data
     */
    public function execute(Call $call, array $data): Call
    {
        $updateData = [
            'status' => $this->mapTwilioStatus($data['CallStatus'] ?? $call->status),
        ];

        // Update Twilio Call SID if not already set — and, for a Plivo
        // call, adopt it even though the column is already non-null. See
        // Call::shouldAdoptProviderCallId() for why the two providers
        // need different tests here (shared with
        // TwimlController::manualCall(), the other place that adopts a
        // provider's own call identifier).
        if (isset($data['CallSid']) && $call->shouldAdoptProviderCallId($call->twilio_call_sid)) {
            $updateData['twilio_call_sid'] = $data['CallSid'];
        }

        // Same identifier, into the provider-neutral column too — this is
        // the first webhook that carries a Plivo call's real CallUUID
        // (only a request identifier was known when the call was
        // created), and it lands here for both providers since
        // provider_call_sid should equal twilio_call_sid whenever a call
        // actually has a Twilio-style SID, not only for Plivo's.
        if (isset($data['CallSid']) && $call->shouldAdoptProviderCallId($call->provider_call_sid)) {
            $updateData['provider_call_sid'] = $data['CallSid'];
        }

        // Capture child PSTN leg SID: when Twilio fires statusCallback for the <Number> leg,
        // it sends ParentCallSid = browser leg SID and CallSid = PSTN leg SID.
        // We store this so the hold controller can redirect the PSTN leg directly.
        if (
            isset($data['ParentCallSid']) &&
            isset($data['CallSid']) &&
            $data['ParentCallSid'] === $call->twilio_call_sid &&
            $data['CallSid'] !== $call->twilio_call_sid
        ) {
            $metadata = $call->metadata ?? [];
            if (empty($metadata['pstn_call_sid'])) {
                $metadata['pstn_call_sid'] = $data['CallSid'];
                $updateData['metadata'] = $metadata;
                Log::info('Stored PSTN child call SID', [
                    'call_id' => $call->id,
                    'parent_sid' => $data['ParentCallSid'],
                    'pstn_call_sid' => $data['CallSid'],
                ]);
            }
        }

        // Update duration if available
        if (isset($data['CallDuration'])) {
            $updateData['duration_seconds'] = (int) $data['CallDuration'];
        }

        // Update pricing if available (actual cost from Twilio)
        $actualCost = null;
        if (isset($data['Price'])) {
            // Twilio returns negative price, convert to positive
            $actualCost = abs((float) $data['Price']);
            $updateData['price'] = $actualCost;
            $updateData['price_unit'] = $data['PriceUnit'] ?? 'USD';
        }

        // Update answered by if available
        if (isset($data['AnsweredBy'])) {
            $updateData['answered_by'] = strtolower($data['AnsweredBy']);
        }

        // Set timestamps
        if ($updateData['status'] === 'in-progress' && ! $call->started_at) {
            $updateData['started_at'] = now();
        }

        if (in_array($updateData['status'], ['completed', 'failed', 'canceled', 'busy', 'no-answer'])) {
            $updateData['ended_at'] = now();
        }

        // Capture pre-update state before DB changes (getOriginal won't work after update())
        $hadStartedAt = (bool) $call->started_at;

        // Wrap call update + billing in a transaction to ensure atomicity
        DB::transaction(function () use ($call, $updateData, $actualCost) {
            $call->update($updateData);

            // Deduct credit when call is completed with actual cost from Twilio
            if ($updateData['status'] === 'completed' && isset($updateData['duration_seconds']) && $updateData['duration_seconds'] > 0) {
                $this->deductCallCost($call, $actualCost);
            }
        });

        // Update campaign statistics if this is a campaign call
        if ($call->campaign_id && $call->campaign) {
            $this->updateCampaignStatistics($call, $updateData['status'], $hadStartedAt);
        }

        // Update campaign contact status if this is a campaign call
        if ($call->campaign_contact_id && $call->campaignContact) {
            $this->updateContactStatus($call, $updateData['status']);
        }

        // Track message variant performance if variant is assigned
        if ($call->message_variant_id && $call->messageVariant) {
            $this->trackVariantPerformance($call, $updateData['status']);
        }

        // Auto-analyze sentiment if transcript is available and call is completed
        if ($updateData['status'] === 'completed' && ! empty($call->transcript_text)) {
            $this->autoAnalyzeSentiment($call);
        }

        // Auto-enroll in follow-up sequences if applicable
        if (in_array($updateData['status'], ['completed', 'no-answer', 'busy', 'failed'])) {
            $this->autoEnrollInSequence($call);
        }

        // Dispatch CRM events for completed calls
        if ($updateData['status'] === 'completed') {
            $this->dispatchCrmEvents($call);
        }

        // Close the AI-agent conversation row this call owns, if any —
        // its own status webhook is the only other writer of those
        // fields, and no campaign call has ever pointed at it, on either
        // provider (see CloseAiAgentCallAction).
        //
        // Last, and inside its own try/catch, like every other optional
        // step here. webhookStatus() answers 200 even when this action
        // throws, so the provider never retries: an exception escaping a
        // bookkeeping write would cost the campaign its counters and
        // leave a contact stuck mid-dial for ever. Cost tracking is not
        // allowed to do that.
        $this->closeOwnedAiAgentCall($call, $updateData['status']);

        Log::info('Call status updated', [
            'call_id' => $call->id,
            'status' => $updateData['status'],
        ]);

        return $call->fresh();
    }

    /**
     * Close the AiAgentCall this call owns, never letting a failure reach
     * the caller — see the call site for what that would cost.
     */
    private function closeOwnedAiAgentCall(Call $call, string $status): void
    {
        try {
            $this->closeAiAgentCall->execute($call, $status);
        } catch (\Throwable $e) {
            Log::error('Failed to close AI agent call', [
                'call_id' => $call->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Deduct call cost from user's credit balance.
     */
    private function deductCallCost(Call $call, ?float $actualCost): void
    {
        try {
            // Idempotency: skip if we already deducted for this call
            $existingDeduction = Transaction::where('metadata->call_id', $call->id)
                ->where('type', 'debit')
                ->where('service_type', 'call')
                ->where('status', 'completed')
                ->exists();

            if ($existingDeduction) {
                Log::info('Call cost already deducted, skipping', ['call_id' => $call->id]);

                return;
            }

            $user = $call->user;

            if (! $user) {
                Log::warning('Cannot deduct call cost: User not found', ['call_id' => $call->id]);

                return;
            }

            // Extract country code from phone number
            $countryCode = $this->extractCountryCode($call->to_number);

            // Store country code in call metadata if not already set
            if (! isset($call->metadata['country_code'])) {
                $metadata = $call->metadata ?? [];
                $metadata['country_code'] = $countryCode;
                $call->update(['metadata' => $metadata]);
            }

            // Get user's tier (you can add tier field to users table or default to standard)
            $tier = $user->pricing_tier ?? 'standard';

            // Calculate estimated cost with profit margin
            $costData = $this->pricingService->calculateCallCost(
                $call->duration_seconds,
                $countryCode,
                $tier
            );

            // Use actual cost from Twilio if available, otherwise use calculated base cost
            $finalActualCost = $actualCost ?? $costData['base_cost'];
            $estimatedCharge = $costData['charge'];

            // Check if user can afford
            if (! $this->creditService->canAfford($user, $estimatedCharge)) {
                Log::error('Insufficient credits for call', [
                    'call_id' => $call->id,
                    'user_id' => $user->id,
                    'required' => $estimatedCharge,
                    'balance' => $user->credit_balance,
                ]);

                return;
            }

            // Deduct credits using profit-tracking method
            $transaction = $this->creditService->deductForCall(
                $user,
                $call,
                $finalActualCost,
                $estimatedCharge
            );

            Log::info('Call cost deducted successfully', [
                'call_id' => $call->id,
                'user_id' => $user->id,
                'charged' => $estimatedCharge,
                'actual_cost' => $finalActualCost,
                'profit' => $transaction->profit_amount,
                'margin' => $transaction->profit_percentage.'%',
                'transaction_id' => $transaction->id,
                'cost_status' => $actualCost ? 'confirmed' : 'estimated',
            ]);

            // If we only had estimated cost, log for later webhook update
            if (! $actualCost) {
                Log::info('Call cost deducted with estimated pricing - awaiting webhook for actual cost', [
                    'call_id' => $call->id,
                    'transaction_id' => $transaction->id,
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Failed to deduct call cost', [
                'call_id' => $call->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Extract ISO country code from an E.164 phone number.
     *
     * Uses a comprehensive mapping of ITU calling codes ordered by longest prefix first
     * to avoid mismatches (e.g. +441 is GB, not a collision with +44).
     */
    private function extractCountryCode(string $phoneNumber): string
    {
        // Remove everything except digits
        $cleaned = preg_replace('/[^0-9]/', '', $phoneNumber);

        if (empty($cleaned)) {
            return 'US';
        }

        // Ordered by longest prefix first to avoid partial matches.
        // Covers 50+ countries — extend as needed.
        $prefixMap = [
            // 4-digit prefixes
            '1242' => 'BS', '1246' => 'BB', '1264' => 'AI', '1268' => 'AG',
            '1284' => 'VG', '1340' => 'VI', '1345' => 'KY', '1441' => 'BM',
            '1473' => 'GD', '1649' => 'TC', '1664' => 'MS', '1670' => 'MP',
            '1671' => 'GU', '1684' => 'AS', '1758' => 'LC', '1767' => 'DM',
            '1784' => 'VC', '1809' => 'DO', '1829' => 'DO', '1849' => 'DO',
            '1868' => 'TT', '1869' => 'KN', '1876' => 'JM', '1939' => 'PR',
            // 3-digit prefixes
            '353' => 'IE', '354' => 'IS', '358' => 'FI', '370' => 'LT',
            '371' => 'LV', '372' => 'EE', '373' => 'MD', '374' => 'AM',
            '375' => 'BY', '376' => 'AD', '380' => 'UA', '381' => 'RS',
            '385' => 'HR', '386' => 'SI', '387' => 'BA', '389' => 'MK',
            '420' => 'CZ', '421' => 'SK', '852' => 'HK', '853' => 'MO',
            '855' => 'KH', '856' => 'LA', '880' => 'BD', '886' => 'TW',
            '960' => 'MV', '961' => 'LB', '962' => 'JO', '963' => 'SY',
            '964' => 'IQ', '965' => 'KW', '966' => 'SA', '967' => 'YE',
            '968' => 'OM', '971' => 'AE', '972' => 'IL', '973' => 'BH',
            '974' => 'QA', '975' => 'BT', '976' => 'MN', '977' => 'NP',
            '992' => 'TJ', '993' => 'TM', '994' => 'AZ', '995' => 'GE',
            '998' => 'UZ',
            // 2-digit prefixes
            '20' => 'EG', '27' => 'ZA', '30' => 'GR', '31' => 'NL',
            '32' => 'BE', '33' => 'FR', '34' => 'ES', '36' => 'HU',
            '39' => 'IT', '40' => 'RO', '41' => 'CH', '43' => 'AT',
            '44' => 'GB', '45' => 'DK', '46' => 'SE', '47' => 'NO',
            '48' => 'PL', '49' => 'DE', '51' => 'PE', '52' => 'MX',
            '53' => 'CU', '54' => 'AR', '55' => 'BR', '56' => 'CL',
            '57' => 'CO', '58' => 'VE', '60' => 'MY', '61' => 'AU',
            '62' => 'ID', '63' => 'PH', '64' => 'NZ', '65' => 'SG',
            '66' => 'TH', '81' => 'JP', '82' => 'KR', '84' => 'VN',
            '86' => 'CN', '90' => 'TR', '91' => 'IN', '92' => 'PK',
            '93' => 'AF', '94' => 'LK', '95' => 'MM', '98' => 'IR',
            // 1-digit prefix (NANP)
            '1' => 'US',
        ];

        // Try longest prefixes first (4, 3, 2, 1 digits)
        foreach ([4, 3, 2, 1] as $len) {
            $prefix = substr($cleaned, 0, $len);
            if (isset($prefixMap[$prefix])) {
                return $prefixMap[$prefix];
            }
        }

        // Unknown country — return empty to let pricing service use its default
        return 'US';
    }

    /**
     * Map Twilio status to our status
     */
    private function mapTwilioStatus(string $twilioStatus): string
    {
        return match ($twilioStatus) {
            'queued', 'initiated' => 'initiated',
            'ringing' => 'ringing',
            'in-progress' => 'in-progress',
            'completed' => 'completed',
            'busy' => 'busy',
            'no-answer' => 'no-answer',
            'failed' => 'failed',
            'canceled' => 'canceled',
            default => 'initiated',
        };
    }

    /**
     * Update campaign statistics based on call status.
     * Uses atomic DB increment to avoid N+1 queries and race conditions.
     */
    private function updateCampaignStatistics(Call $call, string $status, bool $hadStartedAt = false): void
    {
        $campaignId = $call->campaign_id;

        // Increment total_called when call first starts (not previously started)
        if ($status === 'in-progress' && ! $hadStartedAt) {
            DB::table('campaigns')->where('id', $campaignId)->increment('total_called');
        }

        // Increment total_answered when call is answered (human or machine)
        if ($status === 'completed' && $call->answered_by && $call->answered_by !== 'unknown') {
            DB::table('campaigns')->where('id', $campaignId)->increment('total_answered');
        }

        // Increment total_failed for failed calls
        if (in_array($status, ['failed', 'busy', 'no-answer', 'canceled'])) {
            DB::table('campaigns')->where('id', $campaignId)->increment('total_failed');
        }
    }

    /**
     * Update campaign contact status based on call status
     */
    private function updateContactStatus(Call $call, string $status): void
    {
        $contact = $call->campaignContact;

        if (! $contact) {
            return;
        }

        // Update contact status based on call outcome
        if ($status === 'completed') {
            $contact->markAsCompleted();
        } elseif (in_array($status, ['failed', 'busy', 'no-answer', 'canceled'])) {
            // Increment call_attempts BEFORE checking max retries to prevent infinite loops
            $contact->incrementAttempts();

            $maxRetries = $call->campaign->retry_attempts ?? 0;
            if ($contact->fresh()->call_attempts >= $maxRetries) {
                $contact->markAsFailed();
            } else {
                // Will be retried
                $contact->update(['status' => 'pending']);
            }
        }
    }

    /**
     * Track message variant performance based on call status
     */
    private function trackVariantPerformance(Call $call, string $status): void
    {
        $variant = $call->messageVariant;

        if (! $variant) {
            return;
        }

        try {
            // Track answered calls
            if ($status === 'in-progress' && ! $call->getOriginal('started_at')) {
                $variant->recordAnswer();
                Log::info('Variant answer tracked', [
                    'variant_id' => $variant->id,
                    'call_id' => $call->id,
                ]);
            }

            // Track completed calls
            if ($status === 'completed') {
                $variant->recordCompletion();

                // Track positive responses based on DTMF or sentiment
                if ($this->isPositiveResponse($call)) {
                    $variant->recordPositiveResponse();
                }

                Log::info('Variant completion tracked', [
                    'variant_id' => $variant->id,
                    'call_id' => $call->id,
                    'positive' => $this->isPositiveResponse($call),
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to track variant performance', [
                'variant_id' => $variant->id,
                'call_id' => $call->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Determine if the call had a positive response
     */
    private function isPositiveResponse(Call $call): bool
    {
        // Check DTMF responses (e.g., pressing 1 for interest)
        if ($call->dtmf_digits && in_array($call->dtmf_digits, ['1', '11', '111'])) {
            return true;
        }

        // Check sentiment analysis (if available)
        if ($call->sentiment === 'positive' && $call->sentiment_confidence >= 70) {
            return true;
        }

        // Check lead quality score
        if ($call->lead_quality === 'hot' || $call->lead_quality === 'warm') {
            return true;
        }

        return false;
    }

    /**
     * Auto-analyze call sentiment if transcript exists
     */
    private function autoAnalyzeSentiment(Call $call): void
    {
        try {
            // Dispatch analysis asynchronously to not block webhook
            dispatch(function () use ($call) {
                $this->sentimentAnalyzer->analyzeCall($call);
            })->afterResponse();

            Log::info('Sentiment analysis queued', [
                'call_id' => $call->id,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to queue sentiment analysis', [
                'call_id' => $call->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Auto-enroll contact in follow-up sequence based on call outcome
     */
    private function autoEnrollInSequence(Call $call): void
    {
        try {
            // Only enroll campaign calls that have a campaign contact
            if (! $call->campaign_id || ! $call->campaign_contact_id || ! $call->campaignContact) {
                return;
            }

            // Dispatch sequence enrollment asynchronously to not block webhook
            dispatch(function () use ($call) {
                $this->sequenceEngine->determineAndEnrollSequence($call);
            })->afterResponse();

            Log::info('Sequence enrollment queued', [
                'call_id' => $call->id,
                'status' => $call->status,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to queue sequence enrollment', [
                'call_id' => $call->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Dispatch CRM webhook events for completed calls
     */
    private function dispatchCrmEvents(Call $call): void
    {
        try {
            // Load necessary relationships
            $call->loadMissing(['campaign', 'campaignContact.contact']);

            // Don't dispatch if required data is missing
            if (! $call->campaign || ! $call->campaignContact || ! $call->campaignContact->contact) {
                Log::debug('Skipping CRM event dispatch: missing related data', [
                    'call_id' => $call->id,
                ]);

                return;
            }

            $contact = $call->campaignContact->contact;

            // Always dispatch call completed event
            event(new CallCompletedEvent($call, $contact, $call->campaign));

            // Dispatch lead qualified event if this is a hot lead
            if ($this->isQualifiedLead($call)) {
                event(new LeadQualifiedEvent($call, $contact, $call->campaign));

                Log::info('Lead qualified event dispatched', [
                    'call_id' => $call->id,
                    'lead_score' => $call->lead_score,
                    'lead_quality' => $call->lead_quality,
                ]);
            }

            Log::debug('CRM events dispatched', [
                'call_id' => $call->id,
                'events' => ['call_completed', $this->isQualifiedLead($call) ? 'lead_qualified' : null],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to dispatch CRM events', [
                'call_id' => $call->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Check if the call represents a qualified lead
     */
    private function isQualifiedLead(Call $call): bool
    {
        // Lead is qualified if:
        // 1. Marked as hot lead quality
        // 2. High lead score (8+)
        // 3. Positive sentiment with high confidence
        return $call->lead_quality === 'hot'
            || $call->lead_score >= 8.0
            || ($call->sentiment === 'positive' && $call->sentiment_confidence >= 0.8);
    }
}
