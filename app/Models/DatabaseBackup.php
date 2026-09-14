<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class DatabaseBackup extends Model
{
    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'filename',
        'database_name',
        'driver',
        'size_bytes',
        'table_count',
        'row_count',
        'status',
        'notes',
        'created_by',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'created_by'
        );
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * Path relative to the local disk root.
     */
    public function relativePath(): string
    {
        return 'backups/' . $this->filename;
    }

    /**
     * A register entry is only trustworthy while its file is still there.
     */
    public function fileExists(): bool
    {
        return Storage::disk('local')
            ->exists($this->relativePath());
    }

    public function humanSize(): string
    {
        $bytes = (int) $this->size_bytes;

        if ($bytes < 1024) {
            return $bytes . ' B';
        }

        if ($bytes < 1024 * 1024) {
            return round($bytes / 1024, 1) . ' KB';
        }

        return round($bytes / (1024 * 1024), 2) . ' MB';
    }
}
