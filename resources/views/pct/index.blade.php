@extends('layouts.app')


@section(
    'title',
    'PCT Process | RFA Monitoring System'
)


@section(
    'page_heading',
    'PCT Process'
)


@section('content')

<div class="space-y-7">

    {{-- ========================================================= --}}
    {{-- INTRODUCTION --}}
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
                Monitor active processing timers and
                preserve historical PCT results for the
                two three-day RFA processing checkpoints.
            </p>

            <p
                class="mt-2 text-xs
                       text-slate-400"
            >
                As of
                <span
                    class="font-semibold
                           text-slate-600"
                >
                    {{ $asOf->format('F d, Y') }}
                </span>
                · Calendar-day calculation
            </p>

        </div>


        <div
            class="rounded-xl
                   border border-slate-200
                   bg-white px-4 py-3
                   text-xs
                   leading-5
                   text-slate-500
                   shadow-sm"
        >
            <strong class="text-slate-700">
                PCT Rule:
            </strong>

            0–1 Within ·
            2 Nearing ·
            3 On PCT ·
            &gt;3 Beyond
        </div>

    </section>


    {{-- ========================================================= --}}
    {{-- ACTIVE SUMMARY --}}
    {{-- ========================================================= --}}

    <section>

        <div class="mb-4">

            <p
                class="text-xs font-bold
                       uppercase
                       tracking-[0.15em]
                       text-blue-600"
            >
                Current Monitoring
            </p>

            <h2
                class="mt-1 text-lg
                       font-bold
                       text-slate-950"
            >
                Active PCT Timers
            </h2>

        </div>


        <div
            class="grid grid-cols-2
                   gap-4
                   xl:grid-cols-5"
        >

            <div
                class="rounded-2xl
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
                    Active Timers
                </p>

                <p
                    class="mt-3 text-3xl
                           font-bold
                           text-slate-950"
                >
                    {{
                        number_format(
                            $activeSummary[
                                'total'
                            ]
                        )
                    }}
                </p>

            </div>


            <div
                class="rounded-2xl
                       border border-emerald-200
                       bg-emerald-50 p-5
                       shadow-sm"
            >

                <p
                    class="text-xs font-bold
                           uppercase
                           tracking-wider
                           text-emerald-600"
                >
                    Within PCT
                </p>

                <p
                    class="mt-3 text-3xl
                           font-bold
                           text-emerald-900"
                >
                    {{
                        number_format(
                            $activeSummary[
                                'within'
                            ]
                        )
                    }}
                </p>

            </div>


            <div
                class="rounded-2xl
                       border border-amber-200
                       bg-amber-50 p-5
                       shadow-sm"
            >

                <p
                    class="text-xs font-bold
                           uppercase
                           tracking-wider
                           text-amber-600"
                >
                    Nearing PCT
                </p>

                <p
                    class="mt-3 text-3xl
                           font-bold
                           text-amber-900"
                >
                    {{
                        number_format(
                            $activeSummary[
                                'nearing'
                            ]
                        )
                    }}
                </p>

            </div>


            <div
                class="rounded-2xl
                       border border-orange-200
                       bg-orange-50 p-5
                       shadow-sm"
            >

                <p
                    class="text-xs font-bold
                           uppercase
                           tracking-wider
                           text-orange-600"
                >
                    On PCT
                </p>

                <p
                    class="mt-3 text-3xl
                           font-bold
                           text-orange-900"
                >
                    {{
                        number_format(
                            $activeSummary[
                                'on'
                            ]
                        )
                    }}
                </p>

            </div>


            <div
                class="rounded-2xl
                       border border-red-200
                       bg-red-50 p-5
                       shadow-sm"
            >

                <p
                    class="text-xs font-bold
                           uppercase
                           tracking-wider
                           text-red-600"
                >
                    Beyond PCT
                </p>

                <p
                    class="mt-3 text-3xl
                           font-bold
                           text-red-900"
                >
                    {{
                        number_format(
                            $activeSummary[
                                'beyond'
                            ]
                        )
                    }}
                </p>

            </div>

        </div>

    </section>


    {{-- ========================================================= --}}
    {{-- ACTIVE FILTERS --}}
    {{-- ========================================================= --}}

    <section
        class="rounded-2xl
               border border-slate-200
               bg-white p-5
               shadow-sm"
    >

        <form
            method="GET"
            action="{{ route('pct-process') }}"
            class="grid grid-cols-1
                   gap-4
                   md:grid-cols-4"
        >

            <div
                class="md:col-span-2"
            >

                <label
                    for="search"
                    class="mb-2 block
                           text-sm font-semibold
                           text-slate-700"
                >
                    Search Active Timers
                </label>

                <input
                    id="search"
                    type="search"
                    name="search"
                    value="{{ $search }}"
                    placeholder="Docket, reference, complainant, company..."

                    class="w-full rounded-xl
                           border border-slate-300
                           px-4 py-3
                           text-sm
                           outline-none
                           focus:border-blue-500
                           focus:ring-4
                           focus:ring-blue-100"
                >

            </div>


            <div>

                <label
                    for="stage"
                    class="mb-2 block
                           text-sm font-semibold
                           text-slate-700"
                >
                    Checkpoint
                </label>

                <select
                    id="stage"
                    name="stage"

                    class="w-full rounded-xl
                           border border-slate-300
                           bg-white px-4 py-3
                           text-sm
                           outline-none
                           focus:border-blue-500
                           focus:ring-4
                           focus:ring-blue-100"
                >

                    <option value="">
                        All Checkpoints
                    </option>

                    <option
                        value="assignment"
                        @selected(
                            $stageFilter
                            === 'assignment'
                        )
                    >
                        Filed → Assignment
                    </option>

                    <option
                        value="interview"
                        @selected(
                            $stageFilter
                            === 'interview'
                        )
                    >
                        Assignment → Interview
                    </option>

                </select>

            </div>


            <div>

                <label
                    for="pct_status"
                    class="mb-2 block
                           text-sm font-semibold
                           text-slate-700"
                >
                    PCT Status
                </label>

                <select
                    id="pct_status"
                    name="pct_status"

                    class="w-full rounded-xl
                           border border-slate-300
                           bg-white px-4 py-3
                           text-sm
                           outline-none
                           focus:border-blue-500
                           focus:ring-4
                           focus:ring-blue-100"
                >

                    <option value="">
                        All Statuses
                    </option>

                    <option
                        value="within"
                        @selected(
                            $classificationFilter
                            === 'within'
                        )
                    >
                        Within PCT
                    </option>

                    <option
                        value="nearing"
                        @selected(
                            $classificationFilter
                            === 'nearing'
                        )
                    >
                        Nearing PCT
                    </option>

                    <option
                        value="on"
                        @selected(
                            $classificationFilter
                            === 'on'
                        )
                    >
                        On PCT
                    </option>

                    <option
                        value="beyond"
                        @selected(
                            $classificationFilter
                            === 'beyond'
                        )
                    >
                        Beyond PCT
                    </option>

                </select>

            </div>


            <div
                class="md:col-span-4
                       flex flex-col gap-2
                       border-t border-slate-100
                       pt-4
                       sm:flex-row
                       sm:justify-end"
            >

                <a
                    href="{{ route('pct-process') }}"
                    class="inline-flex
                           justify-center
                           rounded-xl
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
                    class="inline-flex
                           justify-center
                           rounded-xl
                           bg-slate-950
                           px-5 py-2.5
                           text-sm font-semibold
                           text-white
                           hover:bg-slate-800"
                >
                    Apply
                </button>

            </div>

        </form>

    </section>


    {{-- ========================================================= --}}
    {{-- ACTIVE TIMER TABLE --}}
    {{-- ========================================================= --}}

    <section
        class="overflow-hidden
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
                Cases Requiring PCT Monitoring
            </h2>

            <p
                class="mt-1 text-sm
                       text-slate-500"
            >
                Active timers are sorted by urgency.
            </p>

        </div>


        @if (
            $activePaginator->count()
            > 0
        )

            <div class="overflow-x-auto">

                <table
                    class="min-w-[1150px]
                           w-full
                           divide-y
                           divide-slate-200"
                >

                    <thead class="bg-slate-50">

                        <tr>

                            <th
                                class="px-5 py-3
                                       text-left
                                       text-xs font-bold
                                       uppercase
                                       text-slate-500"
                            >
                                Docket / Reference
                            </th>

                            <th
                                class="px-5 py-3
                                       text-left
                                       text-xs font-bold
                                       uppercase
                                       text-slate-500"
                            >
                                Parties
                            </th>

                            <th
                                class="px-5 py-3
                                       text-left
                                       text-xs font-bold
                                       uppercase
                                       text-slate-500"
                            >
                                Checkpoint
                            </th>

                            <th
                                class="px-5 py-3
                                       text-left
                                       text-xs font-bold
                                       uppercase
                                       text-slate-500"
                            >
                                Started
                            </th>

                            <th
                                class="px-5 py-3
                                       text-left
                                       text-xs font-bold
                                       uppercase
                                       text-slate-500"
                            >
                                PCT Deadline
                            </th>

                            <th
                                class="px-5 py-3
                                       text-left
                                       text-xs font-bold
                                       uppercase
                                       text-slate-500"
                            >
                                Days
                            </th>

                            <th
                                class="px-5 py-3
                                       text-left
                                       text-xs font-bold
                                       uppercase
                                       text-slate-500"
                            >
                                PCT Status
                            </th>

                        </tr>

                    </thead>


                    <tbody
                        class="divide-y
                               divide-slate-100"
                    >

                        @foreach (
                            $activePaginator
                            as $item
                        )

                            @php
                                $rfa =
                                    $item['rfa'];

                                $checkpoint =
                                    $item[
                                        'checkpoint'
                                    ];

                                $badge =
                                    match (
                                        $checkpoint[
                                            'classification_key'
                                        ]
                                    ) {
                                        'within' =>
                                            'bg-emerald-100 text-emerald-700',

                                        'nearing' =>
                                            'bg-amber-100 text-amber-700',

                                        'on' =>
                                            'bg-orange-100 text-orange-700',

                                        'beyond' =>
                                            'bg-red-100 text-red-700',

                                        default =>
                                            'bg-slate-100 text-slate-700',
                                    };
                            @endphp


                            <tr
                                class="hover:bg-slate-50"
                            >

                                <td
                                    class="px-5 py-4"
                                >

                                    <div
                                        class="text-sm
                                               font-bold
                                               text-slate-900"
                                    >
                                        {{
                                            $rfa->docket_no
                                            ?: $rfa->reference_no
                                        }}
                                    </div>

                                    @if ($rfa->docket_no)

                                        <div
                                            class="mt-1
                                                   text-xs
                                                   text-slate-400"
                                        >
                                            {{
                                                $rfa->reference_no
                                            }}
                                        </div>

                                    @endif

                                </td>


                                <td
                                    class="px-5 py-4"
                                >

                                    <div
                                        class="text-sm
                                               font-medium
                                               text-slate-800"
                                    >
                                        {{
                                            $rfa->requesting_party
                                            ?: '—'
                                        }}
                                    </div>

                                    <div
                                        class="mt-1 text-xs
                                               text-slate-400"
                                    >
                                        {{
                                            $rfa->responding_party
                                            ?: '—'
                                        }}
                                    </div>

                                </td>


                                <td
                                    class="px-5 py-4
                                           text-sm
                                           text-slate-700"
                                >
                                    {{
                                        $checkpoint[
                                            'stage_label'
                                        ]
                                    }}
                                </td>


                                <td
                                    class="whitespace-nowrap
                                           px-5 py-4
                                           text-sm
                                           text-slate-600"
                                >
                                    {{
                                        $checkpoint[
                                            'start_date'
                                        ]?->format(
                                            'M d, Y'
                                        )
                                        ?? '—'
                                    }}
                                </td>


                                <td
                                    class="whitespace-nowrap
                                           px-5 py-4
                                           text-sm
                                           text-slate-600"
                                >
                                    {{
                                        $checkpoint[
                                            'deadline'
                                        ]?->format(
                                            'M d, Y'
                                        )
                                        ?? '—'
                                    }}
                                </td>


                                <td
                                    class="px-5 py-4"
                                >

                                    <div
                                        class="text-sm
                                               font-bold
                                               text-slate-900"
                                    >
                                        {{
                                            $checkpoint[
                                                'days'
                                            ]
                                        }}
                                    </div>


                                    @if (
                                        $checkpoint[
                                            'remaining_days'
                                        ] < 0
                                    )

                                        <div
                                            class="text-xs
                                                   text-red-600"
                                        >
                                            {{
                                                abs(
                                                    $checkpoint[
                                                        'remaining_days'
                                                    ]
                                                )
                                            }}
                                            day(s) overdue
                                        </div>

                                    @elseif (
                                        $checkpoint[
                                            'remaining_days'
                                        ] === 0
                                    )

                                        <div
                                            class="text-xs
                                                   text-orange-600"
                                        >
                                            Due today
                                        </div>

                                    @else

                                        <div
                                            class="text-xs
                                                   text-slate-400"
                                        >
                                            {{
                                                $checkpoint[
                                                    'remaining_days'
                                                ]
                                            }}
                                            day(s) remaining
                                        </div>

                                    @endif

                                </td>


                                <td
                                    class="px-5 py-4"
                                >

                                    <span
                                        class="
                                            inline-flex
                                            rounded-full
                                            px-2.5 py-1
                                            text-xs font-bold
                                            {{ $badge }}
                                        "
                                    >
                                        {{
                                            $checkpoint[
                                                'classification_label'
                                            ]
                                        }}
                                    </span>

                                </td>

                            </tr>

                        @endforeach

                    </tbody>

                </table>

            </div>


            <div
                class="border-t
                       border-slate-100
                       px-6 py-4"
            >
                {{
                    $activePaginator
                        ->links()
                }}
            </div>

        @else

            <div
                class="px-6 py-12
                       text-center
                       text-sm
                       text-slate-500"
            >
                No active PCT timers match the
                current filters.
            </div>

        @endif

    </section>


    {{-- ========================================================= --}}
    {{-- HISTORICAL SUMMARY --}}
    {{-- ========================================================= --}}

    <section>

        <div class="mb-4">

            <p
                class="text-xs font-bold
                       uppercase
                       tracking-[0.15em]
                       text-blue-600"
            >
                Historical Compliance
            </p>

            <h2
                class="mt-1 text-lg
                       font-bold
                       text-slate-950"
            >
                Completed PCT Checkpoints
            </h2>

        </div>


        <div
            class="grid grid-cols-1
                   gap-5
                   xl:grid-cols-2"
        >

            @foreach (
                [
                    'stage_one' =>
                        'Date Filed → Interviewer Assignment',

                    'stage_two' =>
                        'Interviewer Assignment → Date of Interview',
                ]
                as $key => $label
            )

                @php
                    $summary =
                        $historicalSummary[
                            $key
                        ];
                @endphp


                <div
                    class="rounded-2xl
                           border border-slate-200
                           bg-white p-6
                           shadow-sm"
                >

                    <div
                        class="flex flex-col
                               justify-between
                               gap-3
                               sm:flex-row
                               sm:items-start"
                    >

                        <div>

                            <h3
                                class="font-bold
                                       text-slate-950"
                            >
                                {{ $label }}
                            </h3>

                            <p
                                class="mt-1
                                       text-sm
                                       text-slate-500"
                            >
                                {{
                                    number_format(
                                        $summary[
                                            'completed'
                                        ]
                                    )
                                }}
                                completed checkpoint(s)
                            </p>

                        </div>


                        <div
                            class="rounded-xl
                                   bg-slate-100
                                   px-4 py-2
                                   text-right"
                        >

                            <div
                                class="text-lg
                                       font-bold
                                       text-slate-900"
                            >
                                {{
                                    $summary[
                                        'compliance_rate'
                                    ] !== null
                                        ? $summary[
                                            'compliance_rate'
                                        ].'%'
                                        : '—'
                                }}
                            </div>

                            <div
                                class="text-xs
                                       text-slate-500"
                            >
                                within 3 days
                            </div>

                        </div>

                    </div>


                    <div
                        class="mt-6 grid
                               grid-cols-2
                               gap-3
                               sm:grid-cols-4"
                    >

                        <div
                            class="rounded-xl
                                   bg-emerald-50
                                   p-3"
                        >
                            <div
                                class="text-xs
                                       font-semibold
                                       text-emerald-600"
                            >
                                Within
                            </div>

                            <div
                                class="mt-1
                                       text-xl
                                       font-bold
                                       text-emerald-900"
                            >
                                {{
                                    $summary[
                                        'within'
                                    ]
                                }}
                            </div>
                        </div>


                        <div
                            class="rounded-xl
                                   bg-amber-50
                                   p-3"
                        >
                            <div
                                class="text-xs
                                       font-semibold
                                       text-amber-600"
                            >
                                Nearing
                            </div>

                            <div
                                class="mt-1
                                       text-xl
                                       font-bold
                                       text-amber-900"
                            >
                                {{
                                    $summary[
                                        'nearing'
                                    ]
                                }}
                            </div>
                        </div>


                        <div
                            class="rounded-xl
                                   bg-orange-50
                                   p-3"
                        >
                            <div
                                class="text-xs
                                       font-semibold
                                       text-orange-600"
                            >
                                On PCT
                            </div>

                            <div
                                class="mt-1
                                       text-xl
                                       font-bold
                                       text-orange-900"
                            >
                                {{
                                    $summary[
                                        'on'
                                    ]
                                }}
                            </div>
                        </div>


                        <div
                            class="rounded-xl
                                   bg-red-50
                                   p-3"
                        >
                            <div
                                class="text-xs
                                       font-semibold
                                       text-red-600"
                            >
                                Beyond
                            </div>

                            <div
                                class="mt-1
                                       text-xl
                                       font-bold
                                       text-red-900"
                            >
                                {{
                                    $summary[
                                        'beyond'
                                    ]
                                }}
                            </div>
                        </div>

                    </div>

                </div>

            @endforeach

        </div>

    </section>


    {{-- ========================================================= --}}
    {{-- TOTAL PROCESSING DURATION --}}
    {{-- ========================================================= --}}

    <section
        class="rounded-2xl
               border border-slate-200
               bg-white p-6
               shadow-sm"
    >

        <div>

            <p
                class="text-xs font-bold
                       uppercase
                       tracking-[0.15em]
                       text-slate-500"
            >
                Processing Duration
            </p>

            <h2
                class="mt-1 text-lg
                       font-bold
                       text-slate-950"
            >
                Date Filed → Date Disposed
            </h2>

            <p
                class="mt-2 text-sm
                       text-slate-500"
            >
                This is tracked separately from the
                two three-day PCT checkpoints and
                currently has no overall PCT limit.
            </p>

        </div>


        <div
            class="mt-5 grid
                   grid-cols-2 gap-4
                   lg:grid-cols-4"
        >

            <div
                class="rounded-xl
                       bg-slate-50 p-4"
            >
                <div
                    class="text-xs
                           font-semibold
                           text-slate-500"
                >
                    Disposed Cases Measured
                </div>

                <div
                    class="mt-2 text-2xl
                           font-bold
                           text-slate-900"
                >
                    {{
                        number_format(
                            $processingSummary[
                                'count'
                            ]
                        )
                    }}
                </div>
            </div>


            <div
                class="rounded-xl
                       bg-slate-50 p-4"
            >
                <div
                    class="text-xs
                           font-semibold
                           text-slate-500"
                >
                    Average Days
                </div>

                <div
                    class="mt-2 text-2xl
                           font-bold
                           text-slate-900"
                >
                    {{
                        $processingSummary[
                            'average'
                        ]
                        ?? '—'
                    }}
                </div>
            </div>


            <div
                class="rounded-xl
                       bg-slate-50 p-4"
            >
                <div
                    class="text-xs
                           font-semibold
                           text-slate-500"
                >
                    Minimum Days
                </div>

                <div
                    class="mt-2 text-2xl
                           font-bold
                           text-slate-900"
                >
                    {{
                        $processingSummary[
                            'minimum'
                        ]
                        ?? '—'
                    }}
                </div>
            </div>


            <div
                class="rounded-xl
                       bg-slate-50 p-4"
            >
                <div
                    class="text-xs
                           font-semibold
                           text-slate-500"
                >
                    Maximum Days
                </div>

                <div
                    class="mt-2 text-2xl
                           font-bold
                           text-slate-900"
                >
                    {{
                        $processingSummary[
                            'maximum'
                        ]
                        ?? '—'
                    }}
                </div>
            </div>

        </div>

    </section>


    {{-- ========================================================= --}}
    {{-- HISTORICAL RECORD TABLE --}}
    {{-- ========================================================= --}}

    <section
        class="overflow-hidden
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
                Historical PCT Results
            </h2>

            <p
                class="mt-1 text-sm
                       text-slate-500"
            >
                Completed results remain fixed after
                their corresponding event date is recorded.
            </p>

        </div>


        <div class="overflow-x-auto">

            <table
                class="min-w-[1100px]
                       w-full
                       divide-y
                       divide-slate-200"
            >

                <thead class="bg-slate-50">

                    <tr>

                        <th
                            class="px-5 py-3
                                   text-left
                                   text-xs font-bold
                                   uppercase
                                   text-slate-500"
                        >
                            Docket / Reference
                        </th>

                        <th
                            class="px-5 py-3
                                   text-left
                                   text-xs font-bold
                                   uppercase
                                   text-slate-500"
                        >
                            Filed → Assigned
                        </th>

                        <th
                            class="px-5 py-3
                                   text-left
                                   text-xs font-bold
                                   uppercase
                                   text-slate-500"
                        >
                            Assigned → Interview
                        </th>

                        <th
                            class="px-5 py-3
                                   text-left
                                   text-xs font-bold
                                   uppercase
                                   text-slate-500"
                        >
                            Total Processing
                        </th>

                    </tr>

                </thead>


                <tbody
                    class="divide-y
                           divide-slate-100"
                >

                    @forelse (
                        $historyPaginator
                        as $item
                    )

                        @php
                            $rfa =
                                $item['rfa'];
                        @endphp


                        <tr>

                            <td
                                class="px-5 py-4"
                            >

                                <div
                                    class="text-sm
                                           font-bold
                                           text-slate-900"
                                >
                                    {{
                                        $rfa->docket_no
                                        ?: $rfa->reference_no
                                    }}
                                </div>

                                <div
                                    class="mt-1
                                           text-xs
                                           text-slate-400"
                                >
                                    {{
                                        $rfa->requesting_party
                                        ?: '—'
                                    }}
                                </div>

                            </td>


                            @foreach (
                                [
                                    $item[
                                        'stage_one'
                                    ],
                                    $item[
                                        'stage_two'
                                    ],
                                ]
                                as $checkpoint
                            )

                                <td
                                    class="px-5 py-4"
                                >

                                    @if (
                                        $checkpoint[
                                            'state'
                                        ]
                                        === 'completed'
                                    )

                                        @php
                                            $badge =
                                                match (
                                                    $checkpoint[
                                                        'classification_key'
                                                    ]
                                                ) {
                                                    'within' =>
                                                        'bg-emerald-100 text-emerald-700',

                                                    'nearing' =>
                                                        'bg-amber-100 text-amber-700',

                                                    'on' =>
                                                        'bg-orange-100 text-orange-700',

                                                    'beyond' =>
                                                        'bg-red-100 text-red-700',

                                                    default =>
                                                        'bg-slate-100 text-slate-700',
                                                };
                                        @endphp

                                        <span
                                            class="
                                                inline-flex
                                                rounded-full
                                                px-2.5 py-1
                                                text-xs
                                                font-bold
                                                {{ $badge }}
                                            "
                                        >
                                            {{
                                                $checkpoint[
                                                    'classification_label'
                                                ]
                                            }}
                                        </span>

                                        <div
                                            class="mt-1
                                                   text-xs
                                                   text-slate-500"
                                        >
                                            {{
                                                $checkpoint[
                                                    'days'
                                                ]
                                            }}
                                            day(s)
                                        </div>

                                    @elseif (
                                        $checkpoint[
                                            'state'
                                        ]
                                        === 'active'
                                    )

                                        <span
                                            class="text-xs
                                                   font-semibold
                                                   text-blue-600"
                                        >
                                            Active
                                        </span>

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
                                class="px-5 py-4"
                            >

                                @if (
                                    $item[
                                        'total_processing'
                                    ]['state']
                                    === 'completed'
                                )

                                    <span
                                        class="text-sm
                                               font-bold
                                               text-slate-800"
                                    >
                                        {{
                                            $item[
                                                'total_processing'
                                            ]['days']
                                        }}
                                        day(s)
                                    </span>

                                @else

                                    <span
                                        class="text-sm
                                               text-slate-400"
                                    >
                                        —
                                    </span>

                                @endif

                            </td>

                        </tr>

                    @empty

                        <tr>

                            <td
                                colspan="4"
                                class="px-6 py-12
                                       text-center
                                       text-sm
                                       text-slate-500"
                            >
                                No completed PCT
                                history is available.
                            </td>

                        </tr>

                    @endforelse

                </tbody>

            </table>

        </div>


        @if (
            $historyPaginator
                ->hasPages()
        )

            <div
                class="border-t
                       border-slate-100
                       px-6 py-4"
            >
                {{
                    $historyPaginator
                        ->links()
                }}
            </div>

        @endif

    </section>


    {{-- ========================================================= --}}
    {{-- DATA QUALITY --}}
    {{-- ========================================================= --}}

    <section
        class="overflow-hidden
               rounded-2xl
               border border-slate-200
               bg-white shadow-sm"
    >

        <div
            class="flex flex-col
                   justify-between gap-2
                   border-b
                   border-slate-100
                   px-6 py-5
                   sm:flex-row
                   sm:items-center"
        >

            <div>

                <p
                    class="text-xs font-bold
                           uppercase
                           tracking-[0.15em]
                           text-amber-600"
                >
                    Data Quality
                </p>

                <h2
                    class="mt-1 font-bold
                           text-slate-950"
                >
                    PCT Records Requiring Attention
                </h2>

            </div>


            <span
                class="inline-flex
                       w-fit rounded-full
                       bg-amber-100
                       px-3 py-1
                       text-xs font-bold
                       text-amber-700"
            >
                {{
                    number_format(
                        $dataIssueCount
                    )
                }}
                issue(s)
            </span>

        </div>


        <div class="overflow-x-auto">

            <table
                class="min-w-[900px]
                       w-full
                       divide-y
                       divide-slate-200"
            >

                <thead class="bg-slate-50">

                    <tr>

                        <th
                            class="px-5 py-3
                                   text-left
                                   text-xs font-bold
                                   uppercase
                                   text-slate-500"
                        >
                            Docket / Reference
                        </th>

                        <th
                            class="px-5 py-3
                                   text-left
                                   text-xs font-bold
                                   uppercase
                                   text-slate-500"
                        >
                            Checkpoint
                        </th>

                        <th
                            class="px-5 py-3
                                   text-left
                                   text-xs font-bold
                                   uppercase
                                   text-slate-500"
                        >
                            Problem
                        </th>

                        <th
                            class="px-5 py-3
                                   text-left
                                   text-xs font-bold
                                   uppercase
                                   text-slate-500"
                        >
                            Source Status
                        </th>

                    </tr>

                </thead>


                <tbody
                    class="divide-y
                           divide-slate-100"
                >

                    @forelse (
                        $issuePaginator
                        as $item
                    )

                        @php
                            $rfa =
                                $item['rfa'];

                            $checkpoint =
                                $item[
                                    'checkpoint'
                                ];
                        @endphp


                        <tr>

                            <td
                                class="px-5 py-4"
                            >

                                <div
                                    class="text-sm
                                           font-bold
                                           text-slate-900"
                                >
                                    {{
                                        $rfa->docket_no
                                        ?: $rfa->reference_no
                                    }}
                                </div>

                                <div
                                    class="mt-1
                                           text-xs
                                           text-slate-400"
                                >
                                    {{
                                        $rfa->requesting_party
                                        ?: '—'
                                    }}
                                </div>

                            </td>


                            <td
                                class="px-5 py-4
                                       text-sm
                                       text-slate-700"
                            >
                                {{
                                    $checkpoint[
                                        'stage_label'
                                    ]
                                }}
                            </td>


                            <td
                                class="px-5 py-4
                                       text-sm
                                       text-amber-700"
                            >
                                {{
                                    $checkpoint[
                                        'message'
                                    ]
                                }}
                            </td>


                            <td
                                class="px-5 py-4
                                       text-sm
                                       text-slate-600"
                            >
                                {{
                                    $rfa->source_case_status
                                    ?: '—'
                                }}
                            </td>

                        </tr>

                    @empty

                        <tr>

                            <td
                                colspan="4"
                                class="px-6 py-12
                                       text-center
                                       text-sm
                                       text-slate-500"
                            >
                                No PCT data-quality
                                issues detected.
                            </td>

                        </tr>

                    @endforelse

                </tbody>

            </table>

        </div>


        @if (
            $issuePaginator
                ->hasPages()
        )

            <div
                class="border-t
                       border-slate-100
                       px-6 py-4"
            >
                {{
                    $issuePaginator
                        ->links()
                }}
            </div>

        @endif

    </section>

</div>

@endsection
