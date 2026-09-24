@extends('layouts.app')


@section(
    'title',
    'Process Cycle Time | RFA Monitoring System'
)


@section(
    'page_heading',
    'Process Cycle Time'
)


@section('content')

@php
    /*
    | Colour per rating. Written out in full so Tailwind can see every class.
    */

    $tones = [
        'within' => [
            'bar' => 'bg-emerald-500',
            'chip' => 'bg-emerald-100 text-emerald-800',
            'label' => 'Within PCT',
        ],
        'nearing' => [
            'bar' => 'bg-amber-400',
            'chip' => 'bg-amber-100 text-amber-800',
            'label' => 'Nearing PCT',
        ],
        'on' => [
            'bar' => 'bg-orange-500',
            'chip' => 'bg-orange-100 text-orange-800',
            'label' => 'On PCT (deadline day)',
        ],
        'beyond' => [
            'bar' => 'bg-red-500',
            'chip' => 'bg-red-100 text-red-800',
            'label' => 'Beyond PCT',
        ],
        'unrated' => [
            'bar' => 'bg-slate-200',
            'chip' => 'bg-slate-100 text-slate-500',
            'label' => 'Not measurable yet / missing date',
        ],
    ];

    $milestones = collect($definitions)
        ->pluck('start_label')
        ->push(collect($definitions)->last()['end_label'])
        ->values();
@endphp

<div class="space-y-7">

    {{-- ========================================================= --}}
    {{-- INTRODUCTION + FILTER --}}
    {{-- ========================================================= --}}

    <section
        class="flex flex-col
               justify-between gap-4
               lg:flex-row
               lg:items-end"
    >

        <div>

            <p
                class="max-w-3xl
                       text-sm leading-6
                       text-slate-500"
            >
                How the Prescribed Case Time works: a case passes six
                milestones, and each of the five steps between them has
                its own PCT clock. A clock starts on the date of one
                milestone and stops on the date of the next.
            </p>

            <p
                class="mt-2 text-xs
                       text-slate-400"
            >
                As of
                <span class="font-semibold text-slate-600">
                    {{ $asOf->format('F d, Y') }}
                </span>
                · Calendar days, start day = day 0
                ·
                <span class="font-semibold text-slate-600">
                    {{ $officeLabel ?? 'All offices' }}
                </span>
            </p>

        </div>

        <form
            method="GET"
            action="{{ route('pct-cycle-time') }}"
        >

            @foreach (['cases' => $caseFilter, 'search' => $search] as $name => $value)
                @if ($value !== '' && ! ($name === 'cases' && $value === 'open'))
                    <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                @endif
            @endforeach

            <label
                for="office"
                class="mb-1 block
                       text-xs font-semibold
                       text-slate-600"
            >
                Office
            </label>

            <select
                id="office"
                name="office"
                onchange="this.form.submit()"
                class="w-full rounded-xl
                       border border-slate-300
                       bg-white px-4 py-2.5
                       text-sm
                       outline-none
                       focus:border-blue-500
                       focus:ring-4
                       focus:ring-blue-100
                       sm:w-64"
            >

                <option value="">All Offices</option>

                @foreach ($offices as $label => $code)
                    <option
                        value="{{ $code }}"
                        @selected($officeFilter === $code)
                    >
                        {{ $label }} ({{ $code }})
                    </option>
                @endforeach

            </select>

            <noscript>
                <button
                    type="submit"
                    class="mt-2 rounded-xl bg-slate-950
                           px-4 py-2 text-xs
                           font-semibold text-white"
                >
                    Apply
                </button>
            </noscript>

        </form>

    </section>


    {{-- ========================================================= --}}
    {{-- HOW THE PCT CLOCK RUNS --}}
    {{-- ========================================================= --}}

    <section>

        <div class="mb-4">

            <p
                class="text-xs font-bold
                       uppercase
                       tracking-[0.15em]
                       text-blue-600"
            >
                The Process
            </p>

            <h2
                class="mt-1 text-lg
                       font-bold
                       text-slate-950"
            >
                How the PCT Clock Runs
            </h2>

        </div>


        {{-- Milestone rail --}}

        <ol
            class="mb-4 hidden
                   grid-cols-6 gap-2
                   xl:grid"
            aria-label="Case milestones"
        >
            @foreach ($milestones as $milestone)
                <li
                    class="flex items-center gap-2
                           text-xs font-semibold
                           text-slate-600"
                >
                    <span
                        class="flex h-6 w-6 shrink-0
                               items-center justify-center
                               rounded-full bg-slate-900
                               text-[10px] font-bold
                               text-white"
                    >
                        {{ $loop->iteration }}
                    </span>

                    <span class="truncate">
                        {{ $milestone }}
                    </span>
                </li>
            @endforeach
        </ol>


        {{-- One card per checkpoint --}}

        <ol
            class="grid grid-cols-1
                   gap-4
                   md:grid-cols-2
                   xl:grid-cols-5"
        >

            @foreach ($definitions as $key => $definition)

                @php
                    $stat = $stats[$key];
                @endphp

                <li
                    class="relative flex flex-col
                           rounded-2xl
                           border border-slate-200
                           bg-white p-5
                           shadow-sm"
                >

                    <p
                        class="text-xs font-bold
                               uppercase
                               tracking-wider
                               text-blue-600"
                    >
                        PCT {{ $loop->iteration }}
                    </p>

                    <h3
                        class="mt-1 font-bold
                               leading-snug
                               text-slate-950"
                    >
                        {{ $definition['label'] }}
                    </h3>

                    <p
                        class="mt-3 inline-flex
                               self-start rounded-full
                               bg-slate-900 px-3 py-1
                               text-xs font-bold
                               text-white"
                    >
                        Limit: {{ $definition['limit_label'] }}
                    </p>


                    <dl
                        class="mt-4 space-y-1.5
                               text-xs"
                    >

                        <div class="flex gap-2">
                            <dt class="w-12 shrink-0 font-semibold text-emerald-700">
                                Starts
                            </dt>
                            <dd class="text-slate-700">
                                {{ $definition['start_label'] }}
                            </dd>
                        </div>

                        <div class="flex gap-2">
                            <dt class="w-12 shrink-0 font-semibold text-red-700">
                                Stops
                            </dt>
                            <dd class="text-slate-700">
                                {{ $definition['end_label'] }}
                            </dd>
                        </div>

                    </dl>


                    <div
                        class="mt-auto grid
                               grid-cols-2 gap-2
                               border-t border-slate-100
                               pt-4 text-center"
                    >

                        <div class="rounded-xl bg-blue-50 p-2">
                            <p class="text-lg font-bold text-blue-950">
                                {{ number_format($stat['active']) }}
                            </p>
                            <p class="text-[11px] text-blue-700">
                                running now
                            </p>
                        </div>

                        <div class="rounded-xl bg-red-50 p-2">
                            <p class="text-lg font-bold text-red-900">
                                {{ number_format($stat['active_beyond']) }}
                            </p>
                            <p class="text-[11px] text-red-700">
                                running late
                            </p>
                        </div>

                        <div class="rounded-xl bg-slate-50 p-2">
                            <p class="text-lg font-bold text-slate-900">
                                {{ $stat['average_days'] !== null ? $stat['average_days'] . 'd' : '—' }}
                            </p>
                            <p class="text-[11px] text-slate-500">
                                avg. when done
                            </p>
                        </div>

                        <div class="rounded-xl bg-emerald-50 p-2">
                            <p class="text-lg font-bold text-emerald-900">
                                {{ $stat['compliance_rate'] !== null ? $stat['compliance_rate'] . '%' : '—' }}
                            </p>
                            <p class="text-[11px] text-emerald-700">
                                on time
                            </p>
                        </div>

                    </div>

                    <p class="mt-2 text-center text-[11px] text-slate-400">
                        {{ number_format($stat['completed']) }} completed ·
                        {{ number_format($stat['unrated']) }} not measurable
                    </p>

                    @unless ($loop->last)
                        <span
                            class="absolute -right-3.5 top-1/2
                                   z-10 hidden h-7 w-7
                                   -translate-y-1/2
                                   items-center justify-center
                                   rounded-full border
                                   border-slate-200 bg-white
                                   text-slate-400 shadow-sm
                                   xl:flex"
                            aria-hidden="true"
                        >
                            →
                        </span>
                    @endunless

                </li>

            @endforeach

        </ol>


        <p class="mt-3 text-xs text-slate-400">
            Rating: the deadline day is <strong>On PCT</strong> and still on
            time; past it is <strong>Beyond PCT</strong>. <strong>Nearing</strong>
            is the day before the deadline — the last 3 days on the 10- and
            30-day steps. An on-site filing has no nearing day: it is due the
            day it is filed.
        </p>

    </section>


    {{-- ========================================================= --}}
    {{-- CASE TIMELINES --}}
    {{-- ========================================================= --}}

    <section
        id="case-timelines"
        class="scroll-mt-6
               overflow-hidden
               rounded-2xl
               border border-slate-200
               bg-white shadow-sm"
    >

        <div
            class="flex flex-col gap-4
                   border-b border-slate-100
                   px-6 py-5
                   lg:flex-row
                   lg:items-end
                   lg:justify-between"
        >

            <div>

                <h2 class="font-bold text-slate-950">
                    Case Timelines
                </h2>

                <p class="mt-1 text-sm text-slate-500">
                    The start date of every PCT step for each case, how long
                    it took (or has been running), and its rating.
                </p>

            </div>


            <form
                method="GET"
                action="{{ route('pct-cycle-time') }}#case-timelines"
                class="flex flex-col gap-2
                       sm:flex-row sm:items-end"
            >

                @if ($officeFilter !== '')
                    <input type="hidden" name="office" value="{{ $officeFilter }}">
                @endif

                <div>
                    <label for="cases" class="mb-1 block text-xs font-semibold text-slate-600">
                        Cases
                    </label>

                    <select
                        id="cases"
                        name="cases"
                        class="w-full rounded-xl border border-slate-300
                               bg-white px-3 py-2 text-sm
                               sm:w-40"
                    >
                        <option value="open" @selected($caseFilter === 'open')>Open cases</option>
                        <option value="disposed" @selected($caseFilter === 'disposed')>Disposed cases</option>
                        <option value="all" @selected($caseFilter === 'all')>All cases</option>
                    </select>
                </div>

                <div>
                    <label for="search" class="mb-1 block text-xs font-semibold text-slate-600">
                        Search
                    </label>

                    <input
                        id="search"
                        type="search"
                        name="search"
                        value="{{ $search }}"
                        placeholder="Docket, reference, party..."
                        class="w-full rounded-xl border border-slate-300
                               px-3 py-2 text-sm
                               sm:w-56"
                    >
                </div>

                <button
                    type="submit"
                    class="rounded-xl bg-slate-950
                           px-4 py-2 text-sm
                           font-semibold text-white
                           hover:bg-slate-800"
                >
                    Apply
                </button>

            </form>

        </div>


        {{-- Legend --}}

        <div
            class="flex flex-wrap gap-x-5 gap-y-2
                   border-b border-slate-100
                   bg-slate-50 px-6 py-3
                   text-xs text-slate-600"
        >
            @foreach ($tones as $tone)
                <span class="inline-flex items-center gap-2">
                    <span class="h-2.5 w-6 rounded-full {{ $tone['bar'] }}"></span>
                    {{ $tone['label'] }}
                </span>
            @endforeach

            <span class="inline-flex items-center gap-2">
                <span class="h-2.5 w-6 rounded-full bg-blue-500 ring-2 ring-blue-200"></span>
                Clock running now
            </span>
        </div>


        @if ($cases->count() > 0)

            <div class="overflow-x-auto">

                <div class="min-w-[1100px] divide-y divide-slate-100">

                    @foreach ($cases as $rfa)

                        @php
                            $checkpoints = $timelines[$rfa->id]['checkpoints'];
                        @endphp

                        <div
                            class="grid grid-cols-[220px_minmax(0,1fr)]
                                   gap-5 px-6 py-5"
                        >

                            {{-- Case --}}

                            <div class="min-w-0">

                                <a
                                    href="{{ route('rfas.show', $rfa) }}"
                                    class="block truncate text-sm
                                           font-bold text-slate-900
                                           hover:text-blue-700"
                                >
                                    {{ $rfa->docket_no ?: $rfa->reference_no }}
                                </a>

                                <p class="mt-0.5 truncate text-xs text-slate-500">
                                    {{ $rfa->requesting_party ?: '—' }}
                                </p>

                                <p class="mt-2 text-xs text-slate-400">
                                    {{ $rfa->office ?: 'No office' }}
                                    ·
                                    {{ \Illuminate\Support\Str::headline($rfa->mode_of_filing ?: 'mode not set') }}
                                </p>

                                @if ($rfa->date_disposed)
                                    <p class="mt-1 text-xs font-semibold text-slate-600">
                                        Disposed {{ $rfa->date_disposed->format('M d, Y') }}
                                    </p>
                                @endif

                            </div>


                            {{-- Timeline: one segment per PCT step --}}

                            <ol class="grid grid-cols-5 gap-2">

                                @foreach ($checkpoints as $checkpoint)

                                    @php
                                        $rated = in_array($checkpoint['state'], ['active', 'completed'], true);

                                        $tone = $tones[$rated ? $checkpoint['classification_key'] : 'unrated'];

                                        $running = $checkpoint['state'] === 'active';
                                    @endphp

                                    <li class="min-w-0">

                                        <p class="flex items-center gap-1.5 text-[11px] font-semibold text-slate-500">
                                            <span
                                                @class([
                                                    'h-2 w-2 shrink-0 rounded-full',
                                                    'bg-slate-900' => $checkpoint['start_date'],
                                                    'border border-slate-300 bg-white' => ! $checkpoint['start_date'],
                                                ])
                                            ></span>
                                            <span class="truncate">
                                                PCT {{ $loop->iteration }} started
                                            </span>
                                        </p>

                                        <p class="mt-0.5 text-xs font-bold text-slate-900">
                                            {{ $checkpoint['start_date']?->format('M d, Y') ?? 'No start date' }}
                                        </p>

                                        <div
                                            @class([
                                                'mt-2 h-2.5 rounded-full',
                                                $tone['bar'],
                                                'ring-2 ring-blue-200 animate-pulse' => $running,
                                            ])
                                            title="{{ $checkpoint['stage_label'] }}"
                                        ></div>

                                        <p class="mt-2">
                                            <span class="inline-flex rounded-full px-2 py-0.5 text-[11px] font-bold {{ $tone['chip'] }}">
                                                {{ \App\Services\PctService::statusLabel($checkpoint) }}
                                            </span>
                                        </p>

                                        <p class="mt-1 text-[11px] text-slate-500">
                                            @if ($checkpoint['days'] !== null)
                                                {{ $checkpoint['days'] }}
                                                {{ \Illuminate\Support\Str::plural('day', $checkpoint['days']) }}
                                                {{ $running ? 'so far' : 'taken' }}
                                                · limit {{ $checkpoint['limit_days'] === 0 ? 'same day' : $checkpoint['limit_days'] . 'd' }}
                                            @else
                                                {{ $checkpoint['stage_label'] }}
                                            @endif
                                        </p>

                                        @if ($running && $checkpoint['deadline'])
                                            <p class="mt-0.5 text-[11px] font-semibold text-blue-700">
                                                Due {{ $checkpoint['deadline']->format('M d') }}
                                            </p>
                                        @endif

                                    </li>

                                @endforeach

                            </ol>

                        </div>

                    @endforeach

                </div>

            </div>


            @if ($cases->hasPages())
                <div class="border-t border-slate-100 px-6 py-4">
                    {{ $cases->fragment('case-timelines')->links() }}
                </div>
            @endif

        @else

            <div class="px-6 py-12 text-center text-sm text-slate-500">
                No cases match these filters.
            </div>

        @endif

    </section>

</div>

@endsection
