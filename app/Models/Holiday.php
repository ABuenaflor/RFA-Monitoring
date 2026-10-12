<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A non-working date. The PCT clock does not count it when PCT is
 * measured in working days.
 */
class Holiday extends Model
{
    public const TYPE_HOLIDAY = 'holiday';

    public const TYPE_EVENT = 'event';

    protected $fillable = [
        'date',
        'name',
        'type',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function types(): array
    {
        return [
            self::TYPE_HOLIDAY => 'Holiday',
            self::TYPE_EVENT => 'Event / Office Closure',
        ];
    }

    public function typeLabel(): string
    {
        return self::types()[$this->type] ?? ucfirst((string) $this->type);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
