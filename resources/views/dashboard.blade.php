@extends('layouts.app')


@section(
    'title',
    'Dashboard | RFA Monitoring System'
)


@section(
    'page_heading',
    'Dashboard'
)


@section('page_actions')

    @can('import.manage')

    <a
        href="{{ route('imports.index') }}"

        class="inline-flex items-center gap-2
               rounded-xl bg-blue-600
               px-4 py-2.5
               text-sm font-semibold text-white
               shadow-sm transition
               hover:bg-blue-700
               sm:px-5"
    >

        <svg
            class="h-5 w-5"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="1.8"
        >

            <path
                stroke-linecap="round"
                stroke-linejoin="round"
                d="
                    M12 16V4
                    m0 0-4 4
                    m4-4 4 4
                    M5 13v5
                    a2 2 0 0 0 2 2
                    h10
                    a2 2 0 0 0 2-2
                    v-5
                "
            />

        </svg>


        <span class="hidden sm:inline">
            Import CSV
        </span>

        <span class="sm:hidden">
            Import
        </span>

    </a>

    @endcan

@endsection


@section('content')

@php
    $canViewRfas = auth()->user()->can('rfa.view');

    $cardTag = $canViewRfas ? 'a' : 'div';
@endphp

<div>

    {{-- ========================================================= --}}
    {{-- DASHBOARD INTRO --}}
    {{-- ========================================================= --}}

    <div
        class="mb-7 flex flex-col
               justify-between gap-4
               md:flex-row
               md:items-end"
    >

        <div>

            <p
                class="text-sm font-semibold
                       text-blue-600"
            >
                Overview
            </p>

            <h2
                class="mt-1 text-3xl
                       font-bold tracking-tight
                       text-slate-950"
            >
                RFA Overview
            </h2>

            <p
                class="mt-2 max-w-2xl
                       text-sm leading-6
                       text-slate-500"
            >
                Monitor pending, ongoing, and disposed
                Requests for Assistance.
            </p>

        </div>


        <{{ $cardTag }}
            @if ($canViewRfas)
                href="{{ route('listing') }}#rfa-records"
                title="View all RFAs"
            @endif
            class="block w-fit rounded-xl
                   border border-slate-200
                   bg-white px-5 py-3
                   shadow-sm transition
                   hover:-translate-y-0.5
                   hover:shadow-md"
        >

            <p
                class="text-xs font-medium
                       uppercase tracking-wider
                       text-slate-400"
            >
                Total RFAs
            </p>

            <p
                class="mt-1 text-2xl
                       font-bold text-slate-950"
            >
                {{ number_format($total) }}
            </p>

        </{{ $cardTag }}>

    </div>


    {{-- ========================================================= --}}
    {{-- STATUS CARDS --}}
    {{-- ========================================================= --}}

    <section
        class="grid grid-cols-1
               gap-5 md:grid-cols-3"
    >

        {{-- PENDING --}}

        <{{ $cardTag }}
            @if ($canViewRfas)
                href="{{ route('listing', ['monitoring_bucket' => 'pending']) }}#rfa-records"
                title="View all pending RFAs"
            @endif

            class="group relative
                   overflow-hidden
                   rounded-2xl
                   border border-amber-200
                   bg-white p-6 text-left
                   shadow-sm transition
                   hover:-translate-y-1
                   hover:shadow-lg"
        >

            <div
                class="absolute -right-10
                       -top-10 h-28 w-28
                       rounded-full
                       bg-amber-100/70"
            ></div>

            <div
                class="relative flex
                       items-start justify-between
                       gap-5"
            >

                <div>

                    <p
                        class="text-sm font-semibold
                               text-slate-500"
                    >
                        Pending RFAs
                    </p>

                    <p
                        class="mt-3 text-4xl
                               font-bold tracking-tight
                               text-slate-950"
                    >
                        {{ number_format(
                            $counts['pending']
                        ) }}
                    </p>

                    <p
                        class="mt-2 text-sm
                               font-semibold
                               text-amber-600"
                    >
                        {{ $percentages['pending'] }}%
                        of all RFAs
                    </p>

                    @if ($canViewRfas)
                        <p
                            class="mt-3 text-xs font-semibold
                                   text-slate-400
                                   group-hover:text-slate-700"
                        >
                            View list →
                        </p>
                    @endif

                </div>


                <div
                    class="flex h-12 w-12
                           items-center
                           justify-center
                           rounded-xl
                           bg-amber-100
                           text-amber-600"
                >

                    <svg
                        class="h-6 w-6"
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
                            d="M12 7v5l3 2"
                        />
                    </svg>

                </div>

            </div>

        </{{ $cardTag }}>


        {{-- ONGOING --}}

        <{{ $cardTag }}
            @if ($canViewRfas)
                href="{{ route('listing', ['monitoring_bucket' => 'ongoing']) }}#rfa-records"
                title="View all ongoing RFAs"
            @endif

            class="group relative
                   overflow-hidden
                   rounded-2xl
                   border border-blue-200
                   bg-white p-6 text-left
                   shadow-sm transition
                   hover:-translate-y-1
                   hover:shadow-lg"
        >

            <div
                class="absolute -right-10
                       -top-10 h-28 w-28
                       rounded-full
                       bg-blue-100/70"
            ></div>

            <div
                class="relative flex
                       items-start justify-between
                       gap-5"
            >

                <div>

                    <p
                        class="text-sm font-semibold
                               text-slate-500"
                    >
                        Ongoing RFAs
                    </p>

                    <p
                        class="mt-3 text-4xl
                               font-bold tracking-tight
                               text-slate-950"
                    >
                        {{ number_format(
                            $counts['ongoing']
                        ) }}
                    </p>

                    <p
                        class="mt-2 text-sm
                               font-semibold
                               text-blue-600"
                    >
                        {{ $percentages['ongoing'] }}%
                        of all RFAs
                    </p>

                    @if ($canViewRfas)
                        <p
                            class="mt-3 text-xs font-semibold
                                   text-slate-400
                                   group-hover:text-slate-700"
                        >
                            View list →
                        </p>
                    @endif

                </div>


                <div
                    class="flex h-12 w-12
                           items-center
                           justify-center
                           rounded-xl
                           bg-blue-100
                           text-blue-600"
                >

                    <svg
                        class="h-6 w-6"
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
                            d="M8 12h8"
                        />
                    </svg>

                </div>

            </div>

        </{{ $cardTag }}>


        {{-- DISPOSED --}}

        <{{ $cardTag }}
            @if ($canViewRfas)
                href="{{ route('listing', ['monitoring_bucket' => 'disposed']) }}#rfa-records"
                title="View all disposed RFAs"
            @endif

            class="group relative
                   overflow-hidden
                   rounded-2xl
                   border border-emerald-200
                   bg-white p-6 text-left
                   shadow-sm transition
                   hover:-translate-y-1
                   hover:shadow-lg"
        >

            <div
                class="absolute -right-10
                       -top-10 h-28 w-28
                       rounded-full
                       bg-emerald-100/70"
            ></div>

            <div
                class="relative flex
                       items-start justify-between
                       gap-5"
            >

                <div>

                    <p
                        class="text-sm font-semibold
                               text-slate-500"
                    >
                        Disposed RFAs
                    </p>

                    <p
                        class="mt-3 text-4xl
                               font-bold tracking-tight
                               text-slate-950"
                    >
                        {{ number_format(
                            $counts['disposed']
                        ) }}
                    </p>

                    <p
                        class="mt-2 text-sm
                               font-semibold
                               text-emerald-600"
                    >
                        {{ $percentages['disposed'] }}%
                        of all RFAs
                    </p>

                    @if ($canViewRfas)
                        <p
                            class="mt-3 text-xs font-semibold
                                   text-slate-400
                                   group-hover:text-slate-700"
                        >
                            View list →
                        </p>
                    @endif

                </div>


                <div
                    class="flex h-12 w-12
                           items-center
                           justify-center
                           rounded-xl
                           bg-emerald-100
                           text-emerald-600"
                >

                    <svg
                        class="h-6 w-6"
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
                            d="m8 12 2.5 2.5L16 9"
                        />
                    </svg>

                </div>

            </div>

        </{{ $cardTag }}>

    </section>


    {{-- ========================================================= --}}
    {{-- FILING TREND --}}
    {{-- ========================================================= --}}

    <section
        class="mt-8 rounded-2xl
               border border-slate-200
               bg-white p-6 shadow-sm"
    >

        <div
            class="flex flex-col gap-2
                   sm:flex-row sm:items-start
                   sm:justify-between"
        >

            <div>

                <p
                    class="text-xs font-bold
                           uppercase tracking-[0.15em]
                           text-slate-500"
                >
                    Activity
                </p>

                <h3
                    class="mt-1 text-lg font-bold
                           text-slate-950"
                >
                    RFA Filing Trend
                </h3>

            </div>

            <p class="text-sm text-slate-500">
                RFAs filed per day, on-site and online ·
                {{ $filingTrend['from'] }} – {{ $filingTrend['to'] }}
            </p>

        </div>


        <div class="relative mt-6 h-[300px] w-full">

            <canvas
                id="rfaFilingTrendChart"
                role="img"
                aria-label="Line chart of RFAs filed per day from {{ $filingTrend['from'] }} to {{ $filingTrend['to'] }}: on-site {{ implode(', ', $filingTrend['onsite']) }}; online {{ implode(', ', $filingTrend['online']) }}."
                data-trend="{{ json_encode($filingTrend) }}"
            ></canvas>

        </div>


        @if ($filingTrend['unplotted'] > 0)
            @php
                $one = $filingTrend['unplotted'] === 1;
            @endphp

            <p class="mt-4 text-xs text-slate-400">
                {{ number_format($filingTrend['unplotted']) }} {{ $one ? 'RFA' : 'RFAs' }} filed in this period {{ $one ? 'has' : 'have' }} no mode of filing and {{ $one ? 'is' : 'are' }} not shown.
            </p>
        @endif

    </section>


    {{-- ========================================================= --}}
    {{-- CHART --}}
    {{-- ========================================================= --}}

    <section
        class="mt-8 overflow-hidden
               rounded-2xl
               border border-slate-200
               bg-white shadow-sm"
    >

        <div
            class="border-b border-slate-100
                   px-6 py-5"
        >

            <h3
                class="text-lg font-bold
                       text-slate-950"
            >
                RFA Distribution
            </h3>

            <p
                class="mt-1 text-sm
                       text-slate-500"
            >
                Percentage distribution based on
                records stored in MySQL.
            </p>

        </div>


        <div
            class="grid grid-cols-1
                   gap-8 p-6
                   xl:grid-cols-2"
        >

            <div
                class="relative flex
                       min-h-[350px]
                       items-center
                       justify-center
                       rounded-2xl
                       bg-slate-50 p-6"
            >

                @if ($total > 0)

                    <div
                        class="relative h-[310px]
                               w-full max-w-[500px]"
                    >

                        <canvas
                            id="rfaDistributionChart"
                            data-pending="{{ $counts['pending'] }}"
                            data-ongoing="{{ $counts['ongoing'] }}"
                            data-disposed="{{ $counts['disposed'] }}"
                        ></canvas>

                    </div>

                @else

                    <div class="text-center">

                        <h4
                            class="font-semibold
                                   text-slate-900"
                        >
                            No RFA records
                        </h4>

                        <p
                            class="mt-2 text-sm
                                   text-slate-500"
                        >
                            Imported RFA records will
                            appear here.
                        </p>

                    </div>

                @endif

            </div>


            <div
                class="flex flex-col
                       justify-center space-y-4"
            >

                <x-dashboard-percentage
                    label="Pending RFAs"
                    count="{{ $counts['pending'] }}"
                    percentage="{{ $percentages['pending'] }}"
                    type="pending"
                />

                <x-dashboard-percentage
                    label="Ongoing RFAs"
                    count="{{ $counts['ongoing'] }}"
                    percentage="{{ $percentages['ongoing'] }}"
                    type="ongoing"
                />

                <x-dashboard-percentage
                    label="Disposed RFAs"
                    count="{{ $counts['disposed'] }}"
                    percentage="{{ $percentages['disposed'] }}"
                    type="disposed"
                />

            </div>

        </div>

    </section>



</div>

@endsection
