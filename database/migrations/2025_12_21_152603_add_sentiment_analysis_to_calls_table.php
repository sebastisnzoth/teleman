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
            // AI Sentiment Analysis fields
            $table->enum('sentiment', ['positive', 'neutral', 'negative'])->nullable()->after('transcript_price');
            $table->integer('sentiment_confidence')->nullable()->comment('0-100 confidence score')->after('sentiment');
            $table->integer('lead_score')->nullable()->comment('1-10 lead quality score')->after('sentiment_confidence');
            $table->enum('lead_quality', ['hot', 'warm', 'cold', 'not_interested'])->nullable()->after('lead_score');
            $table->text('ai_summary')->nullable()->comment('AI-generated call summary')->after('lead_quality');
            $table->json('key_intents')->nullable()->comment('Customer intents detected')->after('ai_summary');
            $table->timestamp('sentiment_analyzed_at')->nullable()->after('key_intents');
            
            // Add indexes for filtering
            $table->index('sentiment');
            $table->index('lead_quality');
            $table->index(['sentiment', 'lead_quality']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('calls', function (Blueprint $table) {
            $table->dropIndex(['calls_sentiment_index']);
            $table->dropIndex(['calls_lead_quality_index']);
            $table->dropIndex(['calls_sentiment_lead_quality_index']);
            
            $table->dropColumn([
                'sentiment',
                'sentiment_confidence',
                'lead_score',
                'lead_quality',
                'ai_summary',
                'key_intents',
                'sentiment_analyzed_at',
            ]);
        });
    }
};
