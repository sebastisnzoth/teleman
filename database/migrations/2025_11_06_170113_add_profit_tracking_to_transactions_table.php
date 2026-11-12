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
        Schema::table('transactions', function (Blueprint $table) {
            // Actual cost paid to service provider (Twilio)
            $table->decimal('actual_cost', 10, 4)->nullable()->after('amount');
            
            // Profit amount (amount - actual_cost)
            $table->decimal('profit_amount', 10, 4)->nullable()->after('actual_cost');
            
            // Profit margin percentage
            $table->decimal('profit_percentage', 5, 2)->nullable()->after('profit_amount');
            
            // Service type for analytics
            $table->enum('service_type', ['credit_purchase', 'call', 'sms', 'phone_number', 'manual_adjustment'])->default('manual_adjustment')->after('type');
            
            // Pricing tier used
            $table->string('pricing_tier')->nullable()->after('service_type');
            
            // Cost confirmation status
            $table->enum('cost_status', ['estimated', 'confirmed', 'adjusted'])->default('estimated')->after('status');
            
            // Index for profit analytics queries
            $table->index(['service_type', 'created_at']);
            $table->index(['cost_status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex(['service_type', 'created_at']);
            $table->dropIndex(['cost_status', 'created_at']);
            $table->dropColumn([
                'actual_cost',
                'profit_amount',
                'profit_percentage',
                'service_type',
                'pricing_tier',
                'cost_status'
            ]);
        });
    }
};
