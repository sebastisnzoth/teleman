<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calls', function (Blueprint $table) {
            // The request identifier a call was created with, kept
            // permanently — never overwritten, never cleared. On Twilio
            // this is always null (Twilio's create-call response is
            // synchronous; there is no separate request phase). On Plivo
            // it is the RequestUUID makeCall() returned, the same value
            // provider_call_sid is seeded with at creation.
            //
            // This is what makes identifier adoption (UpdateCallStatusAction,
            // TwimlController::manualCall()) independent of calls.status: a
            // status-based guard broke the moment anything else (the
            // stale-campaign sweeper, chiefly) could move status away from
            // 'initiated' before the real identifier arrived — which, for a
            // Plivo call with no ring/answer callback wired, could be the
            // call's entire duration. Adoption instead compares the
            // currently-stored identifier against this column: as long as
            // they still match, nothing has confirmed the real identifier
            // yet, regardless of what status says.
            $table->string('provider_request_id')->nullable()->after('provider_call_sid');
        });
    }

    public function down(): void
    {
        Schema::table('calls', function (Blueprint $table) {
            $table->dropColumn('provider_request_id');
        });
    }
};
