<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class AudioFile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'filename',
        'original_name',
        'file_path',
        'file_size',
        'duration_seconds',
        'mime_type',
        'format',
        'used_in_campaigns_count',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'duration_seconds' => 'integer',
        'used_in_campaigns_count' => 'integer',
    ];

    protected $appends = ['url', 'duration'];

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Accessors
    public function getUrlAttribute(): string
    {
        return Storage::url($this->file_path);
    }

    public function getFileSizeFormattedAttribute(): string
    {
        $bytes = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $bytes > 1024; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }

    public function getDurationFormattedAttribute(): string
    {
        if ($this->duration_seconds === null) {
            return '0:00';
        }

        $minutes = floor($this->duration_seconds / 60);
        $seconds = $this->duration_seconds % 60;

        return sprintf('%d:%02d', $minutes, $seconds);
    }

    /**
     * Accessor for backward compatibility
     * Returns duration_seconds as duration
     */
    public function getDurationAttribute(): ?int
    {
        return $this->duration_seconds;
    }

    // Helper Methods
    public function incrementUsage(): void
    {
        $this->increment('used_in_campaigns_count');
    }

    public function decrementUsage(): void
    {
        $this->decrement('used_in_campaigns_count');
    }

    public function delete(): ?bool
    {
        // Delete physical file
        if (Storage::exists($this->file_path)) {
            Storage::delete($this->file_path);
        }

        return parent::delete();
    }
}
