<?php

declare(strict_types=1);

namespace App\Services\Telephony\Twilio;

use App\Contracts\Telephony\CallXmlInterface;
use Twilio\TwiML\VoiceResponse;

/**
 * Twilio TwiML generator behind the provider-agnostic CallXmlInterface.
 *
 * This is now the live Twilio XML surface: TwimlController builds every
 * response through TelephonyManager::callXml() rather than calling
 * App\Services\TwiMLService or building TwiML inline. DtmfWebhookController
 * is the one remaining direct caller of TwiMLService (out of scope for the
 * change that routed TwimlController through here — see TwiMLService's own
 * docblock).
 *
 * Several methods here were written to emit byte-identical XML to a
 * corresponding legacy TwiMLService method for equivalent input — see
 * tests/Unit/Services/Telephony/TwilioCallXmlGeneratorTest for the
 * byte-for-byte assertions and the full correspondence notes. That
 * remains true and is the safety net for TwimlController's own endpoints
 * that had a live TwiMLService counterpart (say()/generateTts(),
 * play()/generateVoicePlayback(), generateCampaignTtsXml()/generateTts()
 * again, generateErrorXml()/generateErrorResponse(), dial()/
 * generateTransfer()). The other methods TwimlController now calls —
 * dialClient(), gather() (including its 'speech' input mode, the AI voice
 * agent's turn loop), and dial()'s/dialClient()'s per-child status
 * callback options — never had a TwiMLService shape to begin with
 * (TwimlController built them by hand, inline); those are proven against
 * a literal reproduction of what TwimlController built before, using
 * assertXmlStringEqualsXmlString() rather than assertSame(), since this
 * class's DOMDocument-based serialization is compact while the original
 * hand-rolled heredocs were human-indented — see that test file's newer
 * section for why byte-for-byte was never the right bar for those.
 *
 * generateManualCallTwiml() (TwiMLService) / generateManualCallXml()
 * (here) still has no live caller anywhere in this app (verified: `grep`
 * across app/, routes/, tests/ finds none) — TwimlController's real
 * manual-call endpoint builds a differently-shaped Dial (see manualCall()
 * in TwimlController and dial()'s own docblock below). generateCampaignTwiml()
 * itself was dead code and has been deleted from TwiMLService entirely.
 *
 * Voice/language defaults below are hardcoded literals ('Polly.Joanna',
 * 'en-US'), not config() reads: TwiMLService never read config for these
 * either (they were bare literals in TwiMLService too), and every live
 * caller either omits `voice`/`language` or passes a null `voice` that
 * coalesces to the same literal — so reading config here would only
 * diverge from TwiMLService's actual output on any install that has
 * customized TWILIO_DEFAULT_VOICE/TWILIO_DEFAULT_LANGUAGE.
 */
class TwilioCallXmlGenerator implements CallXmlInterface
{
    public function say(string $text, array $options = []): string
    {
        $response = new VoiceResponse;

        $sayAttributes = $this->sayAttributes($options);

        if (! empty($options['dtmf'])) {
            $gather = $response->gather([
                'numDigits' => $options['dtmf']['num_digits'] ?? 1,
                'timeout' => $options['dtmf']['timeout'] ?? 5,
                'action' => $options['dtmf']['action'] ?? '',
                'method' => 'POST',
            ]);
            $gather->say($text, $sayAttributes);
        } else {
            $response->say($text, $sayAttributes);
        }

        if (! empty($options['record'])) {
            // maxLength/action/method mirror generateTts()'s own record
            // block exactly (30s default, no `transcribe` attribute) so
            // the "recording enabled, no DTMF" path stays byte-comparable
            // to the live method — see TwilioCallXmlGeneratorTest.
            $response->record([
                'maxLength' => $options['record']['max_length'] ?? 30,
                'action' => $options['record']['action'] ?? '',
                'method' => 'POST',
            ]);
        }

        return $response->asXML();
    }

    /**
     * Matches App\Services\TwiMLService::generateVoicePlayback() exactly
     * for the base case and for DTMF/recording: the audio always plays
     * unconditionally first — never nested inside the Gather — then DTMF
     * gathers a *spoken* prompt (bare `say()`, no voice/language
     * attributes — generateVoicePlayback never sets them either, since
     * there's no TTS voice involved in a playback call) as a separate
     * sibling, then recording is a third sibling. Same defaults: 30s max
     * recording length, 'Press any key to respond.' prompt.
     */
    public function play(string $audioUrl, array $options = []): string
    {
        $response = new VoiceResponse;

        // Only set `loop` when the caller actually asked for it — Twilio's
        // TwiML builder renders loop="1" when the key is present, even if
        // the value equals the provider's own implicit default, so adding
        // it unconditionally would diverge from output that never set it.
        $playOptions = [];
        if (isset($options['loop'])) {
            $playOptions['loop'] = $options['loop'];
        }

        $response->play($audioUrl, $playOptions);

        if (! empty($options['dtmf'])) {
            $gather = $response->gather([
                'numDigits' => $options['dtmf']['num_digits'] ?? 1,
                'timeout' => $options['dtmf']['timeout'] ?? 5,
                'action' => $options['dtmf']['action'] ?? '',
                'method' => 'POST',
            ]);
            $gather->say($options['dtmf']['prompt'] ?? 'Press any key to respond.');
        }

        if (! empty($options['record'])) {
            $response->record([
                'maxLength' => $options['record']['max_length'] ?? 30,
                'action' => $options['record']['action'] ?? '',
                'method' => 'POST',
            ]);
        }

        return $response->asXML();
    }

    /**
     * `$options['message']`, when given, is a leading bare `<Say>` (no
     * voice/language attributes) before the Dial — matching
     * App\Services\TwiMLService::generateTransfer()'s optional message
     * exactly, which is the live path (DtmfWebhookController's transfer
     * flow always supplies one). Without it, this matches
     * generateTransfer(phoneNumber, null)'s Dial-only output — see
     * dialAttributes() for the shared default reasoning. Both of those
     * shapes always nest the number in a `<Number>` child, and that stays
     * the default here — the two byte-parity tests above depend on it.
     *
     * `$options['bareNumber'] => true` is a different, opt-in shape:
     * $number becomes Dial's own text content instead of a nested
     * `<Number>` child — TwimlController::respondWithTransfer()'s
     * pre-refactor hand-built XML used exactly this bare form (Twilio
     * documents it as equivalent to a single nested `<Number>` with no
     * attributes, but it is not canonically identical XML, so this
     * reproduces the original shape directly rather than relying on that
     * documented equivalence at runtime — see TwilioCallXmlGeneratorTest).
     * `numberStatusCallback`/`numberStatusCallbackEvents` are meaningless
     * in this shape (there is no `<Number>` child to attach them to) and
     * are silently ignored, matching how every other inapplicable option
     * in this class behaves.
     */
    public function dial(string $number, array $options = []): string
    {
        $response = new VoiceResponse;

        if (! empty($options['message'])) {
            $response->say($options['message']);
        }

        if (! empty($options['bareNumber'])) {
            $response->dial($number, $this->dialAttributes($options));

            return $response->asXML();
        }

        $dial = $response->dial('', $this->dialAttributes($options));
        $dial->number($number, $this->childStatusCallbackAttributes(
            $options['numberStatusCallback'] ?? null,
            $options['numberStatusCallbackEvents'] ?? null,
        ));

        return $response->asXML();
    }

    public function dialClient(string $clientIdentity, array $options = []): string
    {
        $response = new VoiceResponse;

        $dial = $response->dial('', $this->dialAttributes($options));
        $dial->client($clientIdentity, $this->childStatusCallbackAttributes(
            $options['clientStatusCallback'] ?? null,
            $options['clientStatusCallbackEvents'] ?? null,
        ));

        return $response->asXML();
    }

    /**
     * Twilio writes a conference room as a <Conference> noun nested in a
     * <Dial> — the shape its own conference documentation uses, and the
     * shape the SDK's Dial::conference() builds. The room name is the
     * element's body.
     *
     * This is NOT how the inbound call queue works on this provider, and
     * nothing in that flow calls this method: Twilio has a real queue
     * primitive (<Enqueue> to join, <Dial><Queue> to take the caller at
     * its head), CallQueueService still uses it, and the documents a
     * Twilio installation receives are unchanged. This method exists
     * because a conference is the only queueing construct the other
     * provider has, so the interface both share carries one — see
     * CallQueueService::xmlForWaiting().
     *
     * `action` belongs to the <Dial>, not to the <Conference>: Twilio
     * documents <Dial action> as the URL requested when the dial ends,
     * which is when this member leaves the room. `stayAlone` is silently
     * ignored — Twilio has no such attribute, and a lone Twilio
     * participant simply stays.
     */
    public function conference(string $roomName, array $options = []): string
    {
        $response = new VoiceResponse;

        $dialAttributes = [];

        if (! empty($options['action'])) {
            $dialAttributes['action'] = $options['action'];
            $dialAttributes['method'] = 'POST';
        }

        if (isset($options['timeout'])) {
            $dialAttributes['timeout'] = $options['timeout'];
        }

        $conferenceAttributes = [];

        foreach (['startConferenceOnEnter', 'endConferenceOnExit', 'muted'] as $flag) {
            if (isset($options[$flag])) {
                $conferenceAttributes[$flag] = (bool) $options[$flag];
            }
        }

        if (! empty($options['waitUrl'])) {
            $conferenceAttributes['waitUrl'] = $options['waitUrl'];
            $conferenceAttributes['waitMethod'] = 'POST';
        }

        if (isset($options['maxParticipants'])) {
            $conferenceAttributes['maxParticipants'] = (int) $options['maxParticipants'];
        }

        $dial = $response->dial('', $dialAttributes);
        $dial->conference($roomName, $conferenceAttributes);

        return $response->asXML();
    }

    /**
     * `$options['input'] = 'speech'` emits the AI voice agent's turn-loop
     * shape — TwimlController::aiAgentCall()/campaignCallAiAgent() built
     * this by hand (a raw heredoc) before being routed through callXml();
     * see TwilioCallXmlGeneratorTest for the exact reproduction. The five
     * speechTimeout/actionOnEmptyResult/speechModel/enhanced/language
     * values are hardcoded literals, not options, because every real
     * caller always used exactly these same values — mirroring how this
     * class's other literal defaults (voice, recording length, DTMF
     * prompt) are hardcoded rather than threaded through from callers, per
     * the class docblock. `numDigits`/`finishOnKey` (the dtmf-mode
     * defaults) are deliberately omitted from a speech-mode Gather —
     * Twilio's own docs don't use them for input="speech", and the
     * pre-refactor XML never included them either.
     *
     * Every other `$options['input']` value (including the default,
     * unset) keeps this method's original dtmf-only behaviour unchanged.
     */
    public function gather(string $prompt, array $options = []): string
    {
        $response = new VoiceResponse;

        if (($options['input'] ?? 'dtmf') === 'speech') {
            $gatherOptions = [
                'input' => 'speech',
                'action' => $options['action'] ?? '',
                'method' => 'POST',
                'speechTimeout' => 'auto',
                'timeout' => $options['timeout'] ?? 10,
                'language' => $options['language'] ?? 'en-US',
                'actionOnEmptyResult' => true,
                'speechModel' => 'phone_call',
                'enhanced' => true,
            ];
        } else {
            $gatherOptions = [
                'numDigits' => $options['numDigits'] ?? 1,
                'timeout' => $options['timeout'] ?? 5,
                'finishOnKey' => $options['finishOnKey'] ?? '#',
                'method' => 'POST',
            ];

            if (! empty($options['action'])) {
                $gatherOptions['action'] = $options['action'];
            }
        }

        $gather = $response->gather($gatherOptions);

        // Prompt can be text (Say) or a URL (Play)
        if (filter_var($prompt, FILTER_VALIDATE_URL)) {
            $gather->play($prompt);
        } else {
            // Literal, not config() — see the class docblock: matches
            // every other voice default in this file for the same reason.
            $voice = $options['voice'] ?? 'Polly.Joanna';
            $gather->say($prompt, ['voice' => $voice]);
        }

        return $response->asXML();
    }

    public function record(array $options = []): string
    {
        $response = new VoiceResponse;

        $response->record([
            'maxLength' => $options['maxLength'] ?? 3600,
            'action' => $options['action'] ?? '',
            'method' => 'POST',
            'playBeep' => $options['playBeep'] ?? true,
            'transcribe' => $options['transcribe'] ?? false,
            // '' not null: no TwiMLService counterpart forces null here,
            // and '' renders the identical empty attribute without the
            // SDK's "passing null" deprecation notice — same reasoning as
            // generateManualCallXml()'s recordingStatusCallback.
            'recordingStatusCallback' => $options['statusCallback'] ?? '',
        ]);

        return $response->asXML();
    }

    public function pause(int $seconds = 1): string
    {
        $response = new VoiceResponse;
        $response->pause(['length' => $seconds]);

        return $response->asXML();
    }

    public function hangup(): string
    {
        $response = new VoiceResponse;
        $response->hangup();

        return $response->asXML();
    }

    public function redirect(string $url): string
    {
        $response = new VoiceResponse;
        $response->redirect($url, ['method' => 'POST']);

        return $response->asXML();
    }

    public function stream(string $websocketUrl, array $options = []): string
    {
        $response = new VoiceResponse;

        $connect = $response->connect();
        $streamAttrs = ['url' => $websocketUrl];

        if (! empty($options['track'])) {
            $streamAttrs['track'] = $options['track']; // inbound_track, outbound_track, both_tracks
        }

        if (! empty($options['statusCallback'])) {
            $streamAttrs['statusCallback'] = $options['statusCallback'];
        }

        $connect->stream($streamAttrs);

        return $response->asXML();
    }

    public function buildResponse(array $instructions): string
    {
        if (count($instructions) === 0) {
            return (new VoiceResponse)->asXML();
        }

        if (count($instructions) === 1) {
            // reset(), not $instructions[0]: $instructions need not be a
            // 0-indexed list.
            return reset($instructions);
        }

        // Merge multiple TwiML fragments: extract inner content from each <Response>...</Response>
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<Response>';
        foreach ($instructions as $instruction) {
            $xml .= $this->innerResponseXml($instruction);
        }
        $xml .= '</Response>';

        return $xml;
    }

    /**
     * Matches App\Services\TwiMLService::generateManualCallTwiml() exactly
     * for the options it supports (timeout, record, recording_callback):
     * same defaults (60s, 'do-not-record'), same attribute order, and
     * recordingStatusCallback always present (TwiMLService always sets it,
     * falling back to null — which Twilio's TwiML builder renders as an
     * empty attribute, so this generator defaults to '' to get the same
     * byte output without the SDK's "passing null" deprecation notice).
     * callerId and statusCallback have no TwiMLService counterpart at all;
     * they're additive, only present when the caller opts in.
     *
     * generateManualCallTwiml() itself has no live caller (see this
     * class's docblock) — the real manual/browser call flow
     * (TwimlController::manualCall()) is built through dial() instead (a
     * differently-configured Dial with a nested Number carrying its own
     * statusCallback via dial()'s numberStatusCallback option, plus a
     * trailing Say merged in via buildResponse()) and doesn't resemble
     * this method's shape at all. There's no live default to reconcile
     * against here, so this keeps matching the dead method as originally
     * required.
     */
    public function generateManualCallXml(string $phoneNumber, array $options = []): string
    {
        $response = new VoiceResponse;

        $dialOptions = [];
        if (! empty($options['callerId'])) {
            $dialOptions['callerId'] = $options['callerId'];
        }

        $dialOptions['timeout'] = $options['timeout'] ?? 60;
        $dialOptions['record'] = $options['record'] ?? 'do-not-record';
        $dialOptions['recordingStatusCallback'] = $options['recording_callback'] ?? '';

        if (! empty($options['statusCallback'])) {
            $dialOptions['action'] = $options['statusCallback'];
            $dialOptions['method'] = 'POST';
        }

        $dial = $response->dial('', $dialOptions);
        $dial->number($phoneNumber);

        return $response->asXML();
    }

    /**
     * Matches the live App\Services\TwiMLService::generateTts() exactly —
     * that was, and remains, the right method to match: before being
     * routed through callXml(), TwimlController::campaignCallTts() called
     * generateTts() for a campaign TTS call, never generateCampaignTwiml()
     * (which has no caller anywhere — see this class's docblock). Now
     * campaignCallTts() calls this method directly instead, but the
     * defaults it must match are unchanged, so the byte-parity proof
     * against generateTts() is still the right one to keep alive. The
     * script is always said unconditionally first, then DTMF gathering
     * and recording are each a separate, sibling block — never wrapping
     * the main text inside the Gather — with the same defaults as
     * generateTts() (5s DTMF timeout, 'Press any key to respond.' prompt,
     * 30s max recording length).
     */
    public function generateCampaignTtsXml(string $text, array $options = []): string
    {
        $response = new VoiceResponse;

        $voice = $options['voice'] ?? 'Polly.Joanna';
        $language = $options['language'] ?? 'en-US';

        $response->say($text, ['voice' => $voice, 'language' => $language]);

        if (! empty($options['dtmf']['enabled'])) {
            $gather = $response->gather([
                'numDigits' => $options['dtmf']['num_digits'] ?? 1,
                'timeout' => $options['dtmf']['timeout'] ?? 5,
                'action' => $options['dtmf']['action'] ?? '',
                'method' => 'POST',
            ]);
            $gather->say($options['dtmf']['prompt'] ?? 'Press any key to respond.', [
                'voice' => $voice,
                'language' => $language,
            ]);
        }

        if (! empty($options['record']['enabled'])) {
            $response->record([
                'maxLength' => $options['record']['max_length'] ?? 30,
                'action' => $options['record']['action'] ?? '',
                'method' => 'POST',
            ]);
        }

        return $response->asXML();
    }

    /**
     * Matches App\Services\TwiMLService::generateErrorResponse() exactly:
     * a bare <Say> with no voice/language attributes at all, then hangup.
     */
    public function generateErrorXml(string $message): string
    {
        $response = new VoiceResponse;
        $response->say($message);
        $response->hangup();

        return $response->asXML();
    }

    public function getProviderName(): string
    {
        return 'twilio';
    }

    /**
     * Shared <Dial> attribute building for dial() and dialClient(). callerId
     * is only included when given — see generateManualCallXml()'s docblock
     * for why omitting unset attributes (rather than passing null) matters
     * for byte-identical output against the legacy TwiMLService methods.
     *
     * `record`: an absent key still defaults to 'do-not-record' (unchanged,
     * proven by this file's dial()/dialClient() parity tests above) —
     * but `'record' => null` explicitly, opt-in only, omits the attribute
     * entirely. manualCall() and inboundCall() never carried a `record`
     * attribute on their hand-built <Dial> when recording was disabled,
     * and this is the only way to reproduce that once routed through here.
     *
     * `recordingStatusCallback` / `transcribeCallback`: manualCall()'s
     * optional recording/transcription attributes on <Dial> itself,
     * present only when the caller opts in — additive, no existing caller
     * sets these.
     */
    private function dialAttributes(array $options): array
    {
        $dialOptions = [];
        if (! empty($options['callerId'])) {
            $dialOptions['callerId'] = $options['callerId'];
        }

        $dialOptions['timeout'] = $options['timeout'] ?? 30;

        if (array_key_exists('record', $options) && $options['record'] === null) {
            // Explicit opt-out: omit the attribute rather than defaulting it.
        } else {
            $dialOptions['record'] = $options['record'] ?? 'do-not-record';
        }

        if (! empty($options['statusCallback'])) {
            $dialOptions['action'] = $options['statusCallback'];
            $dialOptions['method'] = 'POST';
        }

        if (! empty($options['recordingStatusCallback'])) {
            $dialOptions['recordingStatusCallback'] = $options['recordingStatusCallback'];
            $dialOptions['recordingStatusCallbackMethod'] = 'POST';
        }

        if (! empty($options['transcribeCallback'])) {
            $dialOptions['recordingStatusCallbackEvent'] = 'completed';
            $dialOptions['transcribe'] = 'true';
            $dialOptions['transcribeCallback'] = $options['transcribeCallback'];
        }

        return $dialOptions;
    }

    /**
     * Attributes for dial()'s/dialClient()'s nested <Number>/<Client> child
     * — manualCall()'s and inboundCall()'s per-leg status tracking, a
     * different URL from Dial's own `action`. Both callers are optional and
     * independent: passing only one of the pair still yields a partial
     * (but valid) attribute set, matching how every other conditional
     * attribute in this class behaves.
     */
    private function childStatusCallbackAttributes(?string $statusCallback, ?string $events): array
    {
        $attributes = [];

        if (! empty($statusCallback)) {
            $attributes['statusCallback'] = $statusCallback;
        }

        if (! empty($events)) {
            $attributes['statusCallbackEvent'] = $events;
        }

        return $attributes;
    }

    /**
     * Attributes for say()'s <Say> element. `voice` always defaults to the
     * literal 'Polly.Joanna' (see the class docblock). `language` behaves
     * like `record` in dialAttributes() above: an absent key defaults to
     * 'en-US' (unchanged, proven by this file's say() parity tests), but
     * `'language' => null` explicitly omits it — respondWithGoodbye(),
     * respondWithTransfer() and respondWithFallback() never carried a
     * language attribute on their hand-built <Say>, only voice.
     */
    private function sayAttributes(array $options): array
    {
        $attributes = ['voice' => $options['voice'] ?? 'Polly.Joanna'];

        if (array_key_exists('language', $options) && $options['language'] === null) {
            // Explicit opt-out: omit the attribute rather than defaulting it.
        } else {
            $attributes['language'] = $options['language'] ?? 'en-US';
        }

        return $attributes;
    }

    /**
     * Extract the content between a fragment's <Response> tags for
     * buildResponse()'s merge. Handles both the normal open/close form
     * and the self-closing <Response/> a totally empty VoiceResponse
     * renders as (confirmed: `(new VoiceResponse)->asXML()` produces
     * `<Response/>`, which the open/close regex alone never matches) —
     * without this, a genuinely-empty fragment would still contribute
     * nothing (correct), but by accident rather than by a case the code
     * actually accounts for.
     */
    private function innerResponseXml(string $instruction): string
    {
        $content = preg_replace('/^<\?xml[^?]*\?>\s*/', '', $instruction);

        if (preg_match('/<Response\s*\/>/s', $content)) {
            return '';
        }

        if (preg_match('/<Response>(.*)<\/Response>/s', $content, $matches)) {
            return $matches[1];
        }

        return '';
    }
}
