<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One audit row per destructive telephony provider reset.
 *
 * Written only by ResetTelephonyProviderAction, inside the same
 * transaction as the reset itself, so the record and the wipe succeed or
 * fail together. Nothing updates or deletes these rows.
 *
 * This row is the *only* evidence a reset happened: the reset's whole job
 * is to remove the traces of the previous provider, so without it an admin
 * looking at an empty telephony configuration cannot tell a reset from a
 * fresh install. That is why it snapshots the actor's name and email
 * instead of only referencing the user, and why it lists the phone numbers
 * it retired rather than just counting them.
 */
final class TelephonyProviderReset extends Model
{
    /** Triggered from the admin danger zone by a signed-in admin. */
    public const SOURCE_WEB = 'web';

    /** Triggered by `php artisan telephony:reset` — support access, no session. */
    public const SOURCE_CONSOLE = 'console';

    protected $fillable = [
        'from_provider',
        'source',
        'by_user_id',
        'by_user_name',
        'by_user_email',
        'counts',
        'neutralised_phone_numbers',
    ];

    protected $casts = [
        'counts' => 'array',
        'neutralised_phone_numbers' => 'array',
    ];

    /**
     * The admin who ran the reset — null for a console reset, and also null
     * if that account was deleted afterwards. Use by_user_name /
     * by_user_email and source to tell those two cases apart.
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'by_user_id');
    }

    /**
     * How many rows the reset deleted, per table.
     *
     * @return array<string, int>
     */
    public function deletedCounts(): array
    {
        return $this->counts['deleted'] ?? [];
    }

    /**
     * How many rows the reset stripped of provider identity but kept,
     * per table. These rows were NOT deleted — customer history points
     * at them.
     *
     * @return array<string, int>
     */
    public function neutralisedCounts(): array
    {
        return $this->counts['neutralised'] ?? [];
    }

    /**
     * The phone numbers this reset retired locally, as
     * `[['id' => int, 'number' => string], ...]`.
     *
     * These are still live at the provider and still billing until someone
     * cancels them there — the reset never touches the provider account.
     *
     * @return list<array{id: int, number: string}>
     */
    public function retiredPhoneNumbers(): array
    {
        return $this->neutralised_phone_numbers ?? [];
    }

    public function fromConsole(): bool
    {
        return $this->source === self::SOURCE_CONSOLE;
    }
}
