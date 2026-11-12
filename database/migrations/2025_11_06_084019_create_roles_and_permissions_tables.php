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
        // Create roles table
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Admin, Customer, Agent
            $table->string('slug')->unique(); // admin, customer, agent
            $table->text('description')->nullable();
            $table->tinyInteger('level')->default(3); // 1=Admin, 2=Customer, 3=Agent
            $table->boolean('is_system')->default(false); // System roles cannot be deleted
            $table->timestamps();
            
            $table->index('slug');
            $table->index('level');
        });

        // Create permissions table
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Create Campaign
            $table->string('slug')->unique(); // campaigns.create
            $table->text('description')->nullable();
            $table->string('module')->nullable(); // campaigns, contacts, calls, etc.
            $table->timestamps();
            
            $table->index('slug');
            $table->index('module');
        });

        // Create role_user pivot table (many-to-many with tracking)
        Schema::create('role_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained('roles')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            
            $table->unique(['role_id', 'user_id']);
            $table->index('created_by_user_id');
        });

        // Create permission_role pivot table (many-to-many)
        Schema::create('permission_role', function (Blueprint $table) {
            $table->id();
            $table->foreignId('permission_id')->constrained('permissions')->onDelete('cascade');
            $table->foreignId('role_id')->constrained('roles')->onDelete('cascade');
            $table->timestamps();
            
            $table->unique(['permission_id', 'role_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('permission_role');
        Schema::dropIfExists('role_user');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
    }
};
