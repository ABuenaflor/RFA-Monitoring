@extends('layouts.app')


@section(
    'title',
    'Notifications & Audit | RFA Monitoring System'
)


@section(
    'page_heading',
    'Notifications & Audit'
)


@section('page_actions')

    <div class="flex items-center gap-2">

        <form
            method="POST"
            action="{{ route('operations.scan') }}"
        >

            @csrf

            <button
                type="submit"
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
                Run PCT Scan
            </button>

        </form>


        <form
            method="POST"
            action="{{ route('operations.notifications.read-all') }}"
        >

            @csrf

            <button
                type="submit"
                class="inline-flex items-center
                       justify-center
                       rounded-xl
                       bg-slate-950 px-4 py-2.5
                       text-sm font-semibold
                       text-white shadow-sm
                       transition
                       hover:bg-slate-800"
            >
                Mark All Read
            </button>

        </form>

    </div>

@endsection


@section('content')

<div class="space-y-7">

    <section>

        <p
            class="max-w-4xl text-sm
                   leading-6 text-slate-500"
        >
            Actionable PCT and workflow notifications for your account, plus an
            auditable trail of important user and data changes. Notifications
            are addressed to a person: you only ever see your own.
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
            ['Unread', 'unread', 'border-blue-200 bg-blue-50 text-blue-950'],
            ['Due Today', 'due_today', 'border-amber-200 bg-amber-50 text-amber-950'],
            ['Overdue', 'overdue', 'border-rose-200 bg-rose-50 text-rose-950'],
            ['Assignments', 'assignments', 'border-violet-200 bg-violet-50 text-violet-950'],
        ] as $card)

            <div
                class="rounded-2xl border p-5
                       shadow-sm {{ $card[2] }}"
            >

                <p
                    class="text-xs font-bold
                           uppercase tracking-wider"
                >
                    {{ $card[0] }}
                </p>

                <p class="mt-3 text-3xl font-bold">
                    {{ number_format($summary[$card[1]]) }}
                </p>

            </div>

        @endforeach

    </section>


    {{-- ========================================================= --}}
    {{-- NOTIFICATIONS + RULES --}}
    {{-- ========================================================= --}}

    <section
        class="grid grid-cols-1 gap-5
               xl:grid-cols-2"
    >

        <div
            class="overflow-hidden
                   rounded-2xl
                   border border-slate-200
                   bg-white shadow-sm"
        >

            <div
                class="flex flex-wrap items-center
                       justify-between gap-3
                       border-b border-slate-100
                       px-6 py-5"
            >

                <div>

                    <p
                        class="text-xs font-bold
                               uppercase tracking-[0.15em]
                               text-blue-600"
                    >
                        Notification Center
                    </p>

                    <h2
                        class="mt-1 text-lg
                               font-bold text-slate-950"
                    >
                        Actionable Alerts
                    </h2>

                </div>


                <a
                    href="{{ route('operations.index', $filters['unread'] ? [] : ['unread' => 1]) }}"
                    class="rounded-lg
                           border border-slate-300
                           bg-white px-3 py-1.5
                           text-xs font-semibold
                           text-slate-700
                           transition
                           hover:bg-slate-50"
                >
                    {{ $filters['unread'] ? 'Show All' : 'Unread Only' }}
                </a>

            </div>


            <div class="divide-y divide-slate-100">

                @forelse ($notifications as $notification)

                    @php
                        $dot = match ($notification->accent()) {
                            'rose' => 'bg-rose-500',
                            'amber' => 'bg-amber-500',
                            default => 'bg-blue-500',
                        };
                    @endphp

                    <article
                        @class([
                            'flex gap-4 px-6 py-5',
                            'bg-slate-50/60' => $notification->isRead(),
                        ])
                    >

                        <div
                            class="mt-1.5 h-3 w-3
                                   shrink-0 rounded-full
                                   {{ $dot }}
                                   {{ $notification->isRead() ? 'opacity-30' : '' }}"
                        ></div>


                        <div class="min-w-0 flex-1">

                            <p
                                @class([
                                    'font-semibold',
                                    'text-slate-900' => ! $notification->isRead(),
                                    'text-slate-500' => $notification->isRead(),
                                ])
                            >
                                {{ $notification->title }}
                            </p>

                            @if ($notification->message)

                                <p
                                    class="mt-1 text-sm
                                           leading-6 text-slate-500"
                                >
                                    {{ $notification->message }}
                                </p>

                            @endif


                            <div
                                class="mt-2 flex flex-wrap
                                       items-center gap-x-3 gap-y-1"
                            >

                                <span class="text-xs text-slate-400">
                                    {{ $notification->categoryLabel() }}
                                    &middot;
                                    {{ $notification->created_at?->diffForHumans() }}
                                </span>


                                @if ($notification->action_url)

                                    <a
                                        href="{{ $notification->action_url }}"
                                        class="text-xs font-semibold
                                               text-blue-600
                                               transition
                                               hover:text-blue-700"
                                    >
                                        Open case
                                    </a>

                                @endif


                                @if (! $notification->isRead())

                                    <form
                                        method="POST"
                                        action="{{ route('operations.notifications.read', $notification) }}"
                                    >

                                        @csrf

                                        <button
                                            type="submit"
                                            class="text-xs font-semibold
                                                   text-slate-500
                                                   transition
                                                   hover:text-slate-800"
                                        >
                                            Mark read
                                        </button>

                                    </form>

                                @endif


                                <form
                                    method="POST"
                                    action="{{ route('operations.notifications.destroy', $notification) }}"
                                >

                                    @csrf
                                    @method('DELETE')

                                    <button
                                        type="submit"
                                        class="text-xs font-semibold
                                               text-slate-400
                                               transition
                                               hover:text-rose-600"
                                    >
                                        Dismiss
                                    </button>

                                </form>

                            </div>

                        </div>

                    </article>

                @empty

                    <div
                        class="px-6 py-14
                               text-center text-sm
                               text-slate-500"
                    >
                        No notifications. Run a PCT scan to check active cases
                        against their deadlines.
                    </div>

                @endforelse

            </div>

        </div>


        {{-- ALERT RULES --}}

        <div
            class="rounded-2xl
                   border border-slate-200
                   bg-white p-6 shadow-sm"
        >

            <p
                class="text-xs font-bold
                       uppercase tracking-[0.15em]
                       text-violet-600"
            >
                Alert Rules
            </p>

            <h2
                class="mt-1 text-lg
                       font-bold text-slate-950"
            >
                Operational Triggers
            </h2>


            <div class="mt-5 space-y-3 text-sm">

                <div
                    class="rounded-xl bg-amber-50
                           p-4 text-amber-800"
                >
                    <strong>PCT due today:</strong>
                    an active PCT checkpoint that has reached its deadline day
                    (e.g. day 3 of a 3-day rule, day 30 of 1st Conference - Date
                    Disposed).
                </div>


                <div
                    class="rounded-xl bg-rose-50
                           p-4 text-rose-800"
                >
                    <strong>PCT breached:</strong>
                    an active checkpoint past day 3, or an undisposed case past
                    day 30. Raised to critical, which reopens an alert that was
                    previously marked read.
                </div>


                <div
                    class="rounded-xl bg-violet-50
                           p-4 text-violet-800"
                >
                    <strong>Assignment:</strong>
                    sent to an officer the moment a case is assigned to their
                    account.
                </div>


                <div
                    class="rounded-xl bg-blue-50
                           p-4 text-blue-800"
                >
                    <strong>Unassigned backlog:</strong>
                    cases in breach with no account assigned are counted into a
                    single alert for everyone who can assign work, rather than
                    one alert per case.
                </div>

            </div>


            <div
                class="mt-5 rounded-xl
                       border border-slate-200
                       bg-slate-50 px-4 py-3
                       text-xs leading-5
                       text-slate-500"
            >
                The scan is idempotent: alerts whose condition has cleared are
                removed, and re-running it never duplicates a queue. Schedule it
                with <span class="font-mono">php artisan schedule:work</span>,
                which runs it daily at 07:00.
            </div>

        </div>

    </section>


    {{-- ========================================================= --}}
    {{-- AUDIT TRAIL --}}
    {{-- ========================================================= --}}

    @if ($canViewAudit)

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
                           text-slate-500"
                >
                    Audit Trail
                </p>

                <h2
                    class="mt-1 text-lg
                           font-bold text-slate-950"
                >
                    Recent System Events
                </h2>

            </div>


            {{-- FILTERS --}}

            <div
                class="border-b border-slate-100
                       bg-slate-50/60 px-6 py-5"
            >

                <form
                    method="GET"
                    action="{{ route('operations.index') }}"
                    class="grid grid-cols-1 gap-4
                           md:grid-cols-5"
                >

                    <div>

                        <label
                            for="event"
                            class="mb-2 block text-xs
                                   font-bold uppercase
                                   text-slate-500"
                        >
                            Event
                        </label>

                        <select
                            id="event"
                            name="event"
                            class="w-full rounded-xl
                                   border border-slate-300
                                   bg-white px-3 py-2.5 text-sm"
                        >

                            <option value="">All events</option>

                            @foreach ($auditEvents as $key => $label)

                                <option
                                    value="{{ $key }}"
                                    @selected($filters['event'] === $key)
                                >
                                    {{ $label }}
                                </option>

                            @endforeach

                        </select>

                    </div>


                    <div>

                        <label
                            for="actor"
                            class="mb-2 block text-xs
                                   font-bold uppercase
                                   text-slate-500"
                        >
                            User
                        </label>

                        <select
                            id="actor"
                            name="actor"
                            class="w-full rounded-xl
                                   border border-slate-300
                                   bg-white px-3 py-2.5 text-sm"
                        >

                            <option value="">All users</option>

                            @foreach ($actors as $actor)

                                <option
                                    value="{{ $actor->id }}"
                                    @selected($filters['actor'] === (string) $actor->id)
                                >
                                    {{ $actor->name }}
                                </option>

                            @endforeach

                        </select>

                    </div>


                    <div>

                        <label
                            for="from"
                            class="mb-2 block text-xs
                                   font-bold uppercase
                                   text-slate-500"
                        >
                            From
                        </label>

                        <input
                            id="from"
                            name="from"
                            type="date"
                            value="{{ $filters['from'] }}"
                            class="w-full rounded-xl
                                   border border-slate-300
                                   bg-white px-3 py-2.5 text-sm"
                        >

                    </div>


                    <div>

                        <label
                            for="to"
                            class="mb-2 block text-xs
                                   font-bold uppercase
                                   text-slate-500"
                        >
                            To
                        </label>

                        <input
                            id="to"
                            name="to"
                            type="date"
                            value="{{ $filters['to'] }}"
                            class="w-full rounded-xl
                                   border border-slate-300
                                   bg-white px-3 py-2.5 text-sm"
                        >

                    </div>


                    <div class="flex items-end gap-2">

                        <button
                            type="submit"
                            class="rounded-xl bg-slate-950
                                   px-4 py-2.5
                                   text-sm font-semibold
                                   text-white shadow-sm
                                   transition
                                   hover:bg-slate-800"
                        >
                            Filter
                        </button>

                        <a
                            href="{{ route('operations.index') }}"
                            class="rounded-xl
                                   border border-slate-300
                                   bg-white px-4 py-2.5
                                   text-sm font-semibold
                                   text-slate-700
                                   transition
                                   hover:bg-slate-50"
                        >
                            Reset
                        </a>

                    </div>

                </form>

            </div>


            <div class="overflow-x-auto">

                <table
                    class="w-full min-w-[1150px]
                           divide-y divide-slate-200"
                >

                    <thead class="bg-slate-50">

                        <tr>

                            @foreach ([
                                'Timestamp',
                                'User',
                                'Event',
                                'Record',
                                'Change Summary',
                                'Source / IP',
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

                        @forelse ($events as $entry)

                            @php
                                $badge = match ($entry->accent()) {
                                    'emerald' => 'bg-emerald-50 text-emerald-700',
                                    'rose' => 'bg-rose-50 text-rose-700',
                                    'amber' => 'bg-amber-50 text-amber-700',
                                    'violet' => 'bg-violet-50 text-violet-700',
                                    'blue' => 'bg-blue-50 text-blue-700',
                                    default => 'bg-slate-100 text-slate-700',
                                };
                            @endphp

                            <tr class="transition hover:bg-slate-50/80">

                                <td
                                    class="whitespace-nowrap px-4 py-4
                                           text-sm text-slate-500"
                                >
                                    {{ $entry->created_at?->format('M d, Y g:i A') }}
                                </td>


                                <td
                                    class="px-4 py-4 font-semibold
                                           text-slate-900"
                                >
                                    {{ $entry->actorName() }}
                                </td>


                                <td class="px-4 py-4">

                                    <span
                                        class="rounded-full px-3 py-1
                                               text-xs font-semibold
                                               {{ $badge }}"
                                    >
                                        {{ $entry->eventLabel() }}
                                    </span>

                                </td>


                                <td
                                    class="px-4 py-4 text-sm
                                           text-slate-700"
                                >
                                    {{ $entry->subjectLabel() }}
                                </td>


                                <td
                                    class="px-4 py-4 text-sm
                                           text-slate-600"
                                >
                                    {{ $entry->change_summary ?? '—' }}
                                </td>


                                <td
                                    class="px-4 py-4 text-xs
                                           text-slate-500"
                                >
                                    {{ $entry->ip_address ?? '—' }}
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
                                    No audit events match the selected filters.
                                </td>

                            </tr>

                        @endforelse

                    </tbody>

                </table>

            </div>


            @if ($events && $events->hasPages())

                <div
                    class="border-t border-slate-100
                           px-6 py-4"
                >
                    {{ $events->links() }}
                </div>

            @endif

        </section>

    @else

        <section
            class="rounded-2xl
                   border border-slate-200
                   bg-white p-6 shadow-sm"
        >

            <p
                class="text-xs font-bold
                       uppercase tracking-[0.15em]
                       text-slate-500"
            >
                Audit Trail
            </p>

            <p
                class="mt-3 text-sm leading-6
                       text-slate-500"
            >
                Your role does not include permission to view the system audit
                trail.
            </p>

        </section>

    @endif

</div>

@endsection
