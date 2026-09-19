<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppNotification extends Model
{
    public const SEVERITY_INFO = 'info';

    public const SEVERITY_WARNING = 'warning';

    public const SEVERITY_CRITICAL = 'critical';

    protected $fillable = [
        'user_id',
        'rfa_id',
        'category',
        'severity',
        'title',
        'message',
        'action_url',
        'dedupe_key',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function rfa(): BelongsTo
    {
        return $this->belongsTo(Rfa::class);
    }

    public function scopeUnread(Builder $query): Builder
    {
        return $query->whereNull('read_at');
    }

    public function isRead(): bool
    {
        return $this->read_at !== null;
    }

    public function accent(): string
    {
        return match ($this->severity) {
            self::SEVERITY_CRITICAL => 'rose',
            self::SEVERITY_WARNING => 'amber',
            default => 'blue',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function categories(): array
    {
        return [
            'pct_stage_one' => 'Stage 1 PCT',
            'pct_stage_two' => 'Stage 2 PCT',
            'pct_disposition' => '30-Day Disposition PCT',
            'assignment' => 'Assignment',
            'workflow' => 'Workflow',
            'system' => 'System',
        ];
    }

    public function categoryLabel(): string
    {
        return self::categories()[$this->category]
            ?? $this->category;
    }
}
