<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Only if it is missing: on MySQL, adding a column that is already
        // there is error 1060, which aborts the whole migrate run
        // half-applied and blocks every later release behind it.
        if (Schema::hasColumn('user_kyc_verifications', 'phone_verification_code_hash')) {
            return;
        }

        Schema::table('user_kyc_verifications', function (Blueprint $table) {
            // A hash of the outstanding phone verification code, for a
            // provider with no verification product of its own — the app
            // generates and checks that code itself, and must never keep
            // it in plain text. See PlivoVerificationProvider.
            //
            // A separate column rather than a second meaning for
            // phone_verification_code beside it: that one holds the
            // plain-text code the Twilio SMS path has always sent and
            // compared, and a column that is sometimes a secret and
            // sometimes a hash of one is a column nobody can reason about.
            //
            // 255 wide so that whichever hashing driver the installation is
            // configured with fits (bcrypt is 60 characters, argon2id
            // longer); nullable because a Twilio installation never
            // writes it and a spent code clears it.
            $table->string('phone_verification_code_hash')
                ->nullable()
                ->after('phone_verification_code');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('user_kyc_verifications', 'phone_verification_code_hash')) {
            return;
        }

        Schema::table('user_kyc_verifications', function (Blueprint $table) {
            $table->dropColumn('phone_verification_code_hash');
        });
    }
};
