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
            $table->foreignId('message_variant_id')
                ->nullable()
                ->after('campaign_contact_id')
                ->constrained('message_variants')
                ->nullOnDelete();
            
            $table->index('message_variant_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('calls', function (Blueprint $table) {
            $table->dropForeign(['message_variant_id']);
            $table->dropColumn('message_variant_id');
        });
    }
};
