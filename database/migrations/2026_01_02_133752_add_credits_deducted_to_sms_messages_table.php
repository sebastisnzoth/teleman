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
        Schema::table('sms_messages', function (Blueprint $table) {
            $table->boolean('credits_deducted')->default(false)->after('cost');
            $table->unsignedBigInteger('transaction_id')->nullable()->after('credits_deducted');
            
            $table->foreign('transaction_id')
                ->references('id')
                ->on('transactions')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sms_messages', function (Blueprint $table) {
            $table->dropForeign(['transaction_id']);
            $table->dropColumn(['credits_deducted', 'transaction_id']);
        });
    }
};
