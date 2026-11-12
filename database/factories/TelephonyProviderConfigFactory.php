<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\TelephonyProviderConfig;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TelephonyProviderConfig>
 */
class TelephonyProviderConfigFactory extends Factory
{
    protected $model = TelephonyProviderConfig::class;

    public function definition(): array
    {
        return [
            'name' => 'twilio',
        ];
    }

    /**
     * A row that is already holding the installation's provider lock.
     *
     * `locked_at`/`locked_by_user_id` are not mass-assignable (see
     * TelephonyProviderConfig::$fillable — the lock must not be movable
     * from request input), so `create(['locked_at' => now()])` silently
     * does nothing. This state assigns them the same way TelephonyLock
     * does, as properties on the instance, after the factory has built it
     * and before it is stored — so the model's own single-lock guard
     * still runs on the save, exactly as it would in production.
     */
    public function locked(?int $byUserId = null): static
    {
        return $this->afterMaking(function (TelephonyProviderConfig $config) use ($byUserId): void {
            $config->locked_at = now();
            $config->locked_by_user_id = $byUserId;
        });
    }
}
