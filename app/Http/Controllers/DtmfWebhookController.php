<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Call;
use App\Models\CallDtmfResponse;
use App\Models\CampaignContact;
use App\Services\TwiMLService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class DtmfWebhookController extends Controller
{
    public function __construct(
        private TwiMLService $twimlService
    ) {}

    /**
     * Handle DTMF input from Twilio
     */
    public function handle(Request $request): Response
    {
        Log::info('DTMF Webhook Received', [
            'call_sid' => $request->input('CallSid'),
            'digits' => $request->input('Digits'),
            'all_params' => $request->all(),
        ]);

        $callId = $request->input('call_id');
        $digits = $request->input('Digits');

        if (!$callId || !$digits) {
            Log::warning('DTMF: Missing call_id or Digits', [
                'call_id' => $callId,
                'digits' => $digits,
            ]);
            
            $twiml = $this->twimlService->generateErrorResponse('Invalid input received');
            return response($twiml, 200)->header('Content-Type', 'text/xml');
        }

        $call = Call::find($callId);
        
        if (!$call) {
            Log::warning('DTMF: Call not found', ['call_id' => $callId]);
            $twiml = $this->twimlService->generateErrorResponse('Call not found');
            return response($twiml, 200)->header('Content-Type', 'text/xml');
        }

        $campaign = $call->campaign;
        
        if (!$campaign || !$campaign->enable_dtmf) {
            Log::warning('DTMF: Campaign not found or DTMF not enabled', [
                'campaign_id' => $call->campaign_id,
            ]);
            $twiml = $this->twimlService->generateErrorResponse('DTMF not enabled');
            return response($twiml, 200)->header('Content-Type', 'text/xml');
        }

        // Find the DTMF action configuration for the pressed digit
        $dtmfActions = $campaign->dtmf_actions ?? [];
        $action = collect($dtmfActions)->firstWhere('key', $digits);

        Log::info('DTMF Action Lookup', [
            'digits' => $digits,
            'action_found' => $action !== null,
            'action' => $action,
            'all_actions' => $dtmfActions,
        ]);

        // Record the DTMF response
        $dtmfResponse = CallDtmfResponse::create([
            'call_id' => $call->id,
            'campaign_id' => $campaign->id,
            'campaign_contact_id' => $call->campaign_contact_id,
            'digits_pressed' => $digits,
            'action_taken' => $action['action'] ?? 'none',
            'action_result' => 'pending',
            'metadata' => [
                'call_sid' => $request->input('CallSid'),
                'from' => $request->input('From'),
                'to' => $request->input('To'),
            ],
        ]);

        // Execute the action and generate TwiML response
        $twiml = $this->executeAction($call, $campaign, $action, $dtmfResponse, $digits);

        return response($twiml, 200)->header('Content-Type', 'text/xml');
    }

    /**
     * Execute DTMF action and generate appropriate TwiML
     */
    private function executeAction(Call $call, $campaign, ?array $action, CallDtmfResponse $dtmfResponse, string $digits): string
    {
        if (!$action) {
            Log::info('DTMF: No action configured for digit', ['digit' => $digits]);
            
            $dtmfResponse->update([
                'action_result' => 'no_action_configured',
                'action_details' => "No action configured for digit: {$digits}",
            ]);
            
            return $this->twimlService->generateTts(
                "Invalid selection. Goodbye.",
                ['language' => $campaign->voice_language ?? 'en-US']
            );
        }

        $actionType = $action['action'] ?? 'none';
        $actionValue = $action['value'] ?? null;
        $actionLabel = $action['label'] ?? "Key {$digits}";

        Log::info('DTMF: Executing action', [
            'action_type' => $actionType,
            'action_value' => $actionValue,
            'action_label' => $actionLabel,
        ]);

        try {
            switch ($actionType) {
                case 'transfer':
                    // Transfer to phone number
                    if ($actionValue) {
                        $dtmfResponse->update([
                            'action_result' => 'success',
                            'action_details' => "Transferred to: {$actionValue}",
                        ]);
                        
                        return $this->twimlService->generateTransfer(
                            $actionValue,
                            "Transferring you now. Please hold."
                        );
                    }
                    break;

                case 'opt_out':
                case 'remove':
                    // Mark contact as opted out
                    if ($call->campaignContact) {
                        $call->campaignContact->update(['status' => 'opted_out']);
                        
                        $dtmfResponse->update([
                            'action_result' => 'success',
                            'action_details' => 'Contact marked as opted out',
                        ]);
                    }
                    
                    return $this->twimlService->generateTts(
                        $actionValue ?? "You have been removed from our list. Goodbye.",
                        ['language' => $campaign->voice_language ?? 'en-US']
                    );

                case 'interested':
                case 'mark_interested':
                    // Mark contact as interested
                    if ($call->campaignContact) {
                        $call->campaignContact->update([
                            'status' => 'answered',
                            'notes' => ($call->campaignContact->notes ?? '') . "\nPressed {$digits}: Interested",
                        ]);
                        
                        $dtmfResponse->update([
                            'action_result' => 'success',
                            'action_details' => 'Contact marked as interested',
                        ]);
                    }
                    
                    return $this->twimlService->generateTts(
                        $actionValue ?? "Thank you for your interest. Someone will contact you shortly.",
                        ['language' => $campaign->voice_language ?? 'en-US']
                    );

                case 'callback':
                case 'request_callback':
                    // Mark for callback
                    if ($call->campaignContact) {
                        $call->campaignContact->update([
                            'status' => 'pending',
                            'notes' => ($call->campaignContact->notes ?? '') . "\nPressed {$digits}: Requested callback",
                        ]);
                        
                        $dtmfResponse->update([
                            'action_result' => 'success',
                            'action_details' => 'Callback requested',
                        ]);
                    }
                    
                    return $this->twimlService->generateTts(
                        $actionValue ?? "Thank you. We will call you back soon.",
                        ['language' => $campaign->voice_language ?? 'en-US']
                    );

                case 'message':
                case 'play_message':
                    // Play custom message
                    $dtmfResponse->update([
                        'action_result' => 'success',
                        'action_details' => 'Custom message played',
                    ]);
                    
                    return $this->twimlService->generateTts(
                        $actionValue ?? $actionLabel,
                        ['language' => $campaign->voice_language ?? 'en-US']
                    );

                case 'hangup':
                case 'end':
                    // End call
                    $dtmfResponse->update([
                        'action_result' => 'success',
                        'action_details' => 'Call ended',
                    ]);
                    
                    $response = new \Twilio\TwiML\VoiceResponse();
                    if ($actionValue) {
                        $response->say($actionValue);
                    }
                    $response->hangup();
                    return (string) $response;

                default:
                    // Unknown action
                    $dtmfResponse->update([
                        'action_result' => 'unknown_action',
                        'action_details' => "Unknown action type: {$actionType}",
                    ]);
                    
                    return $this->twimlService->generateTts(
                        "Invalid selection. Goodbye.",
                        ['language' => $campaign->voice_language ?? 'en-US']
                    );
            }
        } catch (\Exception $e) {
            Log::error('DTMF Action Execution Failed', [
                'action_type' => $actionType,
                'error' => $e->getMessage(),
                'call_id' => $call->id,
            ]);
            
            $dtmfResponse->update([
                'action_result' => 'failed',
                'action_details' => $e->getMessage(),
            ]);
            
            return $this->twimlService->generateErrorResponse('An error occurred processing your request');
        }

        // Fallback
        return $this->twimlService->generateTts(
            "Thank you. Goodbye.",
            ['language' => $campaign->voice_language ?? 'en-US']
        );
    }
}
