<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit trail for the destructive telephony provider reset.
 *
 * One row per reset, recording which provider the installation was locked
 * to, who ran it, and how many rows each affected table lost or had
 * cleared. This is the only record that a reset happened at all: the
 * reset's whole job is to remove the evidence of the previous provider,
 * so without this row an admin looking at an empty telephony
 * configuration cannot tell a reset from a fresh install.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telephony_provider_resets', function (Blueprint $table) {
            $table->id();

            // The provider the installation was locked to before the reset.
            // Stored as a plain string rather than a foreign key to
            // telephony_providers: those rows are kept but wiped by the
            // reset, and this must still read correctly if a later change
            // ever removes one.
            $table->string('from_provider');

            // Where the reset was triggered from: the admin page or the
            // console. Without this, a console reset (which has no
            // authenticated user) is indistinguishable from a web reset
            // whose admin account was deleted afterwards.
            // Literal rather than TelephonyProviderReset::SOURCE_WEB: a
            // migration must keep meaning even if the model is renamed.
            $table->string('source')->default('web');

            // Null when the reset was run from the console (support
            // access), which has no authenticated user. Never taken from
            // request input — see ResetTelephonyProviderAction.
            $table->foreignId('by_user_id')->nullable()->constrained('users')->nullOnDelete();

            // Who ran it, snapshotted at the time. by_user_id above is
            // nulled if that admin's account is ever deleted, and this row
            // is the only evidence the reset happened at all — so the
            // identity is copied here rather than only referenced.
            $table->string('by_user_name')->nullable();
            $table->string('by_user_email')->nullable();

            // { "deleted": { table: count, ... }, "neutralised": { table: count, ... } }
            // The two groups are kept apart on purpose: the reset
            // deliberately neutralises some rows in place instead of
            // deleting them, and a flat count list would read as if that
            // data had been destroyed.
            $table->json('counts');

            // [ { "id": 12, "number": "+15550002222" }, ... ] — which phone
            // numbers this reset orphaned.
            //
            // A count alone is not enough. The reset writes status
            // 'released', which is the same status PhoneNumberService
            // writes *after* genuinely handing a number back to the
            // provider. Without this list the data cannot tell a number
            // that was really returned from one the reset retired locally
            // while it is still live and still billing at the provider —
            // exactly the expensive misunderstanding the danger-zone copy
            // exists to prevent. This is the list an admin works through
            // in their provider console afterwards.
            $table->json('neutralised_phone_numbers')->nullable();

            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telephony_provider_resets');
    }
};
