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
            // Which provider placed this call. Existing installs predate Plivo, so
            // every pre-existing row must read as Twilio — no backfill required.
            $table->string('provider')->default('twilio')->after('call_type');

            // Provider-neutral call identifier. twilio_call_sid is kept for
            // compatibility with the many existing call sites that already
            // read it directly — every call writer mirrors the same
            // identifier into both columns, for either provider, so those
            // call sites keep working unchanged on a Plivo installation
            // too. New code should read provider_call_sid.
            $table->string('provider_call_sid')->nullable()->after('twilio_call_sid');
        });
    }

    public function down(): void
    {
        Schema::table('calls', function (Blueprint $table) {
            $table->dropColumn(['provider', 'provider_call_sid']);
        });
    }
};
