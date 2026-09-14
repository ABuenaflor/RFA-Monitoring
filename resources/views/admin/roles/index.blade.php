@extends('layouts.app')


@section(
    'title',
    'Roles & Permissions | RFA Monitoring System'
)


@section(
    'page_heading',
    'Roles & Permissions'
)


@section('page_actions')

    <div class="flex items-center gap-2">

        <a
            href="{{ route('admin.access.index') }}"
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
            Users &amp; Access
        </a>

        <a
            href="{{ route('admin.roles.create') }}"
            class="inline-flex items-center
                   justify-center
                   rounded-xl
                   bg-blue-600 px-4 py-2.5
                   text-sm font-semibold
                   text-white shadow-sm
                   transition
                   hover:bg-blue-700"
        >
            Add Role
        </a>

    </div>

@endsection


@section('content')

<div class="space-y-7">

    <section>

        <p
            class="max-w-4xl text-sm
                   leading-6 text-slate-500"
        >
            A role is a named bundle of permissions. Changing a role changes what
            every user holding it may do, immediately and server-side. The
            Administrator role always holds every permission and cannot be
            narrowed.
        </p>

    </section>


    {{-- ========================================================= --}}
    {{-- ROLE CARDS --}}
    {{-- ========================================================= --}}

    <section
        class="grid grid-cols-1 gap-5
               lg:grid-cols-2"
    >

        @forelse ($roles as $role)

            <div
                class="flex flex-col
                       rounded-2xl
                       border border-slate-200
                       bg-white p-6 shadow-sm"
            >

                <div
                    class="flex items-start
                           justify-between gap-4"
                >

                    <div class="min-w-0">

                        <p
                            @class([
                                'text-xs font-bold uppercase tracking-[0.15em]',

                                'text-violet-600' => $role->isAdministrator(),
                                'text-blue-600' => ! $role->isAdministrator(),
                            ])
                        >
                            {{ $role->is_system ? 'System Role' : 'Custom Role' }}
                        </p>

                        <h2
                            class="mt-1 text-lg
                                   font-bold text-slate-950"
                        >
                            {{ $role->name }}
                        </h2>

                    </div>


                    <span
                        class="shrink-0 rounded-full
                               bg-slate-100 px-3 py-1
                               text-xs font-semibold
                               text-slate-700"
                    >
                        {{ $role->users_count }}
                        {{ \Illuminate\Support\Str::plural('user', $role->users_count) }}
                    </span>

                </div>


                <p
                    class="mt-3 text-sm leading-6
                           text-slate-500"
                >
                    {{ $role->description ?? 'No description recorded.' }}
                </p>


                <div
                    class="mt-5 rounded-xl
                           bg-slate-50 px-4 py-3"
                >

                    <p
                        class="text-xs font-bold
                               uppercase tracking-wider
                               text-slate-400"
                    >
                        Permissions
                    </p>

                    <p
                        class="mt-1 text-sm
                               font-semibold text-slate-800"
                    >
                        @if ($role->isAdministrator())
                            All {{ $permissionCount }} permissions (implicit)
                        @else
                            {{ $role->permissions->count() }} of {{ $permissionCount }} granted
                        @endif
                    </p>

                </div>


                <div
                    class="mt-5 flex items-center
                           gap-2 pt-1"
                >

                    <a
                        href="{{ route('admin.roles.edit', $role) }}"
                        class="rounded-xl
                               border border-slate-300
                               bg-white px-4 py-2.5
                               text-sm font-semibold
                               text-slate-700
                               transition
                               hover:bg-slate-50"
                    >
                        Edit Role
                    </a>


                    @if (! $role->is_system && $role->users_count === 0)

                        <form
                            method="POST"
                            action="{{ route('admin.roles.destroy', $role) }}"
                            onsubmit="return confirm('Delete this role?');"
                        >

                            @csrf
                            @method('DELETE')

                            <button
                                type="submit"
                                class="rounded-xl
                                       border border-rose-200
                                       bg-white px-4 py-2.5
                                       text-sm font-semibold
                                       text-rose-600
                                       transition
                                       hover:bg-rose-50"
                            >
                                Delete
                            </button>

                        </form>

                    @endif

                </div>

            </div>

        @empty

            <div
                class="rounded-2xl
                       border border-slate-200
                       bg-white px-6 py-14
                       text-center text-sm
                       text-slate-500
                       shadow-sm lg:col-span-2"
            >
                No roles have been defined yet.
            </div>

        @endforelse

    </section>

</div>

@endsection
