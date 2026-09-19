@extends('layouts.app')


@section(
    'title',
    'Release Readiness | RFA Monitoring System'
)


@section(
    'page_heading',
    'Release Readiness'
)


@section('content')

@php
    use App\Services\ReadinessService;
    use App\Support\UatPlan;

    $canManage = auth()->user()->hasPermission('readiness.manage');

    $statusTone = [
        ReadinessService::PASS => ['bg-emerald-50', 'text-emerald-700', 'Pass'],
        ReadinessService::WARN => ['bg-amber-50', 'text-amber-700', 'Review'],
        ReadinessService::FAIL => ['bg-rose-50', 'text-rose-700', 'Fail'],
    ];

    $uatTone = [
        UatPlan::STATUS_PASSED => 'bg-emerald-50 text-emerald-700',
        UatPlan::STATUS_FAILED => 'bg-rose-50 text-rose-700',
        UatPlan::STATUS_BLOCKED => 'bg-amber-50 text-amber-700',
        UatPlan::STATUS_NOT_APPLICABLE => 'bg-slate-100 text-slate-600',
        UatPlan::STATUS_PENDING => 'bg-slate-100 text-slate-500',
    ];

    $areaOrder = [];

    foreach ($checks as $check) {
        $areaOrder[$check['area']][] = $check;
    }
@endphp


<div class="space-y-7">

    <section>

        <p
            class="max-w-4xl text-sm
                   leading-6 text-slate-500"
        >
            Live environment, security, data, and deployment checks against the
            running system, alongside the user acceptance test plan and the
            record of who accepted each release.
        </p>

    </section>


    {{-- ========================================================= --}}
    {{-- READINESS SUMMARY --}}
    {{-- ========================================================= --}}

    <section
        class="grid grid-cols-2 gap-4
               xl:grid-cols-4"
    >

        @foreach ([
            ['Checks Run', $checkSummary['total'], 'border-slate-200 bg-white text-slate-950'],
            ['Passing', $checkSummary['passing'], 'border-emerald-200 bg-emerald-50 text-emerald-950'],
            ['Needs Review', $checkSummary['warnings'], 'border-amber-200 bg-amber-50 text-amber-950'],
            ['Failing', $checkSummary['failing'], 'border-rose-200 bg-rose-50 text-rose-950'],
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
                    {{ number_format($card[1]) }}
                </p>

            </div>

        @endforeach

    </section>


    {{-- ========================================================= --}}
    {{-- ENVIRONMENT CHECKS --}}
    {{-- ========================================================= --}}

    @foreach ($areaOrder as $areaName => $areaChecks)

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
                           text-blue-600"
                >
                    Readiness
                </p>

                <h2
                    class="mt-1 text-lg
                           font-bold text-slate-950"
                >
                    {{ $areaName }}
                </h2>

            </div>


            <div class="divide-y divide-slate-100">

                @foreach ($areaChecks as $check)

                    @php
                        $tone = $statusTone[$check['status']];
                    @endphp

                    <div
                        class="flex flex-col gap-3
                               px-6 py-5
                               lg:flex-row lg:items-start"
                    >

                        <div class="lg:w-56 lg:shrink-0">

                            <span
                                class="rounded-full px-3 py-1
                                       text-xs font-semibold
                                       {{ $tone[0] }} {{ $tone[1] }}"
                            >
                                {{ $tone[2] }}
                            </span>

                            <p
                                class="mt-2 font-semibold
                                       text-slate-900"
                            >
                                {{ $check['label'] }}
                            </p>

                        </div>


                        <div class="min-w-0 flex-1">

                            <p
                                class="text-sm
                                       font-medium text-slate-700"
                            >
                                {{ $check['detail'] }}
                            </p>

                            @if ($check['status'] !== ReadinessService::PASS)

                                <p
                                    class="mt-2 text-sm
                                           leading-6 text-slate-500"
                                >
                                    {{ $check['recommendation'] }}
                                </p>

                            @endif

                            <p
                                class="mt-1 font-mono
                                       text-[11px] text-slate-300"
                            >
                                {{ $check['key'] }}
                            </p>

                        </div>

                    </div>

                @endforeach

            </div>

        </section>

    @endforeach


    {{-- ========================================================= --}}
    {{-- UAT SUMMARY --}}
    {{-- ========================================================= --}}

    <section
        class="grid grid-cols-2 gap-4
               xl:grid-cols-4"
    >

        @foreach ([
            ['UAT Cases', $uatSummary['total'], 'border-slate-200 bg-white text-slate-950'],
            ['Passed', $uatSummary['passed'], 'border-emerald-200 bg-emerald-50 text-emerald-950'],
            ['Failed / Blocked', $uatSummary['failed'] + $uatSummary['blocked'], 'border-rose-200 bg-rose-50 text-rose-950'],
            ['Not Tested', $uatSummary['pending'], 'border-amber-200 bg-amber-50 text-amber-950'],
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
                    {{ number_format($card[1]) }}
                </p>

            </div>

        @endforeach

    </section>


    {{-- ========================================================= --}}
    {{-- UAT PLAN --}}
    {{-- ========================================================= --}}

    @foreach ($areas as $areaName => $cases)

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
                    User Acceptance Testing
                </p>

                <h2
                    class="mt-1 text-lg
                           font-bold text-slate-950"
                >
                    {{ $areaName }}
                </h2>

            </div>


            <div class="divide-y divide-slate-100">

                @foreach ($cases as $key => $case)

                    @php
                        $result = $results->get($key);

                        $status = $result?->status ?? UatPlan::STATUS_PENDING;
                    @endphp

                    <div class="px-6 py-5">

                        <div
                            class="flex flex-wrap
                                   items-start
                                   justify-between gap-3"
                        >

                            <div class="min-w-0">

                                <p
                                    class="font-semibold
                                           text-slate-900"
                                >
                                    {{ $case['title'] }}
                                </p>

                                <p
                                    class="mt-1 font-mono
                                           text-[11px] text-slate-300"
                                >
                                    {{ $key }}
                                </p>

                            </div>


                            <span
                                class="shrink-0 rounded-full
                                       px-3 py-1
                                       text-xs font-semibold
                                       {{ $uatTone[$status] }}"
                            >
                                {{ $statuses[$status] }}
                            </span>

                        </div>


                        <dl
                            class="mt-3 grid grid-cols-1
                                   gap-3 text-sm md:grid-cols-2"
                        >

                            <div class="rounded-xl bg-slate-50 p-3">

                                <dt
                                    class="text-xs font-bold
                                           uppercase text-slate-400"
                                >
                                    Steps
                                </dt>

                                <dd
                                    class="mt-1 leading-6
                                           text-slate-600"
                                >
                                    {{ $case['steps'] }}
                                </dd>

                            </div>


                            <div class="rounded-xl bg-slate-50 p-3">

                                <dt
                                    class="text-xs font-bold
                                           uppercase text-slate-400"
                                >
                                    Expected
                                </dt>

                                <dd
                                    class="mt-1 leading-6
                                           text-slate-600"
                                >
                                    {{ $case['expected'] }}
                                </dd>

                            </div>

                        </dl>


                        @if ($result?->notes)

                            <p
                                class="mt-3 rounded-xl
                                       border border-slate-200
                                       bg-white px-4 py-3
                                       text-sm leading-6
                                       text-slate-600"
                            >
                                {{ $result->notes }}
                            </p>

                        @endif


                        @if ($result?->tested_at)

                            <p
                                class="mt-2 text-xs
                                       text-slate-400"
                            >
                                Recorded by
                                {{ $result->tester?->name ?? 'Unknown' }}
                                on
                                {{ $result->tested_at->format('M d, Y g:i A') }}.
                            </p>

                        @endif


                        @if ($canManage)

                            <form
                                method="POST"
                                action="{{ route('admin.readiness.results') }}"
                                class="mt-4 flex flex-col gap-2 sm:flex-row"
                            >

                                @csrf

                                <input
                                    type="hidden"
                                    name="case_key"
                                    value="{{ $key }}"
                                >

                                <select
                                    name="status"
                                    class="rounded-xl
                                           border border-slate-300
                                           bg-white px-3 py-2.5 text-sm
                                           sm:w-48"
                                >

                                    @foreach ($statuses as $statusKey => $statusLabel)

                                        <option
                                            value="{{ $statusKey }}"
                                            @selected($status === $statusKey)
                                        >
                                            {{ $statusLabel }}
                                        </option>

                                    @endforeach

                                </select>

                                <input
                                    name="notes"
                                    maxlength="2000"
                                    value="{{ $result?->notes }}"
                                    placeholder="Observation, defect reference, or reason"
                                    class="w-full rounded-xl
                                           border border-slate-300
                                           px-3 py-2.5 text-sm
                                           outline-none
                                           focus:border-blue-500
                                           focus:ring-4 focus:ring-blue-100"
                                >

                                <button
                                    type="submit"
                                    class="shrink-0 rounded-xl
                                           bg-slate-950 px-4 py-2.5
                                           text-sm font-semibold
                                           text-white shadow-sm
                                           transition
                                           hover:bg-slate-800"
                                >
                                    Record
                                </button>

                            </form>

                        @endif

                    </div>

                @endforeach

            </div>

        </section>

    @endforeach


    {{-- ========================================================= --}}
    {{-- SIGN-OFF --}}
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
                       text-emerald-600"
            >
                Acceptance
            </p>

            <h2
                class="mt-1 text-lg
                       font-bold text-slate-950"
            >
                Release Sign-off
            </h2>

            <p
                class="mt-2 text-sm
                       leading-6 text-slate-500"
            >
                Each sign-off freezes the readiness picture as it stood at that
                moment, so it can never be read as covering a state it did not
                see.
            </p>

        </div>


        @if ($canManage)

            <div
                class="border-b border-slate-100
                       bg-slate-50/60 px-6 py-5"
            >

                <form
                    method="POST"
                    action="{{ route('admin.readiness.signoff') }}"
                    class="grid grid-cols-1 gap-3
                           md:grid-cols-4"
                >

                    @csrf

                    <input
                        name="version"
                        required
                        maxlength="50"
                        placeholder="Version, e.g. 1.0.0"
                        class="rounded-xl
                               border border-slate-300
                               px-4 py-3 text-sm"
                    >

                    <input
                        name="environment"
                        required
                        maxlength="50"
                        value="{{ app()->environment() }}"
                        placeholder="Environment"
                        class="rounded-xl
                               border border-slate-300
                               px-4 py-3 text-sm"
                    >

                    <input
                        name="summary"
                        maxlength="500"
                        placeholder="Scope accepted, outstanding items"
                        class="rounded-xl
                               border border-slate-300
                               px-4 py-3 text-sm"
                    >

                    <button
                        type="submit"
                        class="rounded-xl bg-emerald-600
                               px-5 py-3
                               text-sm font-semibold
                               text-white shadow-sm
                               transition
                               hover:bg-emerald-700"
                    >
                        Record Sign-off
                    </button>

                </form>

            </div>

        @endif


        <div class="overflow-x-auto">

            <table
                class="w-full min-w-[900px]
                       divide-y divide-slate-200"
            >

                <thead class="bg-slate-50">

                    <tr>

                        @foreach ([
                            'Version',
                            'Environment',
                            'Signed By',
                            'Date',
                            'UAT At Sign-off',
                            'Checks Failing',
                            'Summary',
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

                    @forelse ($signoffs as $signoff)

                        <tr class="transition hover:bg-slate-50/80">

                            <td
                                class="px-4 py-4 text-sm
                                       font-semibold
                                       text-slate-900"
                            >
                                {{ $signoff->version }}
                            </td>

                            <td
                                class="px-4 py-4 text-sm
                                       text-slate-600"
                            >
                                {{ $signoff->environment }}
                            </td>

                            <td
                                class="px-4 py-4 text-sm
                                       text-slate-600"
                            >
                                {{ $signoff->signer?->name ?? 'Unknown' }}
                            </td>

                            <td
                                class="whitespace-nowrap px-4 py-4
                                       text-sm text-slate-500"
                            >
                                {{ $signoff->signed_at?->format('M d, Y g:i A') }}
                            </td>

                            <td
                                class="px-4 py-4 text-sm
                                       text-slate-600"
                            >
                                {{ $signoff->cases_passed }} / {{ $signoff->cases_total }}
                            </td>

                            <td class="px-4 py-4">

                                <span
                                    @class([
                                        'rounded-full px-3 py-1 text-xs font-semibold',
                                        'bg-emerald-50 text-emerald-700' => $signoff->checks_failing === 0,
                                        'bg-rose-50 text-rose-700' => $signoff->checks_failing > 0,
                                    ])
                                >
                                    {{ $signoff->checks_failing }}
                                </span>

                            </td>

                            <td
                                class="px-4 py-4 text-sm
                                       text-slate-600"
                            >
                                {{ $signoff->summary ?? '—' }}
                            </td>

                        </tr>

                    @empty

                        <tr>

                            <td
                                colspan="7"
                                class="px-6 py-14
                                       text-center text-sm
                                       text-slate-500"
                            >
                                No release has been signed off yet.
                            </td>

                        </tr>

                    @endforelse

                </tbody>

            </table>

        </div>

    </section>


    {{-- ========================================================= --}}
    {{-- HANDOVER --}}
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
            Operational Handover
        </p>

        <h2
            class="mt-1 text-lg
                   font-bold text-slate-950"
        >
            Where the written procedures live
        </h2>

        <div
            class="mt-5 grid grid-cols-1
                   gap-4 md:grid-cols-3"
        >

            @foreach ([
                'docs/OPERATIONS_MANUAL.md' => 'Day-to-day running: accounts, imports, the PCT scan, backups, and the scheduler.',
                'docs/DEPLOYMENT.md' => 'Deploying a new version and rolling one back, step by step.',
                'docs/CODE_WALKTHROUGH.md' => 'What each part of the codebase does and why it is built that way.',
            ] as $file => $description)

                <div
                    class="rounded-xl
                           border border-slate-200
                           bg-white p-4"
                >

                    <p
                        class="font-mono text-xs
                               font-semibold text-blue-600"
                    >
                        {{ $file }}
                    </p>

                    <p
                        class="mt-2 text-sm
                               leading-6 text-slate-600"
                    >
                        {{ $description }}
                    </p>

                </div>

            @endforeach

        </div>

    </section>

</div>

@endsection
