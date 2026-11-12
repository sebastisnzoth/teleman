<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\TelephonyProvider;
use App\Http\Controllers\Concerns\MapsAiVoice;
use App\Models\AiAgent;
use App\Models\AiAgentCall;
use App\Models\Call;
use App\Models\CallQueueEntry;
use App\Models\Campaign;
use App\Models\CampaignContact;
use App\Models\PhoneNumber;
use App\Models\TrunkPhoneNumber;
use App\Models\TwilioGlobalConfig;
use App\Models\User;
use App\Services\AiAgent\CallStateManager;
use App\Services\AiAgent\ConversationEngine;
use App\Services\AiAgent\DeepgramService;
use App\Services\CallQueueService;
use App\Services\Telephony\Plivo\PlivoEndpointProvisioner;
use App\Services\Telephony\Plivo\PlivoNumberFormat;
use App\Services\Telephony\TelephonyManager;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Twilio\Security\RequestValidator;

class TwimlController extends Controller
{
    use MapsAiVoice;

    /**
     * What a caller hears when they asked for a person and cannot be put
     * through to one — whether the transfer was dialled and went
     * unanswered, or there was no number to dial in the first place.
     */
    private const TRANSFER_UNREACHABLE = "I'm sorry, I couldn't reach a human agent. Please try again later. Goodbye!";

    /**
     * Hold music for a waiting caller. https, not http: Plivo's <Play>
     * requires HTTPS (see the Play attribute table at
     * https://www.plivo.com/docs/voice/xml/audio-output), and Twilio serves
     * this same bucket over HTTPS too, so nothing Twilio-facing changes by
     * sharing one URL.
     */
    private const QUEUE_HOLD_MUSIC_URL = 'https://com.twilio.sounds.music.s3.amazonaws.com/ClockworkWaltz.mp3';

    public function __construct(
        private readonly TelephonyManager $telephony,
        private readonly PlivoEndpointProvisioner $plivoEndpoints,
    ) {}

    /**
     * Validate Twilio webhook signature for security
     */
    private function validateTwilioRequest(Request $request): bool
    {
        // Skip validation in local development
        if (app()->environment('local') && config('app.debug')) {
            return true;
        }

        // Get auth token from database
        $globalConfig = TwilioGlobalConfig::active();

        if (! $globalConfig) {
            Log::warning('No active Twilio configuration found for request validation');

            return false;
        }

        $validator = new RequestValidator($globalConfig->getDecryptedAuthToken());

        $signature = $request->header('X-Twilio-Signature', '');
        $url = $request->fullUrl();
        $params = $request->all();

        return $validator->validate($signature, $url, $params);
    }

    /**
     * Generate TwiML/Plivo XML for the softphone (handles both inbound and
     * outbound). $provider is read from the route's own 'provider'
     * parameter — see inboundCall()'s docblock for why, and for why it is
     * not a typed method parameter.
     */
    public function manualCall(Request $request): Response
    {
        $provider = $request->route('provider', 'twilio');

        // Get call_id from multiple possible sources
        $callId = $this->browserCallId($request, $provider);
        $toNumber = $this->webhookNumber($request->input('To'), $provider);
        $fromNumber = $this->webhookNumber($request->input('From'), $provider);
        $direction = $request->input('Direction'); // 'inbound' or 'outbound-api'
        $providerCallId = $this->providerCallId($request, $provider);

        // Log essential parameters only (prevent response bloat)
        Log::info('TwiML Request', [
            'call_id' => $callId,
            'direction' => $direction,
            'to' => $toNumber,
            'from' => $fromNumber,
        ]);

        // Detect call type with priority:
        // 1. If From starts with "client:" → ALWAYS treat as outbound from browser (even if Direction='inbound')
        // 2. If has call_id parameter → Outbound call from browser
        // 3. If Direction='inbound' AND From is real phone number → Inbound from external phone

        $isFromClient = $this->isBrowserOriginated($fromNumber, $provider);

        // CRITICAL: If call is from a Twilio Client, it's NEVER an inbound call
        // Even if Twilio sends Direction='inbound', it's an outbound call from the browser
        if ($isFromClient) {
            // This is an outbound call from browser - must have call_id
            if (! $callId) {
                Log::warning('TwiML: Outbound call from client missing call_id', [
                    'from' => $fromNumber,
                    'to' => $toNumber,
                ]);
                $twiml = $this->telephony->callXml()->say('Call ID missing for outbound call', ['language' => 'en-US']);

                return $this->respondWithXml($twiml);
            }
            // Continue with outbound call handling below
        } else {
            // Not from client - check if it's a real inbound call from external phone
            $isInboundCall = $direction === 'inbound' && ! $callId;

            if ($isInboundCall) {
                Log::info('Inbound call detected - routing to inboundCall handler', [
                    'from' => $fromNumber,
                    'to' => $toNumber,
                    'call_sid' => $providerCallId,
                ]);

                // Route to dedicated inbound call handler — it reads
                // $provider from the same $request->route() this method
                // just did, so nothing needs forwarding explicitly.
                return $this->inboundCall($request);
            }
        }

        // Handle outbound calls from browser (or API)
        if (! $callId) {
            Log::warning('TwiML: Call ID missing for outbound call', [
                'from' => $fromNumber,
                'to' => $toNumber,
                'is_from_client' => $isFromClient,
            ]);
            $twiml = $this->telephony->callXml()->say('Call ID missing', ['language' => 'en-US']);

            return $this->respondWithXml($twiml);
        }

        $call = Call::find($callId);

        if (! $call) {
            Log::warning('TwiML: Call not found', ['call_id' => $callId]);
            $twiml = $this->telephony->callXml()->say('Call not found', ['language' => 'en-US']);

            return $this->respondWithXml($twiml);
        }

        // A browser names the call it is placing, in a parameter the browser
        // itself sets, and call ids are sequential. Unchecked, any signed-in
        // user of this installation could name another business's call: the
        // document returned below would dial a destination of their choosing
        // while presenting that business's own purchased number as the caller
        // ID, against that business's call record. The softphone identity is
        // minted server-side, one per user, so a genuine browser call always
        // matches the call's owner.
        //
        // Answered exactly as an unknown call id is, deliberately: a distinct
        // response would tell a caller which call ids exist.
        if ($isFromClient && ! $this->browserOwnsCall($fromNumber, $provider, $call)) {
            Log::warning('TwiML: browser identity does not own the requested call', [
                'call_id' => $call->id,
                'from' => $fromNumber,
            ]);
            $twiml = $this->telephony->callXml()->say('Call not found', ['language' => 'en-US']);

            return $this->respondWithXml($twiml);
        }

        // Log the call record details
        Log::info('TwiML: Call found', [
            'call_id' => $call->id,
            'to_number' => $call->to_number,
            'from_number' => $call->from_number,
            'direction' => $call->direction,
        ]);

        $this->adoptProviderCallId($request, $provider, $call);

        // Use the number from the call record if not provided in request
        // Priority: Request To parameter -> Call record to_number
        $toNumber = $toNumber ?? $call->to_number;

        // Validate we have a destination number
        if (empty($toNumber)) {
            Log::error('TwiML: No destination number', [
                'call_id' => $call->id,
                'request_to' => $request->input('To'),
                'call_to_number' => $call->to_number,
            ]);
            $twiml = $this->telephony->callXml()->say('Destination number is missing', ['language' => 'en-US']);

            return $this->respondWithXml($twiml);
        }

        // Ensure phone number is in E.164 format (starts with +)
        if (! str_starts_with($toNumber, '+')) {
            // Assume US number if no country code
            $toNumber = '+1'.preg_replace('/[^0-9]/', '', $toNumber);
        }

        Log::info('TwiML: Dial', [
            'call_id' => $call->id,
            'to' => $toNumber,
            'from' => $call->from_number,
        ]);

        // Prepare status callback URL (fire-and-forget notifications — response format doesn't matter)
        $statusCallbackUrl = $this->callbackRoute($provider, 'call.status', ['call_id' => $call->id]);

        // Prepare dial action URL (the provider expects a document back)
        $dialActionUrl = $this->dialCompleteRoute($provider, $call->id);

        // Prepare recording callback if enabled
        $recordingCallbackUrl = $call->enable_recording
            ? $this->callbackRoute($provider, 'call.recording', ['call_id' => $call->id])
            : null;

        // Prepare transcription callback if enabled. Twilio only: this
        // application has no Plivo transcription webhook, and Plivo's own
        // transcription is a separate paid, English-only feature with a
        // different callback — so it is not offered on Plivo rather than
        // pointed at a route that does not exist.
        $transcriptionCallbackUrl = $call->enable_recording && $provider === 'twilio'
            ? route('webhooks.twilio.call.transcription', ['call_id' => $call->id])
            : null;

        // For browser-to-phone calls using Twilio Client/Device SDK
        // Simply dial the destination number - the browser is already connected
        // action → dial-complete endpoint (the provider expects a document back)
        // statusCallback/numberStatusCallback → webhook endpoint (fire-and-forget
        // for call status tracking; Plivo has no per-leg equivalent — see
        // CallXmlInterface::dial()'s docblock)
        $callXml = $this->telephony->callXml();

        $dialXml = $callXml->dial($toNumber, [
            'callerId' => $call->from_number,
            'timeout' => 30,
            'statusCallback' => $dialActionUrl,
            'record' => $call->enable_recording ? 'record-from-answer' : null,
            'recordingStatusCallback' => $recordingCallbackUrl,
            'transcribeCallback' => $call->enable_recording ? $transcriptionCallbackUrl : null,
            'numberStatusCallback' => $statusCallbackUrl,
            'numberStatusCallbackEvents' => 'initiated ringing answered completed',
        ]);
        $sayXml = $callXml->say('Call ended. Goodbye!', ['voice' => 'alice', 'language' => null]);

        $twiml = $callXml->buildResponse([$dialXml, $sayXml]);

        return $this->respondWithXml($twiml);
    }

    /**
     * Generate call-control XML for a campaign call (TTS). $provider is
     * read from the route's own 'provider' parameter — see
     * inboundCall()'s docblock for why, and for why it is not a typed
     * method parameter.
     */
    public function campaignCallTts(Request $request): Response
    {
        $provider = $request->route('provider', 'twilio');
        $callId = $request->query('call_id');

        if (! $callId) {
            $twiml = $this->telephony->callXml()->say('Call ID missing', ['language' => 'en-US']);

            return $this->respondWithXml($twiml);
        }

        $call = Call::with('campaign')->find($callId);

        if (! $call || ! $call->campaign) {
            $twiml = $this->telephony->callXml()->say('Campaign not found', ['language' => 'en-US']);

            return $this->respondWithXml($twiml);
        }

        $campaign = $call->campaign;

        $this->adoptProviderCallId($request, $provider, $call);
        $this->countAnsweredCampaignCall($provider, $call);

        // Get status callback URL
        $statusCallback = $this->callbackRoute($provider, 'call.status', ['call_id' => $call->id]);

        // Get recording callback if enabled
        $recordingCallback = null;
        if ($campaign->enable_recording) {
            $recordingCallback = $this->callbackRoute($provider, 'call.recording', ['call_id' => $call->id]);
        }

        // Replace variables in message
        // Use variant message if assigned, otherwise use campaign default
        if ($call->message_variant_id && $call->messageVariant) {
            $message = $call->messageVariant->message_text;
            Log::info('Using message variant', [
                'call_id' => $call->id,
                'variant_id' => $call->message_variant_id,
                'variant_label' => $call->messageVariant->variant_label,
            ]);
        } else {
            $message = $campaign->message;
        }

        // First, replace campaign-level variables (same for all contacts)
        $campaignVariables = $campaign->campaign_variables ?? [];
        if (is_string($campaignVariables)) {
            $campaignVariables = json_decode($campaignVariables, true) ?? [];
        }

        foreach ($campaignVariables as $key => $value) {
            $message = str_replace("{{{$key}}}", (string) $value, $message);
        }

        // Then, replace contact-level variables (different per contact)
        if ($call->campaignContact) {
            $contact = $call->campaignContact;

            // Replace contact fields (first_name, last_name, etc.)
            $contactFields = [
                'first_name' => $contact->first_name ?? '',
                'last_name' => $contact->last_name ?? '',
                'phone_number' => $contact->phone_number ?? '',
                'email' => $contact->email ?? '',
                'company' => $contact->company ?? '',
            ];

            foreach ($contactFields as $key => $value) {
                $message = str_replace("{{{$key}}}", (string) $value, $message);
            }

            // Replace custom variables from the variables JSON field
            $customVariables = $contact->variables ?? [];
            if (is_string($customVariables)) {
                $customVariables = json_decode($customVariables, true) ?? [];
            }
            foreach ($customVariables as $key => $value) {
                $message = str_replace("{{{$key}}}", (string) $value, $message);
            }
        }

        // Get DTMF callback if enabled
        $dtmfCallback = null;
        if ($campaign->enable_dtmf) {
            $dtmfCallback = $this->callbackRoute($provider, 'call.dtmf', ['call_id' => $call->id]);
        }

        // Generate call XML with TTS. generateCampaignTtsXml()'s dtmf/record
        // options nest an explicit 'enabled' flag — a different shape from
        // say()'s/play()'s "presence of the key means enabled" convention —
        // see TwilioCallXmlGeneratorTest's parity coverage for this exact
        // translation from the legacy generateTts() flat options.
        $twiml = $this->telephony->callXml()->generateCampaignTtsXml(
            $message,
            [
                'voice' => $campaign->voice,
                'language' => $campaign->language,
                'dtmf' => [
                    'enabled' => (bool) $campaign->enable_dtmf,
                    'action' => $dtmfCallback,
                ],
                'record' => [
                    'enabled' => (bool) $campaign->enable_recording,
                    'action' => $recordingCallback,
                ],
            ]
        );

        return $this->respondWithXml($twiml);
    }

    /**
     * Generate call-control XML for a campaign call (voice playback).
     * $provider is read from the route's own 'provider' parameter — see
     * inboundCall()'s docblock for why, and for why it is not a typed
     * method parameter.
     */
    public function campaignCallVoice(Request $request): Response
    {
        $provider = $request->route('provider', 'twilio');
        $callId = $request->query('call_id');

        if (! $callId) {
            $twiml = $this->telephony->callXml()->say('Call ID missing', ['language' => 'en-US']);

            return $this->respondWithXml($twiml);
        }

        $call = Call::with('campaign.audioFile')->find($callId);

        if (! $call || ! $call->campaign || ! $call->campaign->audioFile) {
            $twiml = $this->telephony->callXml()->say('Audio file not found', ['language' => 'en-US']);

            return $this->respondWithXml($twiml);
        }

        $campaign = $call->campaign;

        $this->adoptProviderCallId($request, $provider, $call);
        $this->countAnsweredCampaignCall($provider, $call);

        // Get audio URL and make it absolute for Twilio
        $audioUrl = $call->campaign->audioFile->url;
        if (! str_starts_with($audioUrl, 'http')) {
            $audioUrl = url($audioUrl);
        }

        // Get status callback URL
        $statusCallback = $this->callbackRoute($provider, 'call.status', ['call_id' => $call->id]);

        // Get recording callback if enabled
        $recordingCallback = null;
        if ($campaign->enable_recording) {
            $recordingCallback = $this->callbackRoute($provider, 'call.recording', ['call_id' => $call->id]);
        }

        // Get DTMF callback if enabled
        $dtmfCallback = null;
        if ($campaign->enable_dtmf) {
            $dtmfCallback = $this->callbackRoute($provider, 'call.dtmf', ['call_id' => $call->id]);
        }

        // Generate call XML with voice playback. play()'s dtmf/record
        // options use "presence of the key means enabled" (unlike
        // generateCampaignTtsXml()'s explicit nested 'enabled' flag above),
        // so these are only set at all when the campaign has them on.
        $playOptions = [];
        if ($campaign->enable_dtmf) {
            $playOptions['dtmf'] = ['action' => $dtmfCallback];
        }
        if ($campaign->enable_recording) {
            $playOptions['record'] = ['action' => $recordingCallback];
        }

        $twiml = $this->telephony->callXml()->play($audioUrl, $playOptions);

        return $this->respondWithXml($twiml);
    }

    /**
     * Handle an inbound call to a Twilio or Plivo number.
     *
     * $provider is read from the route's own 'provider' parameter
     * (twiml/* never sets one, so it defaults to 'twilio'; plivo-xml/*
     * sets it via ->defaults()), not from TelephonyManager::provider()
     * (the installation's current lock): which field name a webhook's
     * own call id arrives under (CallSid vs CallUUID) is a property of
     * who actually sent this specific request, and must stay correct
     * even if the lock changes between this call being placed and this
     * webhook arriving — WebhookNormalizerInterface's own contract makes
     * the same choice (see TelephonyManager::webhookNormalizer()'s
     * docblock).
     *
     * It is deliberately not a typed method parameter: Laravel's
     * controller-dependency resolution splices class dependencies into
     * the route-parameter list positionally
     * (ResolvesRouteDependencies::resolveMethodDependencies()), which can
     * mis-assign a scalar default sitting after them — reading it from
     * the route explicitly sidesteps that.
     */
    public function inboundCall(Request $request): Response
    {
        $provider = $request->route('provider', 'twilio');
        $fromNumber = $this->webhookNumber($request->input('From'), $provider);
        $toNumber = $this->webhookNumber($request->input('To'), $provider);
        $callSid = $this->providerCallId($request, $provider);

        Log::info('Inbound Call Handler', [
            'from' => $fromNumber,
            'to' => $toNumber,
            'call_sid' => $callSid,
        ]);

        // IMPORTANT: If this is from a Twilio Client (browser), redirect to manual-call handler
        // This can happen if TwiML App is still configured with old URLs
        $callXml = $this->telephony->callXml();

        // A Plivo installation has one Application, and its answer URL is this
        // one: it receives calls into the installation's numbers AND every
        // call a browser softphone places. Twilio keeps those apart with two
        // URLs, which is why the check below treats a browser call here as a
        // configuration error. On Plivo it is the normal path, so hand it to
        // the handler that dials out. manualCall() recognises the same call
        // as browser-originated, so it never hands it back here.
        if ($provider !== 'twilio' && $this->isBrowserOriginated($fromNumber, $provider)) {
            return $this->manualCall($request);
        }

        if (str_starts_with($fromNumber ?? '', 'client:')) {
            Log::warning('Inbound handler received client call - this should use manual-call endpoint', [
                'from' => $fromNumber,
                'to' => $toNumber,
            ]);

            // Return an error document to prevent duplicate call creation
            $twiml = $callXml->buildResponse([
                $callXml->say(
                    'Configuration error. Please update your TwiML App to use the manual-call endpoint.',
                    ['voice' => 'alice', 'language' => null]
                ),
                $callXml->hangup(),
            ]);

            return $this->respondWithXml($twiml);
        }

        // Find which user owns this phone number (check both Twilio direct and SIP trunk)
        $phoneNumber = PhoneNumber::where('number', $toNumber)
            ->where('status', 'assigned')
            ->first();

        $userId = null;
        if ($phoneNumber && $phoneNumber->user_id) {
            $userId = $phoneNumber->user_id;
        } else {
            // Check SIP trunk numbers
            $trunkNumber = TrunkPhoneNumber::where('phone_number', $toNumber)
                ->where('status', 'active')
                ->where('assigned_to', 'softphone')
                ->first();
            if ($trunkNumber && $trunkNumber->user_id) {
                $userId = $trunkNumber->user_id;
            }
        }

        // Determine target user (must find phone number owner)
        if (! $userId) {
            Log::warning('Inbound call to unrecognized number - no user found', [
                'to' => $toNumber,
                'from' => $fromNumber,
                'call_sid' => $callSid,
            ]);

            // Try to find any user with this phone number assigned (even non-softphone)
            $trunkNumberAny = TrunkPhoneNumber::where('phone_number', $toNumber)
                ->where('status', 'active')
                ->first();
            if ($trunkNumberAny && $trunkNumberAny->user_id) {
                $userId = $trunkNumberAny->user_id;
            } else {
                // Reject the call if we can't identify the user
                $twiml = $callXml->buildResponse([
                    $callXml->say(
                        'Sorry, this number is not configured to receive calls. Goodbye.',
                        ['voice' => 'alice', 'language' => null]
                    ),
                    $callXml->hangup(),
                ]);

                return $this->respondWithXml($twiml);
            }
        }

        // Generate client identity (must match token identity: user_{id})
        $clientIdentity = 'user_'.$userId;

        // --- Call Queue: check if user is busy and enqueue if needed ---
        $queueService = app(CallQueueService::class);
        $targetUser = User::find($userId);

        if ($targetUser && $queueService->isUserBusy($targetUser) && $queueService->isQueueEnabled($targetUser)) {
            if ($queueService->isQueueFull($targetUser)) {
                Log::info('Inbound call rejected: queue full', [
                    'user_id' => $userId,
                    'from' => $fromNumber,
                    'call_sid' => $callSid,
                ]);

                $twiml = $callXml->buildResponse([
                    $callXml->say(
                        'All lines are currently busy. Please try again later. Goodbye.',
                        ['voice' => 'alice', 'language' => null]
                    ),
                    $callXml->hangup(),
                ]);

                return $this->respondWithXml($twiml);
            }

            // Enqueue the caller
            $entry = $queueService->enqueue($targetUser, $fromNumber, $callSid);

            Log::info('Inbound call enqueued', [
                'user_id' => $userId,
                'from' => $fromNumber,
                'call_sid' => $callSid,
                'queue' => $entry->queue_name,
            ]);

            // Which construct makes the caller wait — a provider-side
            // queue or a conference room they join without starting — is
            // the queue service's decision, not this controller's.
            return $this->respondWithXml($queueService->xmlForWaiting($targetUser, $entry));
        }
        // --- End Call Queue check ---

        // Check if call already exists to prevent duplicates
        $existingCall = Call::where('twilio_call_sid', $callSid)->first();

        if ($existingCall) {
            Log::info('Call already exists, using existing record', [
                'call_id' => $existingCall->id,
                'call_sid' => $callSid,
            ]);
            $call = $existingCall;
        } else {
            // Create call record for inbound call. Twilio's real call id
            // is always known on this very first request, so both
            // identifier columns can be written at creation time — unlike
            // an outbound Plivo call, where only a request identifier
            // exists until the first webhook arrives (see
            // CallController::webhookStatus()), an inbound call's CallUUID
            // is already sitting right here.
            $call = Call::create($provider === 'twilio' ? [
                'user_id' => $userId,
                'call_type' => 'manual',
                'direction' => 'inbound',
                'from_number' => $fromNumber,
                'to_number' => $toNumber,
                'twilio_call_sid' => $callSid,
                'status' => 'ringing',
                'started_at' => now(),
            ] : [
                'user_id' => $userId,
                'call_type' => 'manual',
                'direction' => 'inbound',
                'from_number' => $fromNumber,
                'to_number' => $toNumber,
                'twilio_call_sid' => $callSid,
                'provider_call_sid' => $callSid,
                'provider' => $provider,
                'status' => 'ringing',
                'started_at' => now(),
            ]);

            Log::info('Inbound call created', ['call_id' => $call->id, 'user' => $userId]);
        }

        // Prepare status callback URL (fire-and-forget notifications)
        $statusCallbackUrl = $this->callbackRoute($provider, 'call.status', ['call_id' => $call->id]);

        // Prepare dial action URL (the provider expects a document back)
        $dialActionUrl = $this->dialCompleteRoute($provider, $call->id);

        // Connect the inbound call to the user's browser softphone.
        // action → dial-complete endpoint; clientStatusCallback → webhook
        // endpoint (fire-and-forget call status tracking).
        //
        // Plivo reaches a browser through the user's SIP Endpoint, dialled by
        // the SIP URI Plivo returned when it was created — never one composed
        // from the identity. An Endpoint is created the first time the user
        // opens their softphone, so a user who never has has none, and
        // dialClient() throws InvalidArgumentException for them.
        $dialOptions = [
            'timeout' => 30,
            'callerId' => $fromNumber,
            'statusCallback' => $dialActionUrl,
            'record' => null,
            'clientStatusCallback' => $statusCallbackUrl,
            'clientStatusCallbackEvents' => 'initiated ringing answered completed',
        ];

        if ($provider !== 'twilio' && $targetUser) {
            $dialOptions['sip_uri'] = $this->plivoEndpoints->storedFor($targetUser)['sip_uri'] ?? null;
        }

        try {
            $dialXml = $callXml->dialClient($clientIdentity, $dialOptions);
        } catch (InvalidArgumentException $e) {
            if ($this->telephony->provider() === TelephonyProvider::TWILIO) {
                // Twilio's dialClient() has no such validation today and
                // has no reason to ever throw this — if it somehow does,
                // that is a genuine bug elsewhere in the dial path, not
                // the known Plivo gap below, and must not be misdiagnosed
                // as one by falling through to the generic handling.
                throw $e;
            }

            // The user has no Endpoint yet — they have never opened their
            // softphone — so there is no browser to ring. Fail the call
            // gracefully, the same outcome a caller already gets when the
            // callee's browser never answers, rather than let it surface as
            // an uncaught exception. Log the actual exception message so an
            // operator can tell this apart from a genuine failure, and
            // terminalize the Call row created above: the fallback below
            // carries no action URL, so no status callback will ever move it
            // out of 'ringing', and nothing else sweeps it.
            Log::warning('Inbound call: browser client unreachable on this provider', [
                'user_id' => $userId,
                'provider' => $this->telephony->provider()->value,
                'exception' => $e->getMessage(),
            ]);

            if ($call->status === 'ringing') {
                $call->update([
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                ]);
            }

            $twiml = $callXml->buildResponse([
                $callXml->say(
                    'The person you are trying to reach is not available. Goodbye!',
                    ['voice' => 'alice', 'language' => null]
                ),
                $callXml->hangup(),
            ]);

            return $this->respondWithXml($twiml);
        }

        $twiml = $callXml->buildResponse([
            $callXml->say('Connecting your call. Please wait.', ['voice' => 'alice', 'language' => null]),
            $dialXml,
            $callXml->say(
                'The person you are trying to reach is not available. Goodbye!',
                ['voice' => 'alice', 'language' => null]
            ),
        ]);

        return $this->respondWithXml($twiml);
    }

    /**
     * Handle AI Agent calls with simple gather-based conversation.
     *
     * $provider is read from the route's own 'provider' parameter — see
     * inboundCall()'s docblock for why, and for why it is not a typed
     * method parameter.
     *
     * The turn loop reads every field through a provider-neutral helper
     * rather than off the request by Twilio's own field names:
     * providerCallId() (CallSid or CallUUID), spokenInput() (SpeechResult
     * or Speech), webhookDirection() ('outbound-api' or 'outbound') and
     * webhookNumber() (with or without the leading '+'). Read literally
     * as Twilio names them, a Plivo call reached the NOT NULL
     * ai_agent_calls.call_sid with nothing, threw, and got back the
     * apology document this method's own try/catch produces — a valid
     * document, so the call did not break; it simply never held a
     * conversation. The conversation engine itself is untouched: it still
     * receives one transcript and knows nothing about either provider.
     */
    public function aiAgentCall(Request $request): Response
    {
        $provider = $request->route('provider', 'twilio');

        // Twilio's own request signature. Redundant on the twiml/*
        // route (its own twilio.signature middleware already gates it)
        // and unconditionally wrong on Plivo — there is no Twilio
        // signature on that request to validate — so it only runs for
        // the provider it actually applies to.
        if ($provider === 'twilio' && ! $this->validateTwilioRequest($request)) {
            Log::warning('Invalid Twilio signature for AI agent call', [
                'ip' => $request->ip(),
                'url' => $request->fullUrl(),
            ]);
            abort(403, 'Invalid request signature');
        }

        $fromNumber = $this->webhookNumber($request->input('From'), $provider);
        $toNumber = $this->webhookNumber($request->input('To'), $provider);
        $callSid = $this->providerCallId($request, $provider);
        $speechResult = $this->spokenInput($request, $provider);
        $direction = $this->webhookDirection($request, $provider);

        $pipelineStart = microtime(true);

        Log::info('AI Agent Call Handler', [
            'from' => $fromNumber,
            'to' => $toNumber,
            'call_sid' => $callSid,
            'speech' => $speechResult,
            'direction' => $direction,
        ]);

        // Idempotency guard: Twilio retries webhooks on timeout (up to 3x).
        // If the same (call_sid + speech_result) arrives while we're still
        // processing the first one, return the cached response instead of
        // running the LLM + TTS a second time (which causes duplicate speech).
        $idempotencyKey = 'ai_webhook:'.md5($callSid.'|'.($speechResult ?? '_empty_'));
        $cachedTwiml = cache()->get($idempotencyKey);
        if ($cachedTwiml) {
            Log::info('AI Agent: returning cached TwiML for retried webhook', [
                'call_sid' => $callSid,
            ]);

            return $this->respondWithXml($cachedTwiml);
        }

        try {
            // Find AI agent by phone number (single query — merged for speed)
            // For outbound calls (outbound-api), use From number; for inbound, use To number
            $agentPhoneNumber = ($direction === 'outbound-api') ? $fromNumber : $toNumber;
            $last10 = substr($agentPhoneNumber, -10);

            $aiAgent = AiAgent::where('active', true)
                ->where(function ($q) use ($agentPhoneNumber, $last10) {
                    $q->where('phone_number', $agentPhoneNumber)
                        ->orWhere('phone_number', 'like', '%'.$last10);
                })
                ->first();

            if (! $aiAgent) {
                Log::warning('No active AI agent found for phone number', [
                    'direction' => $direction,
                    'agent_phone' => $agentPhoneNumber,
                ]);

                return $this->respondWithFallback('I\'m sorry, this number is not configured.');
            }

            // Initialize or get existing call
            $isNewCall = false;
            $call = AiAgentCall::where('call_sid', $callSid)->first();
            if (! $call) {
                $isNewCall = true;
                $call = AiAgentCall::create([
                    'call_sid' => $callSid,
                    'ai_agent_id' => $aiAgent->id,
                    'direction' => $direction,
                    'from_number' => $fromNumber,
                    'to_number' => $toNumber,
                    'status' => 'in-progress',
                    'turn_count' => 0,
                    'started_at' => now(),
                    'answered_at' => now(),
                ]);
            }

            // Get services
            $conversationEngine = new ConversationEngine($aiAgent);
            $stateManager = new CallStateManager(
                app(DeepgramService::class),
                $conversationEngine
            );

            $voice = $this->mapVoiceForTwilio($aiAgent->voice);

            // Track silence retries in the AiAgentCall.metadata JSON field
            // so we give the receiver a second chance before hanging up.
            $metadata = is_array($call->metadata) ? $call->metadata : [];
            $silenceCount = (int) ($metadata['silence_count'] ?? 0);

            // Determine what to say
            if ($speechResult) {
                // User spoke - process their speech. The turn counter is
                // the state manager's to keep — it is the thing that knows
                // an exchange has happened, and counting it here as well
                // counted every exchange twice over.
                // Reset silence counter when user actually speaks
                if ($silenceCount > 0) {
                    $metadata['silence_count'] = 0;
                    $call->update(['metadata' => $metadata]);
                }
                $response = $stateManager->processUserInput($call, $speechResult);

                if ($response && $response['action'] === 'transfer') {
                    return $this->rememberTurn($idempotencyKey, $this->respondWithTransfer($aiAgent->transfer_number, $response['text'], $aiAgent->phone_number));
                }

                if ($response && $response['action'] === 'end') {
                    return $this->rememberTurn($idempotencyKey, $this->respondWithGoodbye($response['text']));
                }

                $message = $response ? $response['text'] : "I'm sorry, I didn't understand that. Could you please repeat?";
            } elseif ($isNewCall) {
                // Brand new call - send greeting
                $greeting = $stateManager->handleCallAnswered($call);
                $message = $greeting ? $greeting['text'] : "Hello! I'm your AI assistant. How can I help you today?";
            } else {
                // Gather timed out (no speech).
                // Give the receiver a second chance before hanging up — the first
                // timeout likely means they were still gathering their thoughts.
                $silenceCount++;
                $metadata['silence_count'] = $silenceCount;
                $call->update(['metadata' => $metadata]);

                if ($silenceCount >= 2) {
                    // Second silence — end gracefully
                    return $this->rememberTurn($idempotencyKey, $this->respondWithGoodbye("I didn't hear anything. If you need assistance, please call back anytime. Goodbye!"));
                }

                // First silence — nudge the caller
                $message = "Are you still there? I didn't catch that — please go ahead.";
            }

            // Generate the next turn's input-gathering document.
            // Use agent's configured silence_timeout (fallback to 10s).
            // gather()'s 'speech' input mode is Twilio's speechTimeout="auto"
            // (detects end of speech naturally instead of a fixed cutoff),
            // Twilio's phone-call-optimized speech model and enhanced
            // transcription, with the prompt nested inside so the provider's
            // own barge-in behaviour still applies — see
            // TwilioCallXmlGenerator::gather()'s docblock for the exact
            // reproduction of what this endpoint built by hand before.
            $actionUrl = $provider === 'twilio' ? url('/twiml/ai-agent-call') : url('/plivo-xml/ai-agent-call');
            $gatherTimeout = max(5, (int) ($aiAgent->silence_timeout ?? 10));

            $twiml = $this->telephony->callXml()->gather($message, [
                'input' => 'speech',
                'action' => $actionUrl,
                // 'timeout' is how long Twilio waits for the caller to
                // start speaking. Plivo spells that startInputTimeout and
                // uses executionTimeout for the whole element instead, so
                // both are passed: capping the turn at the time allowed to
                // begin it would cut a caller off mid-sentence. 60 is the
                // top of Plivo's documented executionTimeout range and is
                // a backstop, not the turn length — the turn still ends
                // when the caller stops talking. Neither option exists on
                // Twilio's side, whose generator ignores what it does not
                // recognise, so nothing there moves.
                'timeout' => $gatherTimeout,
                'startInputTimeout' => $gatherTimeout,
                'executionTimeout' => 60,
                'language' => 'en-US',
                'voice' => $voice,
            ]);

            $pipelineMs = (int) ((microtime(true) - $pipelineStart) * 1000);
            Log::info('AI Agent response generated', [
                'agent_id' => $aiAgent->id,
                'call_id' => $call->id,
                'message' => $message,
                'pipeline_ms' => $pipelineMs, // Total time from webhook receipt to TwiML response
            ]);

            // Cache the generated TwiML briefly so a Twilio retry returns the
            // same response without re-invoking the LLM + TTS.
            cache()->put($idempotencyKey, $twiml, 30);

            return $this->respondWithXml($twiml);
        } catch (\Exception $e) {
            Log::error('AI Agent call error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->respondWithFallback('I\'m sorry, an error occurred. Please try again later.');
        }
    }

    /**
     * Generate TwiML/Plivo XML for a campaign call with an AI agent.
     * $provider is read from the route's own 'provider' parameter — see
     * inboundCall()'s docblock for why it is not a typed method
     * parameter, and aiAgentCall()'s docblock for why the internal
     * signature check only runs for Twilio and for the same
     * not-yet-normalised turn-loop fields this method shares.
     */
    public function campaignCallAiAgent(Request $request): Response
    {
        $provider = $request->route('provider', 'twilio');

        // Twilio's own request signature — see aiAgentCall()'s identical
        // guard for why this only applies to that one provider.
        if ($provider === 'twilio' && ! $this->validateTwilioRequest($request)) {
            Log::warning('Invalid Twilio signature for campaign AI agent call');

            return $this->respondWithFallback('Unauthorized request');
        }

        $callId = $request->query('call_id');

        if (! $callId) {
            return $this->respondWithFallback('Call ID missing');
        }

        $call = Call::with(['campaign.aiAgent', 'campaignContact'])->find($callId);

        if (! $call || ! $call->campaign) {
            return $this->respondWithFallback('Campaign not found');
        }

        $campaign = $call->campaign;
        $aiAgent = $campaign->aiAgent;

        if (! $aiAgent) {
            Log::warning('Campaign has no AI agent assigned', ['campaign_id' => $campaign->id]);

            return $this->respondWithFallback('No AI agent configured for this campaign');
        }

        if (! $aiAgent->active) {
            Log::warning('AI agent is inactive', ['agent_id' => $aiAgent->id]);

            return $this->respondWithFallback('AI agent is not active');
        }

        $this->adoptProviderCallId($request, $provider, $call);
        $this->countAnsweredCampaignCall($provider, $call);

        $fromNumber = $this->webhookNumber($request->input('From'), $provider);
        $toNumber = $this->webhookNumber($request->input('To'), $provider);
        $callSid = $this->providerCallId($request, $provider);
        $speechResult = $this->spokenInput($request, $provider);

        Log::info('Campaign AI Agent Call', [
            'call_id' => $call->id,
            'campaign_id' => $campaign->id,
            'agent_id' => $aiAgent->id,
            'call_sid' => $callSid,
            'speech' => $speechResult,
        ]);

        // Idempotency guard, the same one aiAgentCall() carries: both
        // providers re-post a webhook whose answer they did not get in
        // time, and running the turn again asks the model again — a
        // second reply for a turn the caller spoke once, and a second
        // charge for it. The campaign call is in the key as well as the
        // provider's call id, because this endpoint always has it and two
        // calls whose provider id has not arrived yet would otherwise
        // share an answer.
        $idempotencyKey = 'ai_webhook:'.md5($callId.'|'.$callSid.'|'.($speechResult ?? '_empty_'));
        $cachedTwiml = cache()->get($idempotencyKey);
        if ($cachedTwiml) {
            Log::info('Campaign AI Agent: returning cached TwiML for retried webhook', [
                'call_id' => $call->id,
                'call_sid' => $callSid,
            ]);

            return $this->respondWithXml($cachedTwiml);
        }

        try {
            // Initialize or get existing AI agent call record
            $isNewCall = false;
            $aiAgentCall = AiAgentCall::where('call_sid', $callSid)->first();
            if (! $aiAgentCall) {
                $isNewCall = true;
                $aiAgentCall = AiAgentCall::create([
                    'call_sid' => $callSid,
                    'ai_agent_id' => $aiAgent->id,
                    'direction' => 'outbound',
                    'from_number' => $fromNumber,
                    'to_number' => $toNumber,
                    'status' => 'in-progress',
                    'turn_count' => 0,
                    'started_at' => now(),
                    'answered_at' => now(),
                    'campaign_id' => $campaign->id,
                    'campaign_contact_id' => $call->campaignContact ? $call->campaignContact->id : null,
                ]);
            }

            // Get services
            $conversationEngine = new ConversationEngine($aiAgent);
            $stateManager = new CallStateManager(
                app(DeepgramService::class),
                $conversationEngine
            );

            $voice = $this->mapVoiceForTwilio($aiAgent->voice);

            // Track silence retries in the AiAgentCall.metadata JSON field
            $metadata = is_array($aiAgentCall->metadata) ? $aiAgentCall->metadata : [];
            $silenceCount = (int) ($metadata['silence_count'] ?? 0);

            // Determine what to say
            if ($speechResult) {
                // User spoke - process their speech. The turn counter is
                // the state manager's to keep — see aiAgentCall() above.
                if ($silenceCount > 0) {
                    $metadata['silence_count'] = 0;
                    $aiAgentCall->update(['metadata' => $metadata]);
                }
                $response = $stateManager->processUserInput($aiAgentCall, $speechResult);

                if ($response && $response['action'] === 'transfer' && $aiAgent->enable_transfer) {
                    return $this->rememberTurn($idempotencyKey, $this->respondWithTransfer($aiAgent->transfer_number, $response['text'], $aiAgent->phone_number));
                }

                if ($response && $response['action'] === 'end') {
                    if ($call->campaignContact) {
                        $call->campaignContact->update(['status' => 'completed']);
                    }

                    return $this->rememberTurn($idempotencyKey, $this->respondWithGoodbye($response['text']));
                }

                $message = $response ? $response['text'] : "I'm sorry, I didn't understand that. Could you please repeat?";
            } elseif ($isNewCall) {
                // Brand new call - send personalized greeting
                $message = $this->buildPersonalizedGreeting($aiAgent, $campaign, $call->campaignContact);
            } else {
                // Gather timed out — give the receiver one more chance before hanging up.
                $silenceCount++;
                $metadata['silence_count'] = $silenceCount;
                $aiAgentCall->update(['metadata' => $metadata]);

                if ($silenceCount >= 2) {
                    if ($call->campaignContact) {
                        $call->campaignContact->update(['status' => 'no_answer']);
                    }

                    return $this->rememberTurn($idempotencyKey, $this->respondWithGoodbye("I didn't hear a response. Thank you for your time. Goodbye!"));
                }

                $message = "Are you still there? I didn't catch that — please go ahead.";
            }

            // Generate the next turn's input-gathering document — see
            // aiAgentCall()'s identical block above for what this reproduces.
            // Use the agent's silence_timeout (fallback 10s) so receivers have
            // enough time to process the greeting and start speaking.
            $actionRouteName = $provider === 'twilio' ? 'twiml.campaign.ai-agent' : 'plivo-xml.campaign.ai-agent';
            $actionUrl = route($actionRouteName).'?call_id='.$callId;
            $gatherTimeout = max(5, (int) ($aiAgent->silence_timeout ?? 10));

            $twiml = $this->telephony->callXml()->gather($message, [
                'input' => 'speech',
                'action' => $actionUrl,
                // 'timeout' is how long Twilio waits for the caller to
                // start speaking. Plivo spells that startInputTimeout and
                // uses executionTimeout for the whole element instead, so
                // both are passed: capping the turn at the time allowed to
                // begin it would cut a caller off mid-sentence. 60 is the
                // top of Plivo's documented executionTimeout range and is
                // a backstop, not the turn length — the turn still ends
                // when the caller stops talking. Neither option exists on
                // Twilio's side, whose generator ignores what it does not
                // recognise, so nothing there moves.
                'timeout' => $gatherTimeout,
                'startInputTimeout' => $gatherTimeout,
                'executionTimeout' => 60,
                'language' => 'en-US',
                'voice' => $voice,
            ]);

            Log::info('Campaign AI Agent response generated', [
                'campaign_id' => $campaign->id,
                'agent_id' => $aiAgent->id,
                'call_id' => $call->id,
            ]);

            // Cache the generated document briefly so a retry returns the
            // same answer without re-invoking the model.
            cache()->put($idempotencyKey, $twiml, 30);

            return $this->respondWithXml($twiml);
        } catch (\Exception $e) {
            Log::error('Campaign AI Agent call error', [
                'call_id' => $call->id,
                'campaign_id' => $campaign->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Mark contact as failed
            if ($call->campaignContact) {
                $call->campaignContact->update(['status' => 'failed']);
            }

            return $this->respondWithFallback('I\'m sorry, an error occurred. Goodbye.');
        }
    }

    /**
     * Build personalized greeting for campaign calls
     */
    private function buildPersonalizedGreeting(
        AiAgent $aiAgent,
        Campaign $campaign,
        ?CampaignContact $contact
    ): string {
        // Use agent's configured first_message if available
        if (! empty($aiAgent->first_message)) {
            $greeting = $aiAgent->first_message;

            // Replace placeholders with contact info
            if ($contact) {
                $greeting = str_replace(
                    ['{first_name}', '{last_name}', '{company}'],
                    [
                        $contact->first_name ?? '',
                        $contact->last_name ?? '',
                        $contact->company ?? '',
                    ],
                    $greeting
                );
            }

            return trim($greeting);
        }

        // Fallback: build a basic personalized greeting
        $greeting = 'Hello';
        if ($contact && $contact->first_name) {
            $greeting .= ' '.$contact->first_name;
        }
        $greeting .= '! How can I help you today?';

        return $greeting;
    }

    /**
     * Generate a "speak, try to transfer, apologise, hang up" document.
     *
     * Text is passed to callXml() raw (never pre-escaped here) — its
     * DOMDocument-based implementations escape text content themselves;
     * escaping twice would double-encode "&" into "&amp;amp;". See
     * TwilioCallXmlGeneratorTest for the escaping-equivalence proof.
     *
     * The number is nullable because an agent can have transfers armed
     * and no number filled in: nothing that arms a transfer — the trigger
     * keywords, the enable_transfer switch, the engine's own escalation
     * after repeated model failures — checks that there is anywhere to
     * transfer to. Typed `string` under strict_types, that configuration
     * raised a TypeError, which is not an Exception and so fell straight
     * through both callers' catch blocks: a 500 on the answer URL, and a
     * call dropped mid-sentence without a word to the caller.
     */
    private function respondWithTransfer(?string $transferNumber, string $message, ?string $callerId = null): Response
    {
        $transferNumber = trim((string) $transferNumber);
        $callXml = $this->telephony->callXml();

        // Nowhere to transfer to. The caller hears exactly what they would
        // have heard had the transfer been dialled and gone unanswered —
        // the same two lines, in the same order, without the <Dial>
        // between them — and the call ends the way it does then. The
        // agent's own line is spoken rather than skipped because the state
        // manager has already written it to the transcript as something
        // the agent said, and a transcript of words the caller never heard
        // is a record of a call that did not happen.
        if ($transferNumber === '') {
            Log::warning('AI agent transfer requested with no transfer number configured', [
                'agent_number' => $callerId,
            ]);

            $twiml = $callXml->buildResponse([
                $callXml->say($message, ['voice' => 'Polly.Joanna-Generative', 'language' => null]),
                $callXml->say(self::TRANSFER_UNREACHABLE, ['voice' => 'Polly.Joanna-Generative', 'language' => null]),
                $callXml->hangup(),
            ]);

            return $this->respondWithXml($twiml);
        }

        $twiml = $callXml->buildResponse([
            $callXml->say($message, ['voice' => 'Polly.Joanna-Generative', 'language' => null]),
            $callXml->dial($transferNumber, ['timeout' => 30, 'callerId' => $callerId, 'record' => null, 'bareNumber' => true]),
            $callXml->say(self::TRANSFER_UNREACHABLE, ['voice' => 'Polly.Joanna-Generative', 'language' => null]),
            $callXml->hangup(),
        ]);

        return $this->respondWithXml($twiml);
    }

    /**
     * Generate a "speak, hang up" document.
     */
    private function respondWithGoodbye(string $message): Response
    {
        $callXml = $this->telephony->callXml();

        $twiml = $callXml->buildResponse([
            $callXml->say($message, ['voice' => 'Polly.Joanna-Generative', 'language' => null]),
            $callXml->hangup(),
        ]);

        return $this->respondWithXml($twiml);
    }

    /**
     * Fallback response for an error/unauthorized/not-configured condition.
     */
    private function respondWithFallback(string $message): Response
    {
        $callXml = $this->telephony->callXml();

        $twiml = $callXml->buildResponse([
            $callXml->say($message, ['voice' => 'alice', 'language' => null]),
            $callXml->hangup(),
        ]);

        return $this->respondWithXml($twiml);
    }

    /**
     * The document a waiting caller hears, requested by the provider for
     * as long as they are held: Twilio asks the <Enqueue waitUrl> for it
     * periodically, a conference asks its waitSound URL for it before the
     * room starts.
     *
     * Twilio reports the caller's place in the line as a request
     * parameter. A conference has no line to have a place in, so the
     * position is read from the queue itself, and left unsaid when this
     * call is not in one — better silence on the number than a confident
     * wrong one.
     */
    public function queueWait(Request $request): Response
    {
        $provider = $request->route('provider', 'twilio');
        $callXml = $this->telephony->callXml();

        if ($provider === 'twilio') {
            $queuePosition = $request->input('QueuePosition', '1');

            $twiml = $callXml->buildResponse([
                $callXml->say("You are number {$queuePosition} in the queue. Please hold.", ['voice' => 'alice', 'language' => null]),
                $callXml->play(self::QUEUE_HOLD_MUSIC_URL),
            ]);

            return $this->respondWithXml($twiml);
        }

        $position = app(CallQueueService::class)
            ->waitingPositionOf((string) $this->providerCallId($request, $provider));

        $twiml = $callXml->buildResponse([
            $callXml->say(
                $position === null
                    ? 'Please hold. An agent will be with you shortly.'
                    : "You are number {$position} in the queue. Please hold.",
                ['voice' => 'alice', 'language' => null]
            ),
            // loop 0 — keep playing until the room starts or the caller
            // hangs up. Twilio re-requests this document when it runs out;
            // a conference's waitSound is fetched once, so a document that
            // ends leaves the caller on a silent line.
            $callXml->play(self::QUEUE_HOLD_MUSIC_URL, ['loop' => 0]),
        ]);

        return $this->respondWithXml($twiml);
    }

    /**
     * Requested when a caller leaves the queue, whichever way they left
     * it.
     *
     * Twilio says why: 'bridged' means an agent took them, anything else
     * means they are gone. A conference's action URL fires on the way out
     * with no such verdict — the room ends both when the caller hangs up
     * and when the agent does — so the answer comes from the entry
     * instead: a caller who was already connected is not an abandoned
     * one, and abandon() leaves such an entry alone.
     */
    public function queueResult(Request $request): Response
    {
        $provider = $request->route('provider', 'twilio');
        $queueService = app(CallQueueService::class);

        if ($provider === 'twilio') {
            $queueResult = $request->input('QueueResult');
            $callSid = $request->input('CallSid');

            Log::info('Queue result received', [
                'result' => $queueResult,
                'call_sid' => $callSid,
            ]);

            if ($queueResult === 'bridged') {
                $queueService->markConnected($callSid);
            } elseif (in_array($queueResult, ['queue-full', 'leave', 'error', 'hangup', 'system-error'])) {
                $queueService->abandon($callSid);
            }
        } else {
            $callId = (string) $this->providerCallId($request, $provider);

            Log::info('Queue result received', [
                'provider' => $provider,
                'call_sid' => $callId,
            ]);

            $queueService->abandon($callId);
        }

        $twiml = $this->telephony->callXml()->buildResponse([]);

        return $this->respondWithXml($twiml);
    }

    /**
     * The end of the agent's own leg.
     *
     * An agent who lets the call ring out has to give the caller back: on
     * a conference-based provider the room is bound to one queue entry,
     * so a caller left mid-dequeue is one no later dequeue can reach.
     * Which entry the leg was fetching travels in the URL it was dialled
     * with, exactly as it does on the agent's answer URL above.
     *
     * The URL is the whole signal. This one is given to the provider as a
     * hangup notification only, so being asked for it means the leg has
     * ended; nothing in the body is interpreted, and nothing in it could
     * change the answer. The entry and the attempt it names are both
     * ours, put there when the leg was dialled and attested by the
     * signature over the whole URL.
     *
     * Nothing is expected back — a Plivo hangup URL wants a 200 and no
     * instructions — so this answers with the same empty acknowledgement
     * queueResult() does.
     */
    public function queueAgentHangup(Request $request): Response
    {
        // A request naming no attempt, or naming something that is not a
        // whole number, matches nothing: the column is unsigned, so no
        // row can hold -1. Reading it as 0 instead would let a bare
        // ?entry_id= URL match a caller nobody has ever dequeued.
        $attempt = filter_var($request->input('attempt'), FILTER_VALIDATE_INT);

        app(CallQueueService::class)->returnToQueueIfUnanswered(
            (int) $request->input('entry_id'),
            $attempt === false ? -1 : $attempt,
        );

        return $this->respondWithXml($this->telephony->callXml()->buildResponse([]));
    }

    /**
     * Dequeue call document — used to connect the agent's browser to the
     * next queued caller. Called when the agent finishes a call and
     * triggers dequeue.
     */
    public function dequeueCall(Request $request): Response
    {
        $provider = $request->route('provider', 'twilio');
        $queueName = $request->input('queue_name', '');
        $queueService = app(CallQueueService::class);

        Log::info('Dequeue call TwiML requested', ['queue_name' => $queueName]);

        if ($provider !== 'twilio') {
            // A conference-based provider joins one caller's own room, so
            // the entry travels with the URL the agent was dialled
            // through. Being asked for the document IS the agent picking
            // up, which is the moment the caller stops waiting.
            //
            // The URL names which of that caller's legs is asking as well
            // as which caller, and both have to match. A leg dialled for
            // an attempt the caller has moved past is one whose agent was
            // already given them back — answering it with a room would
            // put a second agent in the room the agent who holds them now
            // is joining. Such a request is refused exactly as a request
            // naming no caller at all is: the same hangup document, and
            // nothing recorded.
            //
            // A missing or non-integer attempt is read the same way
            // queueAgentHangup() reads it: -1, which an unsigned column
            // can never hold, so it matches nothing. Reading it as 0
            // instead would let a bare ?entry_id= URL into the room of a
            // caller nobody has ever dequeued.
            //
            // A connected entry still answers with its room: this
            // provider retries a webhook it got no answer to and can
            // deliver the same one twice, and the retry has to reach the
            // same room rather than hang up on an agent who has already
            // been put through. A caller who abandoned or timed out is
            // genuinely gone, and there is no room left to join.
            $attempt = filter_var($request->input('attempt'), FILTER_VALIDATE_INT);
            $attempt = $attempt === false ? -1 : $attempt;

            $entry = CallQueueEntry::whereKey((int) $request->input('entry_id'))
                ->where('dequeue_attempt', $attempt)
                ->whereIn('status', ['waiting', 'connecting', 'connected'])
                ->first();

            if (! $entry) {
                Log::warning('Dequeue call requested for no waiting caller', [
                    'provider' => $provider,
                    'queue_name' => $queueName,
                ]);
            } else {
                $queueService->markConnectedById((int) $entry->getKey(), $attempt);
            }

            return $this->respondWithXml($queueService->xmlForAgentJoin($queueName, $entry));
        }

        // This route is Twilio's own, and its document is Twilio's own
        // noun. Reaching it on an installation locked elsewhere means
        // something is pointed at the wrong URL — hang up gracefully
        // rather than answer with XML the active provider cannot parse.
        if ($this->telephony->provider() !== TelephonyProvider::TWILIO) {
            Log::warning('Dequeue call requested on a provider with no queue support', [
                'provider' => $this->telephony->provider()->value,
                'queue_name' => $queueName,
            ]);

            return $this->respondWithXml($this->telephony->callXml()->hangup());
        }

        return $this->respondWithXml($queueService->xmlForAgentJoin($queueName));
    }

    /**
     * File a turn's answer under its idempotency key on the way out.
     *
     * The guard at the top of both AI-agent endpoints only ever sees a
     * turn that was cached, and the cache was written at the very end of
     * the method — so every branch that answered the caller earlier left
     * nothing behind, and a re-post of that turn ran it again: a second
     * user row and a second assistant row on the transcript, another
     * turn_count, and another charge from the model provider for words
     * the caller spoke once. The transfer branch is the likeliest of them
     * to be re-posted, being the one that has just waited on the model.
     *
     * The bytes filed are the bytes sent, so a retry is answered with the
     * document it is a retry of rather than a rebuilt lookalike.
     */
    private function rememberTurn(string $idempotencyKey, Response $response): Response
    {
        cache()->put($idempotencyKey, (string) $response->getContent(), 30);

        return $response;
    }

    /**
     * Wrap a generated call-control document (TwiML on Twilio, Plivo XML
     * on Plivo) in the response headers the active provider expects.
     */
    private function respondWithXml(string $twiml): Response
    {
        // Log only document length for debugging (avoid logging full XML to prevent response bloat)
        Log::debug('Call control XML response generated', [
            'size_bytes' => strlen($twiml),
            'size_kb' => round(strlen($twiml) / 1024, 2),
        ]);

        return response($twiml, 200, [
            'Content-Type' => 'application/xml; charset=utf-8',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }

    /**
     * The provider's own call identifier from an incoming request —
     * Twilio's CallSid, or (via the normaliser, never a hand-rolled
     * ['CallUUID'] lookup) whatever the given provider's own field is.
     *
     * $provider is bound from the route rather than read from
     * TelephonyManager::provider() (the current lock) — see
     * inboundCall()'s docblock for why: which field name a webhook's own
     * identifier arrives under is a property of who actually sent this
     * specific request, not of what the installation happens to be
     * locked to right now.
     */
    private function providerCallId(Request $request, string $provider): ?string
    {
        if ($provider === 'twilio') {
            return $request->input('CallSid');
        }

        return $this->telephony
            ->webhookNormalizer(TelephonyProvider::tryFrom($provider) ?? TelephonyProvider::TWILIO)
            ->extractCallId($request->all());
    }

    /**
     * What the caller just said, from whichever field the provider that
     * sent this request puts it in: Twilio's <Gather input="speech">
     * posts SpeechResult, Plivo's <GetInput inputType="speech"> posts
     * Speech. The conversation engine reads one value and does not know
     * or care which provider produced it.
     *
     * Twilio's value is returned exactly as it arrives — an empty result
     * has always been falsy here and the silence branch depends on it.
     * Plivo's is trimmed to null when it holds nothing but whitespace,
     * since a GetInput that heard nothing intelligible is silence, not a
     * turn to hand to the engine.
     */
    private function spokenInput(Request $request, string $provider): ?string
    {
        if ($provider === 'twilio') {
            return $request->input('SpeechResult');
        }

        $speech = $request->input('Speech');

        if (! is_string($speech) || trim($speech) === '') {
            return null;
        }

        return trim($speech);
    }

    /**
     * The call's direction in this application's own vocabulary, which
     * spells an outbound call 'outbound-api' — Twilio's own word for it,
     * and what the agent endpoints compare against to decide whether the
     * agent owns the From number or the To number. Plivo simply says
     * 'outbound', so an unnormalised Plivo outbound call looked its agent
     * up by the number it was calling, which belongs to the person being
     * called and to no agent at all.
     *
     * Normalised through the provider's own webhook normaliser rather
     * than with a second mapping table here, so there is one place that
     * decides what a provider's direction means. An absent or unmappable
     * direction keeps the pre-existing default.
     */
    private function webhookDirection(Request $request, string $provider): string
    {
        if ($provider === 'twilio') {
            return $request->input('Direction', 'inbound');
        }

        $direction = $this->telephony
            ->webhookNormalizer(TelephonyProvider::tryFrom($provider) ?? TelephonyProvider::TWILIO)
            ->normalizeCallEvent($request->all())['direction'];

        return $direction !== '' ? $direction : 'inbound';
    }

    /**
     * A phone number read off a webhook, in this application's own
     * convention. Plivo sends numbers without the leading '+' every stored
     * number carries; Twilio sends them with it, and its values pass through
     * untouched. PlivoNumberFormat::toE164() only prefixes a bare digit
     * string, so a SIP URI or a withheld caller ID is never mangled.
     */
    private function webhookNumber(?string $value, string $provider): ?string
    {
        if ($value === null || $provider === 'twilio') {
            return $value;
        }

        return PlivoNumberFormat::toE164($value);
    }

    /**
     * Whether a call was placed by a browser softphone. Twilio's Voice SDK
     * calls come From "client:<identity>"; a Plivo Browser SDK call comes
     * from the user's Endpoint, named either by its full SIP URI or by its
     * bare username.
     */
    private function isBrowserOriginated(?string $from, string $provider): bool
    {
        if ($from === null) {
            return false;
        }

        if ($provider === 'twilio') {
            return str_starts_with($from, 'client:');
        }

        // Not a bare "sip:" test. Which form Plivo presents an Endpoint in on
        // a browser call — the full SIP URI or the bare username — cannot be
        // observed here without a live account, so both are accepted by
        // matching the shape of the usernames this application creates. A
        // PSTN caller's number never matches it.
        return PlivoEndpointProvisioner::userIdFromIdentity($from) !== null;
    }

    /**
     * The user whose browser sent this request, or null if that cannot be
     * established.
     *
     * Twilio asserts the identity itself: the client identity comes from the
     * access token this application minted, inside a signature-validated
     * request, so reading it is enough. Plivo presents an Endpoint, whose
     * username merely NAMES a user — so it is checked against what is stored
     * for that user, or anyone could claim to be anyone by spelling the
     * pattern.
     */
    private function browserUserId(?string $from, string $provider): ?int
    {
        if ($from === null) {
            return null;
        }

        if ($provider === 'twilio') {
            return preg_match('/^client:user_(\\d+)$/', $from, $matches) === 1 ? (int) $matches[1] : null;
        }

        $userId = PlivoEndpointProvisioner::userIdFromIdentity($from);
        $user = $userId ? User::find($userId) : null;

        if (! $user) {
            return null;
        }

        $stored = $this->plivoEndpoints->storedFor($user);

        if (! is_array($stored) || ($from !== $stored['sip_uri'] && $from !== $stored['username'])) {
            return null;
        }

        return $userId;
    }

    /**
     * This application's own id for the call a browser is placing.
     *
     * Twilio forwards the browser's connect parameters as ordinary request
     * fields. Plivo's Browser SDK can only pass data as custom SIP headers,
     * which reach the answer URL as X-PH-<name> parameters, and Plivo allows
     * only letters and digits in a header name — hence X-PH-CallId rather
     * than call_id. Matched without regard to case, and accepted only as
     * digits. A call_id field still wins on Plivo too, which is how calls
     * this application placed through the API arrive.
     *
     * Deliberately untyped on the way out: the call_id field is returned
     * exactly as the request carried it, which is what this method's callers
     * always received before it existed. A form post delivers a string, but
     * a JSON body delivers an integer, and narrowing the type here would turn
     * that into a TypeError on Twilio's live manual-call path.
     */
    private function browserCallId(Request $request, string $provider): mixed
    {
        $callId = $request->input('call_id') ?? $request->query('call_id');

        if ($provider === 'twilio' || $callId !== null) {
            return $callId;
        }

        foreach ($request->all() as $key => $value) {
            if (is_string($key) && strcasecmp($key, 'X-PH-CallId') === 0 && is_string($value) && ctype_digit($value)) {
                return $value;
            }
        }

        return null;
    }

    /**
     * Whether the browser that sent this request may place this call.
     *
     * The acting user is established first (see browserUserId(), which also
     * authenticates a Plivo identity against stored state). They may place
     * the call if they own it, if they administer the installation, or if
     * they and the owner are one business — a customer and the agents beneath
     * them. Two unrelated businesses are refused, which is the boundary this
     * exists for.
     */
    private function browserOwnsCall(?string $from, string $provider, Call $call): bool
    {
        $actingId = $this->browserUserId($from, $provider);

        if ($actingId === null) {
            return false;
        }

        if ($actingId === (int) $call->user_id) {
            return true;
        }

        $acting = User::find($actingId);
        $owner = $call->user_id ? User::find($call->user_id) : null;

        if (! $acting || ! $owner) {
            return false;
        }

        // An administrator already controls the whole installation, so this
        // grants nothing new — and refusing it would break impersonation,
        // which is a client-side visit: the browser stays registered as the
        // admin while the call is created under the impersonated user.
        if ($acting->isAdmin()) {
            return true;
        }

        // A customer and the agents beneath them are one business, sharing the
        // same numbers and the same balance. The boundary worth enforcing is
        // the one between businesses.
        return ($acting->parent_user_id !== null && (int) $acting->parent_user_id === (int) $owner->id)
            || ($owner->parent_user_id !== null && (int) $owner->parent_user_id === (int) $acting->id);
    }

    /**
     * The URL of one of the shared CallController webhook routes
     * (webhooks.twilio.* / webhooks.plivo.*) this controller points a
     * generated document's own callback attributes at. Driven by
     * $provider (the route that reached this method), not the installation's
     * current lock — a document already in flight must keep pointing at the
     * URL its own provider actually calls back to, even if the lock changes
     * mid-call.
     */
    private function callbackRoute(string $provider, string $suffix, array $params = []): string
    {
        $name = $provider === 'twilio' ? "webhooks.twilio.{$suffix}" : "webhooks.plivo.{$suffix}";

        return route($name, $params);
    }

    /**
     * The dial-complete route for $provider. dial()/dialClient() map
     * this onto the <Dial> element's own action attribute (Plivo
     * defaults its redirect to true, exactly like the shared webhook
     * routes callbackRoute() targets), so it must resolve to a URL
     * signed for whichever provider is actually placing this call —
     * pointing Plivo at the Twilio-signed twiml.dial-complete would
     * reject Plivo's own fetch of it and end the call the moment the
     * dial finishes.
     */
    /**
     * Take the provider's own call identifier off an answer-URL request
     * and store it on the call.
     *
     * This is the first request that carries it for a Plivo call: the API
     * that placed the call could only return a request identifier, and
     * the real CallUUID arrives either on the ring notification or here.
     * If the ring notification is lost — and nothing guarantees it —
     * this is the only place the call ever learns its own identifier, so
     * without it the row keeps the placeholder until hangup and anything
     * that addresses the call by identifier, ending it above all, fails
     * on a call that is still up. An AI-agent campaign call, which can
     * run for minutes, is where that costs the most.
     *
     * Call::shouldAdoptProviderCallId(), not a bare "column is empty"
     * check: the actions that place a Plivo call seed twilio_call_sid
     * with the RequestUUID placeholder at creation, so the column is
     * never empty by the time Plivo fetches an answer URL — a bare
     * emptiness check could never fire for a call this application placed
     * itself, only for one it never touched at all.
     *
     * On Twilio that same helper is still the plain emptiness check it
     * always was, and a Twilio call carries its real SID before the
     * callee can answer, so this does nothing there.
     */
    private function adoptProviderCallId(Request $request, string $provider, Call $call): void
    {
        $providerCallId = $this->providerCallId($request, $provider);

        if (! $providerCallId || ! $call->shouldAdoptProviderCallId($call->twilio_call_sid)) {
            return;
        }

        $call->update($provider === 'twilio'
            ? ['twilio_call_sid' => $providerCallId, 'status' => 'ringing']
            : [
                'twilio_call_sid' => $providerCallId,
                'provider_call_sid' => $providerCallId,
                'provider' => $provider,
                'status' => 'ringing',
            ]);

        Log::info('TwiML: Updated call with provider call id', [
            'call_id' => $call->id,
            'provider_call_id' => $providerCallId,
        ]);
    }

    /**
     * Count a Plivo campaign call as "called" the moment its answer URL is
     * actually fetched.
     *
     * UpdateCallStatusAction::updateCampaignStatistics() moves
     * total_called on an 'in-progress' transition. A Plivo campaign call
     * never makes one: Plivo offers ring_url / answer_url / hangup_url and
     * no "answered" notification of its own, and a campaign document
     * contains no <Dial>, so nothing emits the dial event that normalises
     * to 'in-progress'. A customer watching a running campaign on Plivo
     * therefore saw Called: 0 for its whole life. Being asked for the
     * answer URL *is* Plivo saying the call was answered, so that is the
     * honest moment to count it.
     *
     * Twilio is left alone deliberately: its own trigger already fires,
     * and counting here as well would double every Twilio installation's
     * figure.
     *
     * Idempotent, and by the same marker Twilio's trigger reads — an
     * answer URL can be fetched more than once for one call (a <Redirect>
     * back to it, a provider retry). The conditional UPDATE is what makes
     * it safe: whichever concurrent request wins the whereNull() wins the
     * increment too, and every other one sees zero affected rows and does
     * nothing. Sharing started_at with UpdateCallStatusAction's
     * $hadStartedAt also means a status callback that later does report
     * the transition cannot add a second count.
     */
    private function countAnsweredCampaignCall(string $provider, Call $call): void
    {
        if ($provider === 'twilio' || ! $call->campaign_id) {
            return;
        }

        $claimed = DB::table('calls')
            ->where('id', $call->id)
            ->whereNull('started_at')
            ->update(['started_at' => now()]);

        if ($claimed !== 1) {
            return;
        }

        DB::table('campaigns')->where('id', $call->campaign_id)->increment('total_called');

        Log::info('Campaign call counted as called on answer', [
            'call_id' => $call->id,
            'campaign_id' => $call->campaign_id,
            'provider' => $provider,
        ]);
    }

    private function dialCompleteRoute(string $provider, int $callId): string
    {
        $name = $provider === 'twilio' ? 'twiml.dial-complete' : 'plivo-xml.dial-complete';

        return route($name, ['call' => $callId]);
    }
}
