<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImportBatch extends Model
{
    protected $fillable = [
        'uuid',
        'file_name',
        'user_id',
        'rows_processed',
        'rows_imported',
        'rows_created',
        'rows_updated',
        'duplicates_skipped',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Records tagged with this batch. Still accurate for batches imported
     * before this table existed, because the uuid lives on the record.
     */
    public function records(): HasMany
    {
        return $this->hasMany(
            Rfa::class,
            'import_batch_uuid',
            'uuid'
        );
    }
}
