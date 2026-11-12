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
            $table->decimal('original_amount', 12, 2)->nullable()->after('amount');
            $table->string('original_currency', 3)->nullable()->after('original_amount');
            $table->decimal('exchange_rate', 12, 6)->nullable()->after('original_currency');
            $table->string('base_currency', 3)->default('USD')->after('exchange_rate');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn(['original_amount', 'original_currency', 'exchange_rate', 'base_currency']);
        });
    }
};
