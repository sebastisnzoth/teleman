<?php

declare(strict_types=1);

namespace App\Services;

use Twilio\TwiML\VoiceResponse;

/**
 * Legacy Twilio TwiML builder.
 *
 * As of the provider abstraction (App\Contracts\Telephony\CallXmlInterface,
 * resolved via App\Services\Telephony\TelephonyManager::callXml()),
 * TwimlController no longer calls this class at all — every endpoint it
 * used to serve now goes through App\Services\Telephony\Twilio\
 * TwilioCallXmlGenerator, which was proven byte-identical to this class's
 * methods for the shapes that had a live caller (see
 * tests/Unit/Services/Telephony/TwilioCallXmlGeneratorTest's correspondence
 * table). This class is kept for two reasons: DtmfWebhookController still
 * calls generateTts(), generateTransfer() and generateErrorResponse()
 * directly (out of scope for the task that ported TwimlController — a
 * later task should route it through callXml() too), and the parity tests
 * above compare against a real instance of this class as their safety net.
 * Once DtmfWebhookController is migrated, this class can be deleted and
 * those tests re-anchored as plain literal-string pins.
 */
class TwiMLService
{
    /**
     * Generate TwiML for text-to-speech
     */
    public function generateTts(
        string $text,
        array $options = []
    ): string {
        $response = new VoiceResponse();
        
        $voice = $options['voice'] ?? 'Polly.Joanna';
        $language = $options['language'] ?? 'en-US';
        $enableDtmf = $options['enable_dtmf'] ?? false;
        $enableRecording = $options['enable_recording'] ?? false;

        // Say the message
        $response->say(
            $text,
            [
                'voice' => $voice,
                'language' => $language,
            ]
        );

        // Add DTMF gathering if enabled
        if ($enableDtmf) {
            $gather = $response->gather([
                'numDigits' => $options['dtmf_digits'] ?? 1,
                'timeout' => $options['dtmf_timeout'] ?? 5,
                'action' => $options['dtmf_callback'] ?? route('twiml.webhook.dtmf'),
                'method' => 'POST',
            ]);

            $gather->say(
                $options['dtmf_prompt'] ?? 'Press any key to respond.',
                [
                    'voice' => $voice,
                    'language' => $language,
                ]
            );
        }

        // Add recording if enabled
        if ($enableRecording) {
            $response->record([
                'maxLength' => $options['recording_max_length'] ?? 30,
                'action' => $options['recording_callback'] ?? route('twiml.webhook.recording'),
                'method' => 'POST',
            ]);
        }

        return (string) $response;
    }

    /**
     * Generate TwiML for voice file playback
     */
    public function generateVoicePlayback(
        string $audioUrl,
        array $options = []
    ): string {
        $response = new VoiceResponse();
        
        $enableDtmf = $options['enable_dtmf'] ?? false;
        $enableRecording = $options['enable_recording'] ?? false;

        // Play the audio file
        $response->play($audioUrl);

        // Add DTMF gathering if enabled
        if ($enableDtmf) {
            $gather = $response->gather([
                'numDigits' => $options['dtmf_digits'] ?? 1,
                'timeout' => $options['dtmf_timeout'] ?? 5,
                'action' => $options['dtmf_callback'] ?? route('twiml.webhook.dtmf'),
                'method' => 'POST',
            ]);

            $gather->say(
                $options['dtmf_prompt'] ?? 'Press any key to respond.'
            );
        }

        // Add recording if enabled
        if ($enableRecording) {
            $response->record([
                'maxLength' => $options['recording_max_length'] ?? 30,
                'action' => $options['recording_callback'] ?? route('twiml.webhook.recording'),
                'method' => 'POST',
            ]);
        }

        return (string) $response;
    }

    /**
     * Generate TwiML for Softphone
     */
    public function generateManualCallTwiml(
        string $phoneNumber,
        array $options = []
    ): string {
        $response = new VoiceResponse();

        $dial = $response->dial('', [
            'timeout' => $options['timeout'] ?? 60,
            'record' => $options['record'] ?? 'do-not-record',
            'recordingStatusCallback' => $options['recording_callback'] ?? null,
        ]);

        $dial->number($phoneNumber);

        return (string) $response;
    }

    /**
     * Generate TwiML for DTMF response
     */
    public function generateDtmfResponse(string $digit): string
    {
        $response = new VoiceResponse();

        switch ($digit) {
            case '1':
                $response->say('Connecting you to a representative. Please hold.');
                // In production, connect to agent queue
                break;
            case '2':
                $response->say('You have been removed from our list. Goodbye.');
                $response->hangup();
                break;
            default:
                $response->say('Invalid selection. Goodbye.');
                $response->hangup();
        }

        return (string) $response;
    }

    /**
     * Generate TwiML for error handling
     */
    public function generateErrorResponse(string $message = null): string
    {
        $response = new VoiceResponse();
        
        $message = $message ?? 'We apologize, but an error occurred. Please try again later.';
        
        $response->say($message);
        $response->hangup();

        return (string) $response;
    }

    /**
     * Generate TwiML for transferring call to another number
     */
    public function generateTransfer(string $phoneNumber, string $message = null): string
    {
        $response = new VoiceResponse();
        
        if ($message) {
            $response->say($message);
        }
        
        $dial = $response->dial('', [
            'timeout' => 30,
            'record' => 'do-not-record',
        ]);
        
        $dial->number($phoneNumber);

        return (string) $response;
    }
}
