<?php

declare(strict_types=1);

namespace App\Contracts\Telephony;

/**
 * Provider-agnostic interface for generating call XML instructions.
 *
 * Twilio uses TwiML, Plivo uses Plivo XML — this interface normalizes
 * both into a single API that returns XML strings.
 */
interface CallXmlInterface
{
    /**
     * Generate XML for text-to-speech.
     *
     * @param  string  $text  The text to speak
     * @param  array  $options  voice, language (pass `null` explicitly to omit
     *                          the attribute rather than defaulting it — see
     *                          the Twilio implementation's docblock), gender,
     *                          loop, dtmf, record
     * @return string Valid XML string
     */
    public function say(string $text, array $options = []): string;

    /**
     * Generate XML for playing an audio file.
     *
     * @param  string  $audioUrl  URL of audio file to play
     * @param  array  $options  loop, dtmf, record
     * @return string Valid XML string
     */
    public function play(string $audioUrl, array $options = []): string;

    /**
     * Generate XML to dial/connect a call to another number.
     *
     * @param  string  $number  Number to dial
     * @param  array  $options  callerId, timeout, record (pass `null`
     *                          explicitly to omit the attribute rather than
     *                          defaulting it), statusCallback,
     *                          recordingStatusCallback, transcribeCallback,
     *                          numberStatusCallback + numberStatusCallbackEvents
     *                          (attached to the nested Number/User child on
     *                          Twilio. Plivo has no per-leg callback on the
     *                          child, but does document callbackUrl on <Dial>
     *                          itself for real-time dial events, and its
     *                          implementation maps these onto it)
     * @return string Valid XML string
     */
    public function dial(string $number, array $options = []): string;

    /**
     * Generate XML to dial/connect a call to a browser/WebRTC client.
     *
     * @param  string  $clientIdentity  The registered client identity (e.g. "user_123")
     * @param  array  $options  callerId, timeout, record, statusCallback,
     *                          clientStatusCallback + clientStatusCallbackEvents
     *                          (attached to the nested Client/User child — see
     *                          dial()'s numberStatusCallback)
     * @return string Valid XML string
     */
    public function dialClient(string $clientIdentity, array $options = []): string;

    /**
     * Generate XML to put this call into a named conference room.
     *
     * The room is identified by name alone: every call handed the same
     * name joins the same room. Both providers document a moderated
     * "waiting room" built from exactly two of these options — a member
     * who joins with `startConferenceOnEnter` false waits, hearing
     * `waitUrl`, until a member who joins with it true starts the room.
     *
     * @param  string  $roomName  Name of the room to join
     * @param  array  $options  startConferenceOnEnter, endConferenceOnExit,
     *                          waitUrl (the document played while waiting
     *                          for the room to start — Plivo spells this
     *                          waitSound), maxParticipants (Plivo spells
     *                          this maxMembers and caps it at 20),
     *                          muted, action (called when this member
     *                          leaves), stayAlone (Plivo only — whether
     *                          this member may remain in the room alone;
     *                          Twilio has no equivalent and ignores it)
     * @return string Valid XML string
     */
    public function conference(string $roomName, array $options = []): string;

    /**
     * Generate XML to collect input from the caller.
     *
     * @param  string  $prompt  Text or URL to play while gathering
     * @param  array  $options  input ('dtmf', the default, or 'speech' — the
     *                          AI voice agent's turn loop; Plivo's
     *                          implementation also accepts 'dtmf speech'),
     *                          numDigits, timeout, finishOnKey, action, voice,
     *                          language (speech mode only)
     * @return string Valid XML string
     */
    public function gather(string $prompt, array $options = []): string;

    /**
     * Generate XML to record the call.
     *
     * @param  array  $options  maxLength, action, transcribe, playBeep
     * @return string Valid XML string
     */
    public function record(array $options = []): string;

    /**
     * Generate XML to pause/wait.
     *
     * @param  int  $seconds  Seconds to wait
     * @return string Valid XML string
     */
    public function pause(int $seconds = 1): string;

    /**
     * Generate XML to hangup the call.
     *
     * @return string Valid XML string
     */
    public function hangup(): string;

    /**
     * Generate XML to redirect call flow to a new URL.
     *
     * @param  string  $url  URL to redirect to
     * @return string Valid XML string
     */
    public function redirect(string $url): string;

    /**
     * Generate XML for media streaming (bidirectional audio).
     *
     * @param  string  $websocketUrl  WebSocket URL for streaming
     * @param  array  $options  track (inbound/outbound/both), statusCallback
     * @return string Valid XML string
     */
    public function stream(string $websocketUrl, array $options = []): string;

    /**
     * Build a combined XML response from multiple instructions.
     *
     * @param  array  $instructions  Array of XML instruction strings
     * @return string Complete XML response document
     */
    public function buildResponse(array $instructions): string;

    /**
     * Generate a complete manual/browser call XML response.
     *
     * @param  string  $phoneNumber  Number to dial
     * @param  array  $options  callerId, record, statusCallback, timeout
     * @return string Complete XML response
     */
    public function generateManualCallXml(string $phoneNumber, array $options = []): string;

    /**
     * Generate a complete campaign TTS call XML response.
     *
     * @param  string  $text  Script text (with variables already replaced)
     * @param  array  $options  voice, language, dtmf settings, record settings
     * @return string Complete XML response
     */
    public function generateCampaignTtsXml(string $text, array $options = []): string;

    /**
     * Generate error response.
     *
     * @param  string  $message  Error message to speak
     * @return string Complete XML response
     */
    public function generateErrorXml(string $message): string;

    /**
     * Get the provider name.
     */
    public function getProviderName(): string;
}
