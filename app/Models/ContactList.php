<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ContactList extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'description',
        'contact_count',
    ];

    protected $casts = [
        'contact_count' => 'integer',
    ];

    // ==================== RELATIONSHIPS ====================

    /**
     * List belongs to a user
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * List has many contacts
     */
    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(Contact::class, 'contact_list_members')
            ->withTimestamps();
    }

    // ==================== HELPER METHODS ====================

    /**
     * Add contact to this list
     */
    public function addContact(int $contactId): void
    {
        if (!$this->contacts()->where('contact_id', $contactId)->exists()) {
            $this->contacts()->attach($contactId);
            $this->updateContactCount();
        }
    }

    /**
     * Remove contact from this list
     */
    public function removeContact(int $contactId): void
    {
        $this->contacts()->detach($contactId);
        $this->updateContactCount();
    }

    /**
     * Add multiple contacts to this list
     */
    public function addContacts(array $contactIds): void
    {
        $this->contacts()->syncWithoutDetaching($contactIds);
        $this->updateContactCount();
    }

    /**
     * Remove multiple contacts from this list
     */
    public function removeContacts(array $contactIds): void
    {
        $this->contacts()->detach($contactIds);
        $this->updateContactCount();
    }

    /**
     * Update the contact count
     */
    public function updateContactCount(): void
    {
        $this->update(['contact_count' => $this->contacts()->count()]);
    }

    /**
     * Clear all contacts from list
     */
    public function clearContacts(): void
    {
        $this->contacts()->detach();
        $this->update(['contact_count' => 0]);
    }
}
