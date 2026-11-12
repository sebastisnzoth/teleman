<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ContactTag extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'color',
    ];

    // ==================== RELATIONSHIPS ====================

    /**
     * Tag belongs to a user
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Tag can be assigned to many contacts
     */
    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(Contact::class, 'contact_tag_assignments')
            ->withTimestamps();
    }

    // ==================== HELPER METHODS ====================

    /**
     * Get contact count for this tag
     */
    public function getContactCountAttribute(): int
    {
        return $this->contacts()->count();
    }

    /**
     * Add tag to contact
     */
    public function addToContact(int $contactId): void
    {
        if (!$this->contacts()->where('contact_id', $contactId)->exists()) {
            $this->contacts()->attach($contactId);
        }
    }

    /**
     * Remove tag from contact
     */
    public function removeFromContact(int $contactId): void
    {
        $this->contacts()->detach($contactId);
    }

    /**
     * Add tag to multiple contacts
     */
    public function addToContacts(array $contactIds): void
    {
        $this->contacts()->syncWithoutDetaching($contactIds);
    }

    /**
     * Remove tag from multiple contacts
     */
    public function removeFromContacts(array $contactIds): void
    {
        $this->contacts()->detach($contactIds);
    }
}
