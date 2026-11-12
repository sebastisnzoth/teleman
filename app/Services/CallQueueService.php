<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\TelephonyProvider;
use App\Models\CallQueueEntry;
use App\Models\TelephonyProviderConfig;
use App\Models\TwilioGlobalConfig;
use App\Models\User;
use App\Services\Telephony\Plivo\PlivoEndpointProvisioner;
use App\Services\Telephony\TelephonyLock;
use App\Services\Telephony\TelephonyManager;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class CallQueueService
{
    /**
     * What a caller hears on being put in the queue when the customer has
     * not written a greeting of their own, and what they hear if the
     * queue turns out not to be joinable after all. Bare English
     * literals, like every other spoken line in the call-control layer
     * (see TwimlController): the call-control documents are not part of
     * the translated UI.
     */
    private const DEFAULT_GREETING = 'Please hold. You are being placed in a queue. An agent will be with you shortly.';

    private const QUEUE_UNAVAILABLE = 'We are sorry, the queue is no longer available. Goodbye.';

    /**
     * A conference room holds exactly the caller and the agent who comes
     * to fetch them. Only used on the conference-based providers — Twilio
     * queues rather than conferences (see xmlForWaiting()).
     */
    private const ROOM_MEMBERS = 2;

    public function __construct(
        private readonly TelephonyLock $telephonyLock,
        private readonly TelephonyManager $telephony,
        private readonly PlivoEndpointProvisioner $plivoEndpoints,
    ) {}

    /**
     * Get the owner ID for queue operations (agent uses parent's queue).
     */
    public function getOwnerId(User $user): int
    {
        return $user->isAgent() ? (int) $user->parent_user_id : $user->id;
    }

    /**
     * Get the queue name for a user.
     */
    public function getQueueName(User $user): string
    {
        return 'queue_user_' . $this->getOwnerId($user);
    }

    /**
     * Check if the user's queue feature is enabled.
     *
     * The lock check stays even though no provider hides the queue today:
     * it is the same rule every other optional feature follows, and it is
     * what a provider added later would be switched off with while its
     * own queueing construct is being built. An installation locked to a
     * provider that hides the queue never enqueues a caller, regardless
     * of the customer's own queue_enabled setting; an unlocked
     * installation (no lock row yet) hides nothing, matching
     * EnsureTelephonyFeature's same rule elsewhere.
     */
    public function isQueueEnabled(User $user): bool
    {
        $locked = $this->telephonyLock->current();
        if ($locked !== null && $locked->hides('call_queue')) {
            return false;
        }

        $owner = $this->getOwner($user);
        return (bool) $owner->queue_enabled;
    }

    /**
     * Check if the user is currently busy (has an active in-progress call).
     */
    public function isUserBusy(User $user): bool
    {
        $ownerId = $this->getOwnerId($user);

        // Check if there's an active call record in our DB
        $hasActiveCall = \App\Models\Call::where('user_id', $ownerId)
            ->whereIn('status', ['in-progress', 'ringing'])
            ->where('direction', 'inbound')
            ->where('started_at', '>=', now()->subMinutes(30))
            ->exists();

        return $hasActiveCall;
    }

    /**
     * Enqueue a caller into the user's queue.
     */
    public function enqueue(User $user, string $callerNumber, string $callSid): CallQueueEntry
    {
        $ownerId = $this->getOwnerId($user);
        $queueName = $this->getQueueName($user);

        // Calculate position
        $position = CallQueueEntry::where('user_id', $ownerId)
            ->where('status', 'waiting')
            ->count() + 1;

        $entry = CallQueueEntry::create([
            'user_id' => $ownerId,
            'caller_number' => $callerNumber,
            'call_sid' => $callSid,
            'queue_name' => $queueName,
            'status' => 'waiting',
            'position' => $position,
            'queued_at' => now(),
        ]);

        Log::info('Caller enqueued', [
            'entry_id' => $entry->id,
            'user_id' => $ownerId,
            'caller' => $callerNumber,
            'position' => $position,
            'queue' => $queueName,
        ]);

        return $entry;
    }

    /**
     * Take the next waiting caller (FIFO) — and take them exactly once.
     *
     * The claim is a conditional UPDATE, not a read followed by a write:
     * one statement that moves this row from `waiting` to `connecting`
     * and bumps its attempt counter, applied only if the row is still
     * `waiting` when it runs. Two agents pressing dequeue at the same
     * moment both see the same caller in the list; only the one whose
     * UPDATE actually matches is given them, and the other moves on to
     * the next caller rather than being handed the same one. Before
     * this, both were handed that caller and both were dialled into the
     * one conference room that belongs to them.
     *
     * The counter is also what makes an agent's leg identifiable after
     * the fact: the leg carries its own attempt number in the URL it
     * reports its end to, so a callback that arrives late — a retry of a
     * leg that ended two agents ago — names a number this caller has
     * moved past and is ignored (see returnToQueueIfUnanswered()). It
     * only counts up; a leg that could not be placed leaves its number
     * spent rather than handing it to the next one.
     *
     * "One row affected" means the same thing on every engine this ships
     * on. MySQL and MariaDB report CHANGED rows rather than matched ones
     * unless MYSQL_ATTR_FOUND_ROWS is set, which config/database.php
     * does not set — and this write always changes `status`, so a match
     * is never miscounted as a miss.
     */
    public function dequeue(User $user): ?CallQueueEntry
    {
        $ownerId = $this->getOwnerId($user);

        $waiting = CallQueueEntry::where('user_id', $ownerId)
            ->where('status', 'waiting')
            ->orderBy('queued_at', 'asc')
            ->pluck('id');

        foreach ($waiting as $entryId) {
            $claimed = CallQueueEntry::whereKey($entryId)
                ->where('status', 'waiting')
                ->increment('dequeue_attempt', 1, ['status' => 'connecting']);

            // Somebody else took this caller between the list and the
            // claim. Theirs, not ours — and never both.
            if ($claimed !== 1) {
                continue;
            }

            $entry = CallQueueEntry::find($entryId);

            if (! $entry) {
                continue;
            }

            Log::info('Caller dequeued', [
                'entry_id' => $entry->id,
                'caller' => $entry->caller_number,
                'attempt' => $entry->dequeue_attempt,
                'waited' => (int) abs(now()->diffInSeconds($entry->queued_at)),
            ]);

            return $entry;
        }

        return null;
    }

    /**
     * Mark a queue entry as abandoned (caller hung up while waiting).
     */
    public function abandon(string $callSid): void
    {
        $entry = CallQueueEntry::where('call_sid', $callSid)
            ->whereIn('status', ['waiting', 'connecting'])
            ->first();

        if ($entry) {
            $waitedSeconds = (int) abs(now()->diffInSeconds($entry->queued_at));
            $entry->update([
                'status' => 'abandoned',
                'abandoned_at' => now(),
                'wait_duration_seconds' => $waitedSeconds,
            ]);

            Log::info('Caller abandoned queue', [
                'entry_id' => $entry->id,
                'caller' => $entry->caller_number,
                'waited_seconds' => $waitedSeconds,
            ]);
        }
    }

    /**
     * Mark a queue entry as connected, found by the caller's own call
     * identifier — what the provider reports when the caller leaves the
     * queue having been bridged to an agent.
     */
    public function markConnected(string $callSid): void
    {
        $this->recordConnection(
            CallQueueEntry::where('call_sid', $callSid)->active()->first()
        );
    }

    /**
     * The same transition, found by the entry's own id — what a provider
     * with no queue of its own reports, where the moment a caller is
     * connected is the moment the agent answers and asks for the document
     * that joins them to the caller's room. The agent's leg is a
     * different call with a different identifier, so the entry travels
     * with the URL it was dialled through instead.
     *
     * $attempt says which of that caller's legs is asking, and it is
     * required, not optional. A leg that was dialled for an attempt the
     * caller has since moved past belongs to an agent who was already
     * given them back: it must not record a connection, and it must not
     * be answered with the room the agent who holds them now is joining.
     * Matched in the same statement the status is, so there is no window
     * between checking and writing.
     */
    public function markConnectedById(int $entryId, int $attempt): void
    {
        $this->recordConnection(
            CallQueueEntry::whereKey($entryId)
                ->where('dequeue_attempt', $attempt)
                ->active()
                ->first()
        );
    }

    /**
     * The agent's own leg has ended; give the caller it was fetching back
     * to the queue if the agent was never put through.
     *
     * "Never put through" is the entry still being `connecting`: serving
     * the agent-join document is what records `connected`, so an entry
     * that has not reached it is one no agent ever joined. A conference
     * room is bound to one entry and dequeue() only ever looks at
     * `waiting` ones, so an entry left at `connecting` is a caller
     * sitting in a room no later dequeue can name: they would hear hold
     * music until they gave up. Twilio needs none of this — its caller is
     * in a provider-side queue and the next <Dial><Queue> takes them from
     * its head whatever the entry says.
     *
     * Anything that is not `connecting` is left exactly as it is: an
     * entry that reached connected, and one the caller abandoned or that
     * timed out, are all somebody else's outcome to have written. That is
     * what makes this safe on the callback of a call that really was
     * answered, and on a repeat of one already acted on.
     *
     * Nothing in the request is interpreted. The leg is dialled with a
     * hangup-only notification (plivoAgentLeg() passes hangupCallback,
     * never statusCallback), so being asked at all means the leg has
     * ended — there is no ring notification arriving here to be told
     * apart from it, and no payload field to misread. Plivo documents
     * none that could do the telling: CallUUID is the only one named for
     * either URL.
     *
     * $attempt says WHICH leg ended, and it is ours, carried in our own
     * signed URL rather than read out of the provider's payload. A
     * provider retries a callback it got no acknowledgement for and can
     * duplicate one outright, so a leg that ended two agents ago can
     * still be reported now — and without the attempt, that stale report
     * would take the caller away from the agent who is ringing them at
     * this moment and make them dequeuable into the room those two are
     * about to share. Matched here in the same conditional UPDATE as the
     * status, so there is no window between checking and writing; a
     * mismatch affects no rows and does nothing.
     */
    public function returnToQueueIfUnanswered(int $entryId, int $attempt): void
    {
        if (! $this->releaseClaim($entryId, $attempt)) {
            return;
        }

        $entry = CallQueueEntry::find($entryId);

        Log::info('Agent leg ended unanswered; caller returned to the queue', [
            'entry_id' => $entryId,
            'attempt' => $attempt,
            'user_id' => $entry?->user_id,
            'caller' => $entry?->caller_number,
        ]);
    }

    /**
     * Where a caller stands in the queue right now: first in line is 1.
     * Null when this call is not waiting in a queue at all, in which case
     * there is no honest number to read out.
     *
     * Live, not the `position` column: that one is written once at
     * enqueue time and goes stale the moment anyone ahead is answered.
     * Counted by id rather than queued_at because ids are assigned in
     * arrival order and cannot tie, while two callers arriving in the
     * same second can.
     */
    public function waitingPositionOf(string $callSid): ?int
    {
        $entry = CallQueueEntry::where('call_sid', $callSid)
            ->where('status', 'waiting')
            ->first();

        if (! $entry) {
            return null;
        }

        return CallQueueEntry::where('user_id', $entry->user_id)
            ->where('status', 'waiting')
            ->where($entry->getKeyName(), '<=', $entry->getKey())
            ->count();
    }

    /**
     * Check if the user's queue is full.
     */
    public function isQueueFull(User $user): bool
    {
        $owner = $this->getOwner($user);
        $ownerId = $this->getOwnerId($user);

        $waitingCount = CallQueueEntry::where('user_id', $ownerId)
            ->where('status', 'waiting')
            ->count();

        return $waitingCount >= $owner->max_queue_size;
    }

    /**
     * Get queue status for a user.
     */
    public function getQueueStatus(User $user): array
    {
        $ownerId = $this->getOwnerId($user);
        $queueName = $this->getQueueName($user);

        $entries = CallQueueEntry::where('user_id', $ownerId)
            ->where('status', 'waiting')
            ->orderBy('queued_at', 'asc')
            ->get();

        return [
            'waiting_count' => $entries->count(),
            'queue_name' => $queueName,
            'entries' => $entries->map(fn ($entry) => [
                'id' => $entry->id,
                'caller_number' => $entry->caller_number,
                'position' => $entry->position,
                'wait_seconds' => (int) abs(now()->diffInSeconds($entry->queued_at)),
                'queued_at' => $entry->queued_at->toISOString(),
            ])->values()->toArray(),
        ];
    }

    /**
     * Clean up expired queue entries (past max_wait_seconds).
     */
    public function cleanExpired(): void
    {
        $entries = CallQueueEntry::where('status', 'waiting')
            ->get();

        foreach ($entries as $entry) {
            $owner = User::find($entry->user_id);
            if (!$owner) {
                $entry->update(['status' => 'timeout']);
                continue;
            }

            $maxWait = $owner->max_wait_seconds ?? 300;
            $waitedSeconds = (int) abs(now()->diffInSeconds($entry->queued_at));
            if ($waitedSeconds >= $maxWait) {
                $entry->update([
                    'status' => 'timeout',
                    'wait_duration_seconds' => $waitedSeconds,
                ]);

                Log::info('Queue entry timed out', [
                    'entry_id' => $entry->id,
                    'user_id' => $entry->user_id,
                    'waited_seconds' => $entry->wait_duration_seconds,
                ]);
            }
        }
    }

    /**
     * The room a single waiting caller is put in, on a provider whose
     * only queueing construct is a conference.
     *
     * One room per caller, not one per queue: a conference is a room
     * where everyone hears everyone, so a second caller joining the first
     * one's room would be joining their call. The queue itself is still
     * the set of entries under one `queue_name`; the rooms are how each
     * of its members is held apart until an agent comes for them.
     */
    public function roomNameFor(CallQueueEntry $entry): string
    {
        return $entry->queue_name.'_'.$entry->getKey();
    }

    /**
     * The document the caller's own leg is answered with once they have
     * been put in the queue: it is what makes them wait.
     *
     * The two providers do genuinely different things here, and that is
     * not an abstraction leak to be tidied away. Twilio has a real
     * queueing primitive — <Enqueue> hands the caller to a provider-side
     * FIFO queue that <Dial><Queue> later takes them out of — and that is
     * the document a Twilio installation has always received, down to its
     * whitespace, so it is reproduced here exactly rather than rebuilt.
     * A provider without that primitive gets the moderated-conference
     * shape instead: the caller joins a room with
     * startConferenceOnEnter="false", so the room does not start, and
     * hears the waitUrl document until an agent joins and starts it.
     */
    public function xmlForWaiting(User $user, CallQueueEntry $entry): string
    {
        return $this->telephony->provider() === TelephonyProvider::TWILIO
            ? $this->enqueueXml($user, $entry)
            : $this->waitingRoomXml($user, $entry);
    }

    /**
     * The document the agent's own leg is answered with when they take a
     * caller out of the queue: it is what connects the two.
     *
     * $entry is what a conference-based provider needs and Twilio does
     * not: the agent is joining one specific caller's room, whereas
     * <Dial><Queue> names the queue and takes whoever is at its head.
     * Without one, this provider cannot name a room to join, so the leg
     * is hung up rather than answered with a document that would drop the
     * agent into silence.
     */
    public function xmlForAgentJoin(string $queueName, ?CallQueueEntry $entry = null): string
    {
        if ($this->telephony->provider() === TelephonyProvider::TWILIO) {
            return <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <Response>
                <Dial>
                    <Queue>{$queueName}</Queue>
                </Dial>
            </Response>
            XML;
        }

        $callXml = $this->telephony->callXml();

        if (! $entry) {
            return $callXml->hangup();
        }

        return $callXml->conference($this->roomNameFor($entry), [
            // This member is the moderator: their arrival is what starts
            // the room and stops the caller's hold music, and their
            // departure is what releases the caller's leg.
            'startConferenceOnEnter' => true,
            'endConferenceOnExit' => true,
            'stayAlone' => true,
            'maxParticipants' => self::ROOM_MEMBERS,
        ]);
    }

    /**
     * Ring the agent and connect them to the next waiting caller.
     *
     * The call is placed through the installation's locked provider, not
     * through one provider's SDK: which provider that is decides who is
     * dialled (a Twilio browser client, or the SIP endpoint a Plivo
     * browser registers as), which document answers them, and whose
     * credentials are used. A caller whose agent cannot be reached keeps
     * their place in the queue rather than being quietly dropped from it.
     *
     * Keeping their place gives back the claim THIS call made, and only
     * while that claim is still the one standing — never an unconditional
     * `waiting`. Placing a leg can be reported as failed long after the
     * provider accepted it: a read timeout is a failure on this side of a
     * request that was carried out, so the leg is live, and by the time
     * the failure is reported it may have rung out, handed the caller
     * back on its own and seen a second agent claim them. Writing
     * `waiting` regardless would take that caller off the agent ringing
     * them and offer them to a third — into the room the second one is
     * about to join. See releaseClaim().
     */
    public function triggerDequeue(User $user): ?CallQueueEntry
    {
        $entry = $this->dequeue($user);

        if (! $entry) {
            return null;
        }

        $owner = $this->getOwner($user);

        $leg = $this->telephony->provider() === TelephonyProvider::PLIVO
            ? $this->plivoAgentLeg($owner, $entry)
            : $this->twilioAgentLeg($owner, $this->getOwnerId($user), $entry);

        if ($leg === null) {
            // Nothing was dialled, so this claim is certainly still ours
            // — released through the same conditional write regardless,
            // so that every release on this table has one shape.
            $this->releaseClaim((int) $entry->getKey(), (int) $entry->dequeue_attempt);

            return null;
        }

        try {
            $result = $this->telephony->voice()
                ->initialize($leg['credentials'])
                ->makeCall(
                    $leg['to'],
                    // CallerId: show the queued caller's number
                    $entry->caller_number,
                    $leg['answer_url'],
                    $leg['options'],
                );

            if (! ($result['success'] ?? false)) {
                throw new RuntimeException($result['error'] ?? 'Unknown error');
            }

            Log::info('Dequeue call initiated', [
                'entry_id' => $entry->id,
                'user_id' => $owner->id,
                'caller' => $entry->caller_number,
            ]);

            return $entry;
        } catch (Throwable $e) {
            Log::error('Failed to initiate dequeue call', [
                'entry_id' => $entry->id,
                'error' => $e->getMessage(),
            ]);
            $this->releaseClaim((int) $entry->getKey(), (int) $entry->dequeue_attempt);

            return null;
        }
    }

    /**
     * Twilio's <Enqueue> document, reproduced exactly as an installation
     * running Twilio has always received it — the same heredoc, the same
     * indentation, the same ENT_XML1 escaping of a customer's own
     * greeting. See tests/Feature/Telephony/CallQueueProviderTest for the
     * byte-for-byte assertion that keeps it that way.
     */
    private function enqueueXml(User $user, CallQueueEntry $entry): string
    {
        $waitUrl = url('/twiml/queue-wait');
        $queueResultUrl = url('/twiml/queue-result');
        $queueName = $entry->queue_name;
        $unavailable = self::QUEUE_UNAVAILABLE;

        $greeting = $this->greetingFor($user);
        $greetingTwiml = $greeting
            ? '<Say voice="alice">'.htmlspecialchars($greeting, ENT_XML1).'</Say>'
            : '<Say voice="alice">'.self::DEFAULT_GREETING.'</Say>';

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<Response>
    {$greetingTwiml}
    <Enqueue waitUrl="{$waitUrl}" waitUrlMethod="POST" action="{$queueResultUrl}" method="POST">{$queueName}</Enqueue>
    <Say voice="alice">{$unavailable}</Say>
</Response>
XML;
    }

    /**
     * The conference-room equivalent, for a provider with no queueing
     * primitive of its own. The two URLs are Plivo's own: this branch is
     * only ever reached on Plivo, since Twilio is handled above and any
     * further provider would need its own queueing construct chosen
     * before it could be locked to anyway.
     *
     * waitSound is a URL Plivo asks for a document, not an audio file —
     * TwimlController::queueWait() answers it.
     *
     * The closing line is reached only if the room cannot be joined at
     * all: Plivo falls through to the next element rather than saying
     * anything, and a caller must never be left listening to silence.
     */
    private function waitingRoomXml(User $user, CallQueueEntry $entry): string
    {
        $callXml = $this->telephony->callXml();
        $greeting = $this->greetingFor($user) ?: self::DEFAULT_GREETING;

        return $callXml->buildResponse([
            $callXml->say($greeting, ['voice' => 'alice', 'language' => null]),
            $callXml->conference($this->roomNameFor($entry), [
                // The caller waits: their arrival must not start the room.
                'startConferenceOnEnter' => false,
                // Hanging up releases the agent's leg with them.
                'endConferenceOnExit' => true,
                // Explicit, never left to the provider's default: a queued
                // caller is alone in the room until an agent arrives, and
                // a member who may not stay alone is one who gets hung up
                // on the moment they are queued.
                'stayAlone' => true,
                'maxParticipants' => self::ROOM_MEMBERS,
                'waitUrl' => route('plivo-xml.queue-wait'),
                'action' => route('plivo-xml.queue-result'),
            ]),
            $callXml->say(self::QUEUE_UNAVAILABLE, ['voice' => 'alice', 'language' => null]),
        ]);
    }

    /**
     * Who to ring, with what, and where to answer them — Twilio.
     *
     * The request this produces is the one this method has always sent:
     * the agent's browser client, the dequeue document, a status
     * callback, and nothing else. machineDetection and statusCallbackEvent
     * are passed as an explicit null, which TwilioService reads as "leave
     * this out" — detection in particular holds the answer document back
     * until it finishes, which on a call to a browser means the agent
     * picks up and hears nothing.
     *
     * @return array{credentials: array<string, string>, to: string, answer_url: string, options: array<string, mixed>}|null
     */
    private function twilioAgentLeg(User $owner, int $ownerId, CallQueueEntry $entry): ?array
    {
        $credentials = $this->twilioCredentials($owner);

        if ($credentials === null) {
            return null;
        }

        return [
            'credentials' => $credentials,
            // To: agent's browser
            'to' => 'client:user_'.$ownerId,
            'answer_url' => url('/twiml/dequeue-call').'?queue_name='.urlencode($entry->queue_name),
            'options' => [
                'statusCallback' => url('/webhooks/twilio/call/status'),
                'machineDetection' => null,
                'statusCallbackEvent' => null,
            ],
        ];
    }

    /**
     * The same, on Plivo: the installation's one configured account, the
     * agent's SIP endpoint, and a dequeue URL that names the entry — the
     * agent joins one caller's room, so the document has to know which.
     *
     * A browser registers as an Endpoint the first time its softphone is
     * opened, and the SIP URI it is dialled by is the one Plivo returned
     * when creating it, never one rebuilt from the username. An agent who
     * has never opened their softphone has none, and there is nothing to
     * ring.
     *
     * The leg reports its end, and only its end, to the queue's own
     * endpoint, named with the same entry. Not to the AI-agent or inbound
     * status handlers: this leg has no row in either of their tables. And
     * hangupCallback rather than statusCallback, because what the queue
     * does on being told is hand its caller back — right once the leg has
     * ended, wrong while it is still ringing, and statusCallback would
     * deliver both notifications to the one URL with nothing in either to
     * tell them apart.
     *
     * Both URLs name the attempt as well as the entry, so what a leg
     * says — that it has ended, or that it has been answered — is tied to
     * this leg rather than to whichever agent happens to hold the caller
     * when it lands. A leg answered late would otherwise be let into the
     * room of the agent who holds them now. The V3 signature covers the
     * whole URL, query string included, so the attempt is attested
     * exactly as entry_id is.
     *
     * @return array{credentials: array<string, string>, to: string, answer_url: string, options: array<string, mixed>}|null
     */
    private function plivoAgentLeg(User $owner, CallQueueEntry $entry): ?array
    {
        $config = TelephonyProviderConfig::where('name', 'plivo')->first();
        $authId = $config?->credential('auth_id');
        $authToken = $config?->credential('auth_token');

        if (! $authId || ! $authToken) {
            Log::error('Cannot dequeue: the telephony provider is not configured', [
                'user_id' => $owner->id,
            ]);

            return null;
        }

        $sipUri = $this->plivoEndpoints->storedFor($owner)['sip_uri'] ?? null;

        if (! $sipUri) {
            Log::error('Cannot dequeue: the agent has no registered softphone to ring', [
                'user_id' => $owner->id,
            ]);

            return null;
        }

        return [
            'credentials' => ['auth_id' => $authId, 'auth_token' => $authToken],
            'to' => $sipUri,
            'answer_url' => route('plivo-xml.dequeue-call')
                .'?queue_name='.urlencode($entry->queue_name)
                .'&entry_id='.$entry->getKey()
                .'&attempt='.$entry->dequeue_attempt,
            'options' => [
                'hangupCallback' => route('plivo-xml.queue-agent-hangup')
                    .'?entry_id='.$entry->getKey()
                    .'&attempt='.$entry->dequeue_attempt,
            ],
        ];
    }

    /**
     * Whose Twilio account the agent's leg is placed through: the
     * customer's own credential row first, and the installation's global
     * configuration when they have none — the same two sources in the
     * same order TwilioService::initializeForUser() and
     * SMS\TwilioSMSProvider resolve every other Twilio request from.
     *
     * The queue read the first of those and stopped. Nothing in the
     * application writes a per-customer credential row except the
     * administrator's own Twilio settings screen, so on an installation
     * that follows the documented model — one account configured by the
     * administrator, customers never configuring telephony themselves —
     * no customer had one, and their dequeue button could not place a
     * leg at all: the agent was told there was nobody waiting while their
     * caller sat in the queue listening to hold music. A customer who
     * does hold a row is still placed through it, exactly as before.
     *
     * @return array{account_sid: string, auth_token: string}|null
     */
    private function twilioCredentials(User $owner): ?array
    {
        $credential = $owner->getActiveTwilioCredential();

        if ($credential) {
            return [
                'account_sid' => $credential->account_sid,
                'auth_token' => $credential->auth_token,
            ];
        }

        $globalConfig = TwilioGlobalConfig::active();

        if (! $globalConfig) {
            Log::error('Cannot dequeue: no active Twilio credentials', [
                'user_id' => $owner->id,
            ]);

            return null;
        }

        Log::info('Voice: Using global Twilio config for user', ['user_id' => $owner->id]);

        return [
            'account_sid' => $globalConfig->account_sid,
            'auth_token' => $globalConfig->getDecryptedAuthToken(),
        ];
    }

    /**
     * The greeting a caller hears, read from the user the number they
     * dialled is assigned to — never from the queue's owner.
     *
     * The two are the same user for a customer's own number, and
     * different for one assigned to a sub-agent. An agent cannot set a
     * greeting (CallQueueController::updateSettings refuses them), so
     * their column is null and such a number speaks the default line —
     * which is what it has always spoken. Reading the owner's column
     * instead would put a parent customer's custom greeting on every
     * number they handed to an agent.
     */
    private function greetingFor(User $user): ?string
    {
        return $user->queue_greeting_message;
    }

    /**
     * Hand a claimed caller back to the queue — the one statement every
     * release goes through, whether the leg was never placed, could not
     * be placed, or was placed and never answered.
     *
     * Conditional on both halves of the claim: the row is still
     * `connecting`, and it is still on the attempt this release belongs
     * to. A caller who has already been given back and claimed again
     * belongs to another agent now, and this writes nothing at all.
     * Reports of a leg's fate arrive late, out of order and more than
     * once, so "is this still ours" is asked and answered inside the one
     * statement rather than read first and written after.
     *
     * @return bool whether this release is the one that gave the caller back
     */
    private function releaseClaim(int $entryId, int $attempt): bool
    {
        return CallQueueEntry::whereKey($entryId)
            ->where('status', 'connecting')
            ->where('dequeue_attempt', $attempt)
            ->update(['status' => 'waiting']) === 1;
    }

    private function recordConnection(?CallQueueEntry $entry): void
    {
        if (! $entry) {
            return;
        }

        $entry->update([
            'status' => 'connected',
            'connected_at' => now(),
            'wait_duration_seconds' => (int) abs(now()->diffInSeconds($entry->queued_at)),
        ]);
    }

    /**
     * Get the queue owner (the customer, not the agent).
     */
    private function getOwner(User $user): User
    {
        if ($user->isAgent() && $user->parent_user_id) {
            return User::find($user->parent_user_id) ?? $user;
        }
        return $user;
    }
}
