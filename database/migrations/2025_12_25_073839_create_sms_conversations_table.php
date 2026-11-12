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
        Schema::create('sms_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sms_phone_number_id')->constrained()->onDelete('cascade');
            $table->string('contact_phone'); // External person's phone
            $table->string('contact_name')->nullable();
            $table->foreignId('contact_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('ai_agent_id')->nullable()->constrained()->onDelete('set null');
            $table->enum('status', ['active', 'archived', 'blocked'])->default('active');
            $table->timestamp('last_message_at')->nullable();
            $table->integer('unread_count')->default(0);
            $table->json('metadata')->nullable(); // Tags, notes, etc.
            $table->timestamps();
            
            $table->index(['sms_phone_number_id', 'status']);
            $table->index(['contact_phone', 'sms_phone_number_id']);
            $table->index('ai_agent_id');
            $table->index('last_message_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sms_conversations');
    }
};
