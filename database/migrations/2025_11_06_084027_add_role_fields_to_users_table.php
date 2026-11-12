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
        Schema::table('users', function (Blueprint $table) {
            // Parent user ID for agent ownership (agents belong to admin or customer)
            $table->foreignId('parent_user_id')->nullable()->after('id')->constrained('users')->onDelete('cascade');
            
            // Status field
            $table->enum('status', ['active', 'inactive', 'suspended'])->default('active')->after('email_verified_at');
            
            // Additional tracking
            $table->timestamp('last_login_at')->nullable()->after('remember_token');
            
            $table->index('parent_user_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['parent_user_id']);
            $table->dropColumn(['parent_user_id', 'status', 'last_login_at']);
        });
    }
};
