<?php

declare(strict_types=1);

namespace App\Services\Telephony\Plivo;

use App\Contracts\Telephony\WebhookNormalizerInterface;
use Illuminate\Support\Facades\Log;

/**
 * Normalizes Plivo webhook payloads into the standard format.
 *
 * Plivo parameters: CallUUID, From, To, CallStatus, Direction, etc. — see
 * the "Standard request parameters" section of the Plivo Voice XML skill.
 *
 * normalizeIncomingSms() runs From/To through PlivoNumberFormat::toE164():
 * Plivo's real inbound-SMS webhook sends both bare, with no leading '+',
 * while every PhoneNumber row this application stores carries the '+'
 * (PhoneNumberService normalises a Plivo number to E.164 the moment it
 * enters the app). Left unnormalised here, SmsWebhookController's
 * PhoneNumber::where('number', ...) lookup and the SmsConversation it
 * keys by contact phone could never match a real payload — an inbound
 * message to a number a customer had paid for would silently fail to
 * route.
 *
 * The call side had the identical problem and it is fixed where it
 * actually lives: TwimlController reads From/To straight off the request,
 * bypassing this class entirely, so it normalises them itself through
 * webhookNumber() (which is PlivoNumberFormat::toE164() for Plivo and a
 * no-op for Twilio). normalizeCallEvent()'s own from/to are left as the
 * payload sends them on purpose — nothing consumes them for a number
 * lookup, and a second, independent conversion is exactly the duplicated
 * assumption this branch has been bitten by.
 *
 * normalizeIncomingSms() also reports the real num_segments from Plivo's
 * own Units field — the same field PlivoSmsProvider::parseIncoming()
 * already reads correctly — instead of leaving SmsWebhookController to
 * fall back to an assumed 1 for every inbound Plivo message regardless of
 * how many segments it actually used.
 */
class PlivoWebhookNormalizer implements WebhookNormalizerInterface
{
    public function normalizeCallEvent(array $payload): array
    {
        return [
            'call_id' => $payload['CallUUID'] ?? '',
            'from' => $payload['From'] ?? '',
            'to' => $payload['To'] ?? '',
            'direction' => $this->mapDirection($payload['Direction'] ?? ''),
            'status' => $this->mapCallStatus($payload['CallStatus'] ?? ''),
            'provider' => 'plivo',
            'raw' => $payload,
        ];
    }

    public function normalizeStatusCallback(array $payload): array
    {
        return [
            'call_id' => $payload['CallUUID'] ?? ($payload['DialALegUUID'] ?? ''),
            'status' => $this->mapCallStatus(
                $payload['CallStatus'] ?? ($payload['Event'] ?? $this->statusFromDialEvent($payload))
            ),
            'duration' => isset($payload['Duration']) ? (int) $payload['Duration'] : (isset($payload['BillDuration']) ? (int) $payload['BillDuration'] : null),
            'price' => isset($payload['TotalCost']) ? abs((float) $payload['TotalCost']) : null,
            'answered_by' => $payload['Machine'] ?? null,
            'provider' => 'plivo',
            'raw' => $payload,
        ];
    }

    public function normalizeRecordingCallback(array $payload): array
    {
        return [
            'call_id' => $payload['CallUUID'] ?? '',
            'recording_id' => $payload['RecordingID'] ?? ($payload['RecordingUUID'] ?? ''),
            'recording_url' => $payload['RecordUrl'] ?? ($payload['RecordingUrl'] ?? null),
            'duration' => isset($payload['RecordingDuration']) ? (int) $payload['RecordingDuration'] : null,
            'status' => 'completed',
            'provider' => 'plivo',
            'raw' => $payload,
        ];
    }

    public function normalizeIncomingSms(array $payload): array
    {
        return [
            'message_id' => $payload['MessageUUID'] ?? '',
            'from' => PlivoNumberFormat::toE164($payload['From'] ?? ''),
            'to' => PlivoNumberFormat::toE164($payload['To'] ?? ''),
            'body' => $payload['Text'] ?? '',
            'media_urls' => $this->extractMediaUrls($payload),
            // A real webhook POST carries this as a numeric string; cast
            // so callers get the same int shape regardless of how many
            // segments the customer's inbound message actually used.
            'num_segments' => isset($payload['Units']) ? (int) $payload['Units'] : 1,
            'provider' => 'plivo',
            'raw' => $payload,
        ];
    }

    public function extractCallId(array $payload): ?string
    {
        return $payload['CallUUID'] ?? null;
    }

    public function isInboundCall(array $payload): bool
    {
        $direction = strtolower($payload['Direction'] ?? '');

        return $direction === 'inbound';
    }

    public function getProviderName(): string
    {
        return 'plivo';
    }

    /**
     * Map Plivo's call status/event vocabulary to the normalized status.
     */
    /**
     * The status carried by a <Dial callbackUrl> event, which speaks a
     * different vocabulary from a call status callback: Plivo documents it as
     * DialAction / DialBLegStatus / DialALegUUID, with no CallStatus, no
     * Event and no CallUUID. Without this a real-time dial event normalises
     * to nothing, and the very thing callbackUrl exists for — a call moving
     * through ringing and answered instead of staying at its initial status
     * until it hangs up — never happens.
     *
     * Only the answered transitions are mapped. A B-leg hangup is left alone
     * on purpose: the Application's own hangup URL carries the authoritative
     * final status and the call's cost, and terminalizing early from a leg
     * event would overwrite a call that is still being settled.
     */
    private function statusFromDialEvent(array $payload): string
    {
        return match (strtolower((string) ($payload['DialAction'] ?? ''))) {
            'answer', 'connected' => 'in-progress',
            default => '',
        };
    }

    /**
     * `cancel` — singular, no trailing 'ed' — is what Plivo documents for
     * a call the caller hangs up before it is answered. It is mapped here
     * alongside the 'canceled'/'cancelled' spellings an earlier reading
     * assumed, because the documented one is the one that actually
     * arrives.
     *
     * The default arm no longer returns the provider's own string. Every
     * value this method produces has to be a member of the `calls.status`
     * enum, because that is where it ends up (CallController::
     * webhookStatus() -> UpdateCallStatusAction). A verbatim passthrough
     * let any undocumented or future provider string reach that column;
     * on MySQL in strict mode the write is rejected outright, and on the
     * SQLite the test suite runs it is rejected by the CHECK constraint
     * Laravel compiles an enum into.
     *
     * An unmapped value returns the empty string, which is this
     * normaliser's existing "this payload carries no status opinion"
     * signal — the same thing statusFromDialEvent() returns for a B-leg
     * hangup and the same thing an absent CallStatus already produces.
     * webhookStatus() then leaves the call's current status alone, which
     * is the behaviour a status nobody can interpret should have: the
     * alternative, terminalizing the row on a value we do not understand,
     * would end a live call, stamp ended_at and enrol the contact in a
     * follow-up sequence on nothing more than a guess. The warning names
     * the value so it can be mapped deliberately.
     */
    private function mapCallStatus(string $plivoStatus): string
    {
        $status = strtolower(trim($plivoStatus));

        if ($status === '') {
            return '';
        }

        return match ($status) {
            'ringing' => 'ringing',
            'in-progress' => 'in-progress',
            'completed' => 'completed',
            'busy' => 'busy',
            'failed' => 'failed',
            'timeout', 'no-answer' => 'no-answer',
            'cancel', 'canceled', 'cancelled' => 'canceled',
            'hangup' => 'completed',
            default => $this->unmapped('call status', $status, ''),
        };
    }

    /**
     * Plivo's Direction is 'inbound'/'outbound'; the app's normalized
     * vocabulary (already established by TwilioWebhookNormalizer) uses
     * 'outbound-api' for the outbound case.
     *
     * Same audit as mapCallStatus(): the old `default => $plivoDirection`
     * passthrough put an arbitrary provider string into a field whose
     * consumers expect a closed vocabulary. 'inbound' is the fallback
     * because it is the only value legal both in the normalized
     * vocabulary above and in the `calls.direction` enum
     * ('outbound'/'inbound'), which does not contain 'outbound-api' — so
     * it stays storable wherever a future caller decides to write it.
     * Plivo sends Direction on every request and documents only the two
     * values, so this arm is reachable only by something undocumented,
     * and it is logged by name when it is.
     */
    private function mapDirection(string $plivoDirection): string
    {
        $direction = strtolower(trim($plivoDirection));

        if ($direction === '') {
            return '';
        }

        return match ($direction) {
            'inbound' => 'inbound',
            'outbound', 'outbound-api' => 'outbound-api',
            default => $this->unmapped('direction', $direction, 'inbound'),
        };
    }

    /**
     * Log a provider value this normaliser has no mapping for and answer
     * with $fallback, which is always inside the vocabulary the caller is
     * allowed to store.
     */
    private function unmapped(string $field, string $value, string $fallback): string
    {
        Log::warning('Plivo sent an unmapped '.$field.'; falling back', [
            'provider' => 'plivo',
            'field' => $field,
            'received' => $value,
            'stored' => $fallback,
        ]);

        return $fallback;
    }

    private function extractMediaUrls(array $payload): array
    {
        $mediaUrls = [];
        $numMedia = (int) ($payload['NumMedia'] ?? 0);

        for ($i = 0; $i < $numMedia; $i++) {
            if (isset($payload["Media{$i}"])) {
                $mediaUrls[] = $payload["Media{$i}"];
            }
        }

        return $mediaUrls;
    }
}
