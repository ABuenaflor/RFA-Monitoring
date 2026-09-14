@extends('layouts.app')


@section(
    'title',
    'Data Governance | RFA Monitoring System'
)


@section(
    'page_heading',
    'Data Governance'
)


@section('page_actions')

    <div class="flex items-center gap-2">

        <a
            href="{{ route('admin.governance.export') }}"
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
            Export Findings
        </a>


        @can('import.manage')

            <a
                href="{{ route('imports.index') }}"
                class="inline-flex items-center
                       justify-center
                       rounded-xl
                       bg-blue-600 px-4 py-2.5
                       text-sm font-semibold
                       text-white shadow-sm
                       transition
                       hover:bg-blue-700"
            >
                CSV Import
            </a>

        @endcan

    </div>

@endsection


@section('content')

<div class="space-y-7">

    <section>

        <p
            class="max-w-4xl text-sm
                   leading-6 text-slate-500"
        >
            Standing checks over the RFA data, plus the history of every CSV
            import. Each check is a live query, not a stored flag, so a finding
            disappears the moment the underlying record is corrected.
        </p>

    </section>


    {{-- ========================================================= --}}
    {{-- SUMMARY --}}
    {{-- ========================================================= --}}

    <section
        class="grid grid-cols-2 gap-4
               xl:grid-cols-5"
    >

        @foreach ([
            ['Records', 'total_records', 'border-slate-200 bg-white text-slate-950'],
            ['Checks Run', 'checks', 'border-slate-200 bg-white text-slate-950'],
            ['Checks Passing', 'passing', 'border-emerald-200 bg-emerald-50 text-emerald-950'],
            ['Checks Failing', 'failing', 'border-amber-200 bg-amber-50 text-amber-950'],
            ['Records Flagged', 'affected', 'border-rose-200 bg-rose-50 text-rose-950'],
        ] as $card)

            <div
                class="rounded-2xl border p-5
                       shadow-sm {{ $card[2] }}"
            >

                <p
                    class="text-xs font-bold
                           uppercase tracking-wider
                           opacity-70"
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
    {{-- FINDINGS --}}
    {{-- ========================================================= --}}

    <section class="space-y-4">

        <div>

            <p
                class="text-xs font-bold
                       uppercase tracking-[0.15em]
                       text-blue-600"
            >
                Data Quality
            </p>

            <h2
                class="mt-1 text-lg
                       font-bold text-slate-950"
            >
                Standing Checks
            </h2>

        </div>


        <div
            class="grid grid-cols-1 gap-4
                   xl:grid-cols-2"
        >

            @foreach ($findings as $finding)

                @php
                    $clean = $finding['count'] === 0;

                    $tone = match (true) {
                        $clean => ['border-emerald-200', 'bg-emerald-50', 'text-emerald-700', 'Clear'],

                        $finding['severity'] === \App\Services\DataQualityService::SEVERITY_CRITICAL
                            => ['border-rose-200', 'bg-rose-50', 'text-rose-700', 'Critical'],

                        $finding['severity'] === \App\Services\DataQualityService::SEVERITY_WARNING
                            => ['border-amber-200', 'bg-amber-50', 'text-amber-700', 'Warning'],

                        default
                            => ['border-slate-200', 'bg-slate-50', 'text-slate-600', 'Informational'],
                    };
                @endphp

                <div
                    class="rounded-2xl border
                           {{ $tone[0] }}
                           bg-white p-6 shadow-sm"
                >

                    <div
                        class="flex items-start
                               justify-between gap-4"
                    >

                        <div class="min-w-0">

                            <p
                                class="text-xs font-bold
                                       uppercase tracking-[0.15em]
                                       {{ $tone[2] }}"
                            >
                                {{ $tone[3] }}
                            </p>

                            <h3
                                class="mt-1 text-base
                                       font-bold text-slate-950"
                            >
                                {{ $finding['label'] }}
                            </h3>

                        </div>


                        <span
                            class="shrink-0 rounded-xl
                                   {{ $tone[1] }}
                                   px-3 py-1.5
                                   text-lg font-bold
                                   {{ $tone[2] }}"
                        >
                            {{ number_format($finding['count']) }}
                        </span>

                    </div>


                    <p
                        class="mt-3 text-sm
                               leading-6 text-slate-600"
                    >
                        {{ $finding['description'] }}
                    </p>

                    <p
                        class="mt-2 text-xs
                               leading-5 text-slate-500"
                    >
                        <span class="font-semibold">Why it matters:</span>
                        {{ $finding['impact'] }}
                    </p>


                    @if ($finding['samples'] !== [])

                        <div
                            class="mt-4 border-t
                                   border-slate-100 pt-4"
                        >

                            <p
                                class="text-xs font-bold
                                       uppercase tracking-wider
                                       text-slate-400"
                            >
                                Examples
                            </p>

                            <div
                                class="mt-2 flex
                                       flex-wrap gap-2"
                            >

                                @foreach ($finding['samples'] as $sample)

                                    <a
                                        href="{{ route('rfas.show', $sample->id) }}"
                                        class="rounded-lg
                                               border border-slate-200
                                               bg-slate-50 px-2.5 py-1
                                               font-mono text-xs
                                               text-slate-600
                                               transition
                                               hover:bg-slate-100"
                                    >
                                        {{ $sample->docket_no ?: $sample->reference_no }}
                                    </a>

                                @endforeach

                            </div>

                        </div>

                    @endif

                </div>

            @endforeach

        </div>

    </section>


    {{-- ========================================================= --}}
    {{-- IMPORT HISTORY --}}
    {{-- ========================================================= --}}

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
                       text-violet-600"
            >
                Data Intake
            </p>

            <h2
                class="mt-1 text-lg
                       font-bold text-slate-950"
            >
                Import Batch History
            </h2>

            <p
                class="mt-2 text-sm
                       leading-6 text-slate-500"
            >
                Assembled from the records themselves, so batches imported
                before the batch register existed still appear — with their
                file and user details shown as unrecorded.
            </p>

        </div>


        <div class="overflow-x-auto">

            <table
                class="w-full min-w-[1050px]
                       divide-y divide-slate-200"
            >

                <thead class="bg-slate-50">

                    <tr>

                        @foreach ([
                            'Batch',
                            'File',
                            'Imported By',
                            'Records',
                            'Source Rows',
                            'Duplicates Skipped',
                            'First Imported',
                            '',
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

                    @forelse ($batches as $batch)

                        <tr class="transition hover:bg-slate-50/80">

                            <td
                                class="px-4 py-4
                                       font-mono text-xs
                                       text-slate-500"
                            >
                                {{ \Illuminate\Support\Str::limit($batch['uuid'], 13) }}
                            </td>


                            <td
                                class="px-4 py-4 text-sm
                                       font-semibold
                                       text-slate-900"
                            >
                                {{ $batch['file_name'] ?? 'Not recorded' }}
                            </td>


                            <td
                                class="px-4 py-4 text-sm
                                       text-slate-600"
                            >
                                {{ $batch['user_name'] ?? 'Not recorded' }}
                            </td>


                            <td
                                class="px-4 py-4 text-sm
                                       font-semibold
                                       text-slate-900"
                            >
                                {{ number_format($batch['record_count']) }}
                            </td>


                            <td
                                class="px-4 py-4 text-sm
                                       text-slate-600"
                            >
                                {{ $batch['rows_processed'] !== null
                                    ? number_format($batch['rows_processed'])
                                    : '—' }}
                            </td>


                            <td
                                class="px-4 py-4 text-sm
                                       text-slate-600"
                            >
                                {{ $batch['duplicates_skipped'] !== null
                                    ? number_format($batch['duplicates_skipped'])
                                    : '—' }}
                            </td>


                            <td
                                class="whitespace-nowrap px-4 py-4
                                       text-sm text-slate-500"
                            >
                                {{ \Illuminate\Support\Carbon::parse($batch['first_seen'])->format('M d, Y g:i A') }}
                            </td>


                            <td class="px-4 py-4">

                                @can('import.manage')

                                    <a
                                        href="{{ route('imports.index', ['batch' => $batch['uuid']]) }}"
                                        class="text-sm font-semibold
                                               text-blue-600
                                               transition
                                               hover:text-blue-700"
                                    >
                                        Review
                                    </a>

                                @endcan

                            </td>

                        </tr>

                    @empty

                        <tr>

                            <td
                                colspan="8"
                                class="px-6 py-14
                                       text-center text-sm
                                       text-slate-500"
                            >
                                No CSV imports have been recorded yet.
                            </td>

                        </tr>

                    @endforelse

                </tbody>

            </table>

        </div>

    </section>


    {{-- ========================================================= --}}
    {{-- GOVERNANCE NOTE --}}
    {{-- ========================================================= --}}

    <section
        class="rounded-2xl
               border border-slate-200
               bg-slate-50 p-6 shadow-sm"
    >

        <p
            class="text-xs font-bold
                   uppercase tracking-[0.15em]
                   text-slate-500"
        >
            Governance Position
        </p>

        <h2
            class="mt-1 text-lg
                   font-bold text-slate-950"
        >
            What the system will and will not infer
        </h2>

        <ul
            class="mt-4 space-y-2 text-sm
                   leading-6 text-slate-600"
        >

            <li>
                The official <span class="font-semibold">disposition status</span>
                and the raw CSV <span class="font-semibold">Mode</span> are
                stored separately and never substituted for each other.
            </li>

            <li>
                Raw mode values such as SC, RCA, SWBF, LOI, NSWBF, DP, ROGO, and
                RVA are carried through as text. No meaning is inferred from
                them without an approved mapping.
            </li>

            <li>
                A 1st Conference date is not a Date of Interview, and a missing
                historical interview date is never converted into a Beyond PCT
                result.
            </li>

            <li>
                <span class="font-semibold">reference_no</span> is the unique
                internal identifier. <span class="font-semibold">docket_no</span>
                is not guaranteed unique and repeats are reported as
                informational only.
            </li>

        </ul>

    </section>

</div>

@endsection
