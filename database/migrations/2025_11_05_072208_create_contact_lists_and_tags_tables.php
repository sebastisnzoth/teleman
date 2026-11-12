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
        // Contact Lists (Audiences)
        Schema::create('contact_lists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();
            $table->integer('contact_count')->default(0);
            $table->timestamps();
            
            $table->index('user_id');
            $table->index(['user_id', 'name']);
        });

        // Contact List Members (Pivot)
        Schema::create('contact_list_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_list_id')->constrained()->onDelete('cascade');
            $table->foreignId('contact_id')->constrained()->onDelete('cascade');
            $table->timestamps();
            
            // Ensure no duplicate contacts in same list
            $table->unique(['contact_list_id', 'contact_id']);
            $table->index('contact_list_id');
            $table->index('contact_id');
        });

        // Contact Tags
        Schema::create('contact_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('color')->default('#3b82f6'); // Hex color for UI
            $table->timestamps();
            
            $table->index('user_id');
            $table->unique(['user_id', 'name']); // Unique tag name per user
        });

        // Contact Tag Assignments (Pivot)
        Schema::create('contact_tag_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained()->onDelete('cascade');
            $table->foreignId('contact_tag_id')->constrained()->onDelete('cascade');
            $table->timestamps();
            
            // Ensure no duplicate tags on same contact
            $table->unique(['contact_id', 'contact_tag_id']);
            $table->index('contact_id');
            $table->index('contact_tag_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contact_tag_assignments');
        Schema::dropIfExists('contact_tags');
        Schema::dropIfExists('contact_list_members');
        Schema::dropIfExists('contact_lists');
    }
};
