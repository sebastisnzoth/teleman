<?php

declare(strict_types=1);

namespace App\Services\Telephony\Plivo;

use App\Contracts\Telephony\SmsProviderInterface;
use Exception;
use Illuminate\Support\Facades\Log;
use Plivo\RestClient;

/**
 * Plivo SMS provider implementation.
 *
 * Key differences from Twilio:
 * - Auth: auth_id + auth_token (not account_sid).
 * - Send params: src/dst/text (not from/to/body).
 * - Message ID: message UUID (not message SID).
 *
 * messages->create() tolerates being called with one associative array —
 * unlike calls->create(), it explicitly destructures a leading array
 * argument into src/dst/text/optionalArgs, so the prototype's
 * ->create($params) call shape is safe on the installed SDK (v4.71). Its
 * response, however, is a MessageCreateResponse object with no
 * ArrayAccess (read via getMessageUuid(), which returns an array — one
 * entry per SMS segment for a long message). That array's length is the
 * real segment count and send() reports it as such: `num_segments` is
 * what sms_messages.num_segments stores, which is what cost and
 * credits_deducted are computed from, so hardcoding 1 would bill a
 * three-segment message as one.
 */
class PlivoSMSProvider implements SmsProviderInterface
{
    private ?RestClient $client = null;

    private string $authToken = '';

    public function initialize(array $credentials): static
    {
        $this->authToken = $credentials['auth_token'] ?? '';
        $this->client = new RestClient(
            $credentials['auth_id'] ?? null,
            $credentials['auth_token'] ?? null
        );

        return $this;
    }

    public function send(string $from, string $to, string $message, array $options = []): array
    {
        try {
            $this->ensureInitialized();

            $params = [
                'src' => $from,
                'dst' => $to,
                'text' => $message,
                // Without this, Plivo never calls back with a delivery
                // receipt at all, and SmsWebhookController::handleStatus()
                // — the only place credits are actually deducted for an
                // outbound message — has nothing to react to: the message
                // sends, and it is never billed. Unlike Twilio (whose
                // account/messaging-service console settings can supply a
                // status callback with no code on this side at all),
                // Plivo's own API takes it per-message, so it must be set
                // here on every send. Route name, not a hardcoded path:
                // sms.webhook.plivo.status is guarded by plivo.signature,
                // registered in routes/api.php alongside .incoming.
                'url' => route('sms.webhook.plivo.status'),
            ];

            if (! empty($options['media_urls'])) {
                $params['media_urls'] = $options['media_urls'];
                $params['type'] = 'mms';
            }

            $response = $this->client->messages->create($params);

            // One message UUID per segment — see the class docblock. The
            // SDK normalises a missing message_uuid to [], so count() is
            // 0 for a response that carried none; report at least 1,
            // since Plivo accepted a message that certainly was not sent
            // in zero pieces.
            $messageUuids = $response->getMessageUuid();

            return [
                'success' => true,
                'message_id' => $messageUuids[0] ?? null,
                'status' => 'queued',
                'cost' => null, // Plivo doesn't return cost immediately.
                'num_segments' => max(1, count($messageUuids)),
                'error' => null,
            ];
        } catch (Exception $e) {
            Log::error('Plivo SMS send failed', [
                'from' => $from,
                'to' => $to,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message_id' => null,
                'status' => 'failed',
                'cost' => null,
                // Nothing was sent, so there is no real count to report.
                // Kept at 1 rather than 0 purely so the two providers'
                // failure shapes stay identical — TwilioSmsProvider's
                // failure return says 1 for the same reason. Nothing
                // bills off a send that reported success => false.
                'num_segments' => 1,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function sendBatch(string $from, array $recipients, array $options = []): array
    {
        $results = [];
        $successCount = 0;
        $failedCount = 0;

        foreach ($recipients as $recipient) {
            $result = $this->send($from, $recipient['to'], $recipient['message'], $options);

            if ($result['success']) {
                $successCount++;
            } else {
                $failedCount++;
            }

            $results[] = [
                'to' => $recipient['to'],
                'result' => $result,
            ];
        }

        return [
            'success' => $successCount,
            'failed' => $failedCount,
            'total' => count($recipients),
            'results' => $results,
        ];
    }

    public function getStatus(string $messageId): array
    {
        try {
            $this->ensureInitialized();

            $message = $this->client->messages->get($messageId);

            return [
                'status' => $this->mapMessageStatus((string) (self::property($message, 'messageState') ?? '')),
                'delivered_at' => null, // Plivo doesn't provide this directly.
                'error' => self::property($message, 'errorMessage'),
                'error_code' => self::property($message, 'errorCode'),
            ];
        } catch (Exception $e) {
            Log::error('Plivo SMS status check failed', [
                'message_id' => $messageId,
                'error' => $e->getMessage(),
            ]);

            return [
                'status' => 'unknown',
                'delivered_at' => null,
                'error' => $e->getMessage(),
                'error_code' => null,
            ];
        }
    }

    /**
     * SmsProviderInterface's signature predates Plivo's V3 scheme and
     * carries no nonce argument — see PlivoVoiceProvider::validateWebhook()
     * for why the nonce travels inside $payload under this key. The
     * algorithm and the request URL are both built through PlivoSignature
     * — the one place they are implemented, shared with the middleware
     * and PlivoVoiceProvider so a correction lands once rather than
     * diverging (PlivoSignature::requestedUrl() specifically avoids
     * Request::fullUrl(), which reorders and re-encodes the query string
     * — see its own docblock).
     */
    public function validateWebhook(array $payload, string $signature): bool
    {
        try {
            if ($this->authToken === '') {
                return false;
            }

            $nonce = $payload['X-Plivo-Signature-V3-Nonce'] ?? null;
            if (empty($nonce)) {
                return false;
            }

            unset($payload['X-Plivo-Signature-V3-Nonce']);

            $url = PlivoSignature::requestedUrl(request());

            return PlivoSignature::matches(
                PlivoSignature::compute($url, $payload, (string) $nonce, $this->authToken),
                $signature
            );
        } catch (Exception $e) {
            Log::error('Plivo webhook validation failed', ['error' => $e->getMessage()]);

            return false;
        }
    }

    public function parseIncoming(array $payload): array
    {
        return [
            'from' => $payload['From'] ?? '',
            'to' => $payload['To'] ?? '',
            'body' => $payload['Text'] ?? '',
            'message_id' => $payload['MessageUUID'] ?? '',
            'timestamp' => now()->toDateTimeString(),
            'num_segments' => $payload['Units'] ?? 1,
            'media_urls' => $this->extractMediaUrls($payload),
        ];
    }

    public function getName(): string
    {
        return 'plivo';
    }

    public function supportsMMS(): bool
    {
        return true;
    }

    // ─── Private Helpers ─────────────────────────

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

    /**
     * Read one field off a Plivo SDK resource, safely.
     *
     * The same hazard PlivoVoiceProvider::property() documents at length:
     * Plivo\Resources\Resource serves every field through __get() off a
     * public $properties bag and declares no __isset(), so isset()/empty()
     * on a present field is always false and `??` is the only form that
     * reads it — but __get() *throws* PlivoRestException for a name the
     * bag does not hold, which `??` cannot suppress. That was live here:
     * this SDK's Message resource never populates `errorMessage` at all,
     * so `$message->errorMessage ?? null` threw on every call and
     * getStatus()'s catch answered 'unknown' for every message, mapped or
     * not. One absent field must not cost the caller the whole response.
     */
    private static function property(object $resource, string $name): mixed
    {
        try {
            return $resource->{$name} ?? null;
        } catch (Exception) {
            return null;
        }
    }

    /**
     * Map Plivo's message-state vocabulary onto the app's own.
     *
     * The default arm no longer returns the provider's own string. Every
     * value this method produces is handed to a caller that expects the
     * closed vocabulary above, and a verbatim passthrough let any
     * undocumented or future Plivo state out of here — the same pattern
     * already closed twice on this branch
     * (PlivoWebhookNormalizer::mapCallStatus() and mapDirection()).
     *
     * 'unknown' is the fallback because it is already this method's
     * caller's own "no opinion" value: getStatus()'s catch block answers
     * with exactly that. The warning names the value so it can be mapped
     * deliberately.
     */
    private function mapMessageStatus(string $plivoStatus): string
    {
        $status = strtolower(trim($plivoStatus));

        return match ($status) {
            'queued' => 'queued',
            'sent' => 'sent',
            'delivered' => 'delivered',
            'undelivered' => 'undelivered',
            'failed' => 'failed',
            'rejected' => 'failed',
            default => $this->unmappedMessageStatus($status),
        };
    }

    private function unmappedMessageStatus(string $status): string
    {
        Log::warning('Plivo reported an unmapped message state; reporting it as unknown', [
            'provider' => 'plivo',
            'received' => $status,
        ]);

        return 'unknown';
    }

    private function ensureInitialized(): void
    {
        if (! $this->client) {
            throw new Exception('Plivo SMS provider not initialized. Call initialize() first.');
        }
    }
}
