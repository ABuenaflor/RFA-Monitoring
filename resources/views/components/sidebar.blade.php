@php

    use App\Support\Permissions;

    /*
    |--------------------------------------------------------------------------
    | Navigation Definition
    |--------------------------------------------------------------------------
    |
    | Each item renders only when its route exists AND the signed-in user
    | holds the matching permission. Hiding a link is a convenience only —
    | every route is independently guarded by the same gate.
    |
    */

    $navigation = [

        'Main Menu' => [
            [
                'label' => 'Dashboard',
                'route' => 'dashboard',
                'icon' => 'dashboard',
                'permission' => Permissions::DASHBOARD_VIEW,
            ],
            [
                'label' => 'Listing',
                'route' => 'listing',
                'icon' => 'listing',
                'permission' => Permissions::RFA_VIEW,
            ],
            [
                'label' => 'Reports',
                'route' => 'reports',
                'icon' => 'reports',
                'permission' => Permissions::REPORTS_VIEW,
            ],
            [
                'label' => 'PCT Process',
                'route' => 'pct-process',
                'icon' => 'pct',
                'permission' => Permissions::PCT_VIEW,
            ],
        ],

        'Operations' => [
            [
                'label' => 'Notifications & Audit',
                'route' => 'operations.index',
                'icon' => 'operations',
                'permission' => Permissions::NOTIFICATIONS_VIEW,
                'active' => 'operations.*',
            ],
            [
                'label' => 'CSV Import',
                'route' => 'imports.index',
                'icon' => 'imports',
                'permission' => Permissions::IMPORT_MANAGE,
            ],
        ],

        'Administration' => [
            [
                'label' => 'Users & Access',
                'route' => 'admin.access.index',
                'icon' => 'users',
                'permission' => Permissions::USERS_VIEW,
                'active' => 'admin.access.*',
            ],
            [
                'label' => 'Roles & Permissions',
                'route' => 'admin.roles.index',
                'icon' => 'roles',
                'permission' => Permissions::ROLES_MANAGE,
                'active' => 'admin.roles.*',
            ],
            [
                'label' => 'Data Governance',
                'route' => 'admin.governance.index',
                'icon' => 'governance',
                'permission' => Permissions::GOVERNANCE_VIEW,
                'active' => 'admin.governance.*',
            ],
            [
                'label' => 'Database Backups',
                'route' => 'admin.backups.index',
                'icon' => 'backups',
                'permission' => Permissions::BACKUP_VIEW,
                'active' => 'admin.backups.*',
            ],
            [
                'label' => 'Release Readiness',
                'route' => 'admin.readiness.index',
                'icon' => 'readiness',
                'permission' => Permissions::READINESS_VIEW,
                'active' => 'admin.readiness.*',
            ],
            [
                'label' => 'System Settings',
                'route' => 'admin.settings.index',
                'icon' => 'administration',
                'permission' => Permissions::SETTINGS_MANAGE,
                'active' => 'admin.settings.*',
            ],
        ],

    ];

@endphp


<aside
    class="fixed inset-y-0 left-0 z-50
           flex w-72 flex-col
           border-r border-slate-800
           bg-slate-950
           text-white
           shadow-2xl shadow-slate-950/20
           transition-all duration-300 ease-in-out
           lg:translate-x-0"

    :class="[
        mobileSidebarOpen
            ? 'translate-x-0'
            : '-translate-x-full',

        sidebarExpanded
            ? 'lg:w-72'
            : 'lg:w-20'
    ]"
>

    {{-- ========================================================= --}}
    {{-- SIDEBAR HEADER --}}
    {{-- ========================================================= --}}

    <div
        class="flex h-20 shrink-0
               items-center
               border-b border-slate-800
               px-4"
        :class="
            sidebarExpanded
                ? 'justify-between'
                : 'lg:justify-center'
        "
    >

        <a
            href="{{ route('dashboard') }}"
            class="flex min-w-0 items-center gap-3"
        >

            {{-- LOGO --}}

            <div
                class="flex h-11 w-11 shrink-0
                       items-center justify-center
                       rounded-xl bg-blue-600
                       font-black text-white
                       shadow-lg shadow-blue-950/40"
            >
                RFA
            </div>


            {{-- BRAND TEXT --}}

            <div
                x-cloak
                x-show="sidebarExpanded || mobileSidebarOpen"
                x-transition.opacity
                class="min-w-0"
            >

                <p
                    class="truncate text-sm
                           font-bold tracking-wide
                           text-white"
                >
                    RFA Monitoring
                </p>

                <p
                    class="truncate text-xs
                           text-slate-400"
                >
                    Management System
                </p>

            </div>

        </a>


        {{-- MOBILE CLOSE --}}

        <button
            type="button"
            @click="mobileSidebarOpen = false"
            class="ml-auto flex h-9 w-9
                   items-center justify-center
                   rounded-lg text-slate-400
                   transition
                   hover:bg-slate-800
                   hover:text-white
                   lg:hidden"
        >

            <svg
                class="h-5 w-5"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M6 6l12 12M18 6 6 18"
                />
            </svg>

        </button>

    </div>


    {{-- ========================================================= --}}
    {{-- NAVIGATION --}}
    {{-- ========================================================= --}}

    <nav
        class="flex min-h-0 flex-1
               flex-col overflow-y-auto
               overflow-x-hidden
               px-3 py-5"
    >

        @foreach ($navigation as $groupLabel => $items)

            @php

                $visible = collect($items)
                    ->filter(
                        fn ($item) =>
                            \Illuminate\Support\Facades\Route::has($item['route'])
                            && auth()->check()
                            && auth()->user()->hasPermission($item['permission'])
                    );

            @endphp


            @if ($visible->isNotEmpty())

                {{-- GROUP LABEL --}}

                <p
                    x-cloak
                    x-show="sidebarExpanded || mobileSidebarOpen"
                    @class([
                        'mb-3 px-3 text-[10px] font-bold uppercase tracking-[0.22em] text-slate-500',
                        'mt-7' => ! $loop->first,
                    ])
                >
                    {{ $groupLabel }}
                </p>


                <div class="space-y-1">

                    @foreach ($visible as $item)

                        @php
                            $isActive = request()->routeIs(
                                $item['active'] ?? $item['route']
                            );
                        @endphp

                        <a
                            href="{{ route($item['route']) }}"
                            title="{{ $item['label'] }}"
                            @click="mobileSidebarOpen = false"

                            @class([
                                'flex min-h-12 items-center gap-3 rounded-xl px-3 py-3 text-sm font-semibold transition-all duration-200',

                                'bg-blue-600 text-white shadow-lg shadow-blue-950/30'
                                    => $isActive,

                                'text-slate-400 hover:bg-slate-900 hover:text-white'
                                    => ! $isActive,
                            ])

                            :class="
                                !sidebarExpanded
                                    ? 'lg:justify-center'
                                    : ''
                            "
                        >

                            @include('components.icons.' . $item['icon'])

                            <span
                                x-cloak
                                x-show="sidebarExpanded || mobileSidebarOpen"
                                x-transition.opacity
                                class="whitespace-nowrap"
                            >
                                {{ $item['label'] }}
                            </span>

                        </a>

                    @endforeach

                </div>

            @endif

        @endforeach


        {{-- ========================================================= --}}
        {{-- SIGNED-IN ACCOUNT --}}
        {{-- ========================================================= --}}

        <div
            x-cloak
            x-show="sidebarExpanded || mobileSidebarOpen"
            class="mt-auto pt-8"
        >

            @auth

                <div
                    class="rounded-xl
                           border border-slate-800
                           bg-slate-900 p-4"
                >

                    <p
                        class="text-[10px] font-bold
                               uppercase tracking-[0.18em]
                               text-slate-500"
                    >
                        Signed In
                    </p>

                    <p
                        class="mt-2 truncate text-sm
                               font-semibold text-slate-200"
                    >
                        {{ auth()->user()->name }}
                    </p>

                    <p
                        class="mt-1 truncate text-xs
                               text-slate-500"
                    >
                        {{ auth()->user()->roleName() }}

                        @if (auth()->user()->office)
                            &middot; {{ auth()->user()->office }}
                        @endif
                    </p>


                    <div
                        class="mt-4 flex items-center gap-2"
                    >

                        <a
                            href="{{ route('account.edit') }}"
                            class="flex-1 rounded-lg
                                   border border-slate-700
                                   px-3 py-2
                                   text-center text-xs
                                   font-semibold
                                   text-slate-300
                                   transition
                                   hover:bg-slate-800
                                   hover:text-white"
                        >
                            Account
                        </a>

                        <form
                            method="POST"
                            action="{{ route('logout') }}"
                            class="flex-1"
                        >

                            @csrf

                            <button
                                type="submit"
                                class="w-full rounded-lg
                                       bg-slate-800 px-3 py-2
                                       text-xs font-semibold
                                       text-rose-300
                                       transition
                                       hover:bg-rose-900/40
                                       hover:text-rose-200"
                            >
                                Sign Out
                            </button>

                        </form>

                    </div>

                </div>

            @endauth


            <div
                class="mt-3 px-1"
            >

                <p
                    class="text-[10px]
                           text-slate-600"
                >
                    RFA Monitoring System &middot; Version 1.0
                </p>

            </div>

        </div>

    </nav>

</aside>
