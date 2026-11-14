<?php

namespace App\Support;

use Illuminate\Support\Collection;

/**
 * The designated SEADO list in config/seados.php.
 */
final class Seados
{
    /**
     * Designated SEADOs, optionally only those of one office (rfas.office code).
     *
     * @return Collection<int, array{name: string, office: string, position: ?string, id_number: ?string}>
     */
    public static function designated(?string $office = null): Collection
    {
        return collect(config('seados.designated', []))
            ->filter(fn (array $seado) => trim((string) ($seado['name'] ?? '')) !== '')
            ->when($office !== null, fn (Collection $list) => $list->where('office', $office))
            ->values();
    }

    /**
     * Recorded SEADO names merged with the designated ones, one per name
     * (ignoring case and spacing), sorted — for SEADO dropdowns.
     *
     * @param  iterable<int, string>  $recorded
     * @return Collection<int, string>
     */
    public static function withDesignated(iterable $recorded): Collection
    {
        return collect($recorded)
            ->merge(self::designated()->pluck('name'))
            ->map(fn ($name) => trim((string) $name))
            ->filter()
            ->unique(fn (string $name) => self::key($name))
            ->sort(fn (string $a, string $b) => strcasecmp($a, $b))
            ->values();
    }

    public static function key(string $name): string
    {
        return mb_strtoupper(preg_replace('/\s+/', ' ', trim($name)));
    }
}
