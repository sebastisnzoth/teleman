<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContactResource extends JsonResource
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
            'phone_number' => $this->phone_number,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => trim($this->first_name . ' ' . $this->last_name),
            'email' => $this->email,
            'company' => $this->company,
            'job_title' => $this->job_title,
            'timezone' => $this->timezone,
            'language' => $this->language,
            'status' => $this->status,
            'opted_out' => $this->opted_out,
            
            // Custom fields
            'custom_fields' => $this->custom_fields ?? [],
            
            // Engagement metrics
            'engagement' => [
                'total_campaigns' => $this->total_campaigns,
                'total_calls' => $this->total_calls,
                'successful_calls' => $this->successful_calls,
                'last_contacted_at' => $this->last_contacted_at?->toIso8601String(),
                'engagement_score' => $this->engagement_score,
            ],
            
            // Timestamps
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
            
            // Relationships
            'tags' => $this->whenLoaded('tags', function () {
                return $this->tags->pluck('name');
            }),
            'lists' => $this->whenLoaded('contactLists', function () {
                return $this->contactLists->map(function ($list) {
                    return [
                        'id' => $list->id,
                        'name' => $list->name,
                    ];
                });
            }),
        ];
    }
}
