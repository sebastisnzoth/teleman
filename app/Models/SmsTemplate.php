<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class SmsTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'content',
        'variables',
        'category',
        'is_active',
        'usage_count',
    ];

    protected $casts = [
        'variables' => 'array',
        'is_active' => 'boolean',
    ];

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Scopes
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeByCategory(Builder $query, string $category): Builder
    {
        return $query->where('category', $category);
    }

    public function scopePopular(Builder $query): Builder
    {
        return $query->orderBy('usage_count', 'desc');
    }

    // Methods
    public function incrementUsage(): void
    {
        $this->increment('usage_count');
    }

    public function render(array $data = []): string
    {
        $content = $this->content;
        
        foreach ($data as $key => $value) {
            $content = str_replace('{{' . $key . '}}', $value, $content);
        }
        
        return $content;
    }

    public function getRequiredVariables(): array
    {
        return $this->variables ?? [];
    }

    // Accessors
    public function getCharacterCountAttribute(): int
    {
        return strlen($this->content);
    }

    public function getSegmentCountAttribute(): int
    {
        // SMS segment is 160 chars (GSM-7) or 70 chars (UCS-2)
        $length = $this->character_count;
        return $length > 0 ? (int) ceil($length / 160) : 1;
    }
}