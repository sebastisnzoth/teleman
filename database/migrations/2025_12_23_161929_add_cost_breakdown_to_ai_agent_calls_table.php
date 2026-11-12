<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('ai_agent_calls', function (Blueprint $table) {
            // Separate cost tracking for better transparency
            $table->decimal('twilio_cost', 8, 4)->default(0)->after('cost_estimate'); // Twilio call cost
            $table->decimal('openai_cost', 8, 4)->default(0)->after('twilio_cost'); // OpenAI API cost
            $table->integer('total_tokens')->default(0)->after('openai_cost'); // Total tokens used
            $table->integer('input_tokens')->default(0)->after('total_tokens'); // Input tokens
            $table->integer('output_tokens')->default(0)->after('input_tokens'); // Output tokens
        });
        
        // Migrate existing cost_estimate to twilio_cost
        DB::statement('UPDATE ai_agent_calls SET twilio_cost = cost_estimate WHERE cost_estimate > 0');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ai_agent_calls', function (Blueprint $table) {
            $table->dropColumn(['twilio_cost', 'openai_cost', 'total_tokens', 'input_tokens', 'output_tokens']);
        });
    }
};
