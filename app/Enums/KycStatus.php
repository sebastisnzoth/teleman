<?php

namespace App\Enums;

enum KycStatus: string
{
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case EXPIRED = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending Review',
            self::APPROVED => 'Approved',
            self::REJECTED => 'Rejected',
            self::EXPIRED => 'Expired',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PENDING => 'warning',
            self::APPROVED => 'success',
            self::REJECTED => 'destructive',
            self::EXPIRED => 'secondary',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::PENDING => 'Clock',
            self::APPROVED => 'CheckCircle',
            self::REJECTED => 'XCircle',
            self::EXPIRED => 'AlertCircle',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::PENDING => 'Your verification is under review. This typically takes 24-48 hours.',
            self::APPROVED => 'Your KYC verification has been approved.',
            self::REJECTED => 'Your verification was rejected. Please review the feedback and resubmit.',
            self::EXPIRED => 'Your verification has expired. Please submit updated documents.',
        };
    }

    public function canEdit(): bool
    {
        return match ($this) {
            self::PENDING => false, // Cannot edit while under review
            self::APPROVED => false, // Cannot edit approved verification
            self::REJECTED => true,  // Can resubmit after rejection
            self::EXPIRED => true,   // Can renew after expiration
        };
    }

    public function canResubmit(): bool
    {
        return in_array($this, [self::REJECTED, self::EXPIRED]);
    }

    public function needsAction(): bool
    {
        return in_array($this, [self::REJECTED, self::EXPIRED]);
    }

    public function isActive(): bool
    {
        return $this === self::APPROVED;
    }

    public static function getOptions(): array
    {
        return [
            self::PENDING->value => self::PENDING->label(),
            self::APPROVED->value => self::APPROVED->label(),
            self::REJECTED->value => self::REJECTED->label(),
            self::EXPIRED->value => self::EXPIRED->label(),
        ];
    }
}
