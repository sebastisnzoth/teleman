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
        Schema::table('contacts', function (Blueprint $table) {
            $table->integer('data_quality_score')->default(100)->after('notes'); // 0-100 score
            $table->json('quality_issues')->nullable()->after('data_quality_score'); // List of detected issues
            $table->json('ai_suggestions')->nullable()->after('quality_issues'); // AI cleaning suggestions
            $table->boolean('manually_verified')->default(false)->after('ai_suggestions');
            $table->timestamp('quality_checked_at')->nullable()->after('manually_verified');
            
            $table->index('data_quality_score');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropIndex(['data_quality_score']);
            $table->dropColumn([
                'data_quality_score',
                'quality_issues',
                'ai_suggestions',
                'manually_verified',
                'quality_checked_at',
            ]);
        });
    }
};
