<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\DatabaseBackup;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Throwable;

/**
 * Live readiness checks.
 *
 * Every check inspects the running system — configuration, filesystem,
 * database, and data — rather than reporting a value someone typed in.
 */
class ReadinessService
{
    public const PASS = 'pass';

    public const WARN = 'warn';

    public const FAIL = 'fail';

    /**
     * @return array<int, array<string, mixed>>
     */
    public function checks(): array
    {
        return array_merge(
            $this->environmentChecks(),
            $this->securityChecks(),
            $this->dataChecks(),
            $this->deploymentChecks()
        );
    }

    /**
     * @return array<string, int>
     */
    public function summary(): array
    {
        $checks = $this->checks();

        $count = fn (string $status) => count(
            array_filter(
                $checks,
                fn (array $check) => $check['status'] === $status
            )
        );

        return [
            'total' => count($checks),
            'passing' => $count(self::PASS),
            'warnings' => $count(self::WARN),
            'failing' => $count(self::FAIL),
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | Environment
    |--------------------------------------------------------------------------
    */

    /**
     * @return array<int, array<string, mixed>>
     */
    private function environmentChecks(): array
    {
        $environment = app()->environment();

        $isProduction = $environment === 'production';

        return [
            $this->check(
                'env.environment',
                'Environment',
                'Environment',
                $isProduction ? self::PASS : self::WARN,
                'APP_ENV is "' . $environment . '".',
                $isProduction
                    ? 'The application is running in production mode.'
                    : 'Set APP_ENV=production before go-live so framework behaviour matches the deployed environment.'
            ),

            $this->check(
                'env.app_key',
                'Application Key',
                'Environment',
                config('app.key') ? self::PASS : self::FAIL,
                config('app.key')
                    ? 'An application key is set.'
                    : 'APP_KEY is empty.',
                'Sessions and encrypted values depend on APP_KEY. Generate one with php artisan key:generate.'
            ),

            $this->check(
                'env.php_version',
                'PHP Version',
                'Environment',
                version_compare(PHP_VERSION, '8.3.0', '>=')
                    ? self::PASS
                    : self::FAIL,
                'Running PHP ' . PHP_VERSION . '.',
                'The application requires PHP 8.3 or newer.'
            ),

            $this->check(
                'env.storage_writable',
                'Storage Writable',
                'Environment',
                is_writable(storage_path())
                    ? self::PASS
                    : self::FAIL,
                is_writable(storage_path())
                    ? 'storage/ is writable.'
                    : 'storage/ is not writable.',
                'Logs, sessions, compiled views, and backups are all written under storage/.'
            ),

            $this->check(
                'env.cache_writable',
                'Bootstrap Cache Writable',
                'Environment',
                is_writable(base_path('bootstrap/cache'))
                    ? self::PASS
                    : self::FAIL,
                is_writable(base_path('bootstrap/cache'))
                    ? 'bootstrap/cache is writable.'
                    : 'bootstrap/cache is not writable.',
                'Configuration and route caches are written here during deployment.'
            ),
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | Security
    |--------------------------------------------------------------------------
    */

    /**
     * @return array<int, array<string, mixed>>
     */
    private function securityChecks(): array
    {
        $debug = (bool) config('app.debug');

        $url = (string) config('app.url');

        $https = str_starts_with(strtolower($url), 'https://');

        $adminCount = User::query()
            ->active()
            ->whereHas(
                'role',
                fn (Builder $role) => $role->where(
                    'slug',
                    Role::ADMINISTRATOR
                )
            )
            ->count();

        $neverSignedIn = User::query()
            ->active()
            ->whereNull('last_login_at')
            ->count();

        $temporaryPasswords = User::query()
            ->active()
            ->where('must_change_password', true)
            ->count();

        $roleless = User::query()
            ->active()
            ->whereNull('role_id')
            ->count();

        return [
            $this->check(
                'security.debug',
                'Debug Mode',
                'Security',
                $debug ? self::FAIL : self::PASS,
                $debug
                    ? 'APP_DEBUG is true.'
                    : 'APP_DEBUG is false.',
                'With debug on, an error page exposes file paths, configuration, and query contents to whoever triggered it. Set APP_DEBUG=false before go-live.'
            ),

            $this->check(
                'security.https',
                'HTTPS',
                'Security',
                $https ? self::PASS : self::WARN,
                'APP_URL is ' . ($url ?: 'not set') . '.',
                'Sign-in credentials and session cookies travel in the clear over plain HTTP. Serve the system over HTTPS on any network beyond a single workstation.'
            ),

            $this->check(
                'security.administrators',
                'Administrator Accounts',
                'Security',
                match (true) {
                    $adminCount === 0 => self::FAIL,
                    $adminCount === 1 => self::WARN,
                    default => self::PASS,
                },
                $adminCount . ' active administrator account(s).',
                'Keep at least two so a single lost password cannot lock the organisation out of access control.'
            ),

            $this->check(
                'security.roleless_users',
                'Accounts Without a Role',
                'Security',
                $roleless === 0 ? self::PASS : self::WARN,
                $roleless . ' active account(s) have no role assigned.',
                'An account with no role cannot sign in. Assign a role or deactivate the account.'
            ),

            $this->check(
                'security.temporary_passwords',
                'Temporary Passwords Outstanding',
                'Security',
                $temporaryPasswords === 0 ? self::PASS : self::WARN,
                $temporaryPasswords . ' account(s) still hold an administrator-issued password.',
                'Each is forced to change it at next sign-in, but an unused temporary password is a standing risk.'
            ),

            $this->check(
                'security.dormant_accounts',
                'Accounts Never Used',
                'Security',
                $neverSignedIn === 0 ? self::PASS : self::WARN,
                $neverSignedIn . ' active account(s) have never signed in.',
                'Deactivate accounts that were issued but never taken up.'
            ),
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | Data
    |--------------------------------------------------------------------------
    */

    /**
     * @return array<int, array<string, mixed>>
     */
    private function dataChecks(): array
    {
        $connected = true;

        $connectionDetail = '';

        try {
            DB::connection()->getPdo();

            $connectionDetail = 'Connected to '
                . DB::connection()->getDatabaseName()
                . ' over ' . DB::connection()->getDriverName() . '.';
        } catch (Throwable $exception) {
            $connected = false;

            $connectionDetail = 'Connection failed: '
                . $exception->getMessage();
        }

        $latestBackup = DatabaseBackup::query()
            ->latest('id')
            ->first();

        $backupAgeDays = $latestBackup?->created_at
            ? $latestBackup->created_at->diffInDays(now())
            : null;

        $auditEntries = AuditLog::query()->count();

        return [
            $this->check(
                'data.connection',
                'Database Connection',
                'Data',
                $connected ? self::PASS : self::FAIL,
                $connectionDetail,
                'Everything depends on this. Check credentials and that the MySQL service is running.'
            ),

            $this->check(
                'data.migrations',
                'Pending Migrations',
                'Data',
                $this->pendingMigrations() === 0
                    ? self::PASS
                    : self::FAIL,
                $this->pendingMigrations() . ' migration(s) waiting to run.',
                'Run php artisan migrate. A pending migration means the code expects columns the database does not have.'
            ),

            $this->check(
                'data.backup_recent',
                'Recent Backup',
                'Data',
                match (true) {
                    $latestBackup === null => self::FAIL,
                    $backupAgeDays !== null && $backupAgeDays > 7 => self::WARN,
                    default => self::PASS,
                },
                $latestBackup === null
                    ? 'No backup has ever been taken.'
                    : 'Last backup '
                        . $latestBackup->created_at?->diffForHumans()
                        . ' (' . $latestBackup->humanSize() . ').',
                'Take a backup immediately before every deployment — it is what rollback depends on.'
            ),

            $this->check(
                'data.audit_active',
                'Audit Trail Recording',
                'Data',
                $auditEntries > 0 ? self::PASS : self::WARN,
                $auditEntries . ' audit entries recorded.',
                'An empty trail on a system in use means auditing is not reaching the database.'
            ),
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | Deployment
    |--------------------------------------------------------------------------
    */

    /**
     * @return array<int, array<string, mixed>>
     */
    private function deploymentChecks(): array
    {
        $manifest = public_path('build/manifest.json');

        $manifestExists = File::exists($manifest);

        $envInRepo = File::exists(base_path('.env'))
            && $this->isTrackedByGit('.env');

        return [
            $this->check(
                'deploy.assets',
                'Built Frontend Assets',
                'Deployment',
                $manifestExists ? self::PASS : self::FAIL,
                $manifestExists
                    ? 'public/build/manifest.json is present.'
                    : 'public/build/manifest.json is missing.',
                'Run npm ci && npm run build on the server. Without the manifest every page renders unstyled.'
            ),

            $this->check(
                'deploy.env_untracked',
                'Environment File Untracked',
                'Deployment',
                $envInRepo ? self::FAIL : self::PASS,
                $envInRepo
                    ? '.env appears to be tracked by git.'
                    : '.env is not tracked by git.',
                'The .env file holds database credentials and the application key and must never be committed.'
            ),

            $this->check(
                'deploy.queue',
                'Queue Connection',
                'Deployment',
                config('queue.default') === 'sync'
                    ? self::WARN
                    : self::PASS,
                'Queue connection is ' . config('queue.default') . '.',
                'The sync driver runs queued work inside the web request. Acceptable at this scale; move to database or redis with a worker if background work grows.'
            ),

            $this->check(
                'deploy.scheduler',
                'Scheduled PCT Scan',
                'Deployment',
                $this->scanHasRun() ? self::PASS : self::WARN,
                $this->scanHasRun()
                    ? 'The PCT scan has produced notifications.'
                    : 'No PCT notifications exist yet.',
                'Run php artisan schedule:work, or add a Task Scheduler entry calling php artisan schedule:run every minute, so the 07:00 scan actually fires.'
            ),
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | Internals
    |--------------------------------------------------------------------------
    */

    /**
     * @return array<string, mixed>
     */
    private function check(
        string $key,
        string $label,
        string $area,
        string $status,
        string $detail,
        string $recommendation
    ): array {
        return [
            'key' => $key,
            'label' => $label,
            'area' => $area,
            'status' => $status,
            'detail' => $detail,
            'recommendation' => $recommendation,
        ];
    }

    private function pendingMigrations(): int
    {
        try {
            $ran = DB::table('migrations')
                ->pluck('migration')
                ->all();
        } catch (Throwable) {
            return 0;
        }

        $files = collect(
            File::files(database_path('migrations'))
        )
            ->map(fn ($file) => $file->getFilenameWithoutExtension())
            ->all();

        return count(array_diff($files, $ran));
    }

    private function scanHasRun(): bool
    {
        return DB::table('app_notifications')
            ->where('dedupe_key', 'like', 'pct:%')
            ->exists();
    }

    /**
     * Best-effort check that a path is not under version control.
     */
    private function isTrackedByGit(string $relativePath): bool
    {
        $gitDirectory = base_path('.git');

        if (! File::isDirectory($gitDirectory)) {
            return false;
        }

        $ignoreFile = base_path('.gitignore');

        if (! File::exists($ignoreFile)) {
            return true;
        }

        $ignored = File::lines($ignoreFile)
            ->map(fn (string $line) => trim($line))
            ->contains(
                fn (string $line) => $line === $relativePath
                    || $line === '/' . $relativePath
            );

        return ! $ignored;
    }
}
