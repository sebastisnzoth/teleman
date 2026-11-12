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
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            
            // User relationship
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            
            // Transaction type: credit (money added) or debit (money spent)
            $table->enum('type', ['credit', 'debit'])->index();
            
            // Amount and currency
            $table->decimal('amount', 10, 2);
            $table->char('currency', 3)->default('USD');
            
            // Balance tracking
            $table->decimal('balance_before', 10, 2);
            $table->decimal('balance_after', 10, 2);
            
            // Description and reference
            $table->string('description');
            $table->string('reference_type')->nullable()->comment('Call, Campaign, SMS, PhoneNumber, TopUp, AdminAdjustment');
            $table->unsignedBigInteger('reference_id')->nullable();
            
            // Payment gateway info (for credits/top-ups)
            $table->string('payment_gateway')->nullable()->comment('stripe, paypal, etc');
            $table->string('payment_id')->nullable()->comment('Gateway transaction ID');
            
            // Status
            $table->enum('status', ['pending', 'completed', 'failed', 'refunded'])->default('completed')->index();
            
            // Additional metadata (JSON)
            $table->json('metadata')->nullable()->comment('Call duration, SMS count, gateway response, etc');
            
            // Admin user (for manual adjustments)
            $table->foreignId('admin_id')->nullable()->constrained('users')->onDelete('set null');
            
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['user_id', 'type']);
            $table->index(['user_id', 'created_at']);
            $table->index(['reference_type', 'reference_id']);
            $table->index('payment_gateway');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
