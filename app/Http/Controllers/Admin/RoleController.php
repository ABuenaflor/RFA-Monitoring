<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Support\Permissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RoleController extends Controller
{
    public function index(): View
    {
        return view('admin.roles.index', [
            'roles' => Role::query()
                ->with('permissions')
                ->withCount('users')
                ->orderBy('name')
                ->get(),

            'permissionCount' =>
                count(Permissions::all()),
        ]);
    }

    public function create(): View
    {
        return view('admin.roles.create', [
            'groups' => Permissions::groups(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateRole($request);

        $role = Role::create([
            'name' => $data['name'],

            'slug' => $this->uniqueSlug($data['name']),

            'description' => $data['description'] ?? null,

            'is_system' => false,
        ]);

        $role->syncPermissions(
            $request->input('permissions', [])
        );

        return redirect()
            ->route('admin.roles.index')
            ->with(
                'status',
                'Role created.'
            );
    }

    public function edit(Role $role): View
    {
        return view('admin.roles.edit', [
            'role' => $role->load('permissions'),

            'groups' => Permissions::groups(),

            'granted' => $role->permissionKeys(),
        ]);
    }

    public function update(
        Request $request,
        Role $role
    ): RedirectResponse {
        $data = $this->validateRole($request, $role);

        $role->fill([
            'name' => $data['name'],

            'description' => $data['description'] ?? null,
        ])->save();

        /*
        |--------------------------------------------------------------------------
        | Administrator permissions are implicit
        |--------------------------------------------------------------------------
        |
        | The administrator role always holds every permission, so its matrix
        | is not editable and nothing is written for it.
        |
        */

        if (! $role->isAdministrator()) {
            $role->syncPermissions(
                $request->input('permissions', [])
            );
        }

        return redirect()
            ->route('admin.roles.index')
            ->with(
                'status',
                'Role updated.'
            );
    }

    public function destroy(Role $role): RedirectResponse
    {
        if ($role->is_system) {
            return redirect()
                ->route('admin.roles.index')
                ->withErrors([
                    'role' =>
                        'System roles cannot be deleted.',
                ]);
        }

        if ($role->users()->exists()) {
            return redirect()
                ->route('admin.roles.index')
                ->withErrors([
                    'role' =>
                        'Reassign the users holding this role before deleting it.',
                ]);
        }

        $role->delete();

        return redirect()
            ->route('admin.roles.index')
            ->with(
                'status',
                'Role deleted.'
            );
    }

    /**
     * @return array<string, mixed>
     */
    private function validateRole(
        Request $request,
        ?Role $role = null
    ): array {
        return $request->validate([
            'name' =>
                [
                    'required',
                    'string',
                    'max:100',
                    Rule::unique('roles', 'name')
                        ->ignore($role?->id),
                ],

            'description' =>
                ['nullable', 'string', 'max:255'],

            'permissions' =>
                ['nullable', 'array'],

            'permissions.*' =>
                [
                    'string',
                    Rule::in(Permissions::all()),
                ],
        ]);
    }

    private function uniqueSlug(string $name): string
    {
        $base = Role::makeSlug($name);

        $slug = $base;

        $suffix = 2;

        while (
            Role::query()
                ->where('slug', $slug)
                ->exists()
        ) {
            $slug = "{$base}-{$suffix}";

            $suffix++;
        }

        return $slug;
    }
}
