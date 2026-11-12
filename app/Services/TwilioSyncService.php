<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Call;
use App\Models\TwilioCredential;
use Illuminate\Support\Facades\Log;
use Twilio\Rest\Client;

class TwilioSyncService
{
    private ?Client $client = null;

    /**
     * Initialize Twilio client for user
     */
    private function initClient(int $userId): bool
    {
        $credential = TwilioCredential::where('user_id', $userId)->first();

        if (!$credential || !$credential->account_sid || !$credential->auth_token) {
            Log::warning('Twilio credentials not found', ['user_id' => $userId]);
            return false;
        }

        $this->client = new Client($credential->account_sid, $credential->auth_token);
        return true;
    }

    /**
     * Sync a specific call with Twilio
     */
    public function syncCall(Call $call): bool
    {
        if (!$call->twilio_call_sid) {
            Log::warning('Cannot sync call without Twilio SID', ['call_id' => $call->id]);
            return false;
        }

        // Skip calls from previous Twilio accounts
        if ($call->legacy_account_sid) {
            return false;
        }

        if (!$this->initClient($call->user_id)) {
            return false;
        }

        try {
            $twilioCall = $this->client->calls($call->twilio_call_sid)->fetch();

            $updateData = [
                'status' => $this->mapTwilioStatus($twilioCall->status),
                'direction' => $twilioCall->direction,
                'from_number' => $twilioCall->from,
                'to_number' => $twilioCall->to,
                'duration_seconds' => $twilioCall->duration ? (int) $twilioCall->duration : null,
                'price' => $twilioCall->price ? (float) $twilioCall->price : null,
                'price_unit' => $twilioCall->priceUnit,
                'answered_by' => $twilioCall->answeredBy,
            ];

            // Set timestamps based on Twilio data
            if ($twilioCall->startTime) {
                $updateData['started_at'] = $twilioCall->startTime;
            }

            if ($twilioCall->endTime) {
                $updateData['ended_at'] = $twilioCall->endTime;
            }

            $call->update($updateData);

            Log::info('Call synced with Twilio', [
                'call_id' => $call->id,
                'twilio_sid' => $call->twilio_call_sid,
                'status' => $updateData['status'],
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to sync call with Twilio', [
                'call_id' => $call->id,
                'twilio_sid' => $call->twilio_call_sid,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Sync recording data for a call
     */
    public function syncRecordings(Call $call): bool
    {
        if (!$call->twilio_call_sid) {
            return false;
        }

        if (!$this->initClient($call->user_id)) {
            return false;
        }

        try {
            $recordings = $this->client->recordings->read([
                'callSid' => $call->twilio_call_sid,
            ]);

            if (count($recordings) > 0) {
                $recording = $recordings[0];
                
                $call->update([
                    'recording_sid' => $recording->sid,
                    'recording_url' => 'https://api.twilio.com' . $recording->uri,
                    'recording_duration' => $recording->duration,
                ]);

                Log::info('Recording synced', [
                    'call_id' => $call->id,
                    'recording_sid' => $recording->sid,
                ]);

                return true;
            }

            return false;
        } catch (\Exception $e) {
            Log::error('Failed to sync recording', [
                'call_id' => $call->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Sync all active calls for a user
     */
    public function syncActiveCalls(int $userId): int
    {
        $activeCalls = Call::where('user_id', $userId)
            ->whereNull('legacy_account_sid')
            ->whereIn('status', ['initiated', 'ringing', 'in-progress'])
            ->get();

        $synced = 0;

        foreach ($activeCalls as $call) {
            if ($this->syncCall($call)) {
                $synced++;
            }
        }

        return $synced;
    }

    /**
     * Sync recent calls from Twilio
     * This fetches calls from Twilio that might not be in our database
     */
    public function syncRecentCalls(int $userId, int $limit = 20): int
    {
        if (!$this->initClient($userId)) {
            return 0;
        }

        try {
            $twilioCredential = TwilioCredential::where('user_id', $userId)->first();
            
            $calls = $this->client->calls->read([
                'limit' => $limit,
            ]);

            $synced = 0;

            foreach ($calls as $twilioCall) {
                // Check if call exists in database
                $existingCall = Call::where('twilio_call_sid', $twilioCall->sid)->first();

                if (!$existingCall) {
                    // Create new call record
                    Call::create([
                        'user_id' => $userId,
                        'twilio_call_sid' => $twilioCall->sid,
                        'call_type' => 'manual', // Default to manual
                        'direction' => $twilioCall->direction,
                        'from_number' => $twilioCall->from,
                        'to_number' => $twilioCall->to,
                        'status' => $this->mapTwilioStatus($twilioCall->status),
                        'duration_seconds' => $twilioCall->duration,
                        'price' => $twilioCall->price ? (float) $twilioCall->price : null,
                        'price_unit' => $twilioCall->priceUnit,
                        'answered_by' => $twilioCall->answeredBy,
                        'started_at' => $twilioCall->startTime,
                        'ended_at' => $twilioCall->endTime,
                    ]);

                    $synced++;
                } else {
                    // Update existing call
                    $this->syncCall($existingCall);
                }
            }

            Log::info('Recent calls synced', [
                'user_id' => $userId,
                'synced' => $synced,
            ]);

            return $synced;
        } catch (\Exception $e) {
            Log::error('Failed to sync recent calls', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            return 0;
        }
    }

    /**
     * Map Twilio status to our internal status
     */
    private function mapTwilioStatus(string $twilioStatus): string
    {
        return match (strtolower($twilioStatus)) {
            'queued' => 'initiated',
            'ringing' => 'ringing',
            'in-progress' => 'in-progress',
            'completed' => 'completed',
            'busy' => 'busy',
            'failed' => 'failed',
            'no-answer' => 'no-answer',
            'canceled' => 'canceled',
            default => strtolower($twilioStatus),
        };
    }

    /**
     * Verify Twilio credentials are working
     */
    public function verifyCredentials(int $userId): bool
    {
        if (!$this->initClient($userId)) {
            return false;
        }

        try {
            // Try to fetch account details
            $account = $this->client->api->v2010->accounts($this->client->getAccountSid())->fetch();
            return $account->status === 'active';
        } catch (\Exception $e) {
            Log::error('Twilio credentials verification failed', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
