<?php

namespace App\Services;

use App\Models\DatabaseBackup;
use App\Models\User;
use App\Support\SystemSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Writes a self-contained SQL dump of the application database.
 *
 * Implemented in PHP rather than by shelling out to mysqldump, so it works
 * on a Windows workstation where the MySQL client tools may not be on PATH.
 *
 * Restoring is deliberately not automated. The register records what exists
 * and the interface documents the restore command; overwriting a live
 * database is a decision a person should make at a console.
 */
class BackupService
{
    private const DIRECTORY = 'backups';

    public function __construct(
        private readonly SettingsService $settings
    ) {
    }

    /**
     * Whether the active connection can be dumped by this service.
     */
    public function isSupported(): bool
    {
        return $this->driver() === 'mysql';
    }

    public function driver(): string
    {
        return (string) DB::connection()->getDriverName();
    }

    public function databaseName(): string
    {
        return (string) DB::connection()->getDatabaseName();
    }

    /**
     * Create a new dump and register it.
     */
    public function create(?User $actor = null): DatabaseBackup
    {
        if (! $this->isSupported()) {
            throw new RuntimeException(
                'Backups are only supported on a MySQL connection. '
                . 'The active connection is ' . $this->driver() . '.'
            );
        }

        $disk = Storage::disk('local');

        $disk->makeDirectory(self::DIRECTORY);

        $filename = sprintf(
            'rfa_backup_%s.sql',
            now()->format('Y-m-d_His')
        );

        $path = $disk->path(self::DIRECTORY . '/' . $filename);

        $handle = fopen($path, 'w');

        if ($handle === false) {
            throw new RuntimeException(
                'The backup file could not be opened for writing.'
            );
        }

        $tableCount = 0;

        $rowCount = 0;

        try {
            $this->writeHeader($handle);

            foreach ($this->tables() as $table) {
                $tableCount++;

                $rowCount += $this->writeTable($handle, $table);
            }

            $this->writeFooter($handle);
        } catch (Throwable $exception) {
            fclose($handle);

            @unlink($path);

            throw $exception;
        }

        fclose($handle);

        $backup = DatabaseBackup::create([
            'filename' => $filename,

            'database_name' => $this->databaseName(),

            'driver' => $this->driver(),

            'size_bytes' => (int) filesize($path),

            'table_count' => $tableCount,

            'row_count' => $rowCount,

            'status' => DatabaseBackup::STATUS_COMPLETED,

            'created_by' => $actor?->id,
        ]);

        $this->pruneOldBackups();

        return $backup;
    }

    /**
     * Remove a backup file and its register entry.
     */
    public function delete(DatabaseBackup $backup): void
    {
        Storage::disk('local')
            ->delete($backup->relativePath());

        $backup->delete();
    }

    /**
     * Absolute path for downloading, or null when the file has gone missing.
     */
    public function pathFor(DatabaseBackup $backup): ?string
    {
        if (! $backup->fileExists()) {
            return null;
        }

        return Storage::disk('local')
            ->path($backup->relativePath());
    }

    /**
     * Drop the oldest files once the configured retention count is exceeded.
     */
    public function pruneOldBackups(): int
    {
        $keep = (int) $this->settings->get(
            SystemSettings::BACKUP_RETENTION_COUNT
        );

        if ($keep <= 0) {
            return 0;
        }

        $expired = DatabaseBackup::query()
            ->orderByDesc('id')
            ->skip($keep)
            ->take(PHP_INT_MAX)
            ->get();

        foreach ($expired as $backup) {
            $this->delete($backup);
        }

        return $expired->count();
    }

    /**
     * Register entries whose file is no longer on disk.
     */
    public function missingFiles(): int
    {
        return DatabaseBackup::query()
            ->get()
            ->filter(fn (DatabaseBackup $backup) => ! $backup->fileExists())
            ->count();
    }

    public function restoreCommand(DatabaseBackup $backup): string
    {
        return sprintf(
            'mysql -u <user> -p %s < storage/app/private/%s',
            $this->databaseName(),
            $backup->relativePath()
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Dump Internals
    |--------------------------------------------------------------------------
    */

    /**
     * @return array<int, string>
     */
    private function tables(): array
    {
        $rows = DB::select('SHOW TABLES');

        $names = [];

        foreach ($rows as $row) {
            $names[] = array_values((array) $row)[0];
        }

        sort($names);

        return $names;
    }

    /**
     * @param  resource  $handle
     */
    private function writeHeader($handle): void
    {
        fwrite($handle, "-- RFA Monitoring System database backup\n");

        fwrite($handle, '-- Database: ' . $this->databaseName() . "\n");

        fwrite($handle, '-- Generated: ' . now()->toDateTimeString() . "\n\n");

        fwrite($handle, "SET FOREIGN_KEY_CHECKS=0;\n");

        fwrite($handle, "SET NAMES utf8mb4;\n\n");
    }

    /**
     * @param  resource  $handle
     */
    private function writeFooter($handle): void
    {
        fwrite($handle, "\nSET FOREIGN_KEY_CHECKS=1;\n");
    }

    /**
     * @param  resource  $handle
     */
    private function writeTable($handle, string $table): int
    {
        $quoted = '`' . str_replace('`', '``', $table) . '`';

        fwrite($handle, "\n--\n-- Table: {$table}\n--\n\n");

        fwrite($handle, "DROP TABLE IF EXISTS {$quoted};\n");

        $create = DB::select("SHOW CREATE TABLE {$quoted}");

        $statement = array_values((array) $create[0])[1] ?? null;

        if ($statement === null) {
            throw new RuntimeException(
                "The schema for table [{$table}] could not be read."
            );
        }

        fwrite($handle, $statement . ";\n\n");

        /*
        |--------------------------------------------------------------------------
        | Rows
        |--------------------------------------------------------------------------
        |
        | Chunked so a large table never has to fit in memory at once.
        |
        */

        $written = 0;

        $offset = 0;

        $chunk = 500;

        while (true) {
            $rows = DB::table($table)
                ->offset($offset)
                ->limit($chunk)
                ->get();

            if ($rows->isEmpty()) {
                break;
            }

            foreach ($rows as $row) {
                $columns = array_keys((array) $row);

                $values = array_map(
                    fn ($value) => $this->quote($value),
                    array_values((array) $row)
                );

                fwrite(
                    $handle,
                    sprintf(
                        "INSERT INTO %s (`%s`) VALUES (%s);\n",
                        $quoted,
                        implode('`, `', $columns),
                        implode(', ', $values)
                    )
                );

                $written++;
            }

            $offset += $chunk;
        }

        return $written;
    }

    private function quote(mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return DB::connection()
            ->getPdo()
            ->quote((string) $value);
    }
}
