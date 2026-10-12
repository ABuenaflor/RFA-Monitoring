{{--
|--------------------------------------------------------------------------
| Notification Bell
|--------------------------------------------------------------------------
|
| The signed-in user's own alerts. Polls notifications.feed every minute
| (see notificationBell in resources/js/app.js) and, once the user allows
| it, shows a desktop notification for each new nearing / due today /
| beyond PCT alert while the system is open in a browser tab.
|
--}}

@php
    $initialUnread = auth()->user()->appNotifications()->unread()->count();
@endphp

<div
    x-data="notificationBell({
        feedUrl: @js(route('notifications.feed')),
        userId: @js(auth()->id()),
        unread: @js($initialUnread),
        iconUrl: @js(asset('images/sidenavlogo.png')),
    })"
    @click.outside="open = false"
    @keydown.escape.window="open = false"
    class="relative"
>

    <button
        type="button"
        @click="toggle()"
        :aria-expanded="open.toString()"
        aria-label="Notifications"
        title="Notifications"
        class="relative flex h-11 w-11 items-center justify-center
               rounded-xl border border-slate-200 bg-white
               text-slate-600 shadow-sm transition
               hover:bg-slate-50 hover:text-slate-950"
    >

        <svg
            class="h-5 w-5"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="1.8"
            aria-hidden="true"
        >
            <path
                stroke-linecap="round"
                stroke-linejoin="round"
                d="M6 9a6 6 0 1 1 12 0c0 4.5 2 6 2 6H4s2-1.5 2-6ZM10 19a2 2 0 0 0 4 0"
            />
        </svg>

        <span
            x-cloak
            x-show="unread > 0"
            x-text="unread > 99 ? '99+' : unread"
            class="absolute -right-1.5 -top-1.5 min-w-5 rounded-full
                   bg-rose-600 px-1.5 py-0.5 text-center
                   text-[10px] font-bold leading-none text-white
                   ring-2 ring-white"
        >{{ $initialUnread > 99 ? '99+' : $initialUnread }}</span>

    </button>


    <div
        x-cloak
        x-show="open"
        x-transition.opacity
        class="absolute right-0 z-40 mt-2 w-[22rem] max-w-[calc(100vw-2rem)]
               overflow-hidden rounded-2xl border border-slate-200
               bg-white shadow-xl"
    >

        {{-- PCT levels --}}

        <div class="border-b border-slate-100 px-4 py-3">

            <p class="text-xs font-bold uppercase tracking-[0.15em] text-blue-600">
                PCT Alerts
            </p>

            <div class="mt-2 grid grid-cols-3 gap-2 text-center">

                <div class="rounded-lg bg-blue-50 px-2 py-1.5">
                    <p class="text-lg font-bold text-blue-800" x-text="pct.nearing">0</p>
                    <p class="text-[10px] font-semibold uppercase text-blue-700">Nearing</p>
                </div>

                <div class="rounded-lg bg-amber-50 px-2 py-1.5">
                    <p class="text-lg font-bold text-amber-800" x-text="pct.due">0</p>
                    <p class="text-[10px] font-semibold uppercase text-amber-700">Due Today</p>
                </div>

                <div class="rounded-lg bg-rose-50 px-2 py-1.5">
                    <p class="text-lg font-bold text-rose-800" x-text="pct.beyond">0</p>
                    <p class="text-[10px] font-semibold uppercase text-rose-700">Beyond</p>
                </div>

            </div>

            {{-- Desktop notifications --}}

            <div class="mt-3 text-xs">

                <template x-if="desktop === 'default'">
                    <button
                        type="button"
                        @click="enableDesktop()"
                        class="w-full rounded-lg bg-slate-950 px-3 py-2 font-semibold text-white transition hover:bg-slate-800"
                    >
                        Enable desktop notifications
                    </button>
                </template>

                <p x-show="desktop === 'granted'" class="text-emerald-700">
                    Desktop notifications are on for this browser.
                </p>

                <p x-show="desktop === 'denied'" class="text-slate-500">
                    Desktop notifications are blocked. Allow them for this site in the browser's settings.
                </p>

                <p x-show="desktop === 'unsupported'" class="text-slate-500">
                    This browser cannot show desktop notifications here (they need HTTPS or localhost).
                </p>

            </div>

        </div>


        {{-- Items --}}

        <ul class="max-h-96 divide-y divide-slate-100 overflow-y-auto">

            <template x-for="item in items" :key="item.key">

                <li>
                    <a
                        :href="item.url"
                        class="flex gap-3 px-4 py-3 transition hover:bg-slate-50"
                        :class="item.read ? 'opacity-70' : ''"
                    >
                        <span
                            class="mt-1.5 h-2.5 w-2.5 shrink-0 rounded-full"
                            :class="{
                                'bg-rose-500': item.severity === 'critical',
                                'bg-amber-500': item.severity === 'warning',
                                'bg-blue-500': item.severity === 'info',
                            }"
                        ></span>

                        <span class="min-w-0 flex-1">
                            <span
                                class="block text-sm text-slate-900"
                                :class="item.read ? 'font-medium' : 'font-semibold'"
                                x-text="item.title"
                            ></span>
                            <span class="mt-0.5 block text-xs text-slate-500" x-text="item.message"></span>
                            <span class="mt-0.5 block text-[11px] text-slate-400" x-text="item.when"></span>
                        </span>
                    </a>
                </li>

            </template>

            <li x-show="loaded && items.length === 0" class="px-4 py-10 text-center text-sm text-slate-500">
                No notifications. You're all caught up.
            </li>

            <li x-show="! loaded" class="px-4 py-10 text-center text-sm text-slate-400">
                Loading…
            </li>

        </ul>


        @can('notifications.view')

            <a
                href="{{ route('operations.index') }}"
                class="block border-t border-slate-100 px-4 py-3 text-center text-sm font-semibold text-blue-600 transition hover:bg-slate-50"
            >
                View all notifications
            </a>

        @endcan

    </div>

</div>
