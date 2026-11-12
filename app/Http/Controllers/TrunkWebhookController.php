<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\MapsAiVoice;
use App\Models\User;
use App\Models\UserSipTrunk;
use App\Models\Call;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Twilio\TwiML\VoiceResponse;

class TrunkWebhookController extends Controller
{
    use MapsAiVoice;
    /**
     * Handle inbound voice calls from SIP trunk
     * 
     * URL: /sip/trunk/voice/{webhook_token}
     */
    public function handleInboundCall(Request $request, string $webhookToken)
    {
        try {
            // Find user by webhook token
            $user = User::where('webhook_token', $webhookToken)->first();

            if (!$user) {
                Log::error('Invalid webhook token for inbound call', [
                    'token' => $webhookToken,
                    'from' => $request->input('From'),
                    'to' => $request->input('To'),
                ]);

                return $this->rejectCall('Invalid configuration');
            }

            // Get user's active trunk
            $trunk = $user->getActiveSipTrunk();

            if (!$trunk || !$trunk->isOperational()) {
                Log::error('No operational trunk for inbound call', [
                    'user_id' => $user->id,
                    'from' => $request->input('From'),
                    'to' => $request->input('To'),
                ]);

                return $this->rejectCall('Service unavailable');
            }

            // Get call details from Twilio
            $callSid = $request->input('CallSid');
            $from = $request->input('From');
            $to = $request->input('To');
            $callStatus = $request->input('CallStatus');

            Log::info('Inbound call received via SIP trunk', [
                'user_id' => $user->id,
                'trunk_id' => $trunk->id,
                'call_sid' => $callSid,
                'from' => $from,
                'to' => $to,
                'status' => $callStatus,
            ]);

            // Find the phone number assignment
            $trunkNumber = $trunk->phoneNumbers()
                ->where('phone_number', $to)
                ->first();

            if (!$trunkNumber) {
                Log::warning('Inbound call to unknown number on trunk', [
                    'trunk_id' => $trunk->id,
                    'to' => $to,
                ]);

                // Number not in database - forward to trunk owner as fallback
                return $this->forwardToSoftphone($user, $from, $to, $callSid);
            }

            // Route based on assignment
            switch ($trunkNumber->assigned_to) {
                case 'softphone':
                    // Route ONLY to the specific assigned user
                    if ($trunkNumber->user_id) {
                        $assignedUser = User::find($trunkNumber->user_id);
                        if ($assignedUser) {
                            return $this->forwardToSpecificUser($assignedUser, $from, $to, $callSid);
                        }
                    }
                    // Fallback to trunk owner if assigned user not found
                    return $this->forwardToSoftphone($user, $from, $to, $callSid);

                case 'campaign':
                    return $this->handleCampaignInbound($trunkNumber->assigned_id, $from, $to, $callSid);

                case 'ai_agent':
                    return $this->handleAiAgentInbound($trunkNumber->assigned_id, $from, $to, $callSid);

                case 'unassigned':
                default:
                    // Unassigned numbers: forward to trunk owner
                    Log::info('Inbound call to unassigned number, routing to trunk owner', [
                        'trunk_id' => $trunk->id,
                        'to' => $to,
                        'trunk_owner_id' => $user->id,
                    ]);
                    return $this->forwardToSoftphone($user, $from, $to, $callSid);
            }
        } catch (\Exception $e) {
            Log::error('Error handling inbound call', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->rejectCall('System error');
        }
    }

    /**
     * Handle call status callbacks
     * 
     * URL: /sip/trunk/call-status/{trunk}
     */
    public function handleStatusCallback(Request $request, UserSipTrunk $trunk)
    {
        try {
            $callSid = $request->input('CallSid');
            $callStatus = $request->input('CallStatus');
            $callDuration = $request->input('CallDuration', 0);
            $price = $request->input('Price');
            $priceUnit = $request->input('PriceUnit');

            Log::info('Call status callback received', [
                'trunk_id' => $trunk->id,
                'call_sid' => $callSid,
                'status' => $callStatus,
                'duration' => $callDuration,
            ]);

            // Update call record if exists (Call model uses twilio_call_sid)
            $call = Call::where('twilio_call_sid', $callSid)->first();
            if ($call) {
                $updateData = [
                    'status' => $callStatus,
                    'duration_seconds' => (int) $callDuration,
                ];
                if ($price !== null && $price !== '') {
                    $updateData['price'] = abs((float) $price);
                    $updateData['price_unit'] = $priceUnit ?? 'USD';
                }
                $call->update($updateData);
            }

            // Record completed calls in trunk statistics
            if ($callStatus === 'completed' && $callDuration > 0) {
                $trunk->recordCall($callDuration);

                Log::info('Call completed and recorded', [
                    'trunk_id' => $trunk->id,
                    'call_sid' => $callSid,
                    'duration' => $callDuration,
                ]);
            }

            return response('OK', 200);
        } catch (\Exception $e) {
            Log::error('Error handling status callback', [
                'trunk_id' => $trunk->id,
                'error' => $e->getMessage(),
            ]);

            return response('Error', 500);
        }
    }

    /**
     * Handle disaster recovery / failover
     * 
     * URL: /sip/trunk/disaster-recovery
     */
    public function handleDisasterRecovery(Request $request)
    {
        try {
            $callSid = $request->input('CallSid');
            $from = $request->input('From');
            $to = $request->input('To');

            Log::warning('Disaster recovery triggered', [
                'call_sid' => $callSid,
                'from' => $from,
                'to' => $to,
            ]);

            // Try to find user by phone number
            // This is a fallback mechanism
            $response = new VoiceResponse();
            $response->say('We are experiencing technical difficulties. Please try again later.');
            $response->hangup();

            return response($response, 200)
                ->header('Content-Type', 'text/xml');
        } catch (\Exception $e) {
            Log::error('Error in disaster recovery', [
                'error' => $e->getMessage(),
            ]);

            return $this->rejectCall('System error');
        }
    }

    /**
     * Forward call to a specific user's softphone (WebRTC client)
     * Used when the trunk number has a specific user_id assignment
     */
    protected function forwardToSpecificUser(User $targetUser, string $from, string $to, string $callSid): \Illuminate\Http\Response
    {
        $response = new VoiceResponse();

        $dial = $response->dial('', [
            'timeout' => 30,
            'ringTone' => 'us',
        ]);

        // Connect ONLY to this specific user's WebRTC client
        $dial->client('user_' . $targetUser->id);

        Log::info('Forwarding SIP trunk call to assigned user softphone', [
            'assigned_user_id' => $targetUser->id,
            'assigned_user_name' => $targetUser->name,
            'call_sid' => $callSid,
            'from' => $from,
            'to' => $to,
        ]);

        // Create inbound call record (with duplicate prevention)
        try {
            $existingCall = Call::where('twilio_call_sid', $callSid)->first();
            if (!$existingCall) {
                Call::create([
                    'user_id' => $targetUser->id,
                    'twilio_call_sid' => $callSid,
                    'from_number' => $from,
                    'to_number' => $to,
                    'direction' => 'inbound',
                    'call_type' => 'manual',
                    'status' => 'ringing',
                    'started_at' => now(),
                ]);
            }
        } catch (\Exception $e) {
            Log::warning('Failed to create inbound call record', [
                'call_sid' => $callSid,
                'error' => $e->getMessage(),
            ]);
        }

        return response($response, 200)
            ->header('Content-Type', 'text/xml');
    }

    /**
     * Forward call to user's softphone (WebRTC client)
     * Fallback method that looks up the assigned user from the trunk number
     */
    protected function forwardToSoftphone(User $trunkOwner, string $from, string $to, string $callSid): \Illuminate\Http\Response
    {
        // Find the trunk number to get assigned user
        $trunk = $trunkOwner->getActiveSipTrunk();
        $trunkNumber = null;

        if ($trunk) {
            $trunkNumber = $trunk->phoneNumbers()->where('phone_number', $to)->first();
        }

        // Use assigned user if available, otherwise fallback to trunk owner
        $targetUser = ($trunkNumber && $trunkNumber->user_id)
            ? User::find($trunkNumber->user_id)
            : $trunkOwner;

        // If user not found, fallback to trunk owner
        if (!$targetUser) {
            $targetUser = $trunkOwner;
        }

        $response = new VoiceResponse();

        // Play ringing tone while connecting
        $dial = $response->dial('', [
            'timeout' => 30,
            'ringTone' => 'us',
        ]);

        // Connect to assigned user's WebRTC client (identity must match token: user_{id})
        $dial->client('user_' . $targetUser->id);

        Log::info('Forwarding SIP trunk call to softphone', [
            'trunk_owner_id' => $trunkOwner->id,
            'trunk_owner_name' => $trunkOwner->name,
            'assigned_user_id' => $targetUser->id,
            'assigned_user_name' => $targetUser->name,
            'call_sid' => $callSid,
            'from' => $from,
            'to' => $to,
            'is_assigned' => $trunkNumber && $trunkNumber->user_id ? true : false,
        ]);

        // Create inbound call record (with duplicate prevention)
        try {
            $existingCall = Call::where('twilio_call_sid', $callSid)->first();
            if (!$existingCall) {
                Call::create([
                    'user_id' => $targetUser->id,
                    'twilio_call_sid' => $callSid,
                    'from_number' => $from,
                    'to_number' => $to,
                    'direction' => 'inbound',
                    'call_type' => 'manual',
                    'status' => 'ringing',
                    'started_at' => now(),
                ]);
            }
        } catch (\Exception $e) {
            Log::warning('Failed to create inbound call record', [
                'call_sid' => $callSid,
                'error' => $e->getMessage(),
            ]);
        }

        return response($response, 200)
            ->header('Content-Type', 'text/xml');
    }

    /**
     * Handle inbound call for campaign
     */
    protected function handleCampaignInbound(int $campaignId, string $from, string $to, string $callSid): \Illuminate\Http\Response
    {
        // TODO: Implement campaign-specific inbound handling
        // This could be for callbacks, DTMF responses, etc.

        $response = new VoiceResponse();
        $response->say('Thank you for calling. This number is associated with a campaign.');
        
        // You can add more sophisticated handling here
        // For example, record a voicemail, play a message, etc.
        
        $response->hangup();

        Log::info('Campaign inbound call handled', [
            'campaign_id' => $campaignId,
            'call_sid' => $callSid,
            'from' => $from,
        ]);

        return response($response, 200)
            ->header('Content-Type', 'text/xml');
    }

    /**
     * Handle inbound call for AI agent
     */
    protected function handleAiAgentInbound($aiAgentId, string $from, string $to, string $callSid): \Illuminate\Http\Response
    {
        try {
            // Load AI agent
            $aiAgent = \App\Models\AiAgent::find($aiAgentId);

            if (!$aiAgent || !$aiAgent->active) {
                Log::warning('AI agent not found or inactive for SIP trunk call', [
                    'ai_agent_id' => $aiAgentId,
                    'call_sid' => $callSid,
                ]);
                return $this->rejectCall('AI agent not available');
            }

            Log::info('Routing SIP trunk call to AI agent', [
                'ai_agent_id' => $aiAgent->id,
                'agent_name' => $aiAgent->name,
                'call_sid' => $callSid,
                'from' => $from,
                'to' => $to,
            ]);

            // Initialize or get existing AI agent call record
            $call = \App\Models\AiAgentCall::firstOrCreate(
                ['call_sid' => $callSid],
                [
                    'ai_agent_id' => $aiAgent->id,
                    'direction' => 'inbound',
                    'from_number' => $from,
                    'to_number' => $to,
                    'status' => 'in-progress',
                    'started_at' => now(),
                    'answered_at' => now(),
                ]
            );

            // Get conversation engine and state manager
            $conversationEngine = new \App\Services\AiAgent\ConversationEngine($aiAgent);
            $stateManager = new \App\Services\AiAgent\CallStateManager(
                app(\App\Services\AiAgent\DeepgramService::class),
                $conversationEngine
            );

            // Get greeting message
            $greeting = $stateManager->handleCallAnswered($call);
            $message = $greeting ? $greeting['text'] : ($aiAgent->first_message ?? "Hello! How can I help you today?");

            // Generate TwiML with Gather for speech input
            $actionUrl = url('/twiml/ai-agent-call');
            $voice = $this->mapVoiceForTwilio($aiAgent->voice);

            $escapedMessage = htmlspecialchars($message, ENT_XML1, 'UTF-8');
            $response = new VoiceResponse();
            $gather = $response->gather([
                'input' => 'speech',
                'action' => $actionUrl,
                'method' => 'POST',
                'speechTimeout' => 2,
                'timeout' => 5,
                'language' => 'en-US',
            ]);
            $gather->say($escapedMessage, ['voice' => $voice]);
            $response->say("I didn't hear anything. Please call again if you need assistance. Goodbye!", ['voice' => $voice]);
            $response->hangup();

            Log::info('AI agent greeting sent for SIP trunk call', [
                'ai_agent_id' => $aiAgent->id,
                'call_id' => $call->id,
                'message_preview' => substr($message, 0, 50),
            ]);

            return response($response, 200)->header('Content-Type', 'text/xml');

        } catch (\Exception $e) {
            Log::error('Error handling AI agent inbound call from SIP trunk', [
                'ai_agent_id' => $aiAgentId,
                'call_sid' => $callSid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->rejectCall('System error');
        }
    }

    /**
     * Reject call with a message
     */
    protected function rejectCall(string $reason): \Illuminate\Http\Response
    {
        $response = new VoiceResponse();
        $response->say("We're sorry, but we cannot connect your call at this time. $reason.");
        $response->hangup();

        return response($response, 200)
            ->header('Content-Type', 'text/xml');
    }
}
