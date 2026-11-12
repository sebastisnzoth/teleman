<?php

namespace App\Http\Resources;

use App\Models\Campaign;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The REST API's call representation. The embedded campaign publishes `type`
 * in the tts|audio vocabulary the API documents, the same as CampaignResource
 * — the two endpoints must not disagree about the same row.
 */
class CallResource extends JsonResource
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
            'call_type' => $this->call_type,
            'direction' => $this->direction,
            'from_number' => $this->from_number,
            'to_number' => $this->to_number,
            'status' => $this->status,
            'duration_seconds' => $this->duration_seconds,
            'duration' => $this->duration, // Human readable duration
            'answered_by' => $this->answered_by,
            
            // Recording information
            'recording' => [
                'available' => !is_null($this->recording_url),
                'url' => $this->when(!is_null($this->recording_url), $this->recording_url),
                'duration' => $this->recording_duration,
                'sid' => $this->recording_sid,
            ],
            
            // DTMF Response
            'dtmf_digits' => $this->dtmf_digits,
            
            // AI Sentiment Analysis
            'sentiment' => $this->when(!is_null($this->sentiment), [
                'sentiment' => $this->sentiment,
                'confidence' => $this->sentiment_confidence,
                'lead_score' => $this->lead_score,
                'lead_quality' => $this->lead_quality,
                'summary' => $this->ai_summary,
                'key_intents' => $this->key_intents,
                'analyzed_at' => $this->sentiment_analyzed_at?->toIso8601String(),
            ]),
            
            // Pricing
            'price' => $this->price,
            'price_unit' => $this->price_unit,
            
            // Error information
            'error_message' => $this->error_message,
            
            // Timestamps
            'started_at' => $this->started_at?->toIso8601String(),
            'ended_at' => $this->ended_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
            
            // Relationships
            'campaign' => $this->whenLoaded('campaign', function () {
                return [
                    'id' => $this->campaign->id,
                    'name' => $this->campaign->name,
                    'type' => Campaign::typeToApi($this->campaign->type),
                ];
            }),
            'contact' => $this->whenLoaded('campaignContact', function () {
                return [
                    'id' => $this->campaignContact->id,
                    'phone_number' => $this->campaignContact->phone_number,
                    'first_name' => $this->campaignContact->first_name,
                    'last_name' => $this->campaignContact->last_name,
                ];
            }),
        ];
    }
}
