<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ConnectionPolicy extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'byoc_trunk_id',
        'policy_sid',
        'friendly_name',
        'description',
        'is_active',
        'metadata',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];

    protected $appends = [
        'total_targets',
        'active_targets',
    ];

    /**
     * User who owns this policy
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * BYOC trunk this policy belongs to
     */
    public function byocTrunk(): BelongsTo
    {
        return $this->belongsTo(ByocTrunk::class);
    }

    /**
     * Routing targets (external SIP providers)
     */
    public function targets(): HasMany
    {
        return $this->hasMany(ConnectionPolicyTarget::class);
    }

    /**
     * Get active targets only
     */
    public function activeTargets(): HasMany
    {
        return $this->targets()->where('enabled', true);
    }

    /**
     * Get total number of targets
     */
    public function getTotalTargetsAttribute(): int
    {
        return $this->targets()->count();
    }

    /**
     * Get number of active targets
     */
    public function getActiveTargetsAttribute(): int
    {
        return $this->activeTargets()->count();
    }

    /**
     * Get targets ordered by priority and weight
     */
    public function getOrderedTargets()
    {
        return $this->activeTargets()
            ->orderBy('priority', 'asc')
            ->orderBy('weight', 'desc')
            ->get();
    }

    /**
     * Get primary target (lowest priority, highest weight)
     */
    public function getPrimaryTarget()
    {
        return $this->getOrderedTargets()->first();
    }

    /**
     * Get total statistics from all targets
     */
    public function getTotalStatistics(): array
    {
        $targets = $this->targets;

        return [
            'total_calls' => $targets->sum('total_calls'),
            'successful_calls' => $targets->sum('successful_calls'),
            'failed_calls' => $targets->sum('failed_calls'),
            'success_rate' => $this->calculateSuccessRate($targets),
        ];
    }

    /**
     * Calculate success rate
     */
    private function calculateSuccessRate($targets): float
    {
        $totalCalls = $targets->sum('total_calls');
        
        if ($totalCalls === 0) {
            return 0.0;
        }

        $successfulCalls = $targets->sum('successful_calls');
        return round(($successfulCalls / $totalCalls) * 100, 2);
    }
}
