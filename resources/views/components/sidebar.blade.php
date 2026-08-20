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

        {{-- MENU LABEL --}}

        <p
            x-cloak
            x-show="sidebarExpanded || mobileSidebarOpen"
            class="mb-3 px-3
                   text-[10px] font-bold
                   uppercase tracking-[0.22em]
                   text-slate-500"
        >
            Main Menu
        </p>


        <div class="space-y-1">


            {{-- ================================================= --}}
            {{-- DASHBOARD --}}
            {{-- ================================================= --}}

            <a
                href="{{ route('dashboard') }}"
                title="Dashboard"
                @click="mobileSidebarOpen = false"

                @class([
                    'flex min-h-12 items-center gap-3 rounded-xl px-3 py-3 text-sm font-semibold transition-all duration-200',

                    'bg-blue-600 text-white shadow-lg shadow-blue-950/30'
                        => request()->routeIs('dashboard'),

                    'text-slate-400 hover:bg-slate-900 hover:text-white'
                        => !request()->routeIs('dashboard'),
                ])

                :class="
                    !sidebarExpanded
                        ? 'lg:justify-center'
                        : ''
                "
            >

                <svg
                    class="h-5 w-5 shrink-0"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.8"
                >

                    <rect
                        x="3"
                        y="3"
                        width="7"
                        height="7"
                        rx="1"
                    />

                    <rect
                        x="14"
                        y="3"
                        width="7"
                        height="7"
                        rx="1"
                    />

                    <rect
                        x="3"
                        y="14"
                        width="7"
                        height="7"
                        rx="1"
                    />

                    <rect
                        x="14"
                        y="14"
                        width="7"
                        height="7"
                        rx="1"
                    />

                </svg>


                <span
                    x-cloak
                    x-show="sidebarExpanded || mobileSidebarOpen"
                    x-transition.opacity
                    class="whitespace-nowrap"
                >
                    Dashboard
                </span>

            </a>


            {{-- ================================================= --}}
            {{-- LISTING --}}
            {{-- ================================================= --}}

            <a
                href="{{ route('listing') }}"
                title="Listing"
                @click="mobileSidebarOpen = false"

                @class([
                    'flex min-h-12 items-center gap-3 rounded-xl px-3 py-3 text-sm font-semibold transition-all duration-200',

                    'bg-blue-600 text-white shadow-lg shadow-blue-950/30'
                        => request()->routeIs('listing'),

                    'text-slate-400 hover:bg-slate-900 hover:text-white'
                        => !request()->routeIs('listing'),
                ])

                :class="
                    !sidebarExpanded
                        ? 'lg:justify-center'
                        : ''
                "
            >

                <svg
                    class="h-5 w-5 shrink-0"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.8"
                >

                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M9 6h11M9 12h11M9 18h11"
                    />

                    <circle cx="4" cy="6" r="1" />
                    <circle cx="4" cy="12" r="1" />
                    <circle cx="4" cy="18" r="1" />

                </svg>


                <span
                    x-cloak
                    x-show="sidebarExpanded || mobileSidebarOpen"
                    x-transition.opacity
                    class="whitespace-nowrap"
                >
                    Listing
                </span>

            </a>


            {{-- ================================================= --}}
            {{-- REPORTS --}}
            {{-- ================================================= --}}

            <a
                href="{{ route('reports') }}"
                title="Reports"
                @click="mobileSidebarOpen = false"

                @class([
                    'flex min-h-12 items-center gap-3 rounded-xl px-3 py-3 text-sm font-semibold transition-all duration-200',

                    'bg-blue-600 text-white shadow-lg shadow-blue-950/30'
                        => request()->routeIs('reports'),

                    'text-slate-400 hover:bg-slate-900 hover:text-white'
                        => !request()->routeIs('reports'),
                ])

                :class="
                    !sidebarExpanded
                        ? 'lg:justify-center'
                        : ''
                "
            >

                <svg
                    class="h-5 w-5 shrink-0"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.8"
                >

                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M5 20V10M12 20V4M19 20v-7"
                    />

                </svg>


                <span
                    x-cloak
                    x-show="sidebarExpanded || mobileSidebarOpen"
                    x-transition.opacity
                    class="whitespace-nowrap"
                >
                    Reports
                </span>

            </a>


            {{-- ================================================= --}}
            {{-- PCT PROCESS --}}
            {{-- ================================================= --}}

            <a
                href="{{ route('pct-process') }}"
                title="PCT Process"
                @click="mobileSidebarOpen = false"

                @class([
                    'flex min-h-12 items-center gap-3 rounded-xl px-3 py-3 text-sm font-semibold transition-all duration-200',

                    'bg-blue-600 text-white shadow-lg shadow-blue-950/30'
                        => request()->routeIs('pct-process'),

                    'text-slate-400 hover:bg-slate-900 hover:text-white'
                        => !request()->routeIs('pct-process'),
                ])

                :class="
                    !sidebarExpanded
                        ? 'lg:justify-center'
                        : ''
                "
            >

                <svg
                    class="h-5 w-5 shrink-0"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.8"
                >

                    <circle
                        cx="12"
                        cy="12"
                        r="9"
                    />

                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M12 7v5l3 2"
                    />

                </svg>


                <span
                    x-cloak
                    x-show="sidebarExpanded || mobileSidebarOpen"
                    x-transition.opacity
                    class="whitespace-nowrap"
                >
                    PCT Process
                </span>

            </a>


            {{-- ================================================= --}}
            {{-- USERS --}}
            {{-- ================================================= --}}

            <a
                href="{{ route('users') }}"
                title="Users"
                @click="mobileSidebarOpen = false"

                @class([
                    'flex min-h-12 items-center gap-3 rounded-xl px-3 py-3 text-sm font-semibold transition-all duration-200',

                    'bg-blue-600 text-white shadow-lg shadow-blue-950/30'
                        => request()->routeIs('users'),

                    'text-slate-400 hover:bg-slate-900 hover:text-white'
                        => !request()->routeIs('users'),
                ])

                :class="
                    !sidebarExpanded
                        ? 'lg:justify-center'
                        : ''
                "
            >

                <svg
                    class="h-5 w-5 shrink-0"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.8"
                >

                    <circle
                        cx="9"
                        cy="8"
                        r="4"
                    />

                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M3 20c0-3.3 2.7-6 6-6s6 2.7 6 6"
                    />

                    <path
                        stroke-linecap="round"
                        d="M17 11a3 3 0 1 0 0-6"
                    />

                    <path
                        stroke-linecap="round"
                        d="M18 14c2.2.5 3 2.2 3 4"
                    />

                </svg>


                <span
                    x-cloak
                    x-show="sidebarExpanded || mobileSidebarOpen"
                    x-transition.opacity
                    class="whitespace-nowrap"
                >
                    Users
                </span>

            </a>


            {{-- ================================================= --}}
            {{-- ADMINISTRATION --}}
            {{-- ================================================= --}}

            <a
                href="{{ route('administration') }}"
                title="Administration"
                @click="mobileSidebarOpen = false"

                @class([
                    'flex min-h-12 items-center gap-3 rounded-xl px-3 py-3 text-sm font-semibold transition-all duration-200',

                    'bg-blue-600 text-white shadow-lg shadow-blue-950/30'
                        => request()->routeIs('administration'),

                    'text-slate-400 hover:bg-slate-900 hover:text-white'
                        => !request()->routeIs('administration'),
                ])

                :class="
                    !sidebarExpanded
                        ? 'lg:justify-center'
                        : ''
                "
            >

                <svg
                    class="h-5 w-5 shrink-0"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.8"
                >

                    <circle
                        cx="12"
                        cy="12"
                        r="3"
                    />

                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="
                            M19.4 15
                            a1.7 1.7 0 0 0 .34 1.88
                            l.06.06
                            a2 2 0 1 1-2.83 2.83
                            l-.06-.06
                            A1.7 1.7 0 0 0 15 19.4
                            a1.7 1.7 0 0 0-1 .6
                            V20
                            a2 2 0 1 1-4 0
                            v-.09
                            a1.7 1.7 0 0 0-1-.51
                            1.7 1.7 0 0 0-1.88.34
                            l-.06.06
                            a2 2 0 1 1-2.83-2.83
                            l.06-.06
                            A1.7 1.7 0 0 0 4.6 15
                            a1.7 1.7 0 0 0-.6-1
                            H4
                            a2 2 0 1 1 0-4
                            h.09
                            a1.7 1.7 0 0 0 .51-1
                            1.7 1.7 0 0 0-.34-1.88
                            l-.06-.06
                            a2 2 0 1 1 2.83-2.83
                            l.06.06
                            A1.7 1.7 0 0 0 9 4.6
                            a1.7 1.7 0 0 0 1-.6
                            V4
                            a2 2 0 1 1 4 0
                            v.09
                            a1.7 1.7 0 0 0 1 .51
                            1.7 1.7 0 0 0 1.88-.34
                            l.06-.06
                            a2 2 0 1 1 2.83 2.83
                            l-.06.06
                            A1.7 1.7 0 0 0 19.4 9
                            c.14.36.34.7.6 1
                            H20
                            a2 2 0 1 1 0 4
                            h-.09
                            c-.26.3-.46.64-.51 1
                        "
                    />

                </svg>


                <span
                    x-cloak
                    x-show="sidebarExpanded || mobileSidebarOpen"
                    x-transition.opacity
                    class="whitespace-nowrap"
                >
                    Administration
                </span>

            </a>

        </div>


        {{-- ========================================================= --}}
        {{-- FOOTER --}}
        {{-- ========================================================= --}}

        <div
            x-cloak
            x-show="sidebarExpanded || mobileSidebarOpen"
            class="mt-auto pt-8"
        >

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
                    System
                </p>

                <p
                    class="mt-2 text-sm
                           font-semibold text-slate-300"
                >
                    RFA Monitoring System
                </p>

                <p
                    class="mt-1 text-xs
                           text-slate-500"
                >
                    Version 1.0 Development
                </p>

            </div>

        </div>

    </nav>

</aside>