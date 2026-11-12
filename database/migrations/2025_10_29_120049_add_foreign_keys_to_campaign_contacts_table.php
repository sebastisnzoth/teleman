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
        Schema::table('campaign_contacts', function (Blueprint $table) {
            $table->foreign('call_id')->references('id')->on('calls')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('campaign_contacts', function (Blueprint $table) {
            $table->dropForeign(['call_id']);
        });
    }
};
