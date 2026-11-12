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
        Schema::create('pricing_rules', function (Blueprint $table) {
            $table->id();
            
            // Service identification
            $table->enum('service_type', ['call', 'sms', 'phone_number'])->index();
            $table->string('country_code', 2)->index(); // ISO 3166-1 alpha-2
            $table->string('country_name')->nullable();
            
            // Base cost (what we pay to provider like Twilio)
            $table->decimal('base_cost', 10, 4);
            $table->string('base_cost_unit', 10)->default('per_minute'); // per_minute, per_sms, per_month
            
            // Markup configuration
            $table->enum('markup_type', ['percentage', 'fixed', 'hybrid'])->default('percentage');
            $table->decimal('markup_value', 10, 4); // percentage (e.g., 25.00 for 25%) or fixed amount
            $table->decimal('markup_fixed', 10, 4)->nullable(); // for hybrid: percentage + fixed
            
            // Minimum charge to prevent loss on short calls
            $table->decimal('minimum_charge', 10, 4)->default(0.0100);
            
            // Pricing tier for different customer segments
            $table->string('tier')->default('standard'); // standard, premium, enterprise
            
            // Metadata
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true)->index();
            
            // Auto-update from provider API
            $table->boolean('auto_update_base_cost')->default(false);
            $table->timestamp('last_cost_update')->nullable();
            
            $table->timestamps();
            
            // Unique constraint to prevent duplicate rules
            $table->unique(['service_type', 'country_code', 'tier']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pricing_rules');
    }
};
