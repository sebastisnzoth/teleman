<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('phone_numbers', function (Blueprint $table) {
            // Which provider owns this number. Existing installs predate Plivo, so
            // every pre-existing row must read as Twilio — no backfill required.
            $table->string('provider')->default('twilio')->after('status');

            // Provider-neutral number identifier, alongside (not replacing) twilio_sid.
            $table->string('provider_number_sid')->nullable()->after('provider');
        });
    }

    public function down(): void
    {
        Schema::table('phone_numbers', function (Blueprint $table) {
            $table->dropColumn(['provider', 'provider_number_sid']);
        });
    }
};
