<?php

namespace App\Events;

use App\Models\SmsMessage;
use App\Models\SmsConversation;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SmsMessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $message;
    public $conversation;

    /**
     * Create a new event instance.
     */
    public function __construct(SmsMessage $message, SmsConversation $conversation)
    {
        $this->message = $message;
        $this->conversation = $conversation;
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): Channel
    {
        return new Channel('sms.phone.' . $this->conversation->phone_number_id);
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'message' => [
                'id' => $this->message->id,
                'conversation_id' => $this->message->conversation_id,
                'direction' => $this->message->direction,
                'message_body' => $this->message->message_body,
                'sender_phone' => $this->message->sender_phone,
                'receiver_phone' => $this->message->receiver_phone,
                'status' => $this->message->status,
                'ai_generated' => $this->message->ai_generated,
                'created_at' => $this->message->created_at->toISOString(),
            ],
            'conversation' => [
                'id' => $this->conversation->id,
                'contact_phone' => $this->conversation->contact_phone,
                'contact_name' => $this->conversation->contact_name,
                'last_message_at' => $this->conversation->last_message_at?->toISOString(),
                'unread_count' => $this->conversation->unread_count,
            ],
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'message.sent';
    }
}
