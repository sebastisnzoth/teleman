<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('crm_integrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('name', 100)->comment('HubSpot, Salesforce, Custom CRM, etc.');
            $table->string('webhook_url', 500);
            $table->json('events')->comment('call_completed, contact_added, campaign_started, etc.');
            $table->enum('auth_type', ['none', 'bearer', 'api_key', 'hmac'])->default('none');
            $table->json('auth_credentials')->nullable()->comment('token, key, secret');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_triggered_at')->nullable();
            $table->integer('total_triggers')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamps();

            // Indexes
            $table->index(['user_id', 'is_active']);
            $table->index('is_active');
            $table->index('last_triggered_at');
        });

        Schema::create('crm_webhook_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('crm_integration_id')->nullable()->constrained()->onDelete('set null');
            $table->string('event_type', 50);
            $table->json('payload');
            $table->integer('response_status')->nullable();
            $table->text('response_body')->nullable();
            $table->timestamp('triggered_at')->useCurrent();

            // Indexes
            $table->index(['user_id', 'event_type']);
            $table->index('crm_integration_id');
            $table->index('triggered_at');
            $table->index(['user_id', 'triggered_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('crm_webhook_logs');
        Schema::dropIfExists('crm_integrations');
    }
};
