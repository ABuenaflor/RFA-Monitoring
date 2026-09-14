<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReleaseSignoff extends Model
{
    protected $fillable = [
        'version',
        'environment',
        'summary',
        'cases_passed',
        'cases_total',
        'checks_failing',
        'signed_by',
        'signed_at',
    ];

    protected function casts(): array
    {
        return [
            'signed_at' => 'datetime',
        ];
    }

    public function signer(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'signed_by'
        );
    }
}
