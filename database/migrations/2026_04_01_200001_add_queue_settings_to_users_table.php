<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('queue_enabled')->default(true)->after('webhook_token');
            $table->unsignedInteger('max_queue_size')->default(5)->after('queue_enabled');
            $table->unsignedInteger('max_wait_seconds')->default(300)->after('max_queue_size');
            $table->text('queue_greeting_message')->nullable()->after('max_wait_seconds');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['queue_enabled', 'max_queue_size', 'max_wait_seconds', 'queue_greeting_message']);
        });
    }
};
