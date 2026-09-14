<?php

namespace App\Models;

use App\Support\UatPlan;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UatResult extends Model
{
    protected $fillable = [
        'case_key',
        'status',
        'notes',
        'tested_by',
        'tested_at',
    ];

    protected function casts(): array
    {
        return [
            'tested_at' => 'datetime',
        ];
    }

    public function tester(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'tested_by'
        );
    }

    public function statusLabel(): string
    {
        return UatPlan::statuses()[$this->status]
            ?? $this->status;
    }
}
