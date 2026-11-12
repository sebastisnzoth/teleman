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
            // DTMF Enhancements
            $table->integer('dtmf_num_digits')->default(1)->after('enable_dtmf')->comment('Number of digits to collect (1-10)');
            $table->integer('dtmf_timeout')->default(5)->after('dtmf_num_digits')->comment('Timeout in seconds for DTMF input');
            $table->text('dtmf_prompt')->nullable()->after('dtmf_timeout')->comment('Custom prompt message for DTMF');
            // Note: dtmf_actions already exists in campaigns table as JSON
            
            // Recording Enhancements
            $table->string('recording_mode')->default('full')->after('enable_recording')->comment('full, after_answer, skip_dtmf');
            $table->integer('recording_max_length')->default(300)->after('recording_mode')->comment('Max recording length in seconds');
            
            // Call Flow Enhancements
            $table->integer('answer_delay_seconds')->default(0)->after('enable_recording')->comment('Delay before speaking (0-5 seconds)');
            $table->boolean('enable_amd')->default(false)->after('answer_delay_seconds')->comment('Answering Machine Detection');
            $table->string('amd_action')->nullable()->after('enable_amd')->comment('leave_message, hangup, callback');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropColumn([
                'dtmf_num_digits',
                'dtmf_timeout',
                'dtmf_prompt',
                'recording_mode',
                'recording_max_length',
                'answer_delay_seconds',
                'enable_amd',
                'amd_action',
            ]);
        });
    }
};
