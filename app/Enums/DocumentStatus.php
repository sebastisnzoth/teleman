<?php

namespace App\Enums;

enum DocumentStatus: string
{
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending Review',
            self::APPROVED => 'Approved',
            self::REJECTED => 'Rejected',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PENDING => 'warning',
            self::APPROVED => 'success',
            self::REJECTED => 'destructive',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::PENDING => 'Clock',
            self::APPROVED => 'CheckCircle',
            self::REJECTED => 'XCircle',
        };
    }

    public static function getOptions(): array
    {
        return [
            self::PENDING->value => self::PENDING->label(),
            self::APPROVED->value => self::APPROVED->label(),
            self::REJECTED->value => self::REJECTED->label(),
        ];
    }
}
