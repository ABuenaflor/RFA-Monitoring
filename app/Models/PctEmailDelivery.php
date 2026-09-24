<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A PCT email alert that has already been sent.
 *
 * Exists only so the daily scan does not email the same alert twice.
 */
class PctEmailDelivery extends Model
{
    public const LEVEL_NEARING = 'nearing';

    public const LEVEL_DUE_TODAY = 'due_today';

    public const LEVEL_BREACHED = 'breached';

    protected $fillable = [
        'user_id',
        'rfa_id',
        'stage',
        'level',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
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
}
