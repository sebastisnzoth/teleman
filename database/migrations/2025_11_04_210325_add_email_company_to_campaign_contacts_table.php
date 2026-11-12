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
            if (!Schema::hasColumn('campaign_contacts', 'email')) {
                $table->string('email')->nullable()->after('last_name');
            }
            
            if (!Schema::hasColumn('campaign_contacts', 'company')) {
                $table->string('company')->nullable()->after('email');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('campaign_contacts', function (Blueprint $table) {
            if (Schema::hasColumn('campaign_contacts', 'email')) {
                $table->dropColumn('email');
            }
            
            if (Schema::hasColumn('campaign_contacts', 'company')) {
                $table->dropColumn('company');
            }
        });
    }
};
