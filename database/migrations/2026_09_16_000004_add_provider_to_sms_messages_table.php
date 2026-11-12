<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sms_messages', function (Blueprint $table) {
            // Which provider sent/received this message. provider_message_id
            // already holds a provider-neutral SID, so no second identifier
            // column is needed here. Existing installs predate Plivo, so every
            // pre-existing row must read as Twilio — no backfill required.
            $table->string('provider', 20)->default('twilio')->after('provider_message_id');
        });
    }

    public function down(): void
    {
        Schema::table('sms_messages', function (Blueprint $table) {
            $table->dropColumn('provider');
        });
    }
};
