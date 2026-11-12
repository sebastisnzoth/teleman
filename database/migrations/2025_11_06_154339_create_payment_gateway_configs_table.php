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
        Schema::create('payment_gateway_configs', function (Blueprint $table) {
            $table->id();
            
            // Gateway identifier
            $table->string('gateway')->unique()->comment('stripe, paypal, razorpay, etc');
            
            // Display name
            $table->string('name');
            
            // Configuration (encrypted JSON)
            $table->text('config')->comment('Encrypted JSON: API keys, secrets, webhook URLs, etc');
            
            // Status
            $table->boolean('is_active')->default(false)->index();
            $table->boolean('is_test_mode')->default(true);
            
            // Supported currencies (JSON array)
            $table->json('supported_currencies')->nullable()->comment('["USD", "EUR", "GBP"]');
            
            // Processing fees (optional)
            $table->decimal('fee_percentage', 5, 2)->default(0.00)->comment('Gateway fee %');
            $table->decimal('fee_fixed', 10, 2)->default(0.00)->comment('Gateway fixed fee');
            
            // Display order and icon
            $table->integer('sort_order')->default(0);
            $table->string('icon_url')->nullable();
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_gateway_configs');
    }
};
