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
        // Add scheduling fields to contacts table
        Schema::table('contacts', function (Blueprint $table) {
            if (!Schema::hasColumn('contacts', 'optimal_call_time')) {
                $table->timestamp('optimal_call_time')->nullable()->after('phone_number');
            }
            if (!Schema::hasColumn('contacts', 'optimal_call_confidence')) {
                $table->tinyInteger('optimal_call_confidence')->default(0)->after('timezone');
            }
            if (!Schema::hasColumn('contacts', 'best_call_hours')) {
                $table->json('best_call_hours')->nullable()->after('optimal_call_confidence');
            }
        });

        // Add scheduling fields to campaigns table
        Schema::table('campaigns', function (Blueprint $table) {
            if (!Schema::hasColumn('campaigns', 'best_call_hours')) {
                $table->json('best_call_hours')->nullable();
            }
            if (!Schema::hasColumn('campaigns', 'best_call_days')) {
                $table->json('best_call_days')->nullable();
            }
            if (!Schema::hasColumn('campaigns', 'use_smart_scheduling')) {
                $table->boolean('use_smart_scheduling')->default(false);
            }
        });

        // Add scheduling fields to campaign_contacts table
        Schema::table('campaign_contacts', function (Blueprint $table) {
            if (!Schema::hasColumn('campaign_contacts', 'scheduled_call_time')) {
                $table->timestamp('scheduled_call_time')->nullable()->after('status');
            }
            if (!Schema::hasColumn('campaign_contacts', 'timezone')) {
                $table->string('timezone', 50)->nullable()->after('scheduled_call_time');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            if (Schema::hasColumn('contacts', 'optimal_call_time')) {
                $table->dropColumn('optimal_call_time');
            }
            if (Schema::hasColumn('contacts', 'optimal_call_confidence')) {
                $table->dropColumn('optimal_call_confidence');
            }
            if (Schema::hasColumn('contacts', 'best_call_hours')) {
                $table->dropColumn('best_call_hours');
            }
        });

        Schema::table('campaigns', function (Blueprint $table) {
            if (Schema::hasColumn('campaigns', 'best_call_hours')) {
                $table->dropColumn('best_call_hours');
            }
            if (Schema::hasColumn('campaigns', 'best_call_days')) {
                $table->dropColumn('best_call_days');
            }
            if (Schema::hasColumn('campaigns', 'use_smart_scheduling')) {
                $table->dropColumn('use_smart_scheduling');
            }
        });

        Schema::table('campaign_contacts', function (Blueprint $table) {
            if (Schema::hasColumn('campaign_contacts', 'scheduled_call_time')) {
                $table->dropColumn('scheduled_call_time');
            }
            if (Schema::hasColumn('campaign_contacts', 'timezone')) {
                $table->dropColumn('timezone');
            }
        });
    }
};
