<?php

namespace App\Services;

use App\Models\UserSipTrunk;
use App\Models\TrunkPhoneNumber;
use Twilio\Rest\Client as TwilioClient;
use Illuminate\Support\Facades\Log;
use Exception;

class TrunkCallService
{
    /**
     * Make outbound call through SIP trunk
     * 
     * @param UserSipTrunk $trunk
     * @param string $from Phone number (from trunk)
     * @param string $to Destination number
     * @param string $twimlUrl TwiML webhook URL
     * @param array $options Additional call options
     * @return object Twilio Call object
     */
    public function makeCall(
        UserSipTrunk $trunk,
        string $from,
        string $to,
        string $twimlUrl,
        array $options = []
    ): object {
        if (!$trunk->isOperational()) {
            throw new Exception('SIP Trunk is not operational. Status: ' . $trunk->status);
        }

        // Verify the 'from' number belongs to this trunk
        $fromNumber = $trunk->phoneNumbers()
            ->where('phone_number', $from)
            ->where('status', 'active')
            ->first();

        if (!$fromNumber) {
            throw new Exception("Phone number {$from} is not assigned to this trunk or is inactive");
        }

        try {
            $client = $trunk->getTwilioClient();

            // Prepare call parameters
            $callParams = array_merge([
                'url' => $twimlUrl,
                'method' => 'POST',
                'callerId' => $from,  // Essential for SIP trunk calls
                'statusCallback' => route('sip.trunk.status', ['trunk' => $trunk->id]),
                'statusCallbackEvent' => ['initiated', 'ringing', 'answered', 'completed'],
                'statusCallbackMethod' => 'POST',
                'timeout' => 60,
                'record' => $trunk->recording_enabled,
            ], $options);

            // Make the call
            $call = $client->calls->create(
                $to,    // To
                $from,  // From (will route through trunk automatically)
                $callParams
            );

            Log::info("Call initiated through SIP trunk", [
                'trunk_id' => $trunk->id,
                'call_sid' => $call->sid,
                'from' => $from,
                'to' => $to,
            ]);

            return $call;

        } catch (Exception $e) {
            Log::error("Failed to make call through trunk", [
                'trunk_id' => $trunk->id,
                'from' => $from,
                'to' => $to,
                'error' => $e->getMessage(),
            ]);

            throw new Exception('Failed to make call: ' . $e->getMessage());
        }
    }

    /**
     * Make call using user's trunk (auto-select)
     * 
     * @param int $userId
     * @param string $from
     * @param string $to
     * @param string $twimlUrl
     * @param array $options
     * @return object|null
     */
    public function makeCallForUser(
        int $userId,
        string $from,
        string $to,
        string $twimlUrl,
        array $options = []
    ): ?object {
        $trunk = UserSipTrunk::where('user_id', $userId)
            ->where('status', 'active')
            ->where('health_status', 'healthy')
            ->first();

        if (!$trunk) {
            return null; // No trunk available, caller should use fallback
        }

        return $this->makeCall($trunk, $from, $to, $twimlUrl, $options);
    }

    /**
     * Record call completion and update statistics
     * 
     * @param UserSipTrunk $trunk
     * @param string $callSid
     * @param int $duration Duration in seconds
     * @param float|null $price
     * @return void
     */
    public function recordCallCompletion(
        UserSipTrunk $trunk,
        string $callSid,
        int $duration,
        ?float $price = null
    ): void {
        try {
            // Update trunk statistics
            $trunk->recordCall($duration);

            Log::info("Call completed through trunk", [
                'trunk_id' => $trunk->id,
                'call_sid' => $callSid,
                'duration' => $duration,
                'price' => $price,
            ]);

        } catch (Exception $e) {
            Log::error("Failed to record call completion", [
                'trunk_id' => $trunk->id,
                'call_sid' => $callSid,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get call details from Twilio
     * 
     * @param UserSipTrunk $trunk
     * @param string $callSid
     * @return object
     */
    public function getCallDetails(UserSipTrunk $trunk, string $callSid): object
    {
        try {
            $client = $trunk->getTwilioClient();
            return $client->calls($callSid)->fetch();

        } catch (Exception $e) {
            throw new Exception('Failed to fetch call details: ' . $e->getMessage());
        }
    }

    /**
     * Terminate an active call
     * 
     * @param UserSipTrunk $trunk
     * @param string $callSid
     * @return bool
     */
    public function terminateCall(UserSipTrunk $trunk, string $callSid): bool
    {
        try {
            $client = $trunk->getTwilioClient();
            
            $call = $client->calls($callSid)->update([
                'status' => 'completed'
            ]);

            Log::info("Call terminated", [
                'trunk_id' => $trunk->id,
                'call_sid' => $callSid,
            ]);

            return true;

        } catch (Exception $e) {
            Log::error("Failed to terminate call", [
                'trunk_id' => $trunk->id,
                'call_sid' => $callSid,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Get trunk statistics
     * 
     * @param UserSipTrunk $trunk
     * @return array
     */
    public function getTrunkStatistics(UserSipTrunk $trunk): array
    {
        $totalCalls = $trunk->total_calls_count ?? 0;
        $totalMinutes = (float)($trunk->total_minutes_used ?? 0);
        
        return [
            'total_calls' => $totalCalls,
            'total_minutes' => $totalMinutes,
            'average_call_duration' => $totalCalls > 0 ? round($totalMinutes / $totalCalls, 2) : 0,
            'last_call_at' => $trunk->last_call_at?->toDateTimeString(),
            'active_numbers' => $trunk->phoneNumbers()->where('status', 'active')->count(),
            'assigned_numbers' => $trunk->phoneNumbers()
                ->where('status', 'active')
                ->where('assigned_to', '!=', 'unassigned')
                ->count(),
            'concurrent_limit' => $trunk->concurrent_calls_limit ?? 0,
            'health_status' => $trunk->health_status ?? 'unknown',
            'last_health_check' => $trunk->last_health_check_at?->toDateTimeString(),
        ];
    }
}
