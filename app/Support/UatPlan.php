<?php

namespace App\Support;

/**
 * The user acceptance test plan.
 *
 * Kept in code rather than in the database so the plan is versioned with the
 * application it tests. Only the outcome of each case is persisted.
 */
final class UatPlan
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PASSED = 'passed';

    public const STATUS_FAILED = 'failed';

    public const STATUS_BLOCKED = 'blocked';

    public const STATUS_NOT_APPLICABLE = 'not_applicable';

    /**
     * @return array<string, string>
     */
    public static function statuses(): array
    {
        return [
            self::STATUS_PENDING => 'Not Tested',
            self::STATUS_PASSED => 'Passed',
            self::STATUS_FAILED => 'Failed',
            self::STATUS_BLOCKED => 'Blocked',
            self::STATUS_NOT_APPLICABLE => 'Not Applicable',
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function statusKeys(): array
    {
        return array_keys(self::statuses());
    }

    /**
     * Test cases grouped by the area they exercise.
     *
     * @return array<string, array<string, array{title: string, steps: string, expected: string}>>
     */
    public static function areas(): array
    {
        return [
            'Authentication & Access' => [
                'auth.sign_in' => [
                    'title' => 'A valid account can sign in',
                    'steps' => 'Sign in with an active account and a correct password.',
                    'expected' => 'The dashboard opens and the account name appears in the top bar.',
                ],

                'auth.bad_password' => [
                    'title' => 'A wrong password is refused',
                    'steps' => 'Sign in with a correct email and a wrong password, five times.',
                    'expected' => 'Each attempt is refused, and further attempts are rate limited.',
                ],

                'auth.deactivated' => [
                    'title' => 'A deactivated account cannot sign in',
                    'steps' => 'Deactivate a test account, then try to sign in as it.',
                    'expected' => 'Sign-in is refused with a message to contact an administrator.',
                ],

                'auth.mid_session_deactivation' => [
                    'title' => 'Deactivation ends an open session',
                    'steps' => 'Sign in as a test account, deactivate it from another browser, then navigate.',
                    'expected' => 'The open session is signed out on its next request.',
                ],

                'auth.forced_password_change' => [
                    'title' => 'A temporary password must be changed',
                    'steps' => 'Sign in as an account flagged for a password change and try to open the listing.',
                    'expected' => 'Every page redirects to My Account until a new password is set.',
                ],

                'auth.role_limits' => [
                    'title' => 'A role cannot reach what it lacks',
                    'steps' => 'Sign in as Viewer and request /users, /roles, and /reports/export directly by URL.',
                    'expected' => 'Each returns 403, not a redirect and not a partial page.',
                ],

                'auth.last_admin' => [
                    'title' => 'The last administrator is protected',
                    'steps' => 'With only one administrator, try to demote, deactivate, and delete that account.',
                    'expected' => 'All three are refused with an explanatory message.',
                ],
            ],

            'Case Management' => [
                'case.open' => [
                    'title' => 'A case opens from the listing',
                    'steps' => 'Open the listing and use the Open action on any row.',
                    'expected' => 'The case record shows parties, dates, PCT cards, and the timeline.',
                ],

                'case.edit_details' => [
                    'title' => 'Case information saves',
                    'steps' => 'Change the requesting party and save Case Information.',
                    'expected' => 'The value is saved and appears as a change entry on the timeline.',
                ],

                'case.assignment' => [
                    'title' => 'Assignment links an account',
                    'steps' => 'Assign an interviewer account and save.',
                    'expected' => 'The name updates, the case moves to Ongoing, and the officer receives a notification.',
                ],

                'case.chronology_guard' => [
                    'title' => 'A new date conflict is refused',
                    'steps' => 'Set an assignment date earlier than the filing date and save.',
                    'expected' => 'The save is refused and names the conflicting dates.',
                ],

                'case.legacy_tolerance' => [
                    'title' => 'Existing data problems do not block work',
                    'steps' => 'Open a case already listed under Data Governance and edit an unrelated field.',
                    'expected' => 'The edit saves, and the existing inconsistency is shown as a warning on the page.',
                ],

                'case.conference_order' => [
                    'title' => 'A 2nd conference needs a 1st',
                    'steps' => 'Record only a 2nd Conference date and save.',
                    'expected' => 'The save is refused.',
                ],

                'case.disposition' => [
                    'title' => 'Disposition closes the case',
                    'steps' => 'Record an official disposition and a Date Disposed.',
                    'expected' => 'The case moves to Disposed, and the 1st Conference - Date Disposed PCT result is shown.',
                ],

                'case.disposition_pairing' => [
                    'title' => 'A disposition cannot be half recorded',
                    'steps' => 'Save a disposition status with no date, then a date with no status.',
                    'expected' => 'Both are refused.',
                ],

                'case.reopen' => [
                    'title' => 'Reopening preserves history',
                    'steps' => 'Reopen a disposed case with a reason.',
                    'expected' => 'The disposition clears, and the previous values remain visible on the timeline.',
                ],
            ],

            'PCT & Reporting' => [
                'pct.stage_classification' => [
                    'title' => 'Stage classifications are correct',
                    'steps' => 'Check cases at 0, 1, 2, 3, and 4 elapsed days on a stage.',
                    'expected' => 'Within, Within, Nearing, On PCT, Beyond respectively.',
                ],

                'pct.day_thirty' => [
                    'title' => 'Day 30 is compliant',
                    'steps' => 'Check a case disposed exactly 30 days after filing.',
                    'expected' => 'Reported as Disposed Within PCT, not beyond.',
                ],

                'pct.indeterminate' => [
                    'title' => 'A closed case with no date does not age',
                    'steps' => 'Check a case marked disposed with no Date Disposed.',
                    'expected' => 'Reported as indeterminate, never as an ageing active timer.',
                ],

                'pct.missing_interview' => [
                    'title' => 'A missing historical interview date is not a breach',
                    'steps' => 'Check an imported case with no Date of Interview but later workflow dates.',
                    'expected' => 'Interviewer Assignment - Date Interviewed reports a missing end date, not Beyond PCT.',
                ],

                'report.filters' => [
                    'title' => 'Report filters apply',
                    'steps' => 'Filter reports by date range, office, SEADO, and disposition.',
                    'expected' => 'Summaries and the detail table both reflect the filter.',
                ],

                'report.export' => [
                    'title' => 'CSV export matches the screen',
                    'steps' => 'Export CSV with filters applied.',
                    'expected' => 'The file contains the same records as the filtered report.',
                ],

                'report.print' => [
                    'title' => 'The 8×13 print report is complete',
                    'steps' => 'Open the print report with a filter applied and print to PDF.',
                    'expected' => 'Landscape 13in × 8in, all matching records, repeated headers, no navigation.',
                ],

                'report.disposition_separation' => [
                    'title' => 'Official disposition and source Mode stay separate',
                    'steps' => 'Compare the two columns on the print report and the CSV.',
                    'expected' => 'They are separate columns, and neither substitutes for the other.',
                ],
            ],

            'Notifications & Audit' => [
                'ops.scan' => [
                    'title' => 'The PCT scan produces alerts',
                    'steps' => 'Run the PCT scan from Notifications & Audit.',
                    'expected' => 'Alerts appear for breaching cases and a count is reported.',
                ],

                'ops.idempotent' => [
                    'title' => 'Re-running the scan does not duplicate',
                    'steps' => 'Run the scan twice in a row.',
                    'expected' => 'The queue is unchanged the second time.',
                ],

                'ops.privacy' => [
                    'title' => 'Notifications are private to their owner',
                    'steps' => 'Sign in as another user and look for the first user’s alerts.',
                    'expected' => 'They are not visible, and marking them read by URL is refused.',
                ],

                'audit.change_record' => [
                    'title' => 'Changes are attributed',
                    'steps' => 'Edit a case, then open the audit trail.',
                    'expected' => 'The entry names the user, the record, the fields, and the before and after values.',
                ],

                'audit.no_secrets' => [
                    'title' => 'Passwords never appear in the trail',
                    'steps' => 'Reset a user password, then inspect the audit entry.',
                    'expected' => 'The password field is shown as redacted.',
                ],
            ],

            'Administration & Data' => [
                'import.round_trip' => [
                    'title' => 'A CSV import runs end to end',
                    'steps' => 'Import the operational CSV export.',
                    'expected' => 'Counts are reported, records appear, and one batch entry is written to the audit trail.',
                ],

                'import.reimport' => [
                    'title' => 'Re-importing updates rather than duplicates',
                    'steps' => 'Import the same file twice.',
                    'expected' => 'The second run updates existing records; the total does not double.',
                ],

                'governance.checks' => [
                    'title' => 'Data-quality checks reflect reality',
                    'steps' => 'Correct a flagged record, then reload Data Governance.',
                    'expected' => 'The finding count drops without any further action.',
                ],

                'settings.effect' => [
                    'title' => 'A setting changes behaviour',
                    'steps' => 'Change the default listing page size and reload the listing.',
                    'expected' => 'The listing opens at the new page size.',
                ],

                'backup.create_restore' => [
                    'title' => 'A backup can be taken and restored',
                    'steps' => 'Create a backup, download it, and restore it into a scratch database.',
                    'expected' => 'The restored database opens and the record count matches.',
                ],
            ],

            'Security & Deployment' => [
                'security.debug_off' => [
                    'title' => 'Debug mode is off in production',
                    'steps' => 'Check APP_DEBUG on the production environment.',
                    'expected' => 'False, and error pages reveal no stack traces.',
                ],

                'security.direct_url' => [
                    'title' => 'Protected URLs refuse guests',
                    'steps' => 'Sign out and request /dashboard, /users, and a case URL directly.',
                    'expected' => 'Each redirects to sign-in; none renders content.',
                ],

                'security.env_secret' => [
                    'title' => 'Environment secrets are not exposed',
                    'steps' => 'Confirm .env is not committed and not reachable over HTTP.',
                    'expected' => 'Not in version control, and the web root serves only public/.',
                ],

                'deploy.migrations' => [
                    'title' => 'Migrations run cleanly',
                    'steps' => 'Run php artisan migrate on a copy of production data.',
                    'expected' => 'All migrations apply with no pending items and no data loss.',
                ],

                'deploy.rollback' => [
                    'title' => 'Rollback has been rehearsed',
                    'steps' => 'Restore the pre-deployment backup and check out the previous commit.',
                    'expected' => 'The prior version runs against the restored data.',
                ],

                'deploy.assets' => [
                    'title' => 'Built assets are present',
                    'steps' => 'Confirm npm run build has produced public/build/manifest.json on the server.',
                    'expected' => 'Pages render with styling; no missing asset errors.',
                ],
            ],
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function caseKeys(): array
    {
        $keys = [];

        foreach (self::areas() as $cases) {
            foreach (array_keys($cases) as $key) {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    public static function totalCases(): int
    {
        return count(self::caseKeys());
    }
}
