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

@endsection


@section('content')

<div
    x-data="{
        statusModal: false,
        {{-- importModal: false, --}}
        selectedStatus: null,

        openStatus(label, count, percentage) {
            this.selectedStatus = {
                label: label,
                count: count,
                percentage: percentage
            };

            this.statusModal = true;
        }
    }"

    @keydown.escape.window="
    statusModal = false
"

    @keydown.escape.window="
        statusModal = false;
        importModal = false;
    "
>

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


        <div
            class="w-fit rounded-xl
                   border border-slate-200
                   bg-white px-5 py-3
                   shadow-sm"
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

        </div>

    </div>


    {{-- ========================================================= --}}
    {{-- STATUS CARDS --}}
    {{-- ========================================================= --}}

    <section
        class="grid grid-cols-1
               gap-5 md:grid-cols-3"
    >

        {{-- PENDING --}}

        <button
            type="button"

            @click="
                openStatus(
                    'Pending RFAs',
                    {{ $counts['pending'] }},
                    {{ $percentages['pending'] }}
                )
            "

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

        </button>


        {{-- ONGOING --}}

        <button
            type="button"

            @click="
                openStatus(
                    'Ongoing RFAs',
                    {{ $counts['ongoing'] }},
                    {{ $percentages['ongoing'] }}
                )
            "

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

        </button>


        {{-- DISPOSED --}}

        <button
            type="button"

            @click="
                openStatus(
                    'Disposed RFAs',
                    {{ $counts['disposed'] }},
                    {{ $percentages['disposed'] }}
                )
            "

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

        </button>

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


    {{-- ========================================================= --}}
    {{-- STATUS SUMMARY MODAL --}}
    {{-- ========================================================= --}}

    <div
        x-cloak
        x-show="statusModal"
        x-transition.opacity

        class="fixed inset-0 z-[70]
               flex items-center
               justify-center p-4"
    >

        <div
            class="absolute inset-0
                   bg-slate-950/50
                   backdrop-blur-sm"

            @click="statusModal = false"
        ></div>


        <div
            x-show="statusModal"

            x-transition:enter="
                transition duration-200 ease-out
            "

            x-transition:enter-start="
                scale-95 opacity-0
            "

            x-transition:enter-end="
                scale-100 opacity-100
            "

            class="relative w-full
                   max-w-md rounded-2xl
                   bg-white p-6
                   shadow-2xl"
        >

            <p
                class="text-xs font-semibold
                       uppercase tracking-wider
                       text-blue-600"
            >
                RFA Summary
            </p>

            <h3
                class="mt-2 text-xl
                       font-bold text-slate-950"

                x-text="selectedStatus?.label"
            ></h3>


            <div
                class="mt-6 grid
                       grid-cols-2 gap-4"
            >

                <div
                    class="rounded-xl
                           bg-slate-50 p-4"
                >

                    <p
                        class="text-xs uppercase
                               tracking-wider
                               text-slate-400"
                    >
                        Records
                    </p>

                    <p
                        class="mt-2 text-3xl
                               font-bold text-slate-950"

                        x-text="
                            selectedStatus?.count ?? 0
                        "
                    ></p>

                </div>


                <div
                    class="rounded-xl
                           bg-slate-50 p-4"
                >

                    <p
                        class="text-xs uppercase
                               tracking-wider
                               text-slate-400"
                    >
                        Percentage
                    </p>

                    <p
                        class="mt-2 text-3xl
                               font-bold text-blue-600"
                    >
                        <span
                            x-text="
                                selectedStatus?.percentage
                                ?? 0
                            "
                        ></span>%
                    </p>

                </div>

            </div>


            <div
                class="mt-6 flex justify-end"
            >

                <button
                    type="button"

                    @click="
                        statusModal = false
                    "

                    class="rounded-xl bg-slate-900
                           px-5 py-2.5
                           text-sm font-semibold
                           text-white transition
                           hover:bg-slate-700"
                >
                    Close
                </button>

            </div>

        </div>

    </div>




    </div>

</div>

@endsection
