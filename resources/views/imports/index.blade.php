@extends('layouts.app')


@section(
    'title',
    'Import CSV | RFA Monitoring System'
)


@section(
    'page_heading',
    'Import CSV'
)


@section('page_actions')

    <a
        href="{{ route('dashboard') }}"
        class="inline-flex items-center gap-2
               rounded-xl border border-slate-200
               bg-white px-4 py-2.5
               text-sm font-semibold text-slate-700
               shadow-sm transition
               hover:bg-slate-50
               hover:text-slate-950"
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
                d="m15 18-6-6 6-6"
            />
        </svg>

        Dashboard

    </a>

@endsection


@section('content')

<div class="space-y-7">

    {{-- ========================================================= --}}
    {{-- SUCCESS MESSAGE --}}
    {{-- ========================================================= --}}

    @if (session('success'))

        <div
            class="rounded-2xl border
                   border-emerald-200
                   bg-emerald-50
                   px-5 py-4
                   text-sm font-medium
                   text-emerald-800"
        >

            {{ session('success') }}

        </div>

    @endif


    {{-- ========================================================= --}}
    {{-- VALIDATION ERRORS --}}
    {{-- ========================================================= --}}

    @if ($errors->any())

        <div
            class="rounded-2xl border
                   border-red-200
                   bg-red-50
                   px-5 py-4"
        >

            <p
                class="font-semibold
                       text-red-800"
            >
                Import failed
            </p>

            <ul
                class="mt-2 list-disc
                       space-y-1 pl-5
                       text-sm text-red-700"
            >

                @foreach ($errors->all() as $error)

                    <li>
                        {{ $error }}
                    </li>

                @endforeach

            </ul>

        </div>

    @endif


    {{-- ========================================================= --}}
    {{-- IMPORT FORM --}}
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

            <p
                class="text-xs font-bold
                       uppercase
                       tracking-[0.16em]
                       text-blue-600"
            >
                RFA Data Import
            </p>

            <h2
                class="mt-2 text-xl
                       font-bold
                       text-slate-950"
            >
                Upload CSV File
            </h2>

            <p
                class="mt-2 max-w-3xl
                       text-sm leading-6
                       text-slate-500"
            >
                Select a CSV file containing RFA
                records. After import, the records
                will be displayed below for review.
            </p>

        </div>


        <form
            method="POST"
            action="{{ route('imports.store') }}"
            enctype="multipart/form-data"
            class="p-6"
        >

            @csrf


            <div
                class="grid grid-cols-1
                       gap-6
                       xl:grid-cols-[minmax(0,1fr)_auto]
                       xl:items-end"
            >

                <div>

                    <label
                        for="csv_file"
                        class="mb-2 block
                               text-sm font-semibold
                               text-slate-700"
                    >
                        CSV File
                    </label>


                    <input
                        type="file"
                        id="csv_file"
                        name="csv_file"
                        accept=".csv,.txt,text/csv"
                        required

                        class="block w-full
                               rounded-xl
                               border border-slate-300
                               bg-white px-4 py-3
                               text-sm text-slate-700
                               shadow-sm
                               file:mr-4
                               file:rounded-lg
                               file:border-0
                               file:bg-slate-100
                               file:px-4
                               file:py-2
                               file:text-sm
                               file:font-semibold
                               file:text-slate-700
                               hover:file:bg-slate-200
                               focus:border-blue-500
                               focus:outline-none
                               focus:ring-4
                               focus:ring-blue-100"
                    >


                    <p
                        class="mt-2 text-xs
                               text-slate-400"
                    >
                        Maximum file size: 20 MB.
                    </p>

                </div>


                <button
                    type="submit"

                    class="inline-flex
                           min-h-12
                           items-center
                           justify-center
                           gap-2
                           rounded-xl
                           bg-blue-600
                           px-6 py-3
                           text-sm font-semibold
                           text-white
                           shadow-sm
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

                    Import CSV

                </button>

            </div>

        </form>

    </section>


    {{-- ========================================================= --}}
    {{-- SUPPORTED FIELDS --}}
    {{-- ========================================================= --}}

    <section
        class="rounded-2xl
               border border-slate-200
               bg-white p-6
               shadow-sm"
    >

        <h3
            class="font-bold
                   text-slate-950"
        >
            Recommended CSV Columns
        </h3>

        <p
            class="mt-2 text-sm
                   leading-6
                   text-slate-500"
        >
            The importer accepts common variations
            of these column names.
        </p>


        <div
            class="mt-4 overflow-x-auto
                   rounded-xl
                   bg-slate-950
                   p-4"
        >

            <code
                class="whitespace-nowrap
                       text-xs
                       text-slate-300"
            >reference_no,requesting_party,responding_party,status,mode_of_filing,date_filed,date_assigned_interviewer,date_interview,date_validated,date_turned_over_lr,date_assigned_seado,disposition_status,date_disposed</code>

        </div>

    </section>


    {{-- ========================================================= --}}
    {{-- PREVIEW --}}
    {{-- ========================================================= --}}

    @if ($batch)

        <section
            class="overflow-hidden
                   rounded-2xl
                   border border-slate-200
                   bg-white shadow-sm"
        >

            {{-- PREVIEW HEADER --}}

            <div
                class="flex flex-col
                       justify-between
                       gap-4
                       border-b
                       border-slate-100
                       px-6 py-5
                       lg:flex-row
                       lg:items-center"
            >

                <div>

                    <p
                        class="text-xs font-bold
                               uppercase
                               tracking-[0.16em]
                               text-blue-600"
                    >
                        Import Preview
                    </p>

                    <h2
                        class="mt-1 text-xl
                               font-bold
                               text-slate-950"
                    >
                        Imported Records
                    </h2>

                    <p
                        class="mt-1 text-sm
                               text-slate-500"
                    >
                        {{ number_format(
                            $totalImported
                        ) }}
                        records in this import batch.
                    </p>

                </div>


                {{-- ROW LIMIT --}}

                <form
                    method="GET"
                    action="{{ route('imports.index') }}"
                    class="flex items-center gap-3"
                >

                    <input
                        type="hidden"
                        name="batch"
                        value="{{ $batch }}"
                    >


                    <label
                        for="per_page"
                        class="text-sm
                               font-semibold
                               text-slate-600"
                    >
                        Show
                    </label>


                    <select
                        id="per_page"
                        name="per_page"
                        onchange="this.form.submit()"

                        class="rounded-xl
                               border border-slate-300
                               bg-white px-4 py-2.5
                               text-sm font-semibold
                               text-slate-700
                               focus:border-blue-500
                               focus:outline-none
                               focus:ring-4
                               focus:ring-blue-100"
                    >

                        <option
                            value="10"
                            @selected(
                                $perPage === '10'
                            )
                        >
                            10
                        </option>

                        <option
                            value="20"
                            @selected(
                                $perPage === '20'
                            )
                        >
                            20
                        </option>

                        <option
                            value="all"
                            @selected(
                                $perPage === 'all'
                            )
                        >
                            All
                        </option>

                    </select>

                    <span
                        class="text-sm text-slate-500"
                    >
                        entries
                    </span>

                </form>

            </div>


            {{-- TABLE --}}

            @if ($rows->isNotEmpty())

                <div class="overflow-x-auto">

                    <table
                        class="min-w-full
                               divide-y
                               divide-slate-200"
                    >

                        <thead class="bg-slate-50">

                            <tr>

                                <th
                                    class="whitespace-nowrap
                                           px-5 py-3
                                           text-left
                                           text-xs font-bold
                                           uppercase
                                           tracking-wider
                                           text-slate-500"
                                >
                                    System RFA No.
                                </th>

                                <th
                                    class="whitespace-nowrap
                                           px-5 py-3
                                           text-left
                                           text-xs font-bold
                                           uppercase
                                           tracking-wider
                                           text-slate-500"
                                >
                                    Monitoring
                                </th>


                                @foreach (
                                    $previewHeaders
                                    as $header
                                )

                                    <th
                                        class="whitespace-nowrap
                                               px-5 py-3
                                               text-left
                                               text-xs font-bold
                                               uppercase
                                               tracking-wider
                                               text-slate-500"
                                    >
                                        {{ $header }}
                                    </th>

                                @endforeach

                            </tr>

                        </thead>


                        <tbody
                            class="divide-y
                                   divide-slate-100
                                   bg-white"
                        >

                            @foreach ($rows as $rfa)

                                @php
                                    $payload =
                                        $rfa->import_payload
                                        ?? [];
                                @endphp

                                <tr
                                    class="transition
                                           hover:bg-slate-50"
                                >

                                    <td
                                        class="whitespace-nowrap
                                               px-5 py-4
                                               text-sm font-semibold
                                               text-slate-900"
                                    >
                                        {{ $rfa->reference_no }}
                                    </td>


                                    <td
                                        class="whitespace-nowrap
                                               px-5 py-4"
                                    >

                                        @php
                                            $bucketClasses =
                                                match (
                                                    $rfa->monitoring_bucket
                                                ) {
                                                    'pending' =>
                                                        'bg-amber-100 text-amber-700',

                                                    'ongoing' =>
                                                        'bg-blue-100 text-blue-700',

                                                    'disposed' =>
                                                        'bg-emerald-100 text-emerald-700',

                                                    default =>
                                                        'bg-slate-100 text-slate-700',
                                                };
                                        @endphp


                                        <span
                                            class="inline-flex
                                                   rounded-full
                                                   px-2.5 py-1
                                                   text-xs font-bold
                                                   {{ $bucketClasses }}"
                                        >
                                            {{
                                                ucfirst(
                                                    $rfa->monitoring_bucket
                                                )
                                            }}
                                        </span>

                                    </td>


                                    @foreach (
                                        $previewHeaders
                                        as $header
                                    )

                                        <td
                                            class="whitespace-nowrap
                                                   px-5 py-4
                                                   text-sm
                                                   text-slate-600"
                                        >
                                            {{
                                                $payload[$header]
                                                ?? '—'
                                            }}
                                        </td>

                                    @endforeach

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
                               px-6 py-4"
                    >

                        {{
                            $paginator->links()
                        }}

                    </div>

                @endif

            @else

                <div
                    class="px-6 py-16
                           text-center"
                >

                    <p
                        class="font-semibold
                               text-slate-700"
                    >
                        No imported records found.
                    </p>

                </div>

            @endif

        </section>

    @endif

</div>

@endsection
