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
        Schema::create('sms_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('sms_conversations')->onDelete('cascade');
            $table->enum('direction', ['inbound', 'outbound']);
            $table->text('message_body');
            $table->string('sender_phone');
            $table->string('receiver_phone');
            $table->enum('status', ['queued', 'sent', 'delivered', 'failed', 'received'])->default('queued');
            $table->boolean('ai_generated')->default(false);
            $table->string('provider_message_id')->nullable(); // Twilio Message SID
            $table->string('error_message')->nullable();
            $table->integer('num_segments')->default(1);
            $table->decimal('cost', 10, 4)->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->json('metadata')->nullable(); // Media URLs, etc.
            $table->timestamps();
            
            $table->index(['conversation_id', 'created_at']);
            $table->index(['direction', 'status']);
            $table->index('provider_message_id');
            $table->index('ai_generated');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sms_messages');
    }
};
