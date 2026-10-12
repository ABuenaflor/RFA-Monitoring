<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A SEADO the SEADO Assignment roulette skips.
 */
class RouletteExclusion extends Model
{
    protected $fillable = [
        'seado_key',
        'seado_name',
        'reason',
        'excluded_by',
    ];

    public function excluder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'excluded_by');
    }
}
