@extends('layouts.app')


@section(
    'title',
    'Reports | RFA Monitoring System'
)


@section(
    'page_heading',
    'Reports'
)


@section('page_actions')

    <div
        class="no-print
               flex items-center
               gap-2"
    >

        <a
            href="{{
                route(
                    'reports.export',
                    request()->query()
                )
            }}"
            class="inline-flex
                   items-center
                   justify-center
                   rounded-xl
                   border border-slate-300
                   bg-white
                   px-4 py-2.5
                   text-sm font-semibold
                   text-slate-700
                   shadow-sm
                   transition
                   hover:bg-slate-50"
        >
            Export CSV
        </a>


        <button
            type="button"
            onclick="window.print()"
            class="inline-flex
                   items-center
                   justify-center
                   rounded-xl
                   bg-slate-950
                   px-4 py-2.5
                   text-sm font-semibold
                   text-white
                   shadow-sm
                   transition
                   hover:bg-slate-800"
        >
            Print Report
        </button>

    </div>

@endsection


@section('content')

<style>
    @media print {
        .no-print {
            display: none !important;
        }

        aside,
        header {
            display: none !important;
        }

        main {
            padding: 0 !important;
            margin: 0 !important;
        }

        body {
            background: white !important;
        }

        .report-card {
            box-shadow: none !important;
            break-inside: avoid;
        }

        .report-table {
            font-size: 10px;
        }
    }
</style>


<div class="space-y-7">

    {{-- ========================================================= --}}
    {{-- REPORT DESCRIPTION --}}
    {{-- ========================================================= --}}

    <section>

        <p
            class="max-w-4xl
                   text-sm leading-6
                   text-slate-500"
        >
            Generate consolidated RFA monitoring,
            processing, disposition, worker-benefit,
            monetary-benefit, and PCT reports using
            the selected filters.
        </p>

        <p
            class="mt-2 text-xs
                   text-slate-400"
        >
            Generated:
            {{ now()->format(
                'F d, Y h:i A'
            ) }}
        </p>

    </section>


    {{-- ========================================================= --}}
    {{-- FILTERS --}}
    {{-- ========================================================= --}}

    <section
        class="no-print
               rounded-2xl
               border border-slate-200
               bg-white p-6
               shadow-sm"
    >

        <form
            method="GET"
            action="{{ route('reports') }}"
        >

            <div
                class="grid grid-cols-1
                       gap-5
                       md:grid-cols-2
                       xl:grid-cols-4"
            >

                {{-- SEARCH --}}

                <div
                    class="md:col-span-2"
                >

                    <label
                        for="search"
                        class="mb-2 block
                               text-sm font-semibold
                               text-slate-700"
                    >
                        Search
                    </label>

                    <input
                        id="search"
                        name="search"
                        type="search"
                        value="{{ request('search') }}"
                        placeholder="Docket, reference, complainant, company, interviewer, SEADO..."

                        class="w-full
                               rounded-xl
                               border border-slate-300
                               px-4 py-3
                               text-sm
                               outline-none
                               focus:border-blue-500
                               focus:ring-4
                               focus:ring-blue-100"
                    >

                </div>


                {{-- DATE FROM --}}

                <div>

                    <label
                        for="date_from"
                        class="mb-2 block
                               text-sm font-semibold
                               text-slate-700"
                    >
                        Date Filed From
                    </label>

                    <input
                        id="date_from"
                        name="date_from"
                        type="date"
                        value="{{ request('date_from') }}"

                        class="w-full
                               rounded-xl
                               border border-slate-300
                               px-4 py-3
                               text-sm
                               outline-none
                               focus:border-blue-500
                               focus:ring-4
                               focus:ring-blue-100"
                    >

                </div>


                {{-- DATE TO --}}

                <div>

                    <label
                        for="date_to"
                        class="mb-2 block
                               text-sm font-semibold
                               text-slate-700"
                    >
                        Date Filed To
                    </label>

                    <input
                        id="date_to"
                        name="date_to"
                        type="date"
                        value="{{ request('date_to') }}"

                        class="w-full
                               rounded-xl
                               border border-slate-300
                               px-4 py-3
                               text-sm
                               outline-none
                               focus:border-blue-500
                               focus:ring-4
                               focus:ring-blue-100"
                    >

                </div>


                {{-- OFFICE --}}

                <div>

                    <label
                        for="office"
                        class="mb-2 block
                               text-sm font-semibold
                               text-slate-700"
                    >
                        Office
                    </label>

                    <select
                        id="office"
                        name="office"

                        class="w-full
                               rounded-xl
                               border border-slate-300
                               bg-white
                               px-4 py-3
                               text-sm"
                    >

                        <option value="">
                            All Offices
                        </option>

                        @foreach (
                            $offices
                            as $office
                        )

                            <option
                                value="{{ $office }}"
                                @selected(
                                    request('office')
                                    === $office
                                )
                            >
                                {{ $office }}
                            </option>

                        @endforeach

                    </select>

                </div>


                {{-- MONITORING --}}

                <div>

                    <label
                        for="monitoring_bucket"
                        class="mb-2 block
                               text-sm font-semibold
                               text-slate-700"
                    >
                        Monitoring
                    </label>

                    <select
                        id="monitoring_bucket"
                        name="monitoring_bucket"

                        class="w-full
                               rounded-xl
                               border border-slate-300
                               bg-white
                               px-4 py-3
                               text-sm"
                    >

                        <option value="">
                            All Monitoring
                        </option>

                        @foreach (
                            [
                                'pending' =>
                                    'Pending',

                                'ongoing' =>
                                    'Ongoing',

                                'disposed' =>
                                    'Disposed',
                            ]
                            as $value => $label
                        )

                            <option
                                value="{{ $value }}"
                                @selected(
                                    request(
                                        'monitoring_bucket'
                                    ) === $value
                                )
                            >
                                {{ $label }}
                            </option>

                        @endforeach

                    </select>

                </div>


                {{-- WORKFLOW STATUS --}}

                <div>

                    <label
                        for="status"
                        class="mb-2 block
                               text-sm font-semibold
                               text-slate-700"
                    >
                        Workflow Status
                    </label>

                    <select
                        id="status"
                        name="status"

                        class="w-full
                               rounded-xl
                               border border-slate-300
                               bg-white
                               px-4 py-3
                               text-sm"
                    >

                        <option value="">
                            All Workflow Statuses
                        </option>

                        @foreach (
                            $workflowStatuses
                            as $status
                        )

                            <option
                                value="{{ $status }}"
                                @selected(
                                    request('status')
                                    === $status
                                )
                            >
                                {{
                                    \Illuminate\Support\Str::headline(
                                        $status
                                    )
                                }}
                            </option>

                        @endforeach

                    </select>

                </div>


                {{-- SOURCE STATUS --}}

                <div>

                    <label
                        for="source_case_status"
                        class="mb-2 block
                               text-sm font-semibold
                               text-slate-700"
                    >
                        Source Case Status
                    </label>

                    <select
                        id="source_case_status"
                        name="source_case_status"

                        class="w-full
                               rounded-xl
                               border border-slate-300
                               bg-white
                               px-4 py-3
                               text-sm"
                    >

                        <option value="">
                            All Source Statuses
                        </option>

                        @foreach (
                            $sourceStatuses
                            as $status
                        )

                            <option
                                value="{{ $status }}"
                                @selected(
                                    request(
                                        'source_case_status'
                                    ) === $status
                                )
                            >
                                {{ $status }}
                            </option>

                        @endforeach

                    </select>

                </div>


                {{-- DISPOSITION MODE --}}

                <div>

                    <label
                        for="disposition_mode"
                        class="mb-2 block
                               text-sm font-semibold
                               text-slate-700"
                    >
                        Disposition Mode
                    </label>

                    <select
                        id="disposition_mode"
                        name="disposition_mode"

                        class="w-full
                               rounded-xl
                               border border-slate-300
                               bg-white
                               px-4 py-3
                               text-sm"
                    >

                        <option value="">
                            All Modes
                        </option>

                        @foreach (
                            $dispositionModes
                            as $mode
                        )

                            <option
                                value="{{ $mode }}"
                                @selected(
                                    request(
                                        'disposition_mode'
                                    ) === $mode
                                )
                            >
                                {{ $mode }}
                            </option>

                        @endforeach

                    </select>

                </div>

            </div>


            <div
                class="mt-6 flex
                       justify-end gap-2
                       border-t
                       border-slate-100
                       pt-5"
            >

                <a
                    href="{{ route('reports') }}"
                    class="rounded-xl
                           border border-slate-300
                           bg-white
                           px-5 py-2.5
                           text-sm font-semibold
                           text-slate-700
                           hover:bg-slate-50"
                >
                    Clear
                </a>

                <button
                    type="submit"
                    class="rounded-xl
                           bg-blue-600
                           px-5 py-2.5
                           text-sm font-semibold
                           text-white
                           hover:bg-blue-700"
                >
                    Generate Report
                </button>

            </div>

        </form>

    </section>


    {{-- ========================================================= --}}
    {{-- GENERAL SUMMARY --}}
    {{-- ========================================================= --}}

    <section>

        <div class="mb-4">

            <p
                class="text-xs font-bold
                       uppercase
                       tracking-[0.15em]
                       text-blue-600"
            >
                Report Summary
            </p>

            <h2
                class="mt-1 text-lg
                       font-bold
                       text-slate-950"
            >
                RFA Overview
            </h2>

        </div>


        <div
            class="grid grid-cols-2
                   gap-4
                   xl:grid-cols-4"
        >

            @foreach (
                [
                    [
                        'label' =>
                            'Total RFAs',

                        'value' =>
                            number_format(
                                $summary['total']
                            ),
                    ],

                    [
                        'label' =>
                            'Pending',

                        'value' =>
                            number_format(
                                $summary['pending']
                            ),
                    ],

                    [
                        'label' =>
                            'Ongoing',

                        'value' =>
                            number_format(
                                $summary['ongoing']
                            ),
                    ],

                    [
                        'label' =>
                            'Disposed',

                        'value' =>
                            number_format(
                                $summary['disposed']
                            ),
                    ],

                    [
                        'label' =>
                            'Workers Involved',

                        'value' =>
                            number_format(
                                $summary[
                                    'workers_involved'
                                ]
                            ),
                    ],

                    [
                        'label' =>
                            'Workers Benefited',

                        'value' =>
                            number_format(
                                $summary[
                                    'workers_benefited'
                                ]
                            ),
                    ],

                    [
                        'label' =>
                            'Monetary Benefit',

                        'value' =>
                            '₱'
                            .number_format(
                                $summary[
                                    'monetary_benefit'
                                ],
                                2
                            ),
                    ],

                    [
                        'label' =>
                            'Avg. Processing Days',

                        'value' =>
                            $processingSummary[
                                'average'
                            ]
                            ?? '—',
                    ],
                ]
                as $card
            )

                <div
                    class="report-card
                           rounded-2xl
                           border border-slate-200
                           bg-white p-5
                           shadow-sm"
                >

                    <p
                        class="text-xs font-bold
                               uppercase
                               tracking-wider
                               text-slate-400"
                    >
                        {{ $card['label'] }}
                    </p>

                    <p
                        class="mt-3
                               break-words
                               text-2xl font-bold
                               text-slate-950"
                    >
                        {{ $card['value'] }}
                    </p>

                </div>

            @endforeach

        </div>

    </section>


    {{-- ========================================================= --}}
    {{-- ACTIVE PCT SUMMARY --}}
    {{-- ========================================================= --}}

    <section
        class="report-card
               rounded-2xl
               border border-slate-200
               bg-white p-6
               shadow-sm"
    >

        <h2
            class="font-bold
                   text-slate-950"
        >
            Active PCT Monitoring
        </h2>


        <div
            class="mt-5 grid
                   grid-cols-2 gap-3
                   md:grid-cols-5"
        >

            @foreach (
                [
                    'total' =>
                        'Active',

                    'within' =>
                        'Within',

                    'nearing' =>
                        'Nearing',

                    'on' =>
                        'On PCT',

                    'beyond' =>
                        'Beyond',
                ]
                as $key => $label
            )

                <div
                    class="rounded-xl
                           bg-slate-50
                           p-4"
                >

                    <p
                        class="text-xs
                               font-semibold
                               text-slate-500"
                    >
                        {{ $label }}
                    </p>

                    <p
                        class="mt-2
                               text-2xl
                               font-bold
                               text-slate-900"
                    >
                        {{
                            number_format(
                                $activePct[$key]
                            )
                        }}
                    </p>

                </div>

            @endforeach

        </div>

    </section>


    {{-- ========================================================= --}}
    {{-- HISTORICAL PCT --}}
    {{-- ========================================================= --}}

    <section
        class="grid grid-cols-1
               gap-5
               xl:grid-cols-2"
    >

        @foreach (
            [
                [
                    'title' =>
                        'Filed → Interviewer Assignment',

                    'summary' =>
                        $historicalStageOne,
                ],

                [
                    'title' =>
                        'Interviewer Assignment → Interview',

                    'summary' =>
                        $historicalStageTwo,
                ],
            ]
            as $item
        )

            <div
                class="report-card
                       rounded-2xl
                       border border-slate-200
                       bg-white p-6
                       shadow-sm"
            >

                <h3
                    class="font-bold
                           text-slate-950"
                >
                    {{ $item['title'] }}
                </h3>


                <div
                    class="mt-4
                           text-3xl
                           font-bold
                           text-slate-950"
                >
                    {{
                        $item[
                            'summary'
                        ][
                            'compliance_rate'
                        ] !== null
                            ? $item[
                                'summary'
                            ][
                                'compliance_rate'
                            ].'%'
                            : '—'
                    }}
                </div>

                <p
                    class="mt-1 text-xs
                           text-slate-500"
                >
                    Historical compliance
                    within 3 days
                </p>


                <div
                    class="mt-5 grid
                           grid-cols-2 gap-3
                           sm:grid-cols-4"
                >

                    @foreach (
                        [
                            'within' =>
                                'Within',

                            'nearing' =>
                                'Nearing',

                            'on' =>
                                'On PCT',

                            'beyond' =>
                                'Beyond',
                        ]
                        as $key => $label
                    )

                        <div
                            class="rounded-xl
                                   bg-slate-50
                                   p-3"
                        >

                            <p
                                class="text-xs
                                       text-slate-500"
                            >
                                {{ $label }}
                            </p>

                            <p
                                class="mt-1
                                       text-xl
                                       font-bold
                                       text-slate-900"
                            >
                                {{
                                    $item[
                                        'summary'
                                    ][$key]
                                }}
                            </p>

                        </div>

                    @endforeach

                </div>

            </div>

        @endforeach

    </section>


    {{-- ========================================================= --}}
    {{-- BREAKDOWNS --}}
    {{-- ========================================================= --}}

    <section
        class="grid grid-cols-1
               gap-5
               xl:grid-cols-3"
    >

        {{-- OFFICE --}}

        <div
            class="report-card
                   overflow-hidden
                   rounded-2xl
                   border border-slate-200
                   bg-white shadow-sm"
        >

            <div
                class="border-b
                       border-slate-100
                       px-5 py-4"
            >
                <h3
                    class="font-bold
                           text-slate-950"
                >
                    By Office
                </h3>
            </div>


            <div
                class="divide-y
                       divide-slate-100"
            >

                @forelse (
                    $officeBreakdown
                    as $label => $total
                )

                    <div
                        class="flex
                               justify-between
                               gap-4
                               px-5 py-3
                               text-sm"
                    >

                        <span
                            class="text-slate-600"
                        >
                            {{ $label }}
                        </span>

                        <strong
                            class="text-slate-900"
                        >
                            {{ $total }}
                        </strong>

                    </div>

                @empty

                    <div
                        class="px-5 py-8
                               text-sm
                               text-slate-400"
                    >
                        No data.
                    </div>

                @endforelse

            </div>

        </div>


        {{-- SOURCE STATUS --}}

        <div
            class="report-card
                   overflow-hidden
                   rounded-2xl
                   border border-slate-200
                   bg-white shadow-sm"
        >

            <div
                class="border-b
                       border-slate-100
                       px-5 py-4"
            >
                <h3
                    class="font-bold
                           text-slate-950"
                >
                    By Source Case Status
                </h3>
            </div>


            <div
                class="divide-y
                       divide-slate-100"
            >

                @forelse (
                    $sourceStatusBreakdown
                    as $label => $total
                )

                    <div
                        class="flex
                               justify-between
                               gap-4
                               px-5 py-3
                               text-sm"
                    >

                        <span
                            class="text-slate-600"
                        >
                            {{ $label }}
                        </span>

                        <strong>
                            {{ $total }}
                        </strong>

                    </div>

                @empty

                    <div
                        class="px-5 py-8
                               text-sm
                               text-slate-400"
                    >
                        No data.
                    </div>

                @endforelse

            </div>

        </div>


        {{-- DISPOSITION MODE --}}

        <div
            class="report-card
                   overflow-hidden
                   rounded-2xl
                   border border-slate-200
                   bg-white shadow-sm"
        >

            <div
                class="border-b
                       border-slate-100
                       px-5 py-4"
            >
                <h3
                    class="font-bold
                           text-slate-950"
                >
                    By Disposition Mode
                </h3>
            </div>


            <div
                class="divide-y
                       divide-slate-100"
            >

                @forelse (
                    $dispositionModeBreakdown
                    as $label => $total
                )

                    <div
                        class="flex
                               justify-between
                               gap-4
                               px-5 py-3
                               text-sm"
                    >

                        <span
                            class="text-slate-600"
                        >
                            {{ $label }}
                        </span>

                        <strong>
                            {{ $total }}
                        </strong>

                    </div>

                @empty

                    <div
                        class="px-5 py-8
                               text-sm
                               text-slate-400"
                    >
                        No disposition data.
                    </div>

                @endforelse

            </div>

        </div>

    </section>


    {{-- ========================================================= --}}
    {{-- PROCESSING DURATION --}}
    {{-- ========================================================= --}}

    <section
        class="report-card
               rounded-2xl
               border border-slate-200
               bg-white p-6
               shadow-sm"
    >

        <h2
            class="font-bold
                   text-slate-950"
        >
            Date Filed → Date Disposed
        </h2>

        <p
            class="mt-1 text-sm
                   text-slate-500"
        >
            Total processing duration is reported
            separately and is not treated as a
            three-day PCT checkpoint.
        </p>


        <div
            class="mt-5 grid
                   grid-cols-2 gap-4
                   lg:grid-cols-4"
        >

            @foreach (
                [
                    'count' =>
                        'Cases Measured',

                    'average' =>
                        'Average Days',

                    'minimum' =>
                        'Minimum Days',

                    'maximum' =>
                        'Maximum Days',
                ]
                as $key => $label
            )

                <div
                    class="rounded-xl
                           bg-slate-50
                           p-4"
                >

                    <p
                        class="text-xs
                               font-semibold
                               text-slate-500"
                    >
                        {{ $label }}
                    </p>

                    <p
                        class="mt-2
                               text-2xl
                               font-bold
                               text-slate-900"
                    >
                        {{
                            $processingSummary[
                                $key
                            ]
                            ?? '—'
                        }}
                    </p>

                </div>

            @endforeach

        </div>

    </section>


    {{-- ========================================================= --}}
    {{-- REPORT RECORDS --}}
    {{-- ========================================================= --}}

    <section
        class="report-card
               overflow-hidden
               rounded-2xl
               border border-slate-200
               bg-white shadow-sm"
    >

        <div
            class="border-b
                   border-slate-100
                   px-6 py-5"
        >

            <h2
                class="font-bold
                       text-slate-950"
            >
                Report Records
            </h2>

            <p
                class="mt-1 text-sm
                       text-slate-500"
            >
                {{
                    number_format(
                        $records->total()
                    )
                }}
                record(s) match the selected criteria.
            </p>

        </div>


        <div class="overflow-x-auto">

            <table
                class="report-table
                       min-w-[1500px]
                       w-full
                       divide-y
                       divide-slate-200"
            >

                <thead class="bg-slate-50">

                    <tr>

                        <th
                            class="px-4 py-3
                                   text-left
                                   text-xs font-bold
                                   uppercase
                                   text-slate-500"
                        >
                            Docket
                        </th>

                        <th
                            class="px-4 py-3
                                   text-left
                                   text-xs font-bold
                                   uppercase
                                   text-slate-500"
                        >
                            Requesting Party
                        </th>

                        <th
                            class="px-4 py-3
                                   text-left
                                   text-xs font-bold
                                   uppercase
                                   text-slate-500"
                        >
                            Responding Party
                        </th>

                        <th
                            class="px-4 py-3
                                   text-left
                                   text-xs font-bold
                                   uppercase
                                   text-slate-500"
                        >
                            Date Filed
                        </th>

                        <th
                            class="px-4 py-3
                                   text-left
                                   text-xs font-bold
                                   uppercase
                                   text-slate-500"
                        >
                            Source Status
                        </th>

                        <th
                            class="px-4 py-3
                                   text-left
                                   text-xs font-bold
                                   uppercase
                                   text-slate-500"
                        >
                            Monitoring
                        </th>

                        <th
                            class="px-4 py-3
                                   text-left
                                   text-xs font-bold
                                   uppercase
                                   text-slate-500"
                        >
                            PCT 1
                        </th>

                        <th
                            class="px-4 py-3
                                   text-left
                                   text-xs font-bold
                                   uppercase
                                   text-slate-500"
                        >
                            PCT 2
                        </th>

                        <th
                            class="px-4 py-3
                                   text-left
                                   text-xs font-bold
                                   uppercase
                                   text-slate-500"
                        >
                            Disposition Mode
                        </th>

                        <th
                            class="px-4 py-3
                                   text-right
                                   text-xs font-bold
                                   uppercase
                                   text-slate-500"
                        >
                            Monetary Benefit
                        </th>

                    </tr>

                </thead>


                <tbody
                    class="divide-y
                           divide-slate-100"
                >

                    @forelse (
                        $records
                        as $rfa
                    )

                        @php
                            $pct =
                                $recordPct[
                                    $rfa->id
                                ];

                            $pctOne =
                                $pct[
                                    'stage_one'
                                ];

                            $pctTwo =
                                $pct[
                                    'stage_two'
                                ];
                        @endphp


                        <tr>

                            <td
                                class="px-4 py-4"
                            >

                                <div
                                    class="font-semibold
                                           text-slate-900"
                                >
                                    {{
                                        $rfa->docket_no
                                        ?: '—'
                                    }}
                                </div>

                                <div
                                    class="mt-1
                                           text-xs
                                           text-slate-400"
                                >
                                    {{
                                        $rfa->reference_no
                                    }}
                                </div>

                            </td>


                            <td
                                class="px-4 py-4
                                       text-slate-700"
                            >
                                {{
                                    $rfa->requesting_party
                                    ?: '—'
                                }}
                            </td>


                            <td
                                class="px-4 py-4
                                       text-slate-700"
                            >
                                {{
                                    $rfa->responding_party
                                    ?: '—'
                                }}
                            </td>


                            <td
                                class="whitespace-nowrap
                                       px-4 py-4
                                       text-slate-600"
                            >
                                {{
                                    $rfa->date_filed
                                        ?->format(
                                            'M d, Y'
                                        )
                                    ?? '—'
                                }}
                            </td>


                            <td
                                class="px-4 py-4
                                       text-slate-600"
                            >
                                {{
                                    $rfa->source_case_status
                                    ?: '—'
                                }}
                            </td>


                            <td
                                class="px-4 py-4
                                       text-slate-600"
                            >
                                {{
                                    \Illuminate\Support\Str::headline(
                                        $rfa->monitoring_bucket
                                        ?? ''
                                    )
                                    ?: '—'
                                }}
                            </td>


                            @foreach (
                                [
                                    $pctOne,
                                    $pctTwo,
                                ]
                                as $checkpoint
                            )

                                <td
                                    class="px-4 py-4"
                                >

                                    @if (
                                        in_array(
                                            $checkpoint[
                                                'state'
                                            ],
                                            [
                                                'active',
                                                'completed',
                                            ],
                                            true
                                        )
                                    )

                                        <div
                                            class="text-xs
                                                   font-semibold
                                                   text-slate-700"
                                        >
                                            {{
                                                $checkpoint[
                                                    'classification_label'
                                                ]
                                            }}
                                        </div>

                                        <div
                                            class="mt-1
                                                   text-xs
                                                   text-slate-400"
                                        >
                                            {{
                                                $checkpoint[
                                                    'days'
                                                ]
                                            }}
                                            day(s)
                                        </div>

                                    @else

                                        <span
                                            class="text-xs
                                                   text-slate-400"
                                        >
                                            Unavailable
                                        </span>

                                    @endif

                                </td>

                            @endforeach


                            <td
                                class="px-4 py-4
                                       text-slate-600"
                            >
                                {{
                                    $rfa->disposition_mode
                                    ?: '—'
                                }}
                            </td>


                            <td
                                class="whitespace-nowrap
                                       px-4 py-4
                                       text-right
                                       font-medium
                                       text-slate-700"
                            >
                                @if (
                                    $rfa->monetary_benefit
                                    !== null
                                )

                                    ₱{{
                                        number_format(
                                            (float)
                                            $rfa
                                                ->monetary_benefit,
                                            2
                                        )
                                    }}

                                @else

                                    —

                                @endif
                            </td>

                        </tr>

                    @empty

                        <tr>

                            <td
                                colspan="10"
                                class="px-6 py-14
                                       text-center
                                       text-sm
                                       text-slate-500"
                            >
                                No records match the
                                selected report filters.
                            </td>

                        </tr>

                    @endforelse

                </tbody>

            </table>

        </div>


        @if ($records->hasPages())

            <div
                class="no-print
                       border-t
                       border-slate-100
                       px-6 py-4"
            >
                {{ $records->links() }}
            </div>

        @endif

    </section>

</div>

@endsection
