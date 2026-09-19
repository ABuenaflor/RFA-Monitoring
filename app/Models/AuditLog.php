<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class AuditLog extends Model
{
    protected $fillable = [
        'user_id',
        'user_name',
        'event',
        'auditable_type',
        'auditable_id',
        'record_label',
        'change_summary',
        'changes',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'changes' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Events that can appear in the trail, for the filter dropdown.
     *
     * @return array<string, string>
     */
    public static function events(): array
    {
        return [
            'created' => 'Record Created',
            'updated' => 'Record Updated',
            'deleted' => 'Record Deleted',
            'login' => 'Sign In',
            'logout' => 'Sign Out',
            'login_failed' => 'Failed Sign In',
            'import' => 'CSV Import',
        ];
    }

    public function eventLabel(): string
    {
        return self::events()[$this->event]
            ?? Str::headline($this->event);
    }

    public function actorName(): string
    {
        return $this->user?->name
            ?? $this->user_name
            ?? 'System';
    }

    /**
     * Short name of the model class the entry refers to.
     */
    public function subjectLabel(): string
    {
        if ($this->record_label) {
            return $this->record_label;
        }

        if (! $this->auditable_type) {
            return '—';
        }

        return class_basename($this->auditable_type)
            . ' #' . $this->auditable_id;
    }

    public function accent(): string
    {
        return match ($this->event) {
            'created' => 'emerald',
            'updated' => 'blue',
            'deleted' => 'rose',
            'login' => 'slate',
            'logout' => 'slate',
            'login_failed' => 'amber',
            'import' => 'violet',
            default => 'slate',
        };
    }
}
