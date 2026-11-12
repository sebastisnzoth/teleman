<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TelephonyProvider;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Call extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'campaign_id',
        'campaign_contact_id',
        'contact_id',
        'message_variant_id',
        'call_type',
        'direction',
        'from_number',
        'to_number',
        'twilio_call_sid',
        'legacy_account_sid',
        'provider',
        'provider_call_sid',
        'provider_request_id',
        'status',
        'disposition',
        'call_notes',
        'callback_at',
        'duration_seconds',
        'recording_url',
        'recording_duration',
        'recording_sid',
        'enable_recording',
        'transcript_sid',
        'transcript_text',
        'transcript_status',
        'transcript_price',
        'answered_by',
        'dtmf_digits',
        'error_message',
        'price',
        'price_unit',
        'started_at',
        'ended_at',
        // AI Sentiment Analysis fields
        'sentiment',
        'sentiment_confidence',
        'lead_score',
        'lead_quality',
        'ai_summary',
        'key_intents',
        'sentiment_analyzed_at',
        'metadata',
    ];

    protected $casts = [
        'enable_recording' => 'boolean',
        'duration_seconds' => 'integer',
        'recording_duration' => 'integer',
        'price' => 'decimal:4',
        'transcript_price' => 'decimal:4',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        // AI Sentiment Analysis casts
        'sentiment_confidence' => 'integer',
        'lead_score' => 'integer',
        'key_intents' => 'array',
        'sentiment_analyzed_at' => 'datetime',
        'callback_at' => 'datetime',
        'metadata' => 'array',
    ];

    protected $appends = ['duration'];

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function campaignContact(): BelongsTo
    {
        return $this->belongsTo(CampaignContact::class);
    }

    public function messageVariant(): BelongsTo
    {
        return $this->belongsTo(MessageVariant::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(CallLog::class);
    }

    public function dtmfResponses(): HasMany
    {
        return $this->hasMany(CallDtmfResponse::class);
    }

    // Scopes
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeInProgress($query)
    {
        return $query->whereIn('status', ['initiated', 'ringing', 'in-progress']);
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopeAnswered($query)
    {
        return $query->where('status', 'completed')
            ->whereNotNull('duration_seconds')
            ->where('duration_seconds', '>', 0);
    }

    public function scopeManual($query)
    {
        return $query->where('call_type', 'manual');
    }

    public function scopeCampaign($query)
    {
        return $query->where('call_type', 'campaign');
    }

    // Helper Methods
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isInProgress(): bool
    {
        return in_array($this->status, ['initiated', 'ringing', 'in-progress']);
    }

    public function wasAnswered(): bool
    {
        return $this->status === 'completed' && $this->duration_seconds > 0;
    }

    public function hasRecording(): bool
    {
        return $this->recording_url !== null;
    }

    public function hasTranscript(): bool
    {
        return $this->transcript_text !== null && $this->transcript_text !== '';
    }

    public function hasDtmf(): bool
    {
        return $this->dtmf_digits !== null && $this->dtmf_digits !== '';
    }

    /**
     * Which telephony provider placed this call.
     *
     * tryFrom() with a Twilio fallback, not from(): `provider` is a plain
     * string column, and it is null on a Call instance that has not been
     * saved yet (the 'twilio' default lives in the schema, not the model)
     * and on any row selected without that column. from() would raise a
     * TypeError/ValueError for either. Every call that predates this
     * column was placed on Twilio, and Twilio is the product default
     * (D1), so anything unrecognised reads as Twilio.
     */
    public function providerEnum(): TelephonyProvider
    {
        return TelephonyProvider::tryFrom((string) $this->provider) ?? TelephonyProvider::TWILIO;
    }

    /**
     * Whether an incoming webhook/answer-hit's provider call id should
     * (over)write $currentValue — the identifier column
     * (twilio_call_sid or provider_call_sid) as it stood before this
     * write. Shared by every place that adopts a provider's own call
     * identifier (UpdateCallStatusAction's webhook handling,
     * TwimlController::manualCall()'s answer-hit handling), so the same
     * rule applies everywhere rather than drifting between two
     * hand-rolled copies.
     *
     * Twilio's create-call response is synchronous, so both columns
     * already hold the real, final SID from the moment the row was
     * created — never overwrite once set. (A later webhook for a
     * *different* leg of the same Twilio call — see
     * UpdateCallStatusAction's ParentCallSid capture — depends on that
     * never happening: it must not clobber the original SID with the
     * child leg's own.)
     *
     * Plivo's create-call response is asynchronous and only ever returns
     * a RequestUUID (see PlivoVoiceProvider::makeCall()'s docblock) —
     * every Plivo call-placement site seeds both SID columns with it at
     * creation, purely so the row stays findable and hangupable before
     * the real CallUUID exists — so the column is never null to begin
     * with. It is NOT safe to key adoption off calls.status here: a
     * Plivo call configured with only a hangup callback (no ring or
     * answer notification) can sit at its initial status for its entire
     * duration, and anything else that moves status in the meantime —
     * chiefly the stale-campaign sweeper, which does not know or care
     * whether a call is genuinely still connected — would permanently
     * block adoption of the real identifier if status were the signal.
     * provider_request_id is the one thing nothing else ever touches: as
     * long as the stored SID still equals it (or the SID is still
     * unset), nothing has confirmed the real identifier yet. Once
     * adopted, the SID diverges from provider_request_id permanently, so
     * this can never re-fire for the same call.
     */
    public function shouldAdoptProviderCallId(?string $currentValue): bool
    {
        if ($this->providerEnum() !== TelephonyProvider::PLIVO) {
            return ! $currentValue;
        }

        return $currentValue === null || $currentValue === $this->provider_request_id;
    }

    public function hasSentimentAnalysis(): bool
    {
        return $this->sentiment !== null;
    }

    public function isHotLead(): bool
    {
        return $this->lead_quality === 'hot';
    }

    public function isPositiveSentiment(): bool
    {
        return $this->sentiment === 'positive';
    }

    public function getDurationFormatted(): string
    {
        if ($this->duration_seconds === null) {
            return '0:00';
        }

        $minutes = floor($this->duration_seconds / 60);
        $seconds = $this->duration_seconds % 60;

        return sprintf('%d:%02d', $minutes, $seconds);
    }

    /**
     * Accessor for backward compatibility
     * Returns duration_seconds as duration
     */
    public function getDurationAttribute(): ?int
    {
        return $this->duration_seconds;
    }
}
