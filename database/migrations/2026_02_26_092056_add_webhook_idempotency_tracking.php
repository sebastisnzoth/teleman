<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add webhook idempotency tracking to prevent duplicate processing.
     */
    public function up(): void
    {
        // Create a dedicated table for tracking processed webhook events
        Schema::create('processed_webhooks', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 50)->index(); // twilio, stripe, razorpay, etc.
            $table->string('event_id', 255); // Twilio CallSid, Stripe event ID, etc.
            $table->string('event_type', 100); // status_update, recording, payment.success, etc.
            $table->string('status', 30)->default('processed');
            $table->json('metadata')->nullable();
            $table->timestamp('processed_at')->useCurrent();

            // Unique constraint: same event from same provider can only be processed once
            $table->unique(['provider', 'event_id', 'event_type'], 'processed_webhooks_unique');
        });

        // Add metadata column to calls table if it doesn't exist
        if (!Schema::hasColumn('calls', 'metadata')) {
            Schema::table('calls', function (Blueprint $table) {
                $table->json('metadata')->nullable()->after('error_message');
            });
        }

        // Add soft deletes to campaigns if not present
        if (!Schema::hasColumn('campaigns', 'deleted_at')) {
            Schema::table('campaigns', function (Blueprint $table) {
                $table->softDeletes();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('processed_webhooks');

        if (Schema::hasColumn('calls', 'metadata')) {
            Schema::table('calls', function (Blueprint $table) {
                $table->dropColumn('metadata');
            });
        }

        if (Schema::hasColumn('campaigns', 'deleted_at')) {
            Schema::table('campaigns', function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }
    }
};
