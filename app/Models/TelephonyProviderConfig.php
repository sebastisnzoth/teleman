<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TelephonyProvider;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

final class TelephonyProviderConfig extends Model
{
    use HasFactory;

    protected $table = 'telephony_providers';

    /**
     * `locked_at` and `locked_by_user_id` are deliberately absent.
     *
     * Those two columns *are* the provider lock, and the lock is the one
     * thing in this product that must never move by accident — switching
     * provider is a destructive admin reset, never a toggle. Leaving them
     * mass-assignable meant a future admin controller writing the
     * perfectly ordinary `$config->update($request->validated())` could
     * lock, unlock or re-attribute the installation from request input.
     * TelephonyLock is the only supported way to change them, and it
     * assigns them as properties, so it is unaffected by their absence
     * here. Tests wanting a pre-locked row use the factory's locked()
     * state, which does the same.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'credentials',
        'app_id',
        'webhook_url',
        'settings',
        'verified_at',
    ];

    protected $hidden = [
        'credentials',
    ];

    protected $casts = [
        'credentials' => 'encrypted:array',
        'settings' => 'array',
        'verified_at' => 'datetime',
        'locked_at' => 'datetime',
    ];

    /**
     * Guard the single-lock invariant, in two parts.
     *
     * 1. `locked_at` must be non-null on at most one row. A plain
     *    nullable timestamp column cannot enforce that by itself, so this
     *    is a backstop — not the authority — against a save that would
     *    leave a row locked while another already is. It never blocks
     *    unlocking (`locked_at` -> null).
     * 2. A locked row's `name` may not change. Part 1 keys off
     *    `isDirty('locked_at')`, which a rename does not touch: renaming
     *    the single locked row from 'twilio' to 'plivo' would move the
     *    whole installation's lock to the other provider without ever
     *    making `locked_at` dirty, so part 1 would wave it through. That
     *    is the same destructive switch the reset flow exists to make
     *    deliberate, so it is refused here.
     *
     * Scope: fires only on instance saves — `save()`, `create()`, instance
     * `->update()`. It does NOT fire on Eloquent Builder mass updates
     * (`::where(...)->update([...])`, `::query()->update([...])`) or raw
     * `DB::table(...)` writes — both bypass model events entirely and would
     * silently skip this guard. It is also not atomic across concurrent
     * requests. The authoritative, race-safe enforcement of the lock/unlock
     * transition — and a proper AlreadyLockedException for a clean
     * admin-facing error — belongs to the TelephonyLock service, which
     * wraps its writes in a DB transaction + lockForUpdate(), the same way
     * CreditService guards concurrent credit deduction.
     */
    protected static function booted(): void
    {
        self::saving(function (self $config): void {
            if ($config->exists && $config->isDirty('name') && $config->locked_at !== null) {
                throw new \RuntimeException(
                    'Cannot rename a locked telephony provider: switching provider is a telephony reset, not an update.'
                );
            }

            if (! $config->isDirty('locked_at') || $config->locked_at === null) {
                return;
            }

            $lockedElsewhere = static::query()
                ->whereNotNull('locked_at')
                ->when($config->exists, fn ($query) => $query->whereKeyNot($config->getKey()))
                ->exists();

            if ($lockedElsewhere) {
                throw new \RuntimeException(
                    'Cannot lock telephony provider: another provider is already locked.'
                );
            }
        });
    }

    public static function locked(): ?self
    {
        return self::whereNotNull('locked_at')->first();
    }

    public static function forProvider(TelephonyProvider $provider): self
    {
        return self::firstOrCreate(['name' => $provider->value]);
    }

    public function provider(): TelephonyProvider
    {
        return TelephonyProvider::from($this->name);
    }

    public function isLocked(): bool
    {
        return $this->locked_at !== null;
    }

    public function credential(string $key): ?string
    {
        return $this->credentials[$key] ?? null;
    }

    public function markVerified(): self
    {
        $this->update(['verified_at' => now()]);

        return $this;
    }
}
