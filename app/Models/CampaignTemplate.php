<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaignTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'description',
        'category',
        'template_data',
        'is_system_template',
        'usage_count',
    ];

    protected $casts = [
        'template_data' => 'array',
        'is_system_template' => 'boolean',
        'usage_count' => 'integer',
    ];

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Scopes
    public function scopeSystemTemplates($query)
    {
        return $query->where('is_system_template', true);
    }

    public function scopeUserTemplates($query, int $userId)
    {
        return $query->where('user_id', $userId)->where('is_system_template', false);
    }

    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    // Methods
    public function incrementUsage(): void
    {
        $this->increment('usage_count');
    }
}
