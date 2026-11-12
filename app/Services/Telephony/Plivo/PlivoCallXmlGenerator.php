<?php

declare(strict_types=1);

namespace App\Services\Telephony\Plivo;

use App\Contracts\Telephony\CallXmlInterface;
use DOMDocument;
use DOMElement;
use InvalidArgumentException;

/**
 * Plivo XML generator behind the provider-agnostic CallXmlInterface.
 *
 * Plivo's XML vocabulary is not Twilio's: there is no <Say>, <Gather>,
 * <Pause> or <Reject>. The equivalents used throughout this class are
 * <Speak>, <GetDigits>/<GetInput>, <Wait>, and <Hangup reason="rejected"/>.
 * Every element and attribute emitted here was checked against Plivo's
 * documented Voice XML element reference
 * (https://www.plivo.com/docs/voice/xml/overview and the per-element
 * pages it links) — no attribute here is invented.
 *
 * XML is built with DOMDocument rather than string concatenation (an
 * earlier prototype's approach) for two reasons:
 *
 *  1. Correct escaping. DOMDocument::createElement()'s own $value
 *     parameter does NOT escape a raw "&" in text content — it silently
 *     drops it rather than erroring (verified empirically while building
 *     this class). Every text node here goes through
 *     createTextNode()+appendChild() instead, which does escape
 *     correctly; attribute values are safe through setAttribute()
 *     directly (also verified).
 *  2. The installed plivo/plivo-php SDK does ship its own XML element
 *     builder (Plivo\XML\*), but its GetInput::$valid_attributes
 *     allowlist is missing `retries` and `startInputTimeout` — both real,
 *     documented GetInput attributes — so building through it would mean
 *     either silently omitting documented attributes or fighting a
 *     PlivoXMLException. Hand-building keeps this generator governed
 *     strictly by Plivo's documentation rather than by whichever
 *     attributes one SDK version's allowlist happens to know.
 *
 * <Dial> has no `record` attribute at all in Plivo's XML (its documented
 * attribute set is action, method, timeout, timeLimit, callerId,
 * callerName, hangupOnStar, redirect, callType, callbackUrl,
 * callbackMethod, confirmSound, confirmKey, confirmTimeout, dialMusic,
 * digitsMatch, digitsMatchBLeg, sipHeaders — no `record`). Recording a
 * dialled call is instead a separate
 * <Record recordSession="true" startOnDialAnswer="true"/> placed before
 * <Dial> (Plivo's documented pattern for recording a call in progress) —
 * see dial()'s own docblock.
 */
class PlivoCallXmlGenerator implements CallXmlInterface
{
    /**
     * Plivo's own docs warn that a <GetDigits>/<GetInput> with nothing
     * after it ends the call in silence once retries are exhausted and
     * the caller pressed nothing — every keypad-collecting method here
     * follows it with this fallback sibling instead.
     */
    private const NO_INPUT_FALLBACK_TEXT = "We didn't receive any input. Goodbye.";

    /**
     * "Maximum participants per conference: 20" — Plivo's own
     * <Conference> page, and the ceiling of its maxMembers range.
     */
    private const CONFERENCE_MAX_MEMBERS = 20;

    public function say(string $text, array $options = []): string
    {
        [$dom, $response] = $this->newResponse();

        $voice = $this->sanitizeVoice($options['voice'] ?? null);
        $language = $options['language'] ?? $this->defaultLanguage();

        if (! empty($options['dtmf'])) {
            // <GetDigits> wrapping a nested <Speak>: the prompt goes
            // inside the element so the caller can interrupt it by
            // pressing a key, and a fallback sibling follows it — a
            // caller who presses nothing must not be met with silence.
            $getDigits = $this->emptyElement($dom, 'GetDigits', [
                'action' => $options['dtmf']['action'] ?? '',
                'method' => 'POST',
                'numDigits' => $options['dtmf']['num_digits'] ?? 1,
                'timeout' => $options['dtmf']['timeout'] ?? 5,
            ]);
            $getDigits->appendChild($this->textElement($dom, 'Speak', $text, [
                'voice' => $voice,
                'language' => $language,
            ]));
            $response->appendChild($getDigits);
            $response->appendChild($this->textElement($dom, 'Speak', self::NO_INPUT_FALLBACK_TEXT, [
                'voice' => $voice,
                'language' => $language,
            ]));
        } else {
            $response->appendChild($this->textElement($dom, 'Speak', $text, [
                'voice' => $voice,
                'language' => $language,
            ]));
        }

        if (! empty($options['record'])) {
            $response->appendChild($this->nestedRecordElement($dom, $options['record']));
        }

        return $this->renderXml($dom);
    }

    public function play(string $audioUrl, array $options = []): string
    {
        [$dom, $response] = $this->newResponse();

        $loop = $options['loop'] ?? 1;

        if (! empty($options['dtmf'])) {
            $getDigits = $this->emptyElement($dom, 'GetDigits', [
                'action' => $options['dtmf']['action'] ?? '',
                'method' => 'POST',
                'numDigits' => $options['dtmf']['num_digits'] ?? 1,
                'timeout' => $options['dtmf']['timeout'] ?? 5,
            ]);
            $getDigits->appendChild($this->textElement($dom, 'Play', $audioUrl, ['loop' => $loop]));
            $response->appendChild($getDigits);
            $response->appendChild($this->textElement($dom, 'Speak', self::NO_INPUT_FALLBACK_TEXT, [
                'voice' => $this->defaultVoice(),
                'language' => $this->defaultLanguage(),
            ]));
        } else {
            $response->appendChild($this->textElement($dom, 'Play', $audioUrl, ['loop' => $loop]));
        }

        if (! empty($options['record'])) {
            $response->appendChild($this->nestedRecordElement($dom, $options['record']));
        }

        return $this->renderXml($dom);
    }

    /**
     * Plivo's <Dial> has no `record` attribute (see class docblock), so
     * $options['record'] (Twilio-flavoured: any value except the literal
     * 'do-not-record' means "record it") is translated into a separate
     * <Record recordSession="true" startOnDialAnswer="true"/> placed
     * before <Dial> — Plivo's documented "record a call in progress"
     * shape, adapted for "start recording only once this Dial connects"
     * instead of "record the whole call from the start".
     */
    public function dial(string $number, array $options = []): string
    {
        [$dom, $response] = $this->newResponse();

        if (! empty($options['message'])) {
            $response->appendChild($this->textElement($dom, 'Speak', $options['message'], [
                'voice' => $this->defaultVoice(),
                'language' => $this->defaultLanguage(),
            ]));
        }

        if (! empty($options['record']) && $options['record'] !== 'do-not-record') {
            // callbackUrl is how this application learns the recording exists.
            // Without it Plivo records and bills the call and tells no one.
            // Plivo documents it as the URL "notified when recording is
            // ready", and for a session recording it is the only callback
            // that carries the final duration — the initial request reports
            // -1. It is a notification: Plivo does not run a response to it.
            $response->appendChild($this->emptyElement($dom, 'Record', [
                'recordSession' => 'true',
                'startOnDialAnswer' => 'true',
                'callbackUrl' => $options['recordingStatusCallback'] ?? null,
                'callbackMethod' => empty($options['recordingStatusCallback']) ? null : 'POST',
            ]));
        }

        $dial = $this->emptyElement($dom, 'Dial', $this->dialAttributes($options));
        $dial->appendChild($this->textElement($dom, 'Number', $number));
        $response->appendChild($dial);

        return $this->renderXml($dom);
    }

    /**
     * Plivo's Browser SDK reaches a registered client through a SIP
     * Endpoint, dialled via <User>sip:...</User> — there is no
     * Twilio-style <Client> element in Plivo's XML.
     *
     * The sip: URI is never composed by hand: Plivo's Create Endpoint API
     * returns a `sip_uri` that must be stored at creation time and used
     * verbatim, because Plivo may append digits to the username you
     * asked for — a URI reconstructed later from the username alone can
     * point at the wrong (or no) endpoint. So this method accepts either
     * an already-complete `sip:` URI as $clientIdentity, or the stored
     * URI via $options['sip_uri'], and refuses to guess when neither is
     * given rather than inventing a domain.
     */
    public function dialClient(string $clientIdentity, array $options = []): string
    {
        [$dom, $response] = $this->newResponse();

        $dial = $this->emptyElement($dom, 'Dial', $this->dialAttributes($options));
        $dial->appendChild($this->textElement($dom, 'User', $this->sipUriFor($clientIdentity, $options)));
        $response->appendChild($dial);

        return $this->renderXml($dom);
    }

    /**
     * Plivo's <Conference> sits directly under <Response> — there is no
     * <Dial> around it, the way Twilio nests its own conference noun —
     * and the room name is the element's body. Maximum 20 participants
     * per room, so a larger `maxParticipants` is brought back to that
     * documented ceiling rather than sent as a number Plivo may reject.
     *
     * `stayAlone` is what makes a queue possible here, and it is written
     * explicitly rather than left to Plivo's default, because a queued
     * caller waits ALONE in the room until an agent joins. Plivo's
     * <Conference> table gives the default as `true` while glossing it
     * "End conference if only one member" — two readings that contradict
     * each other. The rest of Plivo's reference is consistent, and agrees
     * with the attribute's name: the Multi-party Call XML table reads
     * "Stay if only participant", the Add Participant API "Allow
     * participant to stay alone", the MPC resource field "Whether
     * participants can remain alone in the MPC", and a session that ends
     * because someone was left alone reports termination code `1010 Stay
     * Alone Not Permitted` — ending is what happens when staying alone is
     * NOT permitted. So `stayAlone="true"` means this member may remain
     * alone, which is precisely what a waiting caller needs.
     *
     * `waitSound` is a URL Plivo requests and expects a document from,
     * not an audio file: Plivo documents the sibling `enterSound` URL as
     * having to return XML containing <Play>, <Speak> or <Wait>, and the
     * page's own waitSound example points at a .xml document.
     */
    public function conference(string $roomName, array $options = []): string
    {
        [$dom, $response] = $this->newResponse();

        $attributes = [];

        foreach (['startConferenceOnEnter', 'endConferenceOnExit', 'stayAlone', 'muted'] as $flag) {
            if (isset($options[$flag])) {
                $attributes[$flag] = $this->boolAttr((bool) $options[$flag]);
            }
        }

        if (isset($options['maxParticipants'])) {
            $attributes['maxMembers'] = min(self::CONFERENCE_MAX_MEMBERS, max(1, (int) $options['maxParticipants']));
        }

        if (! empty($options['waitUrl'])) {
            $attributes['waitSound'] = $options['waitUrl'];
        }

        if (! empty($options['action'])) {
            $attributes['action'] = $options['action'];
            $attributes['method'] = 'POST';
        }

        $response->appendChild($this->textElement($dom, 'Conference', $roomName, $attributes));

        return $this->renderXml($dom);
    }

    /**
     * $options['input'] = 'dtmf' (default, unchanged campaign behaviour)
     * emits <GetDigits> exactly as before, where `action` is optional
     * (Plivo defaults it to none and simply falls through after retries
     * are exhausted). 'speech' or 'dtmf speech' emits
     * <GetInput inputType="..."> instead — its own, differently spelled
     * attribute set: the overall wait time is `executionTimeout`, not
     * Twilio-style `timeout`; digit collection uses
     * `numDigits`/`finishOnKey` just like GetDigits; speech collection
     * adds `language`/`hints`/`speechModel`. Unlike GetDigits, Plivo
     * documents `action` as required on GetInput — there is nowhere for
     * the result to go without it — so that path throws rather than
     * silently emitting an attribute-less element.
     *
     * `startInputTimeout` is how long Plivo waits for the caller to start
     * speaking, which is what Twilio's `timeout` means in its own speech
     * mode; `executionTimeout` is a cap on the whole element, and capping
     * a turn at the time allowed to *start* it would cut a caller off
     * mid-sentence. They are therefore separate options here rather than
     * both being fed from `timeout`, whose existing mapping onto
     * `executionTimeout` is unchanged for callers that pass only it.
     *
     * A speech GetInput is also followed by a <Redirect> back to the same
     * action URL. Plivo documents that after a GetInput with no input
     * "execution falls through to the next element", and that "if there
     * is no next element the call ends silently" — so without it a caller
     * who simply paused would be hung up on without a word. Twilio's own
     * implementation of this same interface method hardcodes
     * actionOnEmptyResult="true" for exactly that reason; this is the
     * Plivo spelling of the same decision, and it is what lets a caller
     * who says nothing hear the prompt again instead of a dead line.
     *
     * @throws InvalidArgumentException when $input is 'speech' or 'dtmf
     *                                  speech' and $options['action'] is missing or empty.
     */
    public function gather(string $prompt, array $options = []): string
    {
        $input = $options['input'] ?? 'dtmf';

        [$dom, $response] = $this->newResponse();

        if ($input === 'dtmf') {
            $element = $this->emptyElement($dom, 'GetDigits', [
                'action' => $options['action'] ?? '',
                'method' => 'POST',
                'numDigits' => $options['numDigits'] ?? 1,
                'timeout' => $options['timeout'] ?? 5,
                'finishOnKey' => $options['finishOnKey'] ?? '#',
            ]);
        } else {
            if (empty($options['action'])) {
                throw new InvalidArgumentException(
                    "Plivo's <GetInput> requires an `action` URL — pass \$options['action']."
                );
            }

            $attributes = [
                'action' => $options['action'],
                'method' => 'POST',
                'inputType' => $input,
                'executionTimeout' => $options['executionTimeout'] ?? ($options['timeout'] ?? 15),
            ];

            if (str_contains($input, 'dtmf')) {
                $attributes['numDigits'] = $options['numDigits'] ?? 32;
                $attributes['finishOnKey'] = $options['finishOnKey'] ?? '#';
            }

            if (! empty($options['startInputTimeout'])) {
                $attributes['startInputTimeout'] = (int) $options['startInputTimeout'];
            }

            if (str_contains($input, 'speech')) {
                $attributes['language'] = $options['language'] ?? $this->defaultLanguage();
                if (! empty($options['hints'])) {
                    $attributes['hints'] = $options['hints'];
                }
                if (! empty($options['speechModel'])) {
                    $attributes['speechModel'] = $options['speechModel'];
                }
            }

            $element = $this->emptyElement($dom, 'GetInput', $attributes);
        }

        if (filter_var($prompt, FILTER_VALIDATE_URL)) {
            $element->appendChild($this->textElement($dom, 'Play', $prompt));
        } else {
            $element->appendChild($this->textElement($dom, 'Speak', $prompt, [
                'voice' => $this->sanitizeVoice($options['voice'] ?? null),
            ]));
        }

        $response->appendChild($element);

        if ($input !== 'dtmf') {
            $response->appendChild(
                $this->textElement($dom, 'Redirect', $options['action'])
            );
        }

        return $this->renderXml($dom);
    }

    public function record(array $options = []): string
    {
        [$dom, $response] = $this->newResponse();

        $attributes = [
            'action' => $options['action'] ?? '',
            'method' => 'POST',
            'fileFormat' => $options['fileFormat'] ?? 'mp3',
            'maxLength' => $options['maxLength'] ?? 3600,
            'playBeep' => $this->boolAttr($options['playBeep'] ?? true),
        ];

        if (! empty($options['statusCallback'])) {
            $attributes['callbackUrl'] = $options['statusCallback'];
        }

        if (! empty($options['transcribe'])) {
            $attributes['transcriptionType'] = 'auto';
        }

        $response->appendChild($this->emptyElement($dom, 'Record', $attributes));

        return $this->renderXml($dom);
    }

    public function pause(int $seconds = 1): string
    {
        [$dom, $response] = $this->newResponse();
        $response->appendChild($this->emptyElement($dom, 'Wait', ['length' => $seconds]));

        return $this->renderXml($dom);
    }

    public function hangup(): string
    {
        [$dom, $response] = $this->newResponse();
        $response->appendChild($this->emptyElement($dom, 'Hangup'));

        return $this->renderXml($dom);
    }

    public function redirect(string $url): string
    {
        [$dom, $response] = $this->newResponse();
        $response->appendChild($this->textElement($dom, 'Redirect', $url, ['method' => 'POST']));

        return $this->renderXml($dom);
    }

    /**
     * Plivo has no <Connect> wrapper — <Stream> sits directly under
     * <Response> (Plivo's Response element accepts any element as a
     * direct child). `track` (Twilio's inbound_track/outbound_track/both_tracks) is
     * deliberately not mapped to Plivo's `audioTrack`: the two vocabularies
     * don't correspond, and audioTrack cannot be "outbound" or "both" when
     * bidirectional="true" (which this method always sets for two-way AI
     * agent audio) — mapping it through without knowing the caller meant
     * Plivo's stricter semantics risks emitting a forbidden combination.
     *
     * The default `contentType` codec (audio/x-mulaw, 8000 Hz) must match
     * PlivoAudioStreamProvider::getAudioFormat() — Plivo documents a
     * mismatch between what the stream was opened with and what the
     * server sends back over the socket as a cause of the caller hearing
     * nothing.
     */
    public function stream(string $websocketUrl, array $options = []): string
    {
        [$dom, $response] = $this->newResponse();

        $attributes = [
            'bidirectional' => 'true',
            'keepCallAlive' => 'true',
            'contentType' => $options['contentType'] ?? 'audio/x-mulaw;rate=8000',
        ];

        if (! empty($options['statusCallback'])) {
            $attributes['statusCallbackUrl'] = $options['statusCallback'];
            $attributes['statusCallbackMethod'] = 'POST';
        }

        $response->appendChild($this->textElement($dom, 'Stream', $websocketUrl, $attributes));

        return $this->renderXml($dom);
    }

    public function buildResponse(array $instructions): string
    {
        if (count($instructions) === 0) {
            [$dom] = $this->newResponse();

            return $this->renderXml($dom);
        }

        if (count($instructions) === 1) {
            // reset(), not $instructions[0]: $instructions need not be a
            // 0-indexed list.
            return reset($instructions);
        }

        // Merge multiple XML fragments: extract inner content from each <Response>...</Response>
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<Response>';
        foreach ($instructions as $instruction) {
            $xml .= $this->innerResponseXml($instruction);
        }
        $xml .= '</Response>';

        return $xml;
    }

    public function generateManualCallXml(string $phoneNumber, array $options = []): string
    {
        return $this->dial($phoneNumber, array_merge([
            'record' => 'record-from-answer-dual',
        ], $options));
    }

    public function generateCampaignTtsXml(string $text, array $options = []): string
    {
        [$dom, $response] = $this->newResponse();

        $voice = $this->sanitizeVoice($options['voice'] ?? null);
        $language = $options['language'] ?? $this->defaultLanguage();

        if (! empty($options['dtmf']['enabled'])) {
            $getDigits = $this->emptyElement($dom, 'GetDigits', [
                'action' => $options['dtmf']['action'] ?? '',
                'method' => 'POST',
                'numDigits' => $options['dtmf']['num_digits'] ?? 1,
                'timeout' => $options['dtmf']['timeout'] ?? 5,
            ]);
            $getDigits->appendChild($this->textElement($dom, 'Speak', $text, [
                'voice' => $voice,
                'language' => $language,
            ]));
            $response->appendChild($getDigits);

            $response->appendChild($this->textElement(
                $dom,
                'Speak',
                self::NO_INPUT_FALLBACK_TEXT,
                ['voice' => $voice, 'language' => $language]
            ));
        } else {
            $response->appendChild($this->textElement($dom, 'Speak', $text, [
                'voice' => $voice,
                'language' => $language,
            ]));
        }

        if (! empty($options['record']['enabled'])) {
            $response->appendChild($this->nestedRecordElement($dom, $options['record']));
        }

        return $this->renderXml($dom);
    }

    public function generateErrorXml(string $message): string
    {
        [$dom, $response] = $this->newResponse();

        $response->appendChild($this->textElement($dom, 'Speak', $message, ['voice' => $this->defaultVoice()]));
        $response->appendChild($this->emptyElement($dom, 'Hangup'));

        return $this->renderXml($dom);
    }

    public function getProviderName(): string
    {
        return 'plivo';
    }

    // ─── Private: element construction ──────────────

    /**
     * @return array{0: DOMDocument, 1: DOMElement}
     */
    private function newResponse(): array
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $response = $dom->createElement('Response');
        $dom->appendChild($response);

        return [$dom, $response];
    }

    /**
     * Element with text content. Uses createTextNode()+appendChild(),
     * never createElement()'s own $value parameter — see class docblock
     * for why that parameter silently drops a raw "&" instead of
     * escaping it.
     */
    private function textElement(DOMDocument $dom, string $name, string $text, array $attributes = []): DOMElement
    {
        $element = $dom->createElement($name);
        $element->appendChild($dom->createTextNode($text));
        $this->setAttributes($element, $attributes);

        return $element;
    }

    private function emptyElement(DOMDocument $dom, string $name, array $attributes = []): DOMElement
    {
        $element = $dom->createElement($name);
        $this->setAttributes($element, $attributes);

        return $element;
    }

    /**
     * setAttribute() escapes its value correctly (verified), so this
     * only needs to skip null/empty-string values — an omitted attribute
     * lets Plivo apply its own documented default; an emitted empty one
     * does not (Plivo's docs specifically warn against e.g. an empty
     * `sendDigits=""`).
     */
    private function setAttributes(DOMElement $element, array $attributes): void
    {
        foreach ($attributes as $name => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $element->setAttribute($name, (string) $value);
        }
    }

    private function boolAttr(bool $value): string
    {
        return $value ? 'true' : 'false';
    }

    private function renderXml(DOMDocument $dom): string
    {
        return $dom->saveXML();
    }

    /**
     * Shared <Dial> attribute building for dial() and dialClient().
     * `record` is deliberately excluded — see class docblock, it is not
     * a Dial attribute in Plivo's XML at all.
     */
    private function dialAttributes(array $options): array
    {
        $attributes = [
            'timeout' => $options['timeout'] ?? 30,
        ];

        if (! empty($options['callerId'])) {
            $attributes['callerId'] = $options['callerId'];
        }

        if (! empty($options['statusCallback'])) {
            $attributes['action'] = $options['statusCallback'];
            $attributes['method'] = 'POST';
        }

        // Plivo documents callbackUrl on <Dial> as the URL for real-time dial
        // events. Without it a call reports nothing until the Application's
        // hangup URL fires at the very end, so the call row never passes
        // through ringing or answered. The callers' names for the per-leg
        // callback differ (a dialled number vs a browser client); both mean
        // the same thing here.
        $perLegCallback = $options['numberStatusCallback'] ?? $options['clientStatusCallback'] ?? null;

        if (! empty($perLegCallback)) {
            $attributes['callbackUrl'] = $perLegCallback;
            $attributes['callbackMethod'] = 'POST';
        }

        return $attributes;
    }

    /**
     * The <Record> emitted alongside say()/play()/generateCampaignTtsXml()'s
     * nested `record` option (voicemail-style: record after a prompt).
     * Not the same shape as the public record() method's own $options —
     * this one is keyed the way TwilioCallXmlGenerator's equivalent
     * nested option already is (snake_case max_length/action), matching
     * the established convention across both providers' CallXmlInterface
     * implementations for this same nested shape.
     */
    private function nestedRecordElement(DOMDocument $dom, array $options): DOMElement
    {
        return $this->emptyElement($dom, 'Record', [
            'maxLength' => $options['max_length'] ?? 60,
            'action' => $options['action'] ?? '',
        ]);
    }

    /**
     * @throws InvalidArgumentException when $clientIdentity is not
     *                                  already a complete `sip:` URI and no stored $options['sip_uri'] was
     *                                  given — see dialClient()'s own docblock for why this never composes
     *                                  a URI from the identity alone.
     */
    private function sipUriFor(string $clientIdentity, array $options): string
    {
        if (str_starts_with($clientIdentity, 'sip:')) {
            return $clientIdentity;
        }

        if (! empty($options['sip_uri'])) {
            return $options['sip_uri'];
        }

        throw new InvalidArgumentException(
            'Plivo has no documented convention for deriving a SIP URI from an identity alone. '.
            "Pass the Endpoint's own sip_uri — returned verbatim by the Create Endpoint API and stored ".
            "at creation time, since Plivo may append digits to the requested username — via \$options['sip_uri'], ".
            "or pass \$clientIdentity as an already-complete 'sip:...' URI."
        );
    }

    /**
     * Plivo's <Speak> documents exactly three legal `voice` forms: the
     * literal `WOMAN`, the literal `MAN`, or a bare `Polly.<Name>` id —
     * see the Speak voice tables at
     * https://www.plivo.com/docs/voice/xml/audio-output and their "Do not
     * use a ... voice that is not in the tables" rule. Every
     * caller of say()/gather()/generateCampaignTtsXml() ultimately passes
     * this method whatever the *Twilio* side settled on — 'alice' (a
     * legacy Twilio-only voice name), a Twilio engine-tier suffix such as
     * '-Neural'/'-Generative' tacked onto a Polly name (App\Http\Controllers\
     * Concerns\MapsAiVoice::mapVoiceForTwilio() always produces one of
     * these for the AI voice agent), or a Twilio Google.* voice id — none
     * of which are in Plivo's documented vocabulary. Rather than pass
     * those through unexamined (a silently wrong voice, or Plivo rejecting
     * the whole document — Plivo's documentation is explicit that what
     * it does with an unrecognised value is undocumented, so it must be
     * removed, not reasoned about), this maps what has an unambiguous Plivo
     * equivalent and falls back to the configured default for everything
     * else, including a Google.* id, which Plivo has no equivalent for at
     * all.
     *
     * This intentionally does not attempt anything cleverer for a Google
     * voice (e.g. guessing a gender from its name) — that would be
     * inventing behaviour nobody asked for on top of a value this
     * vocabulary simply does not support.
     */
    private function sanitizeVoice(?string $voice): string
    {
        if (empty($voice)) {
            return $this->defaultVoice();
        }

        if ($voice === 'WOMAN' || $voice === 'MAN') {
            return $voice;
        }

        $lower = strtolower($voice);
        if ($lower === 'alice' || $lower === 'woman') {
            return 'WOMAN';
        }
        if ($lower === 'man') {
            return 'MAN';
        }

        // A Polly voice, possibly decorated with a Twilio-only engine
        // suffix ('-Neural', '-Generative', ...) that is not part of
        // Plivo's documented voice id — strip it and keep the bare
        // Polly.<Name> form Plivo's own Speak table actually documents.
        if (preg_match('/^Polly\.([A-Za-z]+)(?:-[A-Za-z]+)?$/', $voice, $matches)) {
            return 'Polly.'.$matches[1];
        }

        // Anything else — a Google.* id or any other unrecognised value —
        // is not in Plivo's documented vocabulary at all.
        return $this->defaultVoice();
    }

    private function defaultVoice(): string
    {
        return config('telephony.providers.plivo.tts.default_voice', 'WOMAN');
    }

    private function defaultLanguage(): string
    {
        return config('telephony.providers.plivo.tts.default_language', 'en-US');
    }

    /**
     * Extract the content between a fragment's <Response> tags for
     * buildResponse()'s merge. Handles both the normal open/close form
     * and the self-closing <Response/> an empty response renders as.
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
