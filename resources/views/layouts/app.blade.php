<!DOCTYPE html>

<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <meta
        name="description"
        content="RFA Monitoring System"
    >

    <title>
        @yield('title', 'RFA Monitoring System')
    </title>

    @vite([
        'resources/css/app.css',
        'resources/js/app.js'
    ])

</head>


<body
    class="min-h-screen
           overflow-x-hidden
           bg-slate-50
           text-slate-900
           antialiased"
>

<div
    x-data="{
        mobileSidebarOpen: false,

        sidebarExpanded:
            localStorage.getItem('rfa-sidebar-expanded') !== 'false',

        toggleDesktopSidebar() {
            this.sidebarExpanded = !this.sidebarExpanded;

            localStorage.setItem(
                'rfa-sidebar-expanded',
                this.sidebarExpanded
            );
        }
    }"

    @keydown.escape.window="
        mobileSidebarOpen = false
    "

    class="min-h-screen"
>

    {{-- ========================================================= --}}
    {{-- MOBILE SIDEBAR BACKDROP --}}
    {{-- ========================================================= --}}

    <div
        x-cloak
        x-show="mobileSidebarOpen"
        x-transition.opacity

        @click="mobileSidebarOpen = false"

        class="fixed inset-0 z-40
               bg-slate-950/60
               backdrop-blur-sm
               lg:hidden"
    ></div>


    {{-- ========================================================= --}}
    {{-- SIDEBAR --}}
    {{-- ========================================================= --}}

    <x-sidebar />


    {{-- ========================================================= --}}
    {{-- MAIN APPLICATION AREA --}}
    {{-- ========================================================= --}}

    <div
        class="min-h-screen
               transition-[padding] duration-300
               ease-in-out"

        :class="
            sidebarExpanded
                ? 'lg:pl-72'
                : 'lg:pl-20'
        "
    >

        {{-- ===================================================== --}}
        {{-- TOPBAR --}}
        {{-- ===================================================== --}}

        <header
            class="sticky top-0 z-30
                   border-b border-slate-200
                   bg-white/95
                   backdrop-blur"
        >

            <div
                class="flex min-h-20
                       items-center
                       justify-between
                       gap-4
                       px-5
                       sm:px-6
                       lg:px-8"
            >

                {{-- LEFT SIDE --}}

                <div
                    class="flex min-w-0
                           items-center gap-3"
                >

                    {{-- MOBILE SIDEBAR BUTTON --}}

                    <button
                        type="button"

                        @click="
                            mobileSidebarOpen = true
                        "

                        class="flex h-10 w-10
                               shrink-0
                               items-center
                               justify-center
                               rounded-xl
                               border border-slate-200
                               bg-white
                               text-slate-600
                               shadow-sm
                               transition
                               hover:bg-slate-50
                               hover:text-slate-950
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
                                d="M4 7h16M4 12h16M4 17h16"
                            />

                        </svg>

                    </button>


                    {{-- DESKTOP COLLAPSE BUTTON --}}

                    <button
                        type="button"

                        @click="
                            toggleDesktopSidebar()
                        "

                        class="hidden h-10 w-10
                               shrink-0
                               items-center
                               justify-center
                               rounded-xl
                               border border-slate-200
                               bg-white
                               text-slate-600
                               shadow-sm
                               transition
                               hover:bg-slate-50
                               hover:text-slate-950
                               lg:flex"
                    >

                        <svg
                            x-show="sidebarExpanded"
                            class="h-5 w-5"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="m15 18-6-6 6-6"
                            />
                        </svg>


                        <svg
                            x-cloak
                            x-show="!sidebarExpanded"
                            class="h-5 w-5"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="m9 18 6-6-6-6"
                            />
                        </svg>

                    </button>


                    {{-- PAGE TITLE --}}

                    <div class="min-w-0">

                        <p
                            class="text-[10px]
                                   font-bold uppercase
                                   tracking-[0.18em]
                                   text-blue-600
                                   sm:text-xs"
                        >
                            RFA Monitoring System
                        </p>

                        <h1
                            class="truncate
                                   text-xl font-bold
                                   tracking-tight
                                   text-slate-950
                                   sm:text-2xl"
                        >
                            @yield(
                                'page_heading',
                                'Dashboard'
                            )
                        </h1>

                    </div>

                </div>


                {{-- RIGHT SIDE --}}

                <div
                    class="flex shrink-0
                           items-center gap-3"
                >

                    @yield('page_actions')

                </div>

            </div>

        </header>


        {{-- ===================================================== --}}
        {{-- PAGE CONTENT --}}
        {{-- ===================================================== --}}

        <main
            class="min-h-[calc(100vh-5rem)]
                   px-5 py-7
                   sm:px-6
                   lg:px-8
                   lg:py-8"
        >

            @yield('content')

        </main>

    </div>

</div>

</body>

</html>