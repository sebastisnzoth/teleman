<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telephony_providers', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique(); // 'twilio' or 'plivo' — TelephonyProvider enum's backing value
            $table->text('credentials')->nullable(); // encrypted JSON: Plivo's auth_id/auth_token. Twilio's stay in twilio_global_config.
            $table->string('app_id')->nullable(); // Plivo Application ID / Twilio TwiML App SID
            $table->string('webhook_url')->nullable();
            $table->json('settings')->nullable(); // provider-specific odds and ends
            $table->timestamp('verified_at')->nullable(); // credentials confirmed against the live provider
            $table->timestamp('locked_at')->nullable(); // non-null on at most one row — this IS the lock
            // Declared at create time (not a later Schema::table alter) so SQLite enforces it too.
            // nullOnDelete: an admin leaving must not cascade-delete the installation's provider lock —
            // the row (and its lock) must survive; only the "who locked it" attribution is lost.
            $table->foreignId('locked_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telephony_providers');
    }
};
