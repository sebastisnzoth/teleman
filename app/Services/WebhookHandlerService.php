<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Call;
use App\Models\CallLog;
use App\Models\DtmfResponse;
use App\Events\CRM\DtmfResponseEvent;
use Illuminate\Support\Facades\Log;

class WebhookHandlerService
{
    /**
     * Handle call status webhook from Twilio
     */
    public function handleCallStatus(array $data): void
    {
        $callSid = $data['CallSid'] ?? null;
        $status = $data['CallStatus'] ?? null;

        if (!$callSid || !$status) {
            Log::warning('Invalid call status webhook data', $data);
            return;
        }

        $call = Call::where('twilio_call_sid', $callSid)->first();

        if (!$call) {
            Log::warning('Call not found for webhook', ['call_sid' => $callSid]);
            return;
        }

        // Map Twilio status to our status
        $mappedStatus = $this->mapTwilioStatus($status);

        // Update call
        $call->update([
            'status' => $mappedStatus,
            'duration_seconds' => $data['CallDuration'] ?? null,
            'answered_by' => $data['AnsweredBy'] ?? null,
            'price' => $data['Price'] ?? null,
            'price_unit' => $data['PriceUnit'] ?? null,
        ]);

        // Update timestamps
        if ($mappedStatus === 'in-progress' && !$call->started_at) {
            $call->update(['started_at' => now()]);
        }

        if (in_array($mappedStatus, ['completed', 'failed', 'canceled', 'busy', 'no-answer'])) {
            $call->update(['ended_at' => now()]);
        }

        // Log the event
        CallLog::create([
            'call_id' => $call->id,
            'event_type' => $this->getEventType($mappedStatus),
            'event_data' => $data,
            'event_timestamp' => now(),
        ]);

        // Update campaign statistics if this is a campaign call
        if ($call->campaign_id) {
            $this->updateCampaignStats($call);
        }

        Log::info('Call status updated', [
            'call_id' => $call->id,
            'status' => $mappedStatus,
        ]);
    }

    /**
     * Handle recording webhook from Twilio
     */
    public function handleRecording(array $data): void
    {
        $callSid = $data['CallSid'] ?? null;
        $recordingSid = $data['RecordingSid'] ?? null;
        $recordingUrl = $data['RecordingUrl'] ?? null;
        $recordingDuration = $data['RecordingDuration'] ?? null;

        if (!$callSid || !$recordingSid) {
            Log::warning('Invalid recording webhook data', $data);
            return;
        }

        $call = Call::where('twilio_call_sid', $callSid)->first();

        if (!$call) {
            Log::warning('Call not found for recording webhook', ['call_sid' => $callSid]);
            return;
        }

        // Update call with recording info
        $call->update([
            'recording_sid' => $recordingSid,
            'recording_url' => $recordingUrl,
            'recording_duration' => $recordingDuration,
        ]);

        // Log the event
        CallLog::create([
            'call_id' => $call->id,
            'event_type' => 'recording_started',
            'event_data' => $data,
            'event_timestamp' => now(),
        ]);

        Log::info('Recording saved', [
            'call_id' => $call->id,
            'recording_sid' => $recordingSid,
        ]);
    }

    /**
     * Handle DTMF webhook from Twilio
     */
    public function handleDtmf(array $data): void
    {
        $callSid = $data['CallSid'] ?? null;
        $digits = $data['Digits'] ?? null;

        if (!$callSid || !$digits) {
            Log::warning('Invalid DTMF webhook data', $data);
            return;
        }

        $call = Call::where('twilio_call_sid', $callSid)->first();

        if (!$call) {
            Log::warning('Call not found for DTMF webhook', ['call_sid' => $callSid]);
            return;
        }

        // Save each digit
        for ($i = 0; $i < strlen($digits); $i++) {
            DtmfResponse::create([
                'call_id' => $call->id,
                'digit' => $digits[$i],
                'pressed_at' => now(),
            ]);
        }

        // Update call with concatenated digits
        $existingDigits = $call->dtmf_digits ?? '';
        $call->update([
            'dtmf_digits' => $existingDigits . $digits,
        ]);

        // Log the event
        CallLog::create([
            'call_id' => $call->id,
            'event_type' => 'dtmf_received',
            'event_data' => $data,
            'event_timestamp' => now(),
        ]);

        // Dispatch CRM DTMF event if applicable
        $this->dispatchDtmfEvent($call, $digits);

        Log::info('DTMF digits captured', [
            'call_id' => $call->id,
            'digits' => $digits,
        ]);
    }

    /**
     * Map Twilio call status to our status
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
     * Get event type from status
     */
    private function getEventType(string $status): string
    {
        return match ($status) {
            'initiated' => 'initiated',
            'ringing' => 'ringing',
            'in-progress' => 'answered',
            'completed', 'failed', 'canceled', 'busy', 'no-answer' => 'completed',
            default => 'initiated',
        };
    }

    /**
     * Update campaign statistics
     */
    private function updateCampaignStats(Call $call): void
    {
        $campaign = $call->campaign;

        if (!$campaign) {
            return;
        }

        // Increment called count
        if ($call->status === 'in-progress' && $call->wasChanged('status')) {
            $campaign->incrementCalled();
        }

        // Increment answered count
        if ($call->status === 'completed' && $call->wasAnswered()) {
            $campaign->incrementAnswered();
        }

        // Increment failed count
        if (in_array($call->status, ['failed', 'busy', 'no-answer'])) {
            $campaign->incrementFailed();
        }

        // Update campaign contact status
        if ($call->campaign_contact_id) {
            $contact = $call->campaignContact;
            
            if ($call->status === 'completed') {
                $contact->markAsCompleted();
            } elseif (in_array($call->status, ['failed', 'busy', 'no-answer'])) {
                $contact->markAsFailed();
            }
        }
    }

    /**
     * Dispatch DTMF event to CRM integrations
     */
    private function dispatchDtmfEvent(Call $call, string $digits): void
    {
        try {
            $call->loadMissing(['campaign', 'campaignContact.contact']);

            if (!$call->campaign || !$call->campaignContact || !$call->campaignContact->contact) {
                return;
            }

            event(new DtmfResponseEvent(
                $call,
                $call->campaignContact->contact,
                $call->campaign,
                $digits
            ));
        } catch (\Exception $e) {
            Log::error('Failed to dispatch DTMF CRM event', [
                'call_id' => $call->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
