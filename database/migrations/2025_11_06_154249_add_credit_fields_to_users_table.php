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
        Schema::table('users', function (Blueprint $table) {
            // Credit balance in user's preferred currency
            $table->decimal('credit_balance', 10, 2)->default(0.00)->after('email_verified_at');
            
            // User's preferred currency (ISO 4217 code)
            $table->char('preferred_currency', 3)->default('USD')->after('credit_balance');
            
            // Low balance alert settings
            $table->boolean('low_balance_alert_enabled')->default(true)->after('preferred_currency');
            $table->decimal('low_balance_threshold', 10, 2)->default(5.00)->after('low_balance_alert_enabled');
            
            // Last alert sent (to avoid spam)
            $table->timestamp('last_low_balance_alert_at')->nullable()->after('low_balance_threshold');
            
            // Index for performance
            $table->index('credit_balance');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['credit_balance']);
            $table->dropColumn([
                'credit_balance',
                'preferred_currency',
                'low_balance_alert_enabled',
                'low_balance_threshold',
                'last_low_balance_alert_at',
            ]);
        });
    }
};
