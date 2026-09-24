@extends('layouts.app')


@section(
    'title',
    'RFA Listing | RFA Monitoring System'
)


@section(
    'page_heading',
    'RFA Listing'
)


@section('page_actions')

    @can('import.manage')

    <a
        href="{{ route('imports.index') }}"
        class="inline-flex items-center gap-2
               rounded-xl bg-blue-600
               px-4 py-2.5
               text-sm font-semibold
               text-white shadow-sm
               transition
               hover:bg-blue-700
               focus:outline-none
               focus:ring-4
               focus:ring-blue-100"
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

<div class="space-y-6">

    {{-- ========================================================= --}}
    {{-- PAGE INTRODUCTION --}}
    {{-- ========================================================= --}}

    <section>

        <p
            class="max-w-3xl
                   text-sm leading-6
                   text-slate-500"
        >
            Search, filter, sort, and review all
            Request for Assistance records currently
            stored in the monitoring system.
        </p>

    </section>


    {{-- ========================================================= --}}
    {{-- SUMMARY CARDS --}}
    {{-- ========================================================= --}}

    <section
        class="grid grid-cols-2
               gap-4
               xl:grid-cols-4"
    >

        {{-- TOTAL --}}

        <div
            class="rounded-2xl
                   border border-slate-200
                   bg-white p-5
                   shadow-sm"
        >

            <p
                class="text-xs font-bold
                       uppercase
                       tracking-[0.14em]
                       text-slate-400"
            >
                Total RFAs
            </p>

            <p
                class="mt-3 text-3xl
                       font-bold
                       tracking-tight
                       text-slate-950"
            >
                {{ number_format(
                    $summary['total']
                ) }}
            </p>

        </div>


        {{-- PENDING --}}

        <div
            class="rounded-2xl
                   border border-amber-200
                   bg-amber-50 p-5
                   shadow-sm"
        >

            <p
                class="text-xs font-bold
                       uppercase
                       tracking-[0.14em]
                       text-amber-600"
            >
                Pending
            </p>

            <p
                class="mt-3 text-3xl
                       font-bold
                       tracking-tight
                       text-amber-900"
            >
                {{ number_format(
                    $summary['pending']
                ) }}
            </p>

        </div>


        {{-- ONGOING --}}

        <div
            class="rounded-2xl
                   border border-blue-200
                   bg-blue-50 p-5
                   shadow-sm"
        >

            <p
                class="text-xs font-bold
                       uppercase
                       tracking-[0.14em]
                       text-blue-600"
            >
                Ongoing
            </p>

            <p
                class="mt-3 text-3xl
                       font-bold
                       tracking-tight
                       text-blue-900"
            >
                {{ number_format(
                    $summary['ongoing']
                ) }}
            </p>

        </div>


        {{-- DISPOSED --}}

        <div
            class="rounded-2xl
                   border border-emerald-200
                   bg-emerald-50 p-5
                   shadow-sm"
        >

            <p
                class="text-xs font-bold
                       uppercase
                       tracking-[0.14em]
                       text-emerald-600"
            >
                Disposed
            </p>

            <p
                class="mt-3 text-3xl
                       font-bold
                       tracking-tight
                       text-emerald-900"
            >
                {{ number_format(
                    $summary['disposed']
                ) }}
            </p>

        </div>

    </section>


    {{-- ========================================================= --}}
    {{-- FILTER PANEL --}}
    {{-- ========================================================= --}}

    <section
        x-data="{
            filtersOpen: true
        }"
        class="overflow-hidden
               rounded-2xl
               border border-slate-200
               bg-white shadow-sm"
    >

        {{-- FILTER HEADER --}}

        <div
            class="flex items-center
                   justify-between gap-4
                   border-b border-slate-100
                   px-5 py-4
                   sm:px-6"
        >

            <div>

                <h2
                    class="font-bold
                           text-slate-950"
                >
                    Search & Filters
                </h2>

                <p
                    class="mt-1 text-xs
                           text-slate-500"
                >
                    Refine the master RFA listing.
                </p>

            </div>


            <button
                type="button"
                @click="
                    filtersOpen =
                        ! filtersOpen
                "

                class="inline-flex
                       items-center gap-2
                       rounded-lg
                       px-3 py-2
                       text-sm font-semibold
                       text-slate-600
                       transition
                       hover:bg-slate-100"
            >

                <span
                    x-text="
                        filtersOpen
                            ? 'Hide Filters'
                            : 'Show Filters'
                    "
                ></span>


                <svg
                    class="h-4 w-4 transition"
                    :class="
                        filtersOpen
                            ? 'rotate-180'
                            : ''
                    "
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.8"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="m6 9 6 6 6-6"
                    />
                </svg>

            </button>

        </div>


        {{-- FILTER FORM --}}

        <div
             x-show="filtersOpen"
            x-cloak
        >

            <form
                method="GET"
                action="{{ route('listing') }}"
                class="p-5 sm:p-6"
            >

                <input
                    type="hidden"
                    name="sort"
                    value="{{ $sort }}"
                >

                <input
                    type="hidden"
                    name="direction"
                    value="{{ $direction }}"
                >


                <div
                    class="grid grid-cols-1
                           gap-5
                           md:grid-cols-2
                           xl:grid-cols-4"
                >

                    {{-- SEARCH --}}

                    <div
                        class="md:col-span-2
                               xl:col-span-2"
                    >

                        <label
                            for="search"
                            class="mb-2 block
                                   text-sm font-semibold
                                   text-slate-700"
                        >
                            Search
                        </label>


                        <div class="relative">

                            <svg
                                class="pointer-events-none
                                       absolute left-3.5 top-1/2
                                       h-5 w-5
                                       -translate-y-1/2
                                       text-slate-400"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.8"
                            >
                                <circle
                                    cx="11"
                                    cy="11"
                                    r="7"
                                />

                                <path
                                    stroke-linecap="round"
                                    d="m20 20-3.5-3.5"
                                />
                            </svg>


                            <input
                                type="search"
                                id="search"
                                name="search"
                                value="{{ request('search') }}"
                                placeholder="Docket no., reference no., complainant, company..."

                                class="w-full
                                       rounded-xl
                                       border border-slate-300
                                       bg-white
                                       py-3 pl-11 pr-4
                                       text-sm
                                       text-slate-700
                                       shadow-sm
                                       outline-none
                                       transition
                                       placeholder:text-slate-400
                                       focus:border-blue-500
                                       focus:ring-4
                                       focus:ring-blue-100"
                            >

                        </div>

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
                                   text-sm
                                   text-slate-700
                                   shadow-sm
                                   outline-none
                                   focus:border-blue-500
                                   focus:ring-4
                                   focus:ring-blue-100"
                        >

                            <option value="">
                                All Monitoring
                            </option>

                            <option
                                value="pending"
                                @selected(
                                    request(
                                        'monitoring_bucket'
                                    ) === 'pending'
                                )
                            >
                                Pending
                            </option>

                            <option
                                value="ongoing"
                                @selected(
                                    request(
                                        'monitoring_bucket'
                                    ) === 'ongoing'
                                )
                            >
                                Ongoing
                            </option>

                            <option
                                value="disposed"
                                @selected(
                                    request(
                                        'monitoring_bucket'
                                    ) === 'disposed'
                                )
                            >
                                Disposed
                            </option>

                        </select>

                    </div>


                    {{-- STATUS --}}

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
                                   text-sm
                                   text-slate-700
                                   shadow-sm
                                   outline-none
                                   focus:border-blue-500
                                   focus:ring-4
                                   focus:ring-blue-100"
                        >

                            <option value="">
                                All Statuses
                            </option>

                            @foreach (
                                $statuses
                                as $statusOption
                            )

                                <option
                                    value="{{ $statusOption }}"
                                    @selected(
                                        request('status')
                                        === $statusOption
                                    )
                                >
                                    {{
                                        \Illuminate\Support\Str::headline(
                                            $statusOption
                                        )
                                    }}
                                </option>

                            @endforeach

                        </select>

                    </div>


                    {{-- MODE OF FILING --}}

                    <div>

                        <label
                            for="mode_of_filing"
                            class="mb-2 block
                                   text-sm font-semibold
                                   text-slate-700"
                        >
                            Mode of Filing
                        </label>

                        <select
                            id="mode_of_filing"
                            name="mode_of_filing"

                            class="w-full
                                   rounded-xl
                                   border border-slate-300
                                   bg-white
                                   px-4 py-3
                                   text-sm
                                   text-slate-700
                                   shadow-sm
                                   outline-none
                                   focus:border-blue-500
                                   focus:ring-4
                                   focus:ring-blue-100"
                        >

                            <option value="">
                                All Filing Modes
                            </option>

                            @foreach (
                                $filingModes
                                as $modeOption
                            )

                                <option
                                    value="{{ $modeOption }}"
                                    @selected(
                                        request(
                                            'mode_of_filing'
                                        ) === $modeOption
                                    )
                                >
                                    {{
                                        \Illuminate\Support\Str::headline(
                                            $modeOption
                                        )
                                    }}
                                </option>

                            @endforeach

                        </select>

                    </div>


                    {{-- DISPOSITION --}}

                    <div>

                        <label
                            for="disposition_status"
                            class="mb-2 block
                                   text-sm font-semibold
                                   text-slate-700"
                        >
                            Disposition
                        </label>

                        <select
                            id="disposition_status"
                            name="disposition_status"

                            class="w-full
                                   rounded-xl
                                   border border-slate-300
                                   bg-white
                                   px-4 py-3
                                   text-sm
                                   text-slate-700
                                   shadow-sm
                                   outline-none
                                   focus:border-blue-500
                                   focus:ring-4
                                   focus:ring-blue-100"
                        >

                            <option value="">
                                All Dispositions
                            </option>

                            @foreach (
                                $dispositions
                                as $dispositionOption
                            )

                                <option
                                    value="{{ $dispositionOption }}"
                                    @selected(
                                        request(
                                            'disposition_status'
                                        ) ===
                                        $dispositionOption
                                    )
                                >
                                    {{
                                        \Illuminate\Support\Str::headline(
                                            $dispositionOption
                                        )
                                    }}
                                </option>

                            @endforeach

                        </select>

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
                            type="date"
                            id="date_from"
                            name="date_from"
                            value="{{ request('date_from') }}"

                            class="w-full
                                   rounded-xl
                                   border border-slate-300
                                   bg-white
                                   px-4 py-3
                                   text-sm
                                   text-slate-700
                                   shadow-sm
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
                            type="date"
                            id="date_to"
                            name="date_to"
                            value="{{ request('date_to') }}"

                            class="w-full
                                   rounded-xl
                                   border border-slate-300
                                   bg-white
                                   px-4 py-3
                                   text-sm
                                   text-slate-700
                                   shadow-sm
                                   outline-none
                                   focus:border-blue-500
                                   focus:ring-4
                                   focus:ring-blue-100"
                        >

                    </div>


                    {{-- ENTRIES --}}

                    <div>

                        <label
                            for="per_page"
                            class="mb-2 block
                                   text-sm font-semibold
                                   text-slate-700"
                        >
                            Records Per Page
                        </label>

                        <select
                            id="per_page"
                            name="per_page"

                            class="w-full
                                   rounded-xl
                                   border border-slate-300
                                   bg-white
                                   px-4 py-3
                                   text-sm
                                   text-slate-700
                                   shadow-sm
                                   outline-none
                                   focus:border-blue-500
                                   focus:ring-4
                                   focus:ring-blue-100"
                        >

                            @foreach (
                                [
                                    '10' => '10',
                                    '20' => '20',
                                    '50' => '50',
                                    'all' => 'All',
                                ]
                                as $value => $label
                            )

                                <option
                                    value="{{ $value }}"
                                    @selected(
                                        $perPage === $value
                                    )
                                >
                                    {{ $label }}
                                </option>

                            @endforeach

                        </select>

                    </div>

                </div>


                {{-- FILTER ACTIONS --}}

                <div
                    class="mt-6 flex
                           flex-col gap-3
                           border-t border-slate-100
                           pt-5
                           sm:flex-row
                           sm:items-center
                           sm:justify-between"
                >

                    <p
                        class="text-sm
                               text-slate-500"
                    >
                        <span
                            class="font-bold
                                   text-slate-900"
                        >
                            {{
                                number_format(
                                    $filteredCount
                                )
                            }}
                        </span>

                        matching record{{ $filteredCount === 1 ? '' : 's' }}
                    </p>


                    <div
                        class="flex flex-col
                               gap-2
                               sm:flex-row"
                    >

                        <a
                            href="{{ route('listing') }}"

                            class="inline-flex
                                   items-center
                                   justify-center
                                   rounded-xl
                                   border
                                   border-slate-300
                                   bg-white
                                   px-5 py-2.5
                                   text-sm font-semibold
                                   text-slate-700
                                   transition
                                   hover:bg-slate-50"
                        >
                            Clear Filters
                        </a>


                        <button
                            type="submit"

                            class="inline-flex
                                   items-center
                                   justify-center
                                   gap-2
                                   rounded-xl
                                   bg-slate-950
                                   px-5 py-2.5
                                   text-sm font-semibold
                                   text-white
                                   transition
                                   hover:bg-slate-800"
                        >

                            <svg
                                class="h-4 w-4"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.8"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="
                                        M4 5h16
                                        M7 12h10
                                        M10 19h4
                                    "
                                />
                            </svg>

                            Apply Filters

                        </button>

                    </div>

                </div>

            </form>

        </div>

    </section>


    {{-- ========================================================= --}}
    {{-- MASTER TABLE --}}
    {{-- ========================================================= --}}

    <section
        class="overflow-hidden
               rounded-2xl
               border border-slate-200
               bg-white shadow-sm"
    >

        {{-- TABLE HEADER --}}

        <div
            class="flex flex-col
                   justify-between gap-3
                   border-b border-slate-100
                   px-5 py-5
                   sm:flex-row
                   sm:items-center
                   sm:px-6"
        >

            <div>

                <p
                    class="text-xs font-bold
                           uppercase
                           tracking-[0.15em]
                           text-blue-600"
                >
                    Master Register
                </p>

                <h2
                    class="mt-1
                           text-lg font-bold
                           text-slate-950"
                >
                    RFA Records
                </h2>

            </div>


            <p
                class="text-sm
                       text-slate-500"
            >
                Showing
                <span
                    class="font-semibold
                           text-slate-900"
                >
                    {{ number_format(
                        $rfas->count()
                    ) }}
                </span>
                of
                <span
                    class="font-semibold
                           text-slate-900"
                >
                    {{
                        number_format(
                            $filteredCount
                        )
                    }}
                </span>
                filtered records
            </p>

        </div>


        @if ($rfas->isNotEmpty())

            <div class="overflow-x-auto">

                <table
                    class="min-w-[1250px]
                           w-full
                           divide-y
                           divide-slate-200"
                >

                    <thead class="bg-slate-50">

                        <tr>

                            {{-- # --}}

                            <th
                                class="w-16
                                       px-5 py-3.5
                                       text-left
                                       text-xs font-bold
                                       uppercase
                                       tracking-wider
                                       text-slate-500"
                            >
                                #
                            </th>


                            {{-- REFERENCE NUMBER --}}

                            <th
                                class="px-5 py-3.5
                                       text-left"
                            >

                                @php
    $docketDirection =
        $sort === 'docket_no'
        && $direction === 'asc'
            ? 'desc'
            : 'asc';
@endphp

<a
    href="{{
        route(
            'listing',
            array_merge(
                request()->query(),
                [
                    'sort' => 'docket_no',
                    'direction' => $docketDirection,
                ]
            )
        )
    }}"
    class="inline-flex items-center gap-1.5
           text-xs font-bold uppercase
           tracking-wider text-slate-500
           transition hover:text-blue-600"
>
    Docket / Reference No.

    @if ($sort === 'docket_no')
        <span class="text-blue-600">
            {{ $direction === 'asc' ? '↑' : '↓' }}
        </span>
    @endif
</a>
                            </th>


                            {{-- REQUESTING PARTY --}}

                            <th
                                class="px-5 py-3.5
                                       text-left"
                            >

                                @php
                                    $requestingDirection =
                                        $sort ===
                                        'requesting_party'
                                        && $direction === 'asc'
                                            ? 'desc'
                                            : 'asc';
                                @endphp

                                <a
                                    href="{{
                                        route(
                                            'listing',
                                            array_merge(
                                                request()->query(),
                                                [
                                                    'sort' =>
                                                        'requesting_party',

                                                    'direction' =>
                                                        $requestingDirection,
                                                ]
                                            )
                                        )
                                    }}"

                                    class="inline-flex
                                           items-center
                                           gap-1.5
                                           text-xs font-bold
                                           uppercase
                                           tracking-wider
                                           text-slate-500
                                           transition
                                           hover:text-blue-600"
                                >
                                    Requesting Party

                                    @if (
                                        $sort ===
                                        'requesting_party'
                                    )

                                        <span
                                            class="text-blue-600"
                                        >
                                            {{
                                                $direction
                                                === 'asc'
                                                    ? '↑'
                                                    : '↓'
                                            }}
                                        </span>

                                    @endif

                                </a>

                            </th>


                            {{-- RESPONDING PARTY --}}

                            <th
                                class="px-5 py-3.5
                                       text-left"
                            >

                                @php
                                    $respondingDirection =
                                        $sort ===
                                        'responding_party'
                                        && $direction === 'asc'
                                            ? 'desc'
                                            : 'asc';
                                @endphp

                                <a
                                    href="{{
                                        route(
                                            'listing',
                                            array_merge(
                                                request()->query(),
                                                [
                                                    'sort' =>
                                                        'responding_party',

                                                    'direction' =>
                                                        $respondingDirection,
                                                ]
                                            )
                                        )
                                    }}"

                                    class="inline-flex
                                           items-center
                                           gap-1.5
                                           text-xs font-bold
                                           uppercase
                                           tracking-wider
                                           text-slate-500
                                           transition
                                           hover:text-blue-600"
                                >
                                    Responding Party

                                    @if (
                                        $sort ===
                                        'responding_party'
                                    )

                                        <span
                                            class="text-blue-600"
                                        >
                                            {{
                                                $direction
                                                === 'asc'
                                                    ? '↑'
                                                    : '↓'
                                            }}
                                        </span>

                                    @endif

                                </a>

                            </th>


                            {{-- MODE --}}

                            <th
                                class="px-5 py-3.5
                                       text-left
                                       text-xs font-bold
                                       uppercase
                                       tracking-wider
                                       text-slate-500"
                            >
                                Filing Mode
                            </th>


                            {{-- DATE FILED --}}

                            <th
                                class="px-5 py-3.5
                                       text-left"
                            >

                                @php
                                    $dateDirection =
                                        $sort === 'date_filed'
                                        && $direction === 'asc'
                                            ? 'desc'
                                            : 'asc';
                                @endphp

                                <a
                                    href="{{
                                        route(
                                            'listing',
                                            array_merge(
                                                request()->query(),
                                                [
                                                    'sort' =>
                                                        'date_filed',

                                                    'direction' =>
                                                        $dateDirection,
                                                ]
                                            )
                                        )
                                    }}"

                                    class="inline-flex
                                           items-center
                                           gap-1.5
                                           text-xs font-bold
                                           uppercase
                                           tracking-wider
                                           text-slate-500
                                           transition
                                           hover:text-blue-600"
                                >
                                    Date Filed

                                    @if (
                                        $sort ===
                                        'date_filed'
                                    )

                                        <span
                                            class="text-blue-600"
                                        >
                                            {{
                                                $direction
                                                === 'asc'
                                                    ? '↑'
                                                    : '↓'
                                            }}
                                        </span>

                                    @endif

                                </a>

                            </th>


                            {{-- WORKFLOW STATUS --}}

                            <th
                                class="px-5 py-3.5
                                       text-left
                                       text-xs font-bold
                                       uppercase
                                       tracking-wider
                                       text-slate-500"
                            >
                                Workflow Status


                            </th>


                            {{-- MONITORING --}}

                            <th
                                class="px-5 py-3.5
                                       text-left
                                       text-xs font-bold
                                       uppercase
                                       tracking-wider
                                       text-slate-500"
                            >
                                Monitoring
                            </th>


                            {{-- DISPOSITION --}}

                            <th
                                class="px-5 py-3.5
                                       text-left
                                       text-xs font-bold
                                       uppercase
                                       tracking-wider
                                       text-slate-500"
                            >
                                Disposition
                            </th>


                            {{-- CASE --}}

                            <th
                                class="px-5 py-3.5
                                       text-left
                                       text-xs font-bold
                                       uppercase
                                       tracking-wider
                                       text-slate-500"
                            >
                                Case
                            </th>

                        </tr>

                    </thead>


                    <tbody
                        class="divide-y
                               divide-slate-100
                               bg-white"
                    >

                        @foreach ($rfas as $index => $rfa)

                            @php

                                $monitoringClasses =
                                    match (
                                        $rfa->monitoring_bucket
                                    ) {
                                        'pending' =>
                                            'bg-amber-100 text-amber-700 ring-amber-600/20',

                                        'ongoing' =>
                                            'bg-blue-100 text-blue-700 ring-blue-600/20',

                                        'disposed' =>
                                            'bg-emerald-100 text-emerald-700 ring-emerald-600/20',

                                        default =>
                                            'bg-slate-100 text-slate-700 ring-slate-600/20',
                                    };


                                if ($paginator) {
                                    $rowNumber =
                                        $paginator->firstItem()
                                        + $index;
                                } else {
                                    $rowNumber =
                                        $index + 1;
                                }

                            @endphp


                            <tr
                                class="transition
                                       hover:bg-slate-50/80"
                            >

                                {{-- NUMBER --}}

                                <td
                                    class="px-5 py-4
                                           text-sm
                                           text-slate-400"
                                >
                                    {{ $rowNumber }}
                                </td>


                                {{-- REFERENCE --}}

                                <td
    class="whitespace-nowrap
           px-5 py-4"
>

    @if ($rfa->docket_no)

        <div
            class="text-sm font-bold
                   text-slate-950"
        >
            {{ $rfa->docket_no }}
        </div>

        <div
            class="mt-1 text-xs
                   text-slate-400"
        >
            System:
            {{ $rfa->reference_no }}
        </div>

    @else

        <div
            class="text-sm font-bold
                   text-slate-950"
        >
            {{ $rfa->reference_no }}
        </div>

        <div
            class="mt-1 text-xs
                   text-amber-600"
        >
            No docket number
        </div>

    @endif

</td>


                                {{-- REQUESTING PARTY --}}

                                <td
                                    class="px-5 py-4"
                                >

                                    <div
                                        class="max-w-[250px]
                                               text-sm font-medium
                                               text-slate-800"
                                    >
                                        {{
                                            $rfa->requesting_party
                                            ?: '—'
                                        }}
                                    </div>

                                </td>


                                {{-- RESPONDING PARTY --}}

                                <td
                                    class="px-5 py-4"
                                >

                                    <div
                                        class="max-w-[250px]
                                               text-sm
                                               text-slate-700"
                                    >
                                        {{
                                            $rfa->responding_party
                                            ?: '—'
                                        }}
                                    </div>

                                </td>


                                {{-- MODE OF FILING --}}

                                <td
                                    class="whitespace-nowrap
                                           px-5 py-4
                                           text-sm
                                           text-slate-600"
                                >
                                    {{
                                        $rfa->mode_of_filing
                                            ? \Illuminate\Support\Str::headline(
                                                $rfa->mode_of_filing
                                            )
                                            : '—'
                                    }}
                                </td>


                                {{-- DATE FILED --}}

                                <td
                                    class="whitespace-nowrap
                                           px-5 py-4
                                           text-sm
                                           text-slate-600"
                                >
                                    {{
                                        $rfa->date_filed
                                            ? $rfa->date_filed
                                                ->format(
                                                    'M d, Y'
                                                )
                                            : '—'
                                    }}
                                </td>


                               {{-- WORKFLOW STATUS --}}

<td class="px-5 py-4">

    {{-- SYSTEM WORKFLOW STATUS --}}

    <span
        class="inline-flex
               rounded-lg
               bg-slate-100
               px-2.5 py-1.5
               text-xs font-semibold
               text-slate-700"
    >
        {{
            $rfa->status
                ? \Illuminate\Support\Str::headline(
                    $rfa->status
                )
                : '—'
        }}
    </span>


    {{-- ORIGINAL CSV CASE STATUS --}}

    @if ($rfa->source_case_status)

        <div
            class="mt-1
                   text-xs
                   text-slate-400"
        >
            Source:
            {{ $rfa->source_case_status }}
        </div>

    @endif

</td>


                                {{-- MONITORING --}}

                                <td
                                    class="px-5 py-4"
                                >

                                    <span
                                        class="
                                            inline-flex
                                            rounded-full
                                            px-2.5 py-1
                                            text-xs font-bold
                                            ring-1
                                            ring-inset
                                            {{
                                                $monitoringClasses
                                            }}
                                        "
                                    >
                                        {{
                                            $rfa->monitoring_bucket
                                                ? \Illuminate\Support\Str::headline(
                                                    $rfa->monitoring_bucket
                                                )
                                                : '—'
                                        }}
                                    </span>

                                </td>


                               {{-- DISPOSITION --}}

<td
    class="px-5 py-4"
>

    @if (
        $rfa->disposition_status
    )

        <div
            class="text-sm
                   font-semibold
                   text-emerald-700"
        >
            {{
                \Illuminate\Support\Str::headline(
                    $rfa->disposition_status
                )
            }}
        </div>


        @if ($rfa->date_disposed)

            <div
                class="mt-1
                       text-xs
                       text-slate-400"
            >
                {{
                    $rfa->date_disposed
                        ->format(
                            'M d, Y'
                        )
                }}
            </div>

        @endif


    @else

        <span
            class="text-sm
                   text-slate-400"
        >
            —
        </span>

    @endif


    {{-- SOURCE DISPOSITION MODE FROM CSV --}}

    @if ($rfa->disposition_mode)

        <div
            class="mt-1
                   text-xs
                   font-semibold
                   text-slate-500"
        >
            Mode:
            {{ $rfa->disposition_mode }}
        </div>

    @endif

</td>


                                {{-- OPEN CASE --}}

                                <td
                                    class="whitespace-nowrap
                                           px-5 py-4"
                                >

                                    <a
                                        href="{{ route('rfas.show', $rfa) }}"
                                        class="inline-flex
                                               items-center
                                               rounded-lg
                                               border border-slate-300
                                               bg-white
                                               px-3 py-1.5
                                               text-xs font-semibold
                                               text-slate-700
                                               transition
                                               hover:bg-slate-50"
                                    >
                                        Open
                                    </a>

                                </td>

                            </tr>

                        @endforeach

                    </tbody>

                </table>

            </div>


            {{-- PAGINATION --}}

            @if ($paginator)

                <div
                    class="border-t
                           border-slate-100
                           px-5 py-4
                           sm:px-6"
                >

                    {{ $paginator->links() }}

                </div>

            @endif

        @else

            {{-- EMPTY RESULT --}}

            <div
                class="px-6 py-16
                       text-center"
            >

                <div
                    class="mx-auto
                           flex h-14 w-14
                           items-center
                           justify-center
                           rounded-2xl
                           bg-slate-100
                           text-slate-400"
                >

                    <svg
                        class="h-7 w-7"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.7"
                    >
                        <circle
                            cx="11"
                            cy="11"
                            r="7"
                        />

                        <path
                            stroke-linecap="round"
                            d="m20 20-3.5-3.5"
                        />
                    </svg>

                </div>


                <h3
                    class="mt-4
                           font-bold
                           text-slate-900"
                >
                    No RFA records found
                </h3>


                <p
                    class="mx-auto mt-2
                           max-w-md
                           text-sm
                           leading-6
                           text-slate-500"
                >
                    No records match the current
                    search and filter criteria.
                </p>


                <a
                    href="{{ route('listing') }}"

                    class="mt-5
                           inline-flex
                           rounded-xl
                           border
                           border-slate-300
                           bg-white
                           px-4 py-2.5
                           text-sm font-semibold
                           text-slate-700
                           transition
                           hover:bg-slate-50"
                >
                    Clear Filters
                </a>

            </div>

        @endif

    </section>

</div>

@endsection
