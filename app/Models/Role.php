<?php

namespace App\Models;

use App\Support\Permissions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Role extends Model
{
    public const ADMINISTRATOR = 'administrator';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'is_system',
    ];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
        ];
    }

    public function permissions(): HasMany
    {
        return $this->hasMany(RolePermission::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Permission keys granted to this role.
     *
     * @return array<int, string>
     */
    public function permissionKeys(): array
    {
        return $this->permissions
            ->pluck('permission')
            ->all();
    }

    public function isAdministrator(): bool
    {
        return $this->slug === self::ADMINISTRATOR;
    }

    /**
     * The administrator role always holds every permission, so it is never
     * possible to lock the system out of its own access control screens.
     */
    public function grants(string $permission): bool
    {
        if ($this->isAdministrator()) {
            return true;
        }

        return in_array(
            $permission,
            $this->permissionKeys(),
            true
        );
    }

    /**
     * Replace the role's permission set with the supplied keys.
     *
     * @param  array<int, string>  $permissions
     */
    public function syncPermissions(array $permissions): void
    {
        $permissions = Permissions::onlyKnown($permissions);

        $this->permissions()
            ->whereNotIn('permission', $permissions)
            ->delete();

        $existing = $this->permissions()
            ->pluck('permission')
            ->all();

        foreach ($permissions as $permission) {
            if (in_array($permission, $existing, true)) {
                continue;
            }

            $this->permissions()->create([
                'permission' => $permission,
            ]);
        }

        $this->load('permissions');
    }

    public static function makeSlug(string $name): string
    {
        return Str::slug($name);
    }
}
