<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RfaActivity extends Model
{
    protected $fillable = [
        'rfa_id',
        'user_id',
        'type',
        'title',
        'description',
        'changes',
    ];

    protected function casts(): array
    {
        return [
            'changes' => 'array',
        ];
    }

    public function rfa(): BelongsTo
    {
        return $this->belongsTo(Rfa::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Who performed the action. Imports and console commands have no user.
     */
    public function actorName(): string
    {
        return $this->user?->name ?? 'System';
    }

    /**
     * Accent colour for the timeline marker, matched to the action taken.
     */
    public function accent(): string
    {
        return match ($this->type) {
            'created' => 'slate',
            'details' => 'blue',
            'assignment' => 'indigo',
            'workflow' => 'blue',
            'conference' => 'violet',
            'disposition' => 'emerald',
            'reopened' => 'amber',
            'note' => 'slate',
            default => 'slate',
        };
    }
}
