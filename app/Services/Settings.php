<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * Database-backed platform settings with typed defaults. Changes are audited.
 */
class Settings
{
    /**
     * Known settings and their defaults.
     *
     * @var array<string, mixed>
     */
    public const DEFAULTS = [
        'payouts.hold_days' => 7,
        'payouts.minimum_amount' => 5000,
        'payouts.currency' => 'RWF',
        'checkout.cod_enabled' => false,
        'checkout.default_country' => 'RW',
        'checkout.payment_timeout_minutes' => 30,
        'moderation.require_approval' => true,
        'marketplace.finished_commission_bps' => 1000,
        'marketplace.contact_email' => 'support@inganzo.rw',
        'marketplace.contact_phone' => '+250 788 000 000',
    ];

    public function __construct(private AuditLogger $audit) {}

    public function get(string $key, mixed $default = null): mixed
    {
        $all = $this->all();

        return array_key_exists($key, $all) ? $all[$key] : ($default ?? self::DEFAULTS[$key] ?? null);
    }

    public function integer(string $key): int
    {
        return (int) $this->get($key);
    }

    public function boolean(string $key): bool
    {
        return filter_var($this->get($key), FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        $stored = Cache::rememberForever(
            'platform-settings',
            fn () => Setting::query()->pluck('value', 'key')->all(),
        );

        return array_merge(self::DEFAULTS, $stored);
    }

    public function set(string $key, mixed $value, ?User $actor = null): void
    {
        $old = $this->get($key);

        Setting::query()->updateOrCreate(['key' => $key], ['value' => $value, 'updated_by' => $actor?->id]);
        Cache::forget('platform-settings');

        if ($old !== $value) {
            $this->audit->log('settings.updated', null, ['key' => $key, 'old' => $old, 'new' => $value], $actor);
        }
    }
}
