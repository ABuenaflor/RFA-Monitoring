@extends('layouts.app')


@section(
    'title',
    'My Conference Schedule | RFA Monitoring System'
)


@section(
    'page_heading',
    'My Conference Schedule'
)


@section('content')

@php
    $columns = [
        5 => 'xl:grid-cols-5',
        6 => 'xl:grid-cols-6',
        7 => 'xl:grid-cols-7',
    ][max(5, $days->count())];

    $canOpenCase = auth()->user()->can('rfa.view');
@endphp

<div class="space-y-7">

    {{-- ========================================================= --}}
    {{-- WEEK HEADER --}}
    {{-- ========================================================= --}}

    <section class="flex flex-col justify-between gap-4 lg:flex-row lg:items-end">

        <div>

            <p class="text-xs font-bold uppercase tracking-[0.15em] text-blue-600">
                {{ $isCurrentWeek ? 'This Week' : 'Week of' }}
            </p>

            <h2 class="mt-1 text-2xl font-bold text-slate-950">
                {{ $weekStart->format('M d') }} – {{ $weekEnd->format('M d, Y') }}
            </h2>

            <p class="mt-1 max-w-3xl text-sm leading-6 text-slate-500">
                Your own 1st and 2nd conferences only — no one else can see
                this schedule. Cases are matched to you when you are the
                assigned SEADO account, or when an imported case's SEADO name
                is exactly <span class="font-semibold text-slate-700">{{ auth()->user()->name }}</span>.
            </p>

        </div>

        <div class="flex flex-wrap items-center gap-2">

            <a
                href="{{ route('schedule.index', ['week' => $previousWeek]) }}"
                class="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50"
            >
                ← Previous
            </a>

            @unless ($isCurrentWeek)
                <a
                    href="{{ route('schedule.index') }}"
                    class="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50"
                >
                    This Week
                </a>
            @endunless

            <a
                href="{{ route('schedule.index', ['week' => $nextWeek]) }}"
                class="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50"
            >
                Next →
            </a>

            <form method="GET" action="{{ route('schedule.index') }}">
                <label for="week" class="sr-only">Go to week</label>
                <input
                    id="week"
                    type="date"
                    name="week"
                    value="{{ $weekStart->toDateString() }}"
                    onchange="this.form.submit()"
                    class="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm"
                >
            </form>

        </div>

    </section>


    {{-- ========================================================= --}}
    {{-- SUMMARY --}}
    {{-- ========================================================= --}}

    <section class="grid grid-cols-1 gap-4 sm:grid-cols-3">

        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-wider text-slate-400">Conferences This Week</p>
            <p class="mt-3 text-3xl font-bold text-slate-950">{{ number_format($total) }}</p>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-wider text-slate-400">Cases Handled</p>
            <p class="mt-3 text-3xl font-bold text-slate-950">{{ number_format($caseCount) }}</p>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-wider text-slate-400">Next Conference After This Week</p>

            @if ($upcoming)
                <a
                    href="{{ route('schedule.index', ['week' => $upcoming['date']->toDateString()]) }}"
                    class="mt-3 block text-lg font-bold text-blue-700 hover:underline"
                >
                    {{ $upcoming['date']->format('D, M d, Y') }}
                </a>
                <p class="mt-1 truncate text-xs text-slate-500">
                    {{ $upcoming['label'] }} · {{ $upcoming['rfa']->displayReference() }}
                </p>
            @else
                <p class="mt-3 text-lg font-bold text-slate-400">None scheduled</p>
            @endif
        </div>

    </section>


    {{-- ========================================================= --}}
    {{-- WEEK GRID --}}
    {{-- ========================================================= --}}

    <section class="grid grid-cols-1 gap-4 md:grid-cols-2 {{ $columns }}">

        @foreach ($days as $day)

            @php
                $isToday = $day['date']->isToday();
            @endphp

            <div
                @class([
                    'flex min-h-48 flex-col rounded-2xl border bg-white shadow-sm',
                    'border-blue-300 ring-2 ring-blue-100' => $isToday,
                    'border-slate-200' => ! $isToday,
                ])
            >

                <div
                    @class([
                        'rounded-t-2xl border-b px-4 py-3',
                        'border-blue-100 bg-blue-50' => $isToday,
                        'border-slate-100 bg-slate-50' => ! $isToday,
                    ])
                >

                    <p
                        @class([
                            'text-xs font-bold uppercase tracking-wider',
                            'text-blue-700' => $isToday,
                            'text-slate-500' => ! $isToday,
                        ])
                    >
                        {{ $day['date']->format('l') }}
                        @if ($isToday) · Today @endif
                    </p>

                    <p class="mt-0.5 text-lg font-bold text-slate-950">
                        {{ $day['date']->format('M d') }}
                    </p>

                    @if ($day['holiday'])
                        <p class="mt-1 inline-flex rounded-full bg-rose-50 px-2 py-0.5 text-[11px] font-semibold text-rose-700">
                            {{ $day['holiday'] }}
                        </p>
                    @endif

                </div>

                <div class="flex-1 space-y-3 p-3">

                    @forelse ($day['entries'] as $entry)

                        @php
                            $rfa = $entry['rfa'];
                        @endphp

                        <article class="rounded-xl border border-slate-200 p-3 text-sm">

                            <div class="flex items-start justify-between gap-2">

                                <span
                                    @class([
                                        'rounded-full px-2 py-0.5 text-[11px] font-bold',
                                        'bg-indigo-50 text-indigo-700' => $entry['label'] === '1st Conference',
                                        'bg-amber-50 text-amber-700' => $entry['label'] !== '1st Conference',
                                    ])
                                >
                                    {{ $entry['label'] }}
                                </span>

                                @if ($rfa->isDisposed())
                                    <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-bold text-emerald-700">
                                        Disposed
                                    </span>
                                @endif

                            </div>

                            <p class="mt-2 break-all text-xs font-bold text-slate-900">
                                {{ $rfa->displayReference() }}
                            </p>

                            <dl class="mt-2 space-y-2 text-xs">

                                <div>
                                    <dt class="font-semibold uppercase tracking-wide text-slate-400">Requesting Party</dt>
                                    <dd class="mt-0.5 font-medium text-slate-800">{{ $rfa->requesting_party ?: '—' }}</dd>
                                </div>

                                <div>
                                    <dt class="font-semibold uppercase tracking-wide text-slate-400">Responding Party</dt>
                                    <dd class="mt-0.5 font-medium text-slate-800">{{ $rfa->responding_party ?: '—' }}</dd>
                                    @if ($rfa->company_address)
                                        <dd class="mt-0.5 text-slate-500">{{ $rfa->company_address }}</dd>
                                    @endif
                                </div>

                                @if ($rfa->contact_no)
                                    <div>
                                        <dt class="font-semibold uppercase tracking-wide text-slate-400">Contact No.</dt>
                                        <dd class="mt-0.5 text-slate-700">{{ $rfa->contact_no }}</dd>
                                    </div>
                                @endif

                                @if ($rfa->issues)
                                    <div>
                                        <dt class="font-semibold uppercase tracking-wide text-slate-400">Issues</dt>
                                        <dd class="mt-0.5 text-slate-700">{{ str_replace('|', ', ', $rfa->issues) }}</dd>
                                    </div>
                                @endif

                                <div class="flex flex-wrap gap-x-3 gap-y-1 text-slate-500">
                                    @if ($rfa->workers_involved !== null)
                                        <span>{{ $rfa->workers_involved }} {{ str('worker')->plural($rfa->workers_involved) }}</span>
                                    @endif
                                    @if ($rfa->office)
                                        <span>{{ $rfa->office }}</span>
                                    @endif
                                    @if ($rfa->mode_of_filing)
                                        <span>{{ \Illuminate\Support\Str::headline($rfa->mode_of_filing) }}</span>
                                    @endif
                                </div>

                            </dl>

                            @if ($canOpenCase)
                                <a
                                    href="{{ route('rfas.show', $rfa) }}"
                                    class="mt-3 inline-flex text-xs font-semibold text-blue-600 hover:text-blue-700"
                                >
                                    Open case →
                                </a>
                            @endif

                        </article>

                    @empty

                        <p class="px-1 py-6 text-center text-xs text-slate-400">
                            No conferences
                        </p>

                    @endforelse

                </div>

            </div>

        @endforeach

    </section>

</div>

@endsection
