<?php

namespace App\Http\Resources;

use App\Models\Campaign;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The REST API's campaign representation. Nothing else renders through it —
 * the web UI reads campaigns as Inertia props and uses the stored
 * text_to_speech|voice_to_voice vocabulary directly — so `type` is published
 * here in the tts|audio vocabulary the API documents and accepts.
 */
class CampaignResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => Campaign::typeToApi($this->type),
            'status' => $this->status,
            'message' => $this->message,
            'assigned_agent_id' => $this->assigned_agent_id,
            'voice' => $this->voice,
            'voice_language' => $this->voice_language,
            'caller_id' => $this->caller_id,
            'from_number' => $this->from_number,
            
            // Settings
            'settings' => [
                'enable_recording' => $this->enable_recording,
                'enable_dtmf' => $this->enable_dtmf,
                'enable_amd' => $this->enable_amd,
                'max_concurrent_calls' => $this->max_concurrent_calls,
                'retry_attempts' => $this->retry_attempts,
                'retry_delay_minutes' => $this->retry_delay_minutes,
            ],
            
            // Statistics
            'stats' => [
                'total_contacts' => $this->total_contacts,
                'total_calls' => $this->total_calls,
                'completed_calls' => $this->completed_calls,
                'failed_calls' => $this->failed_calls,
                'success_rate' => $this->total_calls > 0 
                    ? round(($this->completed_calls / $this->total_calls) * 100, 2) 
                    : 0,
            ],
            
            // Timestamps
            'scheduled_at' => $this->scheduled_at?->toIso8601String(),
            'started_at' => $this->started_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
            
            // Relationships (conditionally loaded)
            'user' => new UserResource($this->whenLoaded('user')),
            'phone_number' => $this->whenLoaded('phoneNumber', function () {
                return [
                    'id' => $this->phoneNumber->id,
                    'number' => $this->phoneNumber->number,
                    'friendly_name' => $this->phoneNumber->friendly_name,
                ];
            }),
            'ai_agent' => $this->whenLoaded('aiAgent', function () {
                return [
                    'id' => $this->aiAgent->id,
                    'name' => $this->aiAgent->name,
                    'type' => $this->aiAgent->type,
                    'active' => $this->aiAgent->active,
                ];
            }),
            'assigned_agent' => $this->whenLoaded('assignedAgent', function () {
                return [
                    'id' => $this->assignedAgent->id,
                    'name' => $this->assignedAgent->name,
                    'email' => $this->assignedAgent->email,
                ];
            }),
        ];
    }
}
