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
        Schema::table('campaigns', function (Blueprint $table) {
            $table->foreignId('phone_number_id')->nullable()->after('user_id')->constrained('phone_numbers')->onDelete('set null');
            $table->index('phone_number_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropForeign(['phone_number_id']);
            $table->dropIndex(['phone_number_id']);
            $table->dropColumn('phone_number_id');
        });
    }
};
