@extends('layouts.app')


@section(
    'title',
    'Users & Access | RFA Monitoring System'
)


@section(
    'page_heading',
    'Users & Access'
)


@section('page_actions')

    <div class="flex items-center gap-2">

        @can('roles.manage')

            <a
                href="{{ route('admin.roles.index') }}"
                class="inline-flex items-center
                       justify-center
                       rounded-xl
                       border border-slate-300
                       bg-white px-4 py-2.5
                       text-sm font-semibold
                       text-slate-700 shadow-sm
                       transition
                       hover:bg-slate-50"
            >
                Roles &amp; Permissions
            </a>

        @endcan


        @can('users.manage')

            <a
                href="{{ route('admin.access.create') }}"
                class="inline-flex items-center
                       justify-center
                       rounded-xl
                       bg-blue-600 px-4 py-2.5
                       text-sm font-semibold
                       text-white shadow-sm
                       transition
                       hover:bg-blue-700"
            >
                Add User
            </a>

        @endcan

    </div>

@endsection


@section('content')

<div class="space-y-7">

    {{-- ========================================================= --}}
    {{-- DESCRIPTION --}}
    {{-- ========================================================= --}}

    <section>

        <p
            class="max-w-4xl text-sm
                   leading-6 text-slate-500"
        >
            Manage authenticated users, role-based permissions, account status,
            office assignment, and functional access. Every protected route is
            enforced server-side by the permission assigned to the user's role.
        </p>

    </section>


    {{-- ========================================================= --}}
    {{-- SUMMARY --}}
    {{-- ========================================================= --}}

    <section
        class="grid grid-cols-2 gap-4
               xl:grid-cols-4"
    >

        @foreach ([
            'Total Users' => 'total',
            'Active' => 'active',
            'Administrators' => 'admins',
            'Operational Users' => 'operational',
        ] as $label => $key)

            <div
                class="rounded-2xl
                       border border-slate-200
                       bg-white p-5 shadow-sm"
            >

                <p
                    class="text-xs font-bold
                           uppercase tracking-wider
                           text-slate-400"
                >
                    {{ $label }}
                </p>

                <p
                    class="mt-3 text-3xl
                           font-bold text-slate-950"
                >
                    {{ number_format($summary[$key]) }}
                </p>

            </div>

        @endforeach

    </section>


    {{-- ========================================================= --}}
    {{-- FILTERS --}}
    {{-- ========================================================= --}}

    <section
        class="rounded-2xl
               border border-slate-200
               bg-white p-6 shadow-sm"
    >

        <form
            method="GET"
            action="{{ route('admin.access.index') }}"
            class="grid grid-cols-1 gap-5
                   md:grid-cols-4"
        >

            <div class="md:col-span-2">

                <label
                    for="search"
                    class="mb-2 block text-sm
                           font-semibold text-slate-700"
                >
                    Search
                </label>

                <input
                    id="search"
                    name="search"
                    value="{{ $filters['search'] }}"
                    placeholder="Name, email, role, office..."
                    class="w-full rounded-xl
                           border border-slate-300
                           px-4 py-3 text-sm
                           outline-none
                           focus:border-blue-500
                           focus:ring-4 focus:ring-blue-100"
                >

            </div>


            <div>

                <label
                    for="role"
                    class="mb-2 block text-sm
                           font-semibold text-slate-700"
                >
                    Role
                </label>

                <select
                    id="role"
                    name="role"
                    class="w-full rounded-xl
                           border border-slate-300
                           bg-white px-4 py-3 text-sm"
                >

                    <option value="">All Roles</option>

                    @foreach ($roles as $role)

                        <option
                            value="{{ $role->slug }}"
                            @selected($filters['role'] === $role->slug)
                        >
                            {{ $role->name }}
                        </option>

                    @endforeach

                </select>

            </div>


            <div>

                <label
                    for="status"
                    class="mb-2 block text-sm
                           font-semibold text-slate-700"
                >
                    Status
                </label>

                <select
                    id="status"
                    name="status"
                    class="w-full rounded-xl
                           border border-slate-300
                           bg-white px-4 py-3 text-sm"
                >

                    <option value="">All</option>

                    <option
                        value="active"
                        @selected($filters['status'] === 'active')
                    >
                        Active
                    </option>

                    <option
                        value="inactive"
                        @selected($filters['status'] === 'inactive')
                    >
                        Inactive
                    </option>

                </select>

            </div>


            <div
                class="flex items-center gap-2
                       md:col-span-4"
            >

                <button
                    type="submit"
                    class="rounded-xl bg-slate-950
                           px-5 py-2.5
                           text-sm font-semibold
                           text-white shadow-sm
                           transition
                           hover:bg-slate-800"
                >
                    Apply Filters
                </button>

                <a
                    href="{{ route('admin.access.index') }}"
                    class="rounded-xl
                           border border-slate-300
                           bg-white px-5 py-2.5
                           text-sm font-semibold
                           text-slate-700
                           transition
                           hover:bg-slate-50"
                >
                    Reset
                </a>

            </div>

        </form>

    </section>


    {{-- ========================================================= --}}
    {{-- DIRECTORY --}}
    {{-- ========================================================= --}}

    <section
        class="overflow-hidden
               rounded-2xl
               border border-slate-200
               bg-white shadow-sm"
    >

        <div
            class="border-b border-slate-100
                   px-6 py-5"
        >

            <p
                class="text-xs font-bold
                       uppercase tracking-[0.15em]
                       text-blue-600"
            >
                Account Directory
            </p>

            <h2
                class="mt-1 text-lg
                       font-bold text-slate-950"
            >
                Authorized Users
            </h2>

        </div>


        <div class="overflow-x-auto">

            <table
                class="w-full min-w-[1050px]
                       divide-y divide-slate-200"
            >

                <thead class="bg-slate-50">

                    <tr>

                        @foreach ([
                            'User',
                            'Role',
                            'Office / Assignment',
                            'Status',
                            'Last Login',
                            'Actions',
                        ] as $header)

                            <th
                                class="px-4 py-3
                                       text-left text-xs
                                       font-bold uppercase
                                       text-slate-500"
                            >
                                {{ $header }}
                            </th>

                        @endforeach

                    </tr>

                </thead>


                <tbody class="divide-y divide-slate-100">

                    @forelse ($users as $user)

                        <tr class="transition hover:bg-slate-50/80">

                            <td class="px-4 py-4">

                                <div
                                    class="font-semibold
                                           text-slate-900"
                                >
                                    {{ $user->name }}
                                </div>

                                <div
                                    class="mt-1 text-xs
                                           text-slate-400"
                                >
                                    {{ $user->email }}
                                </div>

                                @if ($user->must_change_password)

                                    <div
                                        class="mt-1 text-xs
                                               font-semibold
                                               text-amber-600"
                                    >
                                        Password change required
                                    </div>

                                @endif

                            </td>


                            <td class="px-4 py-4 text-slate-700">

                                <span
                                    @class([
                                        'rounded-full px-3 py-1 text-xs font-semibold',

                                        'bg-violet-50 text-violet-700'
                                            => $user->isAdministrator(),

                                        'bg-slate-100 text-slate-700'
                                            => ! $user->isAdministrator(),
                                    ])
                                >
                                    {{ $user->roleName() }}
                                </span>

                            </td>


                            <td class="px-4 py-4 text-sm text-slate-700">

                                <div>{{ $user->office ?? '—' }}</div>

                                <div
                                    class="mt-1 text-xs
                                           text-slate-400"
                                >
                                    {{ $user->position ?? 'No position recorded' }}
                                </div>

                            </td>


                            <td class="px-4 py-4">

                                <span
                                    @class([
                                        'rounded-full px-3 py-1 text-xs font-semibold',

                                        'bg-emerald-50 text-emerald-700'
                                            => $user->isActive(),

                                        'bg-rose-50 text-rose-700'
                                            => ! $user->isActive(),
                                    ])
                                >
                                    {{ $user->statusLabel() }}
                                </span>

                            </td>


                            <td class="px-4 py-4 text-sm text-slate-500">

                                @if ($user->last_login_at)

                                    {{ $user->last_login_at->format('M d, Y g:i A') }}

                                    <div
                                        class="mt-1 text-xs
                                               text-slate-400"
                                    >
                                        {{ $user->last_login_ip ?? '—' }}
                                    </div>

                                @else

                                    <span class="text-slate-400">
                                        Never signed in
                                    </span>

                                @endif

                            </td>


                            <td class="px-4 py-4">

                                @can('users.manage')

                                    <div
                                        class="flex items-center gap-3
                                               text-sm font-semibold"
                                    >

                                        <a
                                            href="{{ route('admin.access.edit', $user) }}"
                                            class="text-blue-600
                                                   transition
                                                   hover:text-blue-700"
                                        >
                                            Edit
                                        </a>

                                        @if ($user->id !== auth()->id())

                                            <form
                                                method="POST"
                                                action="{{ route('admin.access.destroy', $user) }}"
                                                onsubmit="return confirm('Delete this user account? This cannot be undone.');"
                                            >

                                                @csrf
                                                @method('DELETE')

                                                <button
                                                    type="submit"
                                                    class="text-rose-600
                                                           transition
                                                           hover:text-rose-700"
                                                >
                                                    Delete
                                                </button>

                                            </form>

                                        @endif

                                    </div>

                                @else

                                    <span class="text-sm text-slate-400">
                                        View only
                                    </span>

                                @endcan

                            </td>

                        </tr>

                    @empty

                        <tr>

                            <td
                                colspan="6"
                                class="px-6 py-14
                                       text-center text-sm
                                       text-slate-500"
                            >
                                No users match the selected filters.
                            </td>

                        </tr>

                    @endforelse

                </tbody>

            </table>

        </div>


        @if ($users->hasPages())

            <div
                class="border-t border-slate-100
                       px-6 py-4"
            >
                {{ $users->links() }}
            </div>

        @endif

    </section>


    {{-- ========================================================= --}}
    {{-- PERMISSION MODEL --}}
    {{-- ========================================================= --}}

    <section
        class="rounded-2xl
               border border-violet-200
               bg-violet-50 p-6 shadow-sm"
    >

        <p
            class="text-xs font-bold
                   uppercase tracking-[0.15em]
                   text-violet-700"
        >
            Permission Model
        </p>

        <h2
            class="mt-1 text-lg
                   font-bold text-violet-950"
        >
            RBAC Enforcement
        </h2>

        <p
            class="mt-3 text-sm leading-6
                   text-violet-800"
        >
            UI visibility is not authorization. Every protected route is guarded
            by the gate matching its permission key, deactivated accounts are
            signed out on their next request, and the last active administrator
            cannot be demoted, deactivated, or deleted.
        </p>

    </section>

</div>

@endsection
