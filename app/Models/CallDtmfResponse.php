<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CallDtmfResponse extends Model
{
    use HasFactory;

    protected $fillable = [
        'call_id',
        'campaign_id',
        'campaign_contact_id',
        'digits_pressed',
        'action_taken',
        'action_result',
        'action_details',
        'pressed_at',
        'metadata',
    ];

    protected $casts = [
        'pressed_at' => 'datetime',
        'metadata' => 'array',
    ];

    // Relationships
    public function call(): BelongsTo
    {
        return $this->belongsTo(Call::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function campaignContact(): BelongsTo
    {
        return $this->belongsTo(CampaignContact::class);
    }

    // Scopes
    public function scopeForCampaign($query, int $campaignId)
    {
        return $query->where('campaign_id', $campaignId);
    }

    public function scopeByDigit($query, string $digit)
    {
        return $query->where('digits_pressed', $digit);
    }

    public function scopeByAction($query, string $action)
    {
        return $query->where('action_taken', $action);
    }
}
