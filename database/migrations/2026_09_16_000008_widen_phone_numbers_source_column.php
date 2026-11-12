<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `source` was a two-value enum ('twilio_direct', 'sip_trunk') with no
     * room for a Plivo-purchased number, which needs a third value,
     * 'plivo_direct'. Rather than growing the native enum (MySQL) / CHECK
     * constraint (SQLite emulates enum() that way) to three values and
     * having to touch it again for the next provider, this widens the
     * column to a plain string, exactly mirroring the fix already applied
     * to campaign_contacts.status for the identical problem — see
     * 2025_10_29_172947_update_campaign_contacts_status_enum.php. A string
     * column is trivially valid on both database engines this product
     * runs on (MySQL in production, SQLite in tests) without any
     * driver-conditional SQL. Application code (PhoneNumberService,
     * NumberRequestService) is the source of truth for which values are
     * actually written: 'twilio_direct', 'sip_trunk', 'plivo_direct'.
     *
     * Must run before any code writes 'plivo_direct' to this column: under
     * MySQL's strict mode (this product's documented production setting),
     * inserting a value outside a native ENUM's declared list is rejected
     * outright rather than silently truncated.
     */
    public function up(): void
    {
        Schema::table('phone_numbers', function (Blueprint $table) {
            $table->string('source', 50)->default('twilio_direct')->comment('Where the number comes from')->change();
        });
    }

    /**
     * Not reverted to the original two-value enum. A 'plivo_direct' row
     * written after this migration ran would make that ALTER fail (or, on
     * a lenient SQL mode, silently corrupt the value) on rollback — the
     * same reasoning behind campaign_contacts.status's own down() leaving
     * that column a string too rather than restoring its original enum.
     */
    public function down(): void
    {
        Schema::table('phone_numbers', function (Blueprint $table) {
            $table->string('source', 50)->default('twilio_direct')->comment('Where the number comes from')->change();
        });
    }
};
