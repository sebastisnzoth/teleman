import { InertiaLinkProps } from '@inertiajs/react';
import { LucideIcon } from 'lucide-react';
import type { TelephonySharedProp } from '@/types/telephony';

export interface Auth {
    user: User;
    kyc?: KycSummary | null;
    isImpersonating?: boolean;
}

export interface KycSummary {
    tier: KycTier;
    status: KycStatus | null;
    is_unverified: boolean;
    grace_period_ends_at: string;
    days_remaining: number;
    is_grace_period_expired: boolean;
    needs_renewal: boolean;
    expires_at: string | null;
    limits: {
        max_phone_numbers: number | null;
        max_calls_per_day: number | null;
        max_deposit: number | null;
    };
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavGroup {
    title: string;
    items: NavItem[];
}

export interface NavItem {
    title: string;
    href?: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon | null;
    isActive?: boolean;
    visible?: boolean;
    items?: NavItem[]; // Support for nested items
    isGroup?: boolean; // Indicates this is a group/section
    isSeparator?: boolean; // Visual separator
    badge?: string; // Optional badge text (e.g., 'NEW', 'BETA')
    roles?: string[]; // Restrict visibility to specific roles
}

export interface ActiveLanguage {
    id: number;
    code: string;
    name: string;
    native_name: string;
    flag: string;
    is_rtl: boolean;
    is_default: boolean;
}

export interface SharedData {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    kycEnabled: boolean;
    sidebarOpen: boolean;
    csrf_token: string;
    locale: string;
    translations: Record<string, string>;
    activeLanguages: ActiveLanguage[];
    baseCurrency: string;
    baseCurrencySymbol: string;
    /**
     * Which telephony provider this installation is locked to, and which
     * of the other provider's features to hide. Always present:
     * HandleInertiaRequests::share() serves it on every response,
     * including the install wizard's, falling back to
     * { provider: 'twilio', locked: false, hidden: [] } if the lookup
     * throws. Declared here so a component that reads it directly (rather
     * than through useTelephony()) is not typed `unknown` by the index
     * signature below.
     */
    telephony: TelephonySharedProp;
    [key: string]: unknown;
}

export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    two_factor_enabled?: boolean;
    status?: 'active' | 'inactive' | 'suspended';
    parent_user_id?: number | null;
    last_login_at?: string | null;
    roles?: Role[];
    permissions?: Permission[];
    parent?: User;
    agents?: User[];
    kyc?: {
        id: number;
        status: KycStatus;
        kyc_tier: KycTier;
    } | null;
    created_at: string;
    updated_at: string;
    [key: string]: unknown; // This allows for additional properties...
}

export interface Role {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    level: number;
    is_system: boolean;
    permissions?: Permission[];
    created_at: string;
    updated_at: string;
}

export interface Permission {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    module: string;
    created_at: string;
    updated_at: string;
}

// KYC Types
export type KycTier = 'unverified' | 'basic' | 'business';
export type KycStatus = 'pending' | 'approved' | 'rejected' | 'expired';
export type DocumentStatus = 'pending' | 'approved' | 'rejected';
export type DocumentType = 'passport' | 'drivers_license' | 'national_id' | 'business_registration' | 'tax_certificate' | 'selfie_with_id';

export type KycChecklistKey = 'phone' | 'id_document' | 'selfie_with_id' | 'business_document';

export type KycChecklistState = 'verified' | 'pending' | 'rejected' | 'missing';

export interface KycChecklistItem {
    key: KycChecklistKey;
    state: KycChecklistState;
    tier: KycTier;
}

export interface UserKycVerification {
    id: number;
    user_id: number;
    kyc_tier: KycTier;
    status: KycStatus;
    // Phone Verification
    phone_number?: string;
    phone_verified_at?: string;
    phone_verification_expires_at?: string;
    phone_verification_attempts?: number;
    // Business Information
    business_name?: string;
    business_registration_number?: string;
    business_type?: string;
    business_address_line1?: string;
    business_address_line2?: string;
    business_city?: string;
    business_state?: string;
    business_postal_code?: string;
    business_country?: string;
    // Documents
    id_document_type?: DocumentType;
    id_document_path?: string;
    id_document_status?: DocumentStatus;
    business_document_path?: string;
    business_document_status?: DocumentStatus;
    selfie_with_id_path?: string;
    selfie_with_id_status?: DocumentStatus;
    // Review
    reviewed_by?: number;
    reviewed_at?: string;
    rejection_reason?: string;
    admin_notes?: string;
    // Timestamps
    submitted_at?: string;
    approved_at?: string;
    expires_at?: string;
    created_at: string;
    updated_at: string;
    // Computed
    documents_status?: {
        id_document: { status: DocumentStatus; uploaded: boolean };
        business_document: { status: DocumentStatus; uploaded: boolean };
        selfie_with_id: { status: DocumentStatus; uploaded: boolean };
    };
    /**
     * Server-computed statement of what has ACTUALLY been verified. The KYC
     * page renders this rather than inferring from status, which is how a
     * phone-only account came to be shown four green ticks claiming its ID,
     * address and selfie were checked.
     */
    checklist?: KycChecklistItem[];
    /** Documents genuinely passed review — NOT satisfied by basic tier. */
    business_verified?: boolean;
    can_verify_phone?: boolean;
    needs_renewal?: boolean;
    // Relationships
    user?: User;
    reviewer?: User;
}

export interface KycLimits {
    max_phone_numbers: number | null;
    max_calls_per_day: number | null;
    max_deposit: number | null;
}

export interface KycUsage {
    phone_numbers: number;
    calls_today: number;
}

// Extend Window interface to include axios
declare global {
    interface Window {
        axios: import('axios').AxiosInstance;
    }
}

