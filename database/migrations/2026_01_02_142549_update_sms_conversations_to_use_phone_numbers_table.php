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
        Schema::table('sms_conversations', function (Blueprint $table) {
            // MySQL: drop foreign key first (it depends on the index)
            $table->dropForeign(['sms_phone_number_id']);

            // Then drop indexes that reference sms_phone_number_id
            $table->dropIndex(['sms_phone_number_id', 'status']);
            $table->dropIndex(['contact_phone', 'sms_phone_number_id']);

            // Then drop the column
            $table->dropColumn('sms_phone_number_id');

            // Add new foreign key to phone_numbers table
            $table->foreignId('phone_number_id')->after('id')->constrained('phone_numbers')->onDelete('cascade');
        });

        Schema::table('sms_conversations', function (Blueprint $table) {
            $table->index(['phone_number_id', 'status']);
            $table->index(['contact_phone', 'phone_number_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sms_conversations', function (Blueprint $table) {
            // MySQL: drop foreign key first
            $table->dropForeign(['phone_number_id']);
            $table->dropIndex(['phone_number_id', 'status']);
            $table->dropIndex(['contact_phone', 'phone_number_id']);
            $table->dropColumn('phone_number_id');
            $table->foreignId('sms_phone_number_id')->after('id')->constrained()->onDelete('cascade');
        });

        Schema::table('sms_conversations', function (Blueprint $table) {
            $table->index(['sms_phone_number_id', 'status']);
            $table->index(['contact_phone', 'sms_phone_number_id']);
        });
    }
};
