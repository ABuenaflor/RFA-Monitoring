<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Rfa;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $request->validate([
            'search' =>
                ['nullable', 'string', 'max:150'],

            'role' =>
                ['nullable', 'string', 'max:100'],

            'status' =>
                ['nullable', 'in:active,inactive'],
        ]);

        $query = User::query()->with('role');

        $search = trim(
            (string) $request->query('search', '')
        );

        if ($search !== '') {
            $query->where(
                function (Builder $query) use ($search) {
                    $query
                        ->where(
                            'name',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'email',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'office',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'position',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhereHas(
                            'role',
                            fn (Builder $role) => $role->where(
                                'name',
                                'like',
                                "%{$search}%"
                            )
                        );
                }
            );
        }

        if ($request->filled('role')) {
            $query->whereHas(
                'role',
                fn (Builder $role) => $role->where(
                    'slug',
                    $request->query('role')
                )
            );
        }

        if ($request->filled('status')) {
            $query->where(
                'status',
                $request->query('status')
            );
        }

        $users = $query
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        /*
        |--------------------------------------------------------------------------
        | Directory summary
        |--------------------------------------------------------------------------
        |
        | Counted across all users, not just the filtered page.
        |
        */

        $summary = [
            'total' =>
                User::query()->count(),

            'active' =>
                User::query()->active()->count(),

            'admins' =>
                User::query()
                    ->whereHas(
                        'role',
                        fn (Builder $role) => $role->where(
                            'slug',
                            Role::ADMINISTRATOR
                        )
                    )
                    ->count(),

            'operational' =>
                User::query()
                    ->active()
                    ->whereHas(
                        'role',
                        fn (Builder $role) => $role->where(
                            'slug',
                            '!=',
                            Role::ADMINISTRATOR
                        )
                    )
                    ->count(),
        ];

        return view('admin.access.index', [
            'users' => $users,

            'summary' => $summary,

            'roles' => Role::query()
                ->orderBy('name')
                ->get(),

            'filters' => [
                'search' => $search,

                'role' => (string) $request->query('role', ''),

                'status' => (string) $request->query('status', ''),
            ],
        ]);
    }

    public function create(): View
    {
        return view('admin.access.create', [
            'roles' => Role::query()
                ->orderBy('name')
                ->get(),

            'offices' => $this->officeOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateUser($request);

        $data['password'] =
            $request->validate([
                'password' =>
                    [
                        'required',
                        'string',
                        'confirmed',
                        Password::min(10)
                            ->letters()
                            ->numbers(),
                    ],
            ])['password'];

        $data['must_change_password'] =
            $request->boolean('must_change_password');

        $data['email_verified_at'] = now();

        User::create($data);

        return redirect()
            ->route('admin.access.index')
            ->with(
                'status',
                'User account created.'
            );
    }

    public function edit(User $user): View
    {
        return view('admin.access.edit', [
            'user' => $user->load('role'),

            'roles' => Role::query()
                ->orderBy('name')
                ->get(),

            'offices' => $this->officeOptions(),
        ]);
    }

    public function update(
        Request $request,
        User $user
    ): RedirectResponse {
        $data = $this->validateUser($request, $user);

        $data['must_change_password'] =
            $request->boolean('must_change_password');

        /*
        |--------------------------------------------------------------------------
        | Protect the last active administrator
        |--------------------------------------------------------------------------
        */

        $losesAdminAccess =
            $user->isAdministrator()
            && (int) $data['role_id'] !== (int) $user->role_id;

        $becomesInactive =
            $user->isActive()
            && $data['status'] !== User::STATUS_ACTIVE;

        if (
            ($losesAdminAccess || $becomesInactive)
            && $this->isLastActiveAdministrator($user)
        ) {
            return redirect()
                ->route('admin.access.edit', $user)
                ->withErrors([
                    'role_id' =>
                        'This is the last active administrator. Assign another administrator first.',
                ]);
        }

        if (
            $data['status'] !== User::STATUS_ACTIVE
            && $user->id === $request->user()->id
        ) {
            return redirect()
                ->route('admin.access.edit', $user)
                ->withErrors([
                    'status' =>
                        'You cannot deactivate your own account.',
                ]);
        }

        $data['deactivated_at'] =
            $data['status'] === User::STATUS_ACTIVE
                ? null
                : ($user->deactivated_at ?? now());

        $user->fill($data);

        $user->deactivated_at = $data['deactivated_at'];

        $user->save();

        /*
        |--------------------------------------------------------------------------
        | Optional administrative password reset
        |--------------------------------------------------------------------------
        */

        if ($request->filled('password')) {
            $password = $request->validate([
                'password' =>
                    [
                        'required',
                        'string',
                        'confirmed',
                        Password::min(10)
                            ->letters()
                            ->numbers(),
                    ],
            ])['password'];

            $user->forceFill([
                'password' => $password,
                'must_change_password' => true,
            ])->save();
        }

        return redirect()
            ->route('admin.access.index')
            ->with(
                'status',
                'User account updated.'
            );
    }

    public function destroy(
        Request $request,
        User $user
    ): RedirectResponse {
        if ($user->id === $request->user()->id) {
            return redirect()
                ->route('admin.access.index')
                ->withErrors([
                    'user' =>
                        'You cannot delete your own account.',
                ]);
        }

        if ($this->isLastActiveAdministrator($user)) {
            return redirect()
                ->route('admin.access.index')
                ->withErrors([
                    'user' =>
                        'This is the last active administrator and cannot be deleted.',
                ]);
        }

        $user->delete();

        return redirect()
            ->route('admin.access.index')
            ->with(
                'status',
                'User account deleted.'
            );
    }

    /**
     * @return array<string, mixed>
     */
    private function validateUser(
        Request $request,
        ?User $user = null
    ): array {
        return $request->validate([
            'name' =>
                ['required', 'string', 'max:150'],

            'email' =>
                [
                    'required',
                    'string',
                    'email',
                    'max:255',
                    Rule::unique('users', 'email')
                        ->ignore($user?->id),
                ],

            'role_id' =>
                [
                    'required',
                    Rule::exists('roles', 'id'),
                ],

            'office' =>
                ['nullable', 'string', 'max:50'],

            'position' =>
                ['nullable', 'string', 'max:100'],

            'status' =>
                ['required', 'in:active,inactive'],
        ]);
    }

    private function isLastActiveAdministrator(User $user): bool
    {
        if (! $user->isAdministrator() || ! $user->isActive()) {
            return false;
        }

        return User::query()
            ->active()
            ->where('id', '!=', $user->id)
            ->whereHas(
                'role',
                fn (Builder $role) => $role->where(
                    'slug',
                    Role::ADMINISTRATOR
                )
            )
            ->doesntExist();
    }

    /**
     * Offices already present in the RFA data, so user assignment stays
     * aligned with the operational records.
     *
     * @return array<int, string>
     */
    private function officeOptions(): array
    {
        return Rfa::query()
            ->whereNotNull('office')
            ->where('office', '!=', '')
            ->distinct()
            ->orderBy('office')
            ->pluck('office')
            ->all();
    }
}
