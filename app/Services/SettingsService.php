<?php

namespace App\Services;

use App\Models\SystemSetting;
use App\Models\User;
use App\Support\SystemSettings;
use Illuminate\Support\Facades\Cache;

/**
 * Reads and writes the controlled configuration surface.
 *
 * Values are cached because settings are read on nearly every page and
 * change very rarely; the cache is cleared on every write.
 */
class SettingsService
{
    private const CACHE_KEY = 'rfa.system_settings';

    /**
     * Current value for one setting, falling back to its declared default.
     */
    public function get(string $key): mixed
    {
        $definition = SystemSettings::definition($key);

        $stored = $this->all()[$key] ?? null;

        if ($stored === null) {
            return $definition['default'];
        }

        return $this->cast($stored, $definition['type']);
    }

    /**
     * Every setting, stored values merged over the declared defaults.
     *
     * @return array<string, mixed>
     */
    public function values(): array
    {
        $values = [];

        foreach (SystemSettings::definitions() as $key => $definition) {
            $values[$key] = $this->get($key);
        }

        return $values;
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<int, string>  keys that actually changed
     */
    public function put(array $values, ?User $actor = null): array
    {
        $changed = [];

        foreach ($values as $key => $value) {
            if (! in_array($key, SystemSettings::keys(), true)) {
                continue;
            }

            $current = $this->get($key);

            SystemSetting::updateOrCreate(
                ['key' => $key],
                [
                    'value' => $value === null
                        ? null
                        : (string) $value,

                    'updated_by' => $actor?->id,
                ]
            );

            $this->flush();

            if ((string) $current !== (string) $value) {
                $changed[] = $key;
            }
        }

        $this->flush();

        return $changed;
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array<string, string|null>
     */
    private function all(): array
    {
        return Cache::rememberForever(
            self::CACHE_KEY,
            fn () => SystemSetting::query()
                ->pluck('value', 'key')
                ->all()
        );
    }

    private function cast(string $value, string $type): mixed
    {
        return match ($type) {
            'integer' => (int) $value,

            'boolean' => filter_var(
                $value,
                FILTER_VALIDATE_BOOLEAN
            ),

            default => $value,
        };
    }
}
