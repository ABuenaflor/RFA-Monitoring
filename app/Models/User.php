<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable([
    'name',
    'email',
    'password',
    'role_id',
    'office',
    'position',
    'status',
    'must_change_password',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'must_change_password' => 'boolean',
            'deactivated_at' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Access Control
    |--------------------------------------------------------------------------
    */

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isAdministrator(): bool
    {
        return (bool) $this->role?->isAdministrator();
    }

    /**
     * UI visibility is never authorization on its own — every protected route
     * is additionally guarded by the matching gate.
     */
    public function hasPermission(string $permission): bool
    {
        if (! $this->isActive()) {
            return false;
        }

        if ($this->role === null) {
            return false;
        }

        return $this->role->grants($permission);
    }

    /**
     * @param  array<int, string>  $permissions
     */
    public function hasAnyPermission(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($this->hasPermission($permission)) {
                return true;
            }
        }

        return false;
    }

    public function roleName(): string
    {
        return $this->role?->name ?? 'No Role Assigned';
    }

    public function statusLabel(): string
    {
        return $this->isActive()
            ? 'Active'
            : 'Inactive';
    }

    public function initials(): string
    {
        $parts = preg_split(
            '/\s+/',
            trim((string) $this->name)
        ) ?: [];

        $parts = array_values(
            array_filter($parts)
        );

        if ($parts === []) {
            return '?';
        }

        if (count($parts) === 1) {
            return strtoupper(
                substr($parts[0], 0, 2)
            );
        }

        return strtoupper(
            substr($parts[0], 0, 1)
            . substr($parts[count($parts) - 1], 0, 1)
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeActive(Builder $query): Builder
    {
        return $query->where(
            'status',
            self::STATUS_ACTIVE
        );
    }
}
