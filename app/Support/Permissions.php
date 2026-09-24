<?php

namespace App\Support;

/**
 * Central catalogue of every permission the system understands.
 *
 * Permissions are plain strings stored in role_permissions. Gates are
 * registered from this catalogue in AppServiceProvider, so adding a
 * permission here is all that is required to make it grantable.
 */
final class Permissions
{
    /*
    |--------------------------------------------------------------------------
    | Monitoring
    |--------------------------------------------------------------------------
    */

    public const DASHBOARD_VIEW = 'dashboard.view';

    public const RFA_VIEW = 'rfa.view';

    public const PCT_VIEW = 'pct.view';

    /*
    |--------------------------------------------------------------------------
    | Case Management
    |--------------------------------------------------------------------------
    */

    public const RFA_MANAGE = 'rfa.manage';

    public const RFA_ASSIGN = 'rfa.assign';

    public const RFA_DISPOSE = 'rfa.dispose';

    /*
    |--------------------------------------------------------------------------
    | Reporting
    |--------------------------------------------------------------------------
    */

    public const REPORTS_VIEW = 'reports.view';

    public const REPORTS_EXPORT = 'reports.export';

    /*
    |--------------------------------------------------------------------------
    | Data Intake
    |--------------------------------------------------------------------------
    */

    public const IMPORT_MANAGE = 'import.manage';

    /*
    |--------------------------------------------------------------------------
    | Access Control
    |--------------------------------------------------------------------------
    */

    public const USERS_VIEW = 'users.view';

    public const USERS_MANAGE = 'users.manage';

    public const ROLES_MANAGE = 'roles.manage';

    /*
    |--------------------------------------------------------------------------
    | Notifications & Audit
    |--------------------------------------------------------------------------
    */

    public const NOTIFICATIONS_VIEW = 'notifications.view';

    public const AUDIT_VIEW = 'audit.view';

    /*
    |--------------------------------------------------------------------------
    | Administration
    |--------------------------------------------------------------------------
    */

    public const GOVERNANCE_VIEW = 'governance.view';

    public const GOVERNANCE_MANAGE = 'governance.manage';

    public const BACKUP_VIEW = 'backup.view';

    public const BACKUP_MANAGE = 'backup.manage';

    public const SETTINGS_MANAGE = 'settings.manage';

    /*
    |--------------------------------------------------------------------------
    | Release Readiness
    |--------------------------------------------------------------------------
    */

    public const READINESS_VIEW = 'readiness.view';

    public const READINESS_MANAGE = 'readiness.manage';


    /**
     * Permissions grouped for the role editing matrix.
     *
     * @return array<string, array{label: string, accent: string, permissions: array<string, string>}>
     */
    public static function groups(): array
    {
        return [
            'monitoring' => [
                'label' => 'Monitoring & Visibility',

                'accent' => 'blue',

                'permissions' => [
                    self::DASHBOARD_VIEW =>
                        'View the monitoring dashboard',

                    self::RFA_VIEW =>
                        'View the RFA master listing and case details',

                    self::PCT_VIEW =>
                        'View PCT process monitoring',
                ],
            ],

            'case_management' => [
                'label' => 'Case Management',

                'accent' => 'indigo',

                'permissions' => [
                    self::RFA_MANAGE =>
                        'Edit case information and workflow dates',

                    self::RFA_ASSIGN =>
                        'Assign interviewers and SEADOs',

                    self::RFA_DISPOSE =>
                        'Record official case disposition',
                ],
            ],

            'reporting' => [
                'label' => 'Reporting',

                'accent' => 'emerald',

                'permissions' => [
                    self::REPORTS_VIEW =>
                        'View reports and analytics',

                    self::REPORTS_EXPORT =>
                        'Export CSV and print reports',
                ],
            ],

            'data_intake' => [
                'label' => 'Data Intake',

                'accent' => 'amber',

                'permissions' => [
                    self::IMPORT_MANAGE =>
                        'Upload and process CSV imports',
                ],
            ],

            'access_control' => [
                'label' => 'Users & Access',

                'accent' => 'violet',

                'permissions' => [
                    self::USERS_VIEW =>
                        'View the user directory',

                    self::USERS_MANAGE =>
                        'Create, edit, and deactivate users',

                    self::ROLES_MANAGE =>
                        'Create and edit roles and permissions',
                ],
            ],

            'oversight' => [
                'label' => 'Notifications & Audit',

                'accent' => 'blue',

                'permissions' => [
                    self::NOTIFICATIONS_VIEW =>
                        'View the notification centre',

                    self::AUDIT_VIEW =>
                        'View the system audit trail',
                ],
            ],

            'administration' => [
                'label' => 'Administration & Governance',

                'accent' => 'slate',

                'permissions' => [
                    self::GOVERNANCE_VIEW =>
                        'View data governance and import history',

                    self::GOVERNANCE_MANAGE =>
                        'Resolve data-quality findings',

                    self::BACKUP_VIEW =>
                        'View database backups',

                    self::BACKUP_MANAGE =>
                        'Create and download database backups',

                    self::SETTINGS_MANAGE =>
                        'Change controlled system settings',
                ],
            ],

            'readiness' => [
                'label' => 'QA & Release Readiness',

                'accent' => 'rose',

                'permissions' => [
                    self::READINESS_VIEW =>
                        'View deployment and UAT readiness',

                    self::READINESS_MANAGE =>
                        'Record UAT results and sign-off',
                ],
            ],
        ];
    }

    /**
     * Every known permission key.
     *
     * @return array<int, string>
     */
    public static function all(): array
    {
        $keys = [];

        foreach (self::groups() as $group) {
            foreach (array_keys($group['permissions']) as $permission) {
                $keys[] = $permission;
            }
        }

        return $keys;
    }

    /**
     * Human readable description for a single permission key.
     */
    public static function describe(string $permission): string
    {
        foreach (self::groups() as $group) {
            if (isset($group['permissions'][$permission])) {
                return $group['permissions'][$permission];
            }
        }

        return $permission;
    }

    /**
     * Permissions only the Administrator role may hold. They stay in the
     * catalogue (so their gates exist) but cannot be granted to any other
     * role.
     *
     * @return array<int, string>
     */
    public static function adminOnly(): array
    {
        return [
            self::IMPORT_MANAGE,
        ];
    }

    public static function isAdminOnly(string $permission): bool
    {
        return in_array($permission, self::adminOnly(), true);
    }

    /**
     * Reject any permission key that is not part of the catalogue.
     *
     * @param  array<int, string>  $permissions
     * @return array<int, string>
     */
    public static function onlyKnown(array $permissions): array
    {
        return array_values(
            array_unique(
                array_intersect(
                    $permissions,
                    self::all()
                )
            )
        );
    }
}
