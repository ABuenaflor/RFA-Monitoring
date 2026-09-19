<?php

namespace App\Support;

/**
 * The complete, closed list of settings an administrator may change.
 *
 * Business rules are deliberately absent: the 3-day and 30-day PCT limits
 * are policy, not configuration, and are not editable from the interface.
 */
final class SystemSettings
{
    public const ORGANIZATION_NAME = 'organization_name';

    public const ORGANIZATION_UNIT = 'organization_unit';

    public const REPORT_FOOTER_NOTE = 'reports_footer_note';

    public const LISTING_PER_PAGE = 'listing_per_page';

    public const AUDIT_RETENTION_DAYS = 'audit_retention_days';

    public const BACKUP_RETENTION_COUNT = 'backup_retention_count';

    /**
     * Definition of every setting: type, default, label, help text, and the
     * validation rules applied when it is saved.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function definitions(): array
    {
        return [
            self::ORGANIZATION_NAME => [
                'label' => 'Organization Name',

                'help' => 'Printed as the heading of the 8×13 report.',

                'type' => 'string',

                'default' => 'DOLE 5 RFA MONITORING',

                'rules' => ['required', 'string', 'max:120'],

                'group' => 'Identity',
            ],

            self::ORGANIZATION_UNIT => [
                'label' => 'Unit / Sub-heading',

                'help' => 'Printed beneath the report heading.',

                'type' => 'string',

                'default' => 'Request for Assistance Monitoring Report',

                'rules' => ['required', 'string', 'max:150'],

                'group' => 'Identity',
            ],

            self::REPORT_FOOTER_NOTE => [
                'label' => 'Report Footer Note',

                'help' => 'Optional line printed at the foot of the report, '
                    . 'such as a confidentiality notice.',

                'type' => 'string',

                'default' => '',

                'rules' => ['nullable', 'string', 'max:255'],

                'group' => 'Identity',
            ],

            self::LISTING_PER_PAGE => [
                'label' => 'Default Listing Page Size',

                'help' => 'Rows shown per page on the RFA master listing '
                    . 'before a user chooses otherwise.',

                'type' => 'integer',

                'default' => 10,

                'options' => [10, 20, 50],

                'rules' => ['required', 'integer', 'in:10,20,50'],

                'group' => 'Operations',
            ],

            self::AUDIT_RETENTION_DAYS => [
                'label' => 'Audit Retention (days)',

                'help' => 'Entries older than this are removed by '
                    . 'rfa:prune-audit. Use 0 to keep the trail forever.',

                'type' => 'integer',

                'default' => 0,

                'rules' => ['required', 'integer', 'min:0', 'max:3650'],

                'group' => 'Retention',
            ],

            self::BACKUP_RETENTION_COUNT => [
                'label' => 'Backups Kept',

                'help' => 'Oldest backup files beyond this count are removed '
                    . 'automatically after a new backup completes. Use 0 to '
                    . 'keep every backup.',

                'type' => 'integer',

                'default' => 10,

                'rules' => ['required', 'integer', 'min:0', 'max:200'],

                'group' => 'Retention',
            ],
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function keys(): array
    {
        return array_keys(self::definitions());
    }

    /**
     * @return array<string, mixed>
     */
    public static function definition(string $key): array
    {
        return self::definitions()[$key]
            ?? throw new \InvalidArgumentException(
                "Unknown system setting [{$key}]."
            );
    }

    public static function defaultFor(string $key): mixed
    {
        return self::definition($key)['default'];
    }

    /**
     * Settings arranged by the panel they appear under.
     *
     * @return array<string, array<string, array<string, mixed>>>
     */
    public static function grouped(): array
    {
        $groups = [];

        foreach (self::definitions() as $key => $definition) {
            $groups[$definition['group']][$key] = $definition;
        }

        return $groups;
    }
}
