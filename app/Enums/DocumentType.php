<?php

namespace App\Enums;

enum DocumentType: string
{
    case PASSPORT = 'passport';
    case DRIVERS_LICENSE = 'drivers_license';
    case NATIONAL_ID = 'national_id';
    case BUSINESS_REGISTRATION = 'business_registration';
    case TAX_CERTIFICATE = 'tax_certificate';
    case SELFIE_WITH_ID = 'selfie_with_id';

    public function label(): string
    {
        return match ($this) {
            self::PASSPORT => 'Passport',
            self::DRIVERS_LICENSE => 'Driver\'s License',
            self::NATIONAL_ID => 'National ID Card',
            self::BUSINESS_REGISTRATION => 'Business Registration',
            self::TAX_CERTIFICATE => 'Tax Certificate',
            self::SELFIE_WITH_ID => 'Selfie with ID',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::PASSPORT => 'Valid passport with clear photo and personal details',
            self::DRIVERS_LICENSE => 'Valid driver\'s license (both sides if applicable)',
            self::NATIONAL_ID => 'Government-issued national ID card',
            self::BUSINESS_REGISTRATION => 'Official business registration certificate',
            self::TAX_CERTIFICATE => 'Tax registration or EIN certificate',
            self::SELFIE_WITH_ID => 'Photo of yourself holding your ID next to your face',
        };
    }

    public function isIdDocument(): bool
    {
        return in_array($this, [
            self::PASSPORT,
            self::DRIVERS_LICENSE,
            self::NATIONAL_ID,
        ]);
    }

    public function isBusinessDocument(): bool
    {
        return in_array($this, [
            self::BUSINESS_REGISTRATION,
            self::TAX_CERTIFICATE,
        ]);
    }

    public function requiredFor(): array
    {
        return match ($this) {
            self::PASSPORT, self::DRIVERS_LICENSE, self::NATIONAL_ID => ['basic', 'business'],
            self::BUSINESS_REGISTRATION, self::TAX_CERTIFICATE => ['business'],
            self::SELFIE_WITH_ID => ['business'],
        };
    }

    public static function getIdDocumentOptions(): array
    {
        return [
            self::PASSPORT->value => self::PASSPORT->label(),
            self::DRIVERS_LICENSE->value => self::DRIVERS_LICENSE->label(),
            self::NATIONAL_ID->value => self::NATIONAL_ID->label(),
        ];
    }

    public static function getBusinessDocumentOptions(): array
    {
        return [
            self::BUSINESS_REGISTRATION->value => self::BUSINESS_REGISTRATION->label(),
            self::TAX_CERTIFICATE->value => self::TAX_CERTIFICATE->label(),
        ];
    }
}
