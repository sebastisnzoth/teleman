<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calls', function (Blueprint $table) {
            $table->foreignId('contact_id')->nullable()->after('campaign_contact_id')->constrained()->onDelete('set null');
            $table->string('disposition', 50)->nullable()->after('status');
            $table->text('call_notes')->nullable()->after('disposition');
            $table->timestamp('callback_at')->nullable()->after('call_notes');

            $table->index('contact_id');
            $table->index('disposition');
        });
    }

    public function down(): void
    {
        Schema::table('calls', function (Blueprint $table) {
            $table->dropForeign(['contact_id']);
            $table->dropIndex(['contact_id']);
            $table->dropIndex(['disposition']);
            $table->dropColumn(['contact_id', 'disposition', 'call_notes', 'callback_at']);
        });
    }
};
