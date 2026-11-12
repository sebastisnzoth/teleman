<?php

/**
 * Multi-provider telephony configuration.
 *
 * A buyer picks one telephony provider — Twilio or Plivo — at install time
 * and the app locks to it. Twilio's own settings keep living in
 * config/twilio.php; this file holds the provider switch, Plivo-specific
 * settings, and which Twilio-only features to hide when a buyer is locked
 * to Plivo.
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Default Provider
    |--------------------------------------------------------------------------
    |
    | Which telephony provider the app is locked to.
    |
    */
    'default' => env('TELEPHONY_PROVIDER', 'twilio'),

    /*
    |--------------------------------------------------------------------------
    | Provider-Specific Settings
    |--------------------------------------------------------------------------
    |
    | Twilio settings live in config/twilio.php. This holds Plivo's.
    |
    */
    'providers' => [

        'plivo' => [
            // Escape hatch for local/testing use — mirrors twilio.skip_signature_validation.
            'skip_signature_validation' => env('PLIVO_SKIP_SIGNATURE_VALIDATION', false),

            'tts' => [
                'default_voice' => env('PLIVO_TTS_VOICE', 'WOMAN'),
                'default_language' => env('PLIVO_TTS_LANGUAGE', 'en-US'),
            ],

            // Read by PlivoVoiceProvider::estimateCallCost(). Mirrors
            // twilio.pricing.api_cost_per_minute's shape; the default is a
            // representative Plivo US outbound per-minute rate, not a
            // live-pricing guarantee — installs should override it.
            'pricing' => [
                'api_per_minute' => (float) env('PLIVO_API_COST_PER_MINUTE', 0.010),
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Hidden Features
    |--------------------------------------------------------------------------
    |
    | Twilio-only features hidden from the UI when a buyer is locked to Plivo.
    |
    */
    'hidden_features' => [
        // call_hold: putting the remote party on hold redirects the PSTN leg
        // through the raw Twilio SDK to a hold-music document and back again
        // (CallController::hold(), twiml.hold-music, twiml.resume-call). None
        // of it goes through TelephonyManager, and neither the hold-music nor
        // the resume-call document has a plivo-xml sibling to be redirected
        // to, so on Plivo the softphone's hold button cannot work at all.
        //
        // ai_agent_outbound_call: placing an outbound AI-agent call builds a
        // raw Twilio client from TwilioGlobalConfig and calls
        // $client->calls->create() directly — the call's answer and callback
        // URLs are already provider-aware, but nothing places the call on any
        // provider but Twilio. Until that goes through TelephonyManager, a
        // Plivo buyer is better served by an absent button than by a live one
        // that fails.
        //
        // answered_stats: campaigns.total_answered counts only the calls a
        // provider's answering-machine detection confirmed a human (or a
        // machine) picked up — UpdateCallStatusAction increments it solely
        // when the status callback carried an answered_by. Plivo bills
        // detection separately and this product does not turn it on, so
        // nothing ever populates answered_by there and the counter is
        // permanently 0. Beside a working "Called: 120" that reads as a
        // broken product rather than as a metric nobody is paying for, so
        // every answered-derived figure taken from that column is left out
        // instead. Not a claim that Plivo cannot detect answering machines
        // — PlivoVoiceProvider passes machine_detection through when asked
        // — only that nothing asks, so the column stays empty.
        //
        // ai_agent_number_webhooks: writes voiceUrl/statusCallback onto one
        // rented number. That is a Twilio-shaped operation with no Plivo
        // equivalent: a Plivo number carries no per-number URLs — they live
        // on the Plivo Application the number is attached to, which is why
        // PlivoPhoneNumberProvider::updateNumberConfig() accepts only app_id
        // and alias. On Plivo the work this route does is done already, for
        // every number at once, by PlivoApplicationProvisioner and the app_id
        // PhoneNumberService attaches at purchase — so there is nothing here
        // for a Plivo buyer to run, not merely nothing that works yet.
        'plivo' => ['byoc', 'sip_trunk', 'account_migration', 'geo_permissions', 'twilio_console_commands', 'webrtc_token', 'call_hold', 'ai_agent_outbound_call', 'ai_agent_number_webhooks', 'answered_stats'],
        'twilio' => [],
    ],

];
