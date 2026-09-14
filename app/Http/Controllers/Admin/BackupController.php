<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DatabaseBackup;
use App\Services\AuditLogger;
use App\Services\BackupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class BackupController extends Controller
{
    public function __construct(
        private readonly BackupService $backups,
        private readonly AuditLogger $auditLogger
    ) {
    }

    public function index(): View
    {
        $entries = DatabaseBackup::query()
            ->with('creator')
            ->latest('id')
            ->paginate(15);

        return view('admin.backups.index', [
            'backups' => $entries,

            'supported' => $this->backups->isSupported(),

            'driver' => $this->backups->driver(),

            'databaseName' => $this->backups->databaseName(),

            'missingFiles' => $this->backups->missingFiles(),

            'latest' => DatabaseBackup::query()
                ->latest('id')
                ->first(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        try {
            $backup = $this->backups->create(
                $request->user()
            );
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors([
                'backup' => $exception->getMessage(),
            ]);
        }

        $this->auditLogger->record(
            event: 'created',
            subject: $backup,
            recordLabel: 'Backup ' . $backup->filename,
            summary: sprintf(
                '%d tables and %d rows written (%s).',
                $backup->table_count,
                $backup->row_count,
                $backup->humanSize()
            )
        );

        return back()->with(
            'status',
            'Backup created: ' . $backup->filename
                . ' (' . $backup->humanSize() . ').'
        );
    }

    public function download(
        DatabaseBackup $backup
    ): BinaryFileResponse|RedirectResponse {
        $path = $this->backups->pathFor($backup);

        if ($path === null) {
            return back()->withErrors([
                'backup' =>
                    'That backup file is no longer on disk.',
            ]);
        }

        return response()->download(
            $path,
            $backup->filename
        );
    }

    public function destroy(
        DatabaseBackup $backup
    ): RedirectResponse {
        $filename = $backup->filename;

        $this->backups->delete($backup);

        $this->auditLogger->record(
            event: 'deleted',
            recordLabel: 'Backup ' . $filename,
            summary: 'Backup file deleted'
        );

        return back()->with(
            'status',
            'Backup deleted.'
        );
    }
}
