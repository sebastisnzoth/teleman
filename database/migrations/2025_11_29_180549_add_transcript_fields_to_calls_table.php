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
        Schema::table('calls', function (Blueprint $table) {
            $table->string('transcript_sid')->nullable()->after('recording_sid');
            $table->text('transcript_text')->nullable()->after('transcript_sid');
            $table->string('transcript_status')->nullable()->after('transcript_text');
            $table->decimal('transcript_price', 10, 4)->nullable()->after('transcript_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('calls', function (Blueprint $table) {
            $table->dropColumn(['transcript_sid', 'transcript_text', 'transcript_status', 'transcript_price']);
        });
    }
};
