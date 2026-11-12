<?php

namespace App\Models;

use App\Enums\KycTier;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class UserKycVerification extends Model
{
    use HasFactory;

    /**
     * How many codes a customer may try against one delivered code before
     * it stops being checkable and a new one has to be requested.
     *
     * Five guesses against a uniformly random 6-digit code is a
     * 1-in-200,000 chance of hitting it, inside a window that closes after
     * PHONE_VERIFICATION_TTL_MINUTES; without a ceiling the same code could
     * be ground down in a few thousand requests.
     *
     * This is the only place the number lives. KycController's refusal,
     * the verify page's countdown, canVerifyPhone() and the Plivo
     * provider's attempt reservation all read it here, so it cannot change
     * in one of them and silently not in the others.
     */
    public const PHONE_VERIFICATION_MAX_ATTEMPTS = 5;

    /**
     * How long a delivered code stays good, in minutes.
     *
     * Long enough to survive a slow international carrier and a customer
     * who has to go and find their phone; short enough that a code read off
     * a lock screen, a shared desk or a carrier's logs is worthless by the
     * time anyone could use it.
     *
     * Read here by both of KycController's writes and by the Plivo
     * provider's own. The Twilio SMS fallback is the one exception: its
     * wording quotes the number as a literal, kept exactly as every
     * existing installation sends it — and a test fails if the two ever
     * disagree.
     */
    public const PHONE_VERIFICATION_TTL_MINUTES = 10;

    protected $fillable = [
        'user_id',
        'kyc_tier',
        'status',
        // Tier 1: Phone Verification
        'phone_number',
        'phone_verified_at',
        'phone_verification_code',
        // phone_verification_code_hash is deliberately absent: it decides
        // whether a phone counts as verified, so nothing may be able to
        // set it from request input. PlivoVerificationProvider writes it
        // with forceFill().
        'phone_verification_expires_at',
        'phone_verification_attempts',
        // Tier 2: Business Information
        'business_name',
        'business_registration_number',
        'business_type',
        // Address columns carry no business_ prefix in the schema; the
        // prefixed names matched nothing and every business submission failed.
        'address_line1',
        'address_line2',
        'city',
        'state',
        'postal_code',
        'country',
        // Documents
        'id_document_type',
        'id_document_path',
        'id_document_status',
        'business_document_path',
        'business_document_status',
        'selfie_with_id_path',
        // Column is selfie_status; the selfie_with_id_ prefix never existed in the schema.
        'selfie_status',
        // Admin Review
        'reviewed_by',
        'reviewed_at',
        'rejection_reason',
        'admin_notes',
        // Timestamps
        'submitted_at',
        'approved_at',
        'expires_at',
    ];

    protected $casts = [
        'phone_verified_at' => 'datetime',
        'phone_verification_expires_at' => 'datetime',
        'phone_verification_attempts' => 'integer',
        'reviewed_at' => 'datetime',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    protected $hidden = [
        'phone_verification_code',
        'phone_verification_code_hash',
    ];

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    // Accessors for document URLs
    public function getIdDocumentUrlAttribute(): ?string
    {
        return $this->id_document_path
            ? Storage::disk('private')->url($this->id_document_path)
            : null;
    }

    public function getBusinessDocumentUrlAttribute(): ?string
    {
        return $this->business_document_path
            ? Storage::disk('private')->url($this->business_document_path)
            : null;
    }

    public function getSelfieWithIdUrlAttribute(): ?string
    {
        return $this->selfie_with_id_path
            ? Storage::disk('private')->url($this->selfie_with_id_path)
            : null;
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    public function scopeExpired($query)
    {
        return $query->where('status', 'expired');
    }

    public function scopeBasicTier($query)
    {
        return $query->where('kyc_tier', 'basic');
    }

    public function scopeBusinessTier($query)
    {
        return $query->where('kyc_tier', 'business');
    }

    public function scopeUnverified($query)
    {
        return $query->where('kyc_tier', 'unverified');
    }

    public function scopeExpiringWithin($query, int $days)
    {
        return $query->where('expires_at', '<=', Carbon::now()->addDays($days))
            ->where('status', 'approved');
    }

    public function scopePendingReview($query)
    {
        return $query->where('status', 'pending')
            ->whereNotNull('submitted_at')
            ->orderBy('submitted_at', 'asc');
    }

    // Helper methods
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    public function isExpired(): bool
    {
        return $this->status === 'expired' ||
               ($this->expires_at && $this->expires_at->isPast());
    }

    public function isBasicTier(): bool
    {
        return $this->kyc_tier === 'basic';
    }

    public function isBusinessTier(): bool
    {
        return $this->kyc_tier === 'business';
    }

    public function isUnverified(): bool
    {
        return $this->kyc_tier === 'unverified';
    }

    public function canVerifyPhone(): bool
    {
        // Can verify if phone not already verified
        return is_null($this->phone_verified_at) &&
               $this->phone_verification_attempts < self::PHONE_VERIFICATION_MAX_ATTEMPTS &&
               (! $this->phone_verification_expires_at || $this->phone_verification_expires_at->isFuture());
    }

    public function needsRenewal(int $days = 60): bool
    {
        if (! $this->isApproved() || ! $this->expires_at) {
            return false;
        }

        $daysUntilExpiry = Carbon::now()->diffInDays($this->expires_at, false);

        return $daysUntilExpiry >= 0 && $daysUntilExpiry <= $days;
    }

    public function getFullBusinessAddress(): string
    {
        $parts = array_filter([
            $this->address_line1,
            $this->address_line2,
            $this->city,
            $this->state,
            $this->postal_code,
            $this->country,
        ]);

        return implode(', ', $parts);
    }

    public function getAllDocumentsStatus(): array
    {
        return [
            'id_document' => [
                'status' => $this->id_document_status,
                'uploaded' => ! empty($this->id_document_path),
            ],
            'business_document' => [
                'status' => $this->business_document_status,
                'uploaded' => ! empty($this->business_document_path),
            ],
            'selfie_with_id' => [
                'status' => $this->selfie_status,
                'uploaded' => ! empty($this->selfie_with_id_path),
            ],
        ];
    }

    /**
     * What this account has ACTUALLY had verified, item by item.
     *
     * Computed here rather than in the page so the UI has nothing left to
     * infer. It previously rendered a fixed list of four requirements under
     * green ticks, so an account that had only entered an SMS code was told
     * its government ID, proof of address and selfie were verified (client
     * report 2026-08-02). One of those four — proof of address — has no
     * column, no upload and no review step anywhere in the system, so it
     * could never be satisfied at all. Only collectable items appear here.
     *
     * `state` is one of: verified, pending, rejected, missing.
     * `tier` is the tier that requires the item.
     *
     * @return list<array{key: string, state: string, tier: string}>
     */
    public function getVerificationChecklist(): array
    {
        $documents = $this->getAllDocumentsStatus();

        $documentState = static function (array $document): string {
            // A file on disk is not a passed check — an unreviewed upload is
            // pending, never verified.
            return match ($document['status']) {
                'approved' => 'verified',
                'rejected' => 'rejected',
                default => $document['uploaded'] ? 'pending' : 'missing',
            };
        };

        return [
            [
                'key' => 'phone',
                'state' => $this->phone_verified_at ? 'verified' : 'missing',
                'tier' => KycTier::BASIC->value,
            ],
            [
                'key' => 'id_document',
                'state' => $documentState($documents['id_document']),
                'tier' => KycTier::BUSINESS->value,
            ],
            [
                'key' => 'selfie_with_id',
                'state' => $documentState($documents['selfie_with_id']),
                'tier' => KycTier::BUSINESS->value,
            ],
            [
                'key' => 'business_document',
                'state' => $documentState($documents['business_document']),
                'tier' => KycTier::BUSINESS->value,
            ],
        ];
    }

    /**
     * True only when the document checks genuinely passed review. Distinct
     * from `isApproved()`, which is satisfied by basic (phone-only) tier.
     */
    public function isBusinessVerified(): bool
    {
        return $this->isApproved()
            && $this->kyc_tier === KycTier::BUSINESS->value
            && $this->id_document_status === 'approved'
            && $this->business_document_status === 'approved'
            && $this->selfie_status === 'approved';
    }

    public function allDocumentsApproved(): bool
    {
        if ($this->isBasicTier()) {
            return true; // Basic tier doesn't require documents
        }

        return $this->id_document_status === 'approved' &&
               $this->business_document_status === 'approved' &&
               $this->selfie_status === 'approved';
    }

    public function hasAnyRejectedDocument(): bool
    {
        return $this->id_document_status === 'rejected' ||
               $this->business_document_status === 'rejected' ||
               $this->selfie_status === 'rejected';
    }
}
