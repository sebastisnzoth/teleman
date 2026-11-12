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
        Schema::create('connection_policies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('byoc_trunk_id')->nullable()->constrained()->cascadeOnDelete();
            
            // Twilio Connection Policy identifiers
            $table->string('policy_sid')->unique();
            $table->string('friendly_name');
            
            // Configuration
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            
            // Metadata
            $table->json('metadata')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['user_id', 'is_active']);
            $table->index('policy_sid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('connection_policies');
    }
};
