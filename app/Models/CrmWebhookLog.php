<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrmWebhookLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'crm_integration_id',
        'event_type',
        'payload',
        'response_status',
        'response_body',
        'triggered_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'response_status' => 'integer',
        'triggered_at' => 'datetime',
    ];

    public $timestamps = false;

    /**
     * Get the user that owns this log
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the CRM integration this log belongs to
     */
    public function crmIntegration(): BelongsTo
    {
        return $this->belongsTo(CrmIntegration::class);
    }

    /**
     * Check if the webhook was successful
     */
    public function wasSuccessful(): bool
    {
        return $this->response_status >= 200 && $this->response_status < 300;
    }

    /**
     * Get only successful logs
     */
    public function scopeSuccessful($query)
    {
        return $query->whereBetween('response_status', [200, 299]);
    }

    /**
     * Get only failed logs
     */
    public function scopeFailed($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('response_status')
              ->orWhere('response_status', '<', 200)
              ->orWhere('response_status', '>=', 300);
        });
    }

    /**
     * Get logs for a specific event type
     */
    public function scopeForEvent($query, string $event)
    {
        return $query->where('event_type', $event);
    }
}
