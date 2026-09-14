@extends('layouts.app')


@section(
    'title',
    'RFA Case | RFA Monitoring System'
)


@section(
    'page_heading',
    'RFA Case Management'
)


@section('page_actions')

    <a
        href="{{ route('listing') }}"
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
        Back to Listing
    </a>

@endsection


@section('content')

@php

    use App\Support\Workflow;

    $canManage = auth()->user()->hasPermission('rfa.manage');

    $canAssign = auth()->user()->hasPermission('rfa.assign');

    $canDispose = auth()->user()->hasPermission('rfa.dispose');

    $inputClass = 'w-full rounded-xl border border-slate-300 px-4 py-3 text-sm
                   outline-none focus:border-blue-500 focus:ring-4
                   focus:ring-blue-100 disabled:bg-slate-50
                   disabled:text-slate-500';

    $labelClass = 'mb-2 block text-sm font-semibold text-slate-700';

    $dateValue = fn ($value) => $value?->format('Y-m-d') ?? '';

@endphp


<div class="space-y-7">

    {{-- ========================================================= --}}
    {{-- CASE HEADER --}}
    {{-- ========================================================= --}}

    <section
        class="rounded-2xl
               border border-slate-200
               bg-white p-6 shadow-sm"
    >

        <div
            class="flex flex-col justify-between
                   gap-5 lg:flex-row"
        >

            <div class="min-w-0">

                <p
                    class="text-xs font-bold
                           uppercase tracking-[0.15em]
                           text-blue-600"
                >
                    Case Record
                </p>

                <h1
                    class="mt-1 text-2xl
                           font-bold text-slate-950"
                >
                    {{ $rfa->docket_no ?: 'No Docket Number' }}
                </h1>

                <p
                    class="mt-1 text-sm
                           text-slate-500"
                >
                    System reference: {{ $rfa->reference_no }}
                </p>

            </div>


            <div
                class="flex flex-wrap
                       items-start gap-2"
            >

                <span
                    @class([
                        'rounded-full px-3 py-1.5 text-xs font-semibold',

                        'bg-amber-50 text-amber-700'
                            => $rfa->monitoring_bucket === Workflow::BUCKET_PENDING,

                        'bg-blue-50 text-blue-700'
                            => $rfa->monitoring_bucket === Workflow::BUCKET_ONGOING,

                        'bg-emerald-50 text-emerald-700'
                            => $rfa->monitoring_bucket === Workflow::BUCKET_DISPOSED,
                    ])
                >
                    {{ $rfa->bucketLabel() }}
                </span>

                <span
                    class="rounded-full
                           bg-indigo-50 px-3 py-1.5
                           text-xs font-semibold
                           text-indigo-700"
                >
                    {{ $rfa->statusLabel() }}
                </span>

                <span
                    class="rounded-full
                           bg-slate-100 px-3 py-1.5
                           text-xs font-semibold
                           text-slate-700"
                >
                    Source: {{ $rfa->source_case_status ?: 'Not recorded' }}
                </span>

            </div>

        </div>


        <div
            class="mt-6 grid grid-cols-1 gap-4
                   md:grid-cols-2 xl:grid-cols-4"
        >

            @foreach ([
                'Requesting Party' => $rfa->requesting_party ?: '—',
                'Responding Party' => $rfa->responding_party ?: '—',
                'Office' => $rfa->office ?: '—',
                'Date Filed' => $rfa->date_filed?->format('M d, Y') ?? '—',
            ] as $label => $value)

                <div class="rounded-xl bg-slate-50 p-4">

                    <p
                        class="text-xs font-bold
                               uppercase text-slate-400"
                    >
                        {{ $label }}
                    </p>

                    <p
                        class="mt-2 font-semibold
                               text-slate-900"
                    >
                        {{ $value }}
                    </p>

                </div>

            @endforeach

        </div>

    </section>


    {{-- ========================================================= --}}
    {{-- DATA CONSISTENCY --}}
    {{-- ========================================================= --}}

    @if ($issues !== [])

        <section
            class="rounded-2xl
                   border border-amber-200
                   bg-amber-50 p-6 shadow-sm"
        >

            <p
                class="text-xs font-bold
                       uppercase tracking-[0.15em]
                       text-amber-700"
            >
                Data Consistency
            </p>

            <h2
                class="mt-1 text-lg
                       font-bold text-amber-950"
            >
                This record has {{ count($issues) }}
                {{ \Illuminate\Support\Str::plural('inconsistency', count($issues)) }}
            </h2>

            <ul
                class="mt-4 space-y-2
                       text-sm leading-6
                       text-amber-900"
            >

                @foreach ($issues as $issue)

                    <li class="flex gap-2">

                        <span class="text-amber-500">&bull;</span>

                        <span>{{ $issue }}</span>

                    </li>

                @endforeach

            </ul>

            <p
                class="mt-4 text-xs
                       leading-5 text-amber-700"
            >
                Existing inconsistencies from imported data are reported rather
                than blocked, so they can be corrected deliberately. Saving a
                change that would add a new inconsistency is refused.
            </p>

        </section>

    @endif


    {{-- ========================================================= --}}
    {{-- PCT --}}
    {{-- ========================================================= --}}

    <section>

        <div class="mb-4">

            <p
                class="text-xs font-bold
                       uppercase tracking-[0.15em]
                       text-indigo-600"
            >
                Workflow
            </p>

            <h2
                class="mt-1 text-lg
                       font-bold text-slate-950"
            >
                Case Progress &amp; PCT
            </h2>

        </div>


        <div
            class="grid grid-cols-1 gap-4
                   xl:grid-cols-3"
        >

            {{-- STAGE 1 --}}

            <div
                class="rounded-2xl
                       border border-blue-200
                       bg-blue-50 p-5 shadow-sm"
            >

                <p
                    class="text-xs font-bold
                           uppercase text-blue-700"
                >
                    Stage 1 &middot; 3-Day PCT
                </p>

                <h3 class="mt-2 font-bold text-blue-950">
                    Filed &rarr; Interviewer Assignment
                </h3>

                <p
                    class="mt-4 text-2xl
                           font-bold text-blue-950"
                >
                    {{ $pct['stage_one']['classification_label'] ?? 'Not Available' }}
                </p>

                <p class="mt-1 text-xs text-blue-700">
                    @if ($pct['stage_one']['days'] !== null)
                        {{ $pct['stage_one']['days'] }} day(s)
                    @else
                        No elapsed measurement
                    @endif
                </p>

                <p
                    class="mt-3 text-xs
                           leading-5 text-blue-800"
                >
                    {{ $pct['stage_one']['message'] }}
                </p>

            </div>


            {{-- STAGE 2 --}}

            <div
                class="rounded-2xl
                       border border-violet-200
                       bg-violet-50 p-5 shadow-sm"
            >

                <p
                    class="text-xs font-bold
                           uppercase text-violet-700"
                >
                    Stage 2 &middot; 3-Day PCT
                </p>

                <h3 class="mt-2 font-bold text-violet-950">
                    Assignment &rarr; Interview
                </h3>

                <p
                    class="mt-4 text-2xl
                           font-bold text-violet-950"
                >
                    {{ $pct['stage_two']['classification_label'] ?? 'Not Available' }}
                </p>

                <p class="mt-1 text-xs text-violet-700">
                    @if ($pct['stage_two']['days'] !== null)
                        {{ $pct['stage_two']['days'] }} day(s)
                    @else
                        No elapsed measurement
                    @endif
                </p>

                <p
                    class="mt-3 text-xs
                           leading-5 text-violet-800"
                >
                    {{ $pct['stage_two']['message'] }}
                </p>

            </div>


            {{-- DISPOSITION PCT --}}

            @php
                $dispositionKey = $pct['disposition_pct']['status_key'];
            @endphp

            <div
                @class([
                    'rounded-2xl border p-5 shadow-sm',

                    'border-emerald-200 bg-emerald-50'
                        => in_array($dispositionKey, ['disposed_within', 'active_within'], true),

                    'border-amber-200 bg-amber-50'
                        => $dispositionKey === 'due_today',

                    'border-rose-200 bg-rose-50'
                        => in_array($dispositionKey, ['disposed_beyond', 'active_beyond'], true),

                    'border-slate-200 bg-slate-50'
                        => $dispositionKey === 'indeterminate',
                ])
            >

                <p
                    class="text-xs font-bold
                           uppercase text-slate-600"
                >
                    Overall Disposition &middot; 30-Day PCT
                </p>

                <h3 class="mt-2 font-bold text-slate-950">
                    Filed &rarr; Disposed
                </h3>

                <p
                    class="mt-4 text-2xl
                           font-bold text-slate-950"
                >
                    {{ $pct['disposition_pct']['status_label'] }}
                </p>

                <p class="mt-1 text-xs text-slate-600">
                    @if ($pct['disposition_pct']['days'] !== null)
                        {{ $pct['disposition_pct']['days'] }} day(s)
                    @else
                        No elapsed measurement
                    @endif
                </p>

                <p
                    class="mt-3 text-xs
                           leading-5 text-slate-700"
                >
                    {{ $pct['disposition_pct']['message'] }}
                </p>

            </div>

        </div>

    </section>


    {{-- ========================================================= --}}
    {{-- ASSIGNMENT --}}
    {{-- ========================================================= --}}

    <section
        class="rounded-2xl
               border border-slate-200
               bg-white p-6 shadow-sm"
    >

        <p
            class="text-xs font-bold
                   uppercase tracking-[0.15em]
                   text-indigo-600"
        >
            Assignments
        </p>

        <h2
            class="mt-1 text-lg
                   font-bold text-slate-950"
        >
            Current Responsibility
        </h2>

        <p
            class="mt-2 text-sm leading-6
                   text-slate-500"
        >
            Choose a system account where one exists. Records imported from CSV
            keep their original name until an account is assigned.
        </p>


        <form
            method="POST"
            action="{{ route('rfas.assignment', $rfa) }}"
            class="mt-6"
        >

            @csrf
            @method('PUT')


            <div
                class="grid grid-cols-1 gap-6
                       lg:grid-cols-2"
            >

                {{-- INTERVIEWER --}}

                <div class="space-y-4">

                    <p
                        class="text-xs font-bold
                               uppercase tracking-wider
                               text-slate-400"
                    >
                        Interviewer
                    </p>

                    <div>

                        <label for="interviewer_user_id" class="{{ $labelClass }}">
                            System Account
                        </label>

                        <select
                            id="interviewer_user_id"
                            name="interviewer_user_id"
                            class="{{ $inputClass }} bg-white"
                            @disabled(! $canAssign)
                        >

                            <option value="">No account assigned</option>

                            @foreach ($assignableUsers as $candidate)

                                <option
                                    value="{{ $candidate->id }}"
                                    @selected((int) old('interviewer_user_id', $rfa->interviewer_id) === $candidate->id)
                                >
                                    {{ $candidate->name }}
                                    @if ($candidate->position)
                                        ({{ $candidate->position }})
                                    @endif
                                </option>

                            @endforeach

                        </select>

                    </div>


                    <div>

                        <label for="interviewer_name" class="{{ $labelClass }}">
                            Recorded Name
                        </label>

                        <input
                            id="interviewer_name"
                            name="interviewer_name"
                            value="{{ old('interviewer_name', $rfa->interviewer_name) }}"
                            placeholder="Name as recorded in the source data"
                            class="{{ $inputClass }}"
                            @disabled(! $canAssign)
                        >

                    </div>


                    <div>

                        <label for="date_assigned_interviewer" class="{{ $labelClass }}">
                            Date Assigned to Interviewer
                        </label>

                        <input
                            id="date_assigned_interviewer"
                            name="date_assigned_interviewer"
                            type="date"
                            value="{{ old('date_assigned_interviewer', $dateValue($rfa->date_assigned_interviewer)) }}"
                            class="{{ $inputClass }}"
                            @disabled(! $canAssign)
                        >

                    </div>

                </div>


                {{-- SEADO --}}

                <div class="space-y-4">

                    <p
                        class="text-xs font-bold
                               uppercase tracking-wider
                               text-slate-400"
                    >
                        SEADO
                    </p>

                    <div>

                        <label for="seado_user_id" class="{{ $labelClass }}">
                            System Account
                        </label>

                        <select
                            id="seado_user_id"
                            name="seado_user_id"
                            class="{{ $inputClass }} bg-white"
                            @disabled(! $canAssign)
                        >

                            <option value="">No account assigned</option>

                            @foreach ($assignableUsers as $candidate)

                                <option
                                    value="{{ $candidate->id }}"
                                    @selected((int) old('seado_user_id', $rfa->seado_id) === $candidate->id)
                                >
                                    {{ $candidate->name }}
                                    @if ($candidate->position)
                                        ({{ $candidate->position }})
                                    @endif
                                </option>

                            @endforeach

                        </select>

                    </div>


                    <div>

                        <label for="seado_name" class="{{ $labelClass }}">
                            Recorded Name
                        </label>

                        <input
                            id="seado_name"
                            name="seado_name"
                            value="{{ old('seado_name', $rfa->seado_name) }}"
                            placeholder="Name as recorded in the source data"
                            class="{{ $inputClass }}"
                            @disabled(! $canAssign)
                        >

                    </div>


                    <div>

                        <label for="date_assigned_seado" class="{{ $labelClass }}">
                            Date Assigned to SEADO
                        </label>

                        <input
                            id="date_assigned_seado"
                            name="date_assigned_seado"
                            type="date"
                            value="{{ old('date_assigned_seado', $dateValue($rfa->date_assigned_seado)) }}"
                            class="{{ $inputClass }}"
                            @disabled(! $canAssign)
                        >

                    </div>

                </div>

            </div>


            @if ($canAssign)

                <button
                    type="submit"
                    class="mt-6 rounded-xl
                           bg-blue-600 px-5 py-3
                           text-sm font-semibold
                           text-white shadow-sm
                           transition
                           hover:bg-blue-700"
                >
                    Save Assignment
                </button>

            @else

                <p
                    class="mt-6 text-sm
                           text-slate-400"
                >
                    You do not have permission to change assignments.
                </p>

            @endif

        </form>

    </section>


    {{-- ========================================================= --}}
    {{-- WORKFLOW PROGRESS --}}
    {{-- ========================================================= --}}

    <section
        class="rounded-2xl
               border border-slate-200
               bg-white p-6 shadow-sm"
    >

        <p
            class="text-xs font-bold
                   uppercase tracking-[0.15em]
                   text-blue-600"
        >
            Workflow Progress
        </p>

        <h2
            class="mt-1 text-lg
                   font-bold text-slate-950"
        >
            Status and processing dates
        </h2>

        <p
            class="mt-2 text-sm leading-6
                   text-slate-500"
        >
            The dates on this record suggest
            <span class="font-semibold text-slate-700">
                {{ Workflow::label($suggestedStatus) }}
            </span>.
            The status stays under your control so a case can be held at a stage
            deliberately.
        </p>


        <form
            method="POST"
            action="{{ route('rfas.workflow', $rfa) }}"
            class="mt-6"
        >

            @csrf
            @method('PUT')


            <div
                class="grid grid-cols-1 gap-5
                       md:grid-cols-2 xl:grid-cols-3"
            >

                <div class="xl:col-span-3">

                    <label for="status" class="{{ $labelClass }}">
                        Workflow Status
                    </label>

                    <select
                        id="status"
                        name="status"
                        required
                        class="{{ $inputClass }} bg-white xl:max-w-md"
                        @disabled(! $canManage)
                    >

                        @foreach ($statuses as $key => $label)

                            <option
                                value="{{ $key }}"
                                @selected(old('status', $rfa->status) === $key)
                            >
                                {{ $label }}
                            </option>

                        @endforeach

                    </select>

                </div>


                @foreach ([
                    'date_filed' => 'Date Filed',
                    'date_interview' => 'Date of Interview',
                    'date_validated' => 'Date Validated',
                    'date_turned_over_lr' => 'Date Turned Over to LR',
                ] as $field => $label)

                    <div>

                        <label for="{{ $field }}" class="{{ $labelClass }}">
                            {{ $label }}
                        </label>

                        <input
                            id="{{ $field }}"
                            name="{{ $field }}"
                            type="date"
                            value="{{ old($field, $dateValue($rfa->{$field})) }}"
                            class="{{ $inputClass }}"
                            @disabled(! $canManage)
                        >

                    </div>

                @endforeach

            </div>


            @if ($canManage)

                <button
                    type="submit"
                    class="mt-6 rounded-xl
                           bg-blue-600 px-5 py-3
                           text-sm font-semibold
                           text-white shadow-sm
                           transition
                           hover:bg-blue-700"
                >
                    Save Workflow Progress
                </button>

            @endif

        </form>

    </section>


    {{-- ========================================================= --}}
    {{-- CONFERENCE --}}
    {{-- ========================================================= --}}

    <section
        class="rounded-2xl
               border border-slate-200
               bg-white p-6 shadow-sm"
    >

        <p
            class="text-xs font-bold
                   uppercase tracking-[0.15em]
                   text-violet-600"
        >
            Conference Progression
        </p>

        <h2
            class="mt-1 text-lg
                   font-bold text-slate-950"
        >
            1st and 2nd conference
        </h2>

        <p
            class="mt-2 text-sm leading-6
                   text-slate-500"
        >
            The 1st Conference is not the Date of Interview and is never treated
            as one. A 2nd Conference cannot be recorded before a 1st.
        </p>


        <form
            method="POST"
            action="{{ route('rfas.conference', $rfa) }}"
            class="mt-6"
        >

            @csrf
            @method('PUT')


            <div
                class="grid grid-cols-1 gap-5
                       md:grid-cols-3"
            >

                @foreach ([
                    'date_initial_conference' => 'Date of 1st Conference',
                    'date_second_conference' => 'Date of 2nd Conference',
                    'date_both_parties_appeared' => 'Date Both Parties Appeared',
                ] as $field => $label)

                    <div>

                        <label for="{{ $field }}" class="{{ $labelClass }}">
                            {{ $label }}
                        </label>

                        <input
                            id="{{ $field }}"
                            name="{{ $field }}"
                            type="date"
                            value="{{ old($field, $dateValue($rfa->{$field})) }}"
                            class="{{ $inputClass }}"
                            @disabled(! $canManage)
                        >

                    </div>

                @endforeach

            </div>


            @if ($canManage)

                <button
                    type="submit"
                    class="mt-6 rounded-xl
                           bg-blue-600 px-5 py-3
                           text-sm font-semibold
                           text-white shadow-sm
                           transition
                           hover:bg-blue-700"
                >
                    Save Conference Details
                </button>

            @endif

        </form>

    </section>


    {{-- ========================================================= --}}
    {{-- DISPOSITION --}}
    {{-- ========================================================= --}}

    <section
        class="rounded-2xl
               border border-emerald-200
               bg-white p-6 shadow-sm"
    >

        <p
            class="text-xs font-bold
                   uppercase tracking-[0.15em]
                   text-emerald-600"
        >
            Disposition Capture
        </p>

        <h2
            class="mt-1 text-lg
                   font-bold text-slate-950"
        >
            How the case was closed
        </h2>

        <p
            class="mt-2 text-sm leading-6
                   text-slate-500"
        >
            The official disposition and the raw source Mode are stored
            separately and never merged. An official disposition and a Date
            Disposed must be recorded together.
        </p>


        <form
            method="POST"
            action="{{ route('rfas.disposition', $rfa) }}"
            class="mt-6"
        >

            @csrf
            @method('PUT')


            <div
                class="grid grid-cols-1 gap-5
                       md:grid-cols-2 xl:grid-cols-3"
            >

                <div>

                    <label for="disposition_status" class="{{ $labelClass }}">
                        Official Disposition
                    </label>

                    <input
                        id="disposition_status"
                        name="disposition_status"
                        list="disposition-status-options"
                        value="{{ old('disposition_status', $rfa->disposition_status) }}"
                        class="{{ $inputClass }}"
                        @disabled(! $canDispose)
                    >

                    <datalist id="disposition-status-options">

                        @foreach ($dispositionStatuses as $option)
                            <option value="{{ $option }}"></option>
                        @endforeach

                    </datalist>

                </div>


                <div>

                    <label for="disposition_mode" class="{{ $labelClass }}">
                        Source Disposition Mode
                    </label>

                    <input
                        id="disposition_mode"
                        name="disposition_mode"
                        list="disposition-mode-options"
                        value="{{ old('disposition_mode', $rfa->disposition_mode) }}"
                        class="{{ $inputClass }}"
                        @disabled(! $canDispose)
                    >

                    <datalist id="disposition-mode-options">

                        @foreach ($dispositionModes as $option)
                            <option value="{{ $option }}"></option>
                        @endforeach

                    </datalist>

                    <p class="mt-2 text-xs text-slate-400">
                        Raw CSV value. No meaning is inferred from it.
                    </p>

                </div>


                <div>

                    <label for="date_disposed" class="{{ $labelClass }}">
                        Date Disposed
                    </label>

                    <input
                        id="date_disposed"
                        name="date_disposed"
                        type="date"
                        value="{{ old('date_disposed', $dateValue($rfa->date_disposed)) }}"
                        class="{{ $inputClass }}"
                        @disabled(! $canDispose)
                    >

                </div>


                <div>

                    <label for="monetary_benefit" class="{{ $labelClass }}">
                        Monetary Benefit
                    </label>

                    <input
                        id="monetary_benefit"
                        name="monetary_benefit"
                        type="number"
                        step="0.01"
                        min="0"
                        value="{{ old('monetary_benefit', $rfa->monetary_benefit) }}"
                        class="{{ $inputClass }}"
                        @disabled(! $canDispose)
                    >

                </div>


                <div>

                    <label for="workers_benefited" class="{{ $labelClass }}">
                        Workers Benefited
                    </label>

                    <input
                        id="workers_benefited"
                        name="workers_benefited"
                        type="number"
                        min="0"
                        value="{{ old('workers_benefited', $rfa->workers_benefited) }}"
                        class="{{ $inputClass }}"
                        @disabled(! $canDispose)
                    >

                </div>

            </div>


            @if ($canDispose)

                <button
                    type="submit"
                    class="mt-6 rounded-xl
                           bg-emerald-600 px-5 py-3
                           text-sm font-semibold
                           text-white shadow-sm
                           transition
                           hover:bg-emerald-700"
                >
                    Save Disposition
                </button>

            @endif

        </form>


        {{-- REOPEN --}}

        @if ($canDispose && $rfa->isDisposed())

            <div
                class="mt-7 rounded-xl
                       border border-amber-200
                       bg-amber-50 p-5"
            >

                <p
                    class="text-xs font-bold
                           uppercase tracking-wider
                           text-amber-700"
                >
                    Reopen Case
                </p>

                <p
                    class="mt-2 text-sm leading-6
                           text-amber-900"
                >
                    Reopening clears the official disposition and the Date
                    Disposed, and returns the case to For Disposition. The
                    previous values stay visible in the timeline.
                </p>


                <form
                    method="POST"
                    action="{{ route('rfas.reopen', $rfa) }}"
                    class="mt-4 flex flex-col gap-3 sm:flex-row"
                    onsubmit="return confirm('Reopen this case and clear its disposition?');"
                >

                    @csrf
                    @method('PUT')

                    <input
                        name="reason"
                        required
                        maxlength="1000"
                        placeholder="Reason for reopening"
                        class="w-full rounded-xl
                               border border-amber-300
                               bg-white px-4 py-3 text-sm
                               outline-none
                               focus:border-amber-500
                               focus:ring-4 focus:ring-amber-100"
                    >

                    <button
                        type="submit"
                        class="shrink-0 rounded-xl
                               bg-amber-600 px-5 py-3
                               text-sm font-semibold
                               text-white shadow-sm
                               transition
                               hover:bg-amber-700"
                    >
                        Reopen
                    </button>

                </form>

            </div>

        @endif

    </section>


    {{-- ========================================================= --}}
    {{-- CASE INFORMATION --}}
    {{-- ========================================================= --}}

    <section
        class="rounded-2xl
               border border-slate-200
               bg-white p-6 shadow-sm"
    >

        <p
            class="text-xs font-bold
                   uppercase tracking-[0.15em]
                   text-slate-500"
        >
            Case Information
        </p>

        <h2
            class="mt-1 text-lg
                   font-bold text-slate-950"
        >
            Parties and establishment details
        </h2>


        <form
            method="POST"
            action="{{ route('rfas.details', $rfa) }}"
            class="mt-6"
        >

            @csrf
            @method('PUT')


            <div
                class="grid grid-cols-1 gap-5
                       md:grid-cols-2 xl:grid-cols-3"
            >

                <div>

                    <label for="docket_no" class="{{ $labelClass }}">
                        Docket Number
                    </label>

                    <input
                        id="docket_no"
                        name="docket_no"
                        value="{{ old('docket_no', $rfa->docket_no) }}"
                        class="{{ $inputClass }}"
                        @disabled(! $canManage)
                    >

                    <p class="mt-2 text-xs text-slate-400">
                        Not guaranteed unique. The system reference is
                        {{ $rfa->reference_no }}.
                    </p>

                </div>


                <div>

                    <label for="office" class="{{ $labelClass }}">
                        Office
                    </label>

                    <input
                        id="office"
                        name="office"
                        list="office-options"
                        value="{{ old('office', $rfa->office) }}"
                        class="{{ $inputClass }}"
                        @disabled(! $canManage)
                    >

                    <datalist id="office-options">

                        @foreach ($offices as $option)
                            <option value="{{ $option }}"></option>
                        @endforeach

                    </datalist>

                </div>


                <div>

                    <label for="filer_class" class="{{ $labelClass }}">
                        Filer Class
                    </label>

                    <input
                        id="filer_class"
                        name="filer_class"
                        value="{{ old('filer_class', $rfa->filer_class) }}"
                        class="{{ $inputClass }}"
                        @disabled(! $canManage)
                    >

                </div>


                <div>

                    <label for="requesting_party" class="{{ $labelClass }}">
                        Requesting Party
                    </label>

                    <input
                        id="requesting_party"
                        name="requesting_party"
                        value="{{ old('requesting_party', $rfa->requesting_party) }}"
                        class="{{ $inputClass }}"
                        @disabled(! $canManage)
                    >

                </div>


                <div>

                    <label for="responding_party" class="{{ $labelClass }}">
                        Responding Party
                    </label>

                    <input
                        id="responding_party"
                        name="responding_party"
                        value="{{ old('responding_party', $rfa->responding_party) }}"
                        class="{{ $inputClass }}"
                        @disabled(! $canManage)
                    >

                </div>


                <div>

                    <label for="contact_no" class="{{ $labelClass }}">
                        Contact Number
                    </label>

                    <input
                        id="contact_no"
                        name="contact_no"
                        value="{{ old('contact_no', $rfa->contact_no) }}"
                        class="{{ $inputClass }}"
                        @disabled(! $canManage)
                    >

                </div>


                <div>

                    <label for="industry" class="{{ $labelClass }}">
                        Industry
                    </label>

                    <input
                        id="industry"
                        name="industry"
                        value="{{ old('industry', $rfa->industry) }}"
                        class="{{ $inputClass }}"
                        @disabled(! $canManage)
                    >

                </div>


                <div>

                    <label for="industry_code" class="{{ $labelClass }}">
                        Industry Code
                    </label>

                    <input
                        id="industry_code"
                        name="industry_code"
                        value="{{ old('industry_code', $rfa->industry_code) }}"
                        class="{{ $inputClass }}"
                        @disabled(! $canManage)
                    >

                </div>


                <div>

                    <label for="size_of_enterprise" class="{{ $labelClass }}">
                        Size of Enterprise
                    </label>

                    <input
                        id="size_of_enterprise"
                        name="size_of_enterprise"
                        value="{{ old('size_of_enterprise', $rfa->size_of_enterprise) }}"
                        class="{{ $inputClass }}"
                        @disabled(! $canManage)
                    >

                </div>


                @foreach ([
                    'total_employment' => 'Total Employment',
                    'workers_involved' => 'Workers Involved',
                    'male_workers' => 'Male Workers',
                    'female_workers' => 'Female Workers',
                ] as $field => $label)

                    <div>

                        <label for="{{ $field }}" class="{{ $labelClass }}">
                            {{ $label }}
                        </label>

                        <input
                            id="{{ $field }}"
                            name="{{ $field }}"
                            type="number"
                            min="0"
                            value="{{ old($field, $rfa->{$field}) }}"
                            class="{{ $inputClass }}"
                            @disabled(! $canManage)
                        >

                    </div>

                @endforeach


                <div class="md:col-span-2 xl:col-span-3">

                    <label for="company_address" class="{{ $labelClass }}">
                        Company Address
                    </label>

                    <input
                        id="company_address"
                        name="company_address"
                        value="{{ old('company_address', $rfa->company_address) }}"
                        class="{{ $inputClass }}"
                        @disabled(! $canManage)
                    >

                </div>


                <div class="md:col-span-2 xl:col-span-3">

                    <label for="issues" class="{{ $labelClass }}">
                        Issues
                    </label>

                    <textarea
                        id="issues"
                        name="issues"
                        rows="3"
                        class="{{ $inputClass }}"
                        @disabled(! $canManage)
                    >{{ old('issues', $rfa->issues) }}</textarea>

                </div>

            </div>


            @if ($canManage)

                <button
                    type="submit"
                    class="mt-6 rounded-xl
                           bg-blue-600 px-5 py-3
                           text-sm font-semibold
                           text-white shadow-sm
                           transition
                           hover:bg-blue-700"
                >
                    Save Case Information
                </button>

            @endif

        </form>

    </section>


    {{-- ========================================================= --}}
    {{-- TIMELINE --}}
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
                       text-slate-500"
            >
                Activity
            </p>

            <h2
                class="mt-1 text-lg
                       font-bold text-slate-950"
            >
                Case Timeline
            </h2>

        </div>


        @if ($canManage)

            <div
                class="border-b border-slate-100
                       bg-slate-50/60 px-6 py-5"
            >

                <form
                    method="POST"
                    action="{{ route('rfas.notes', $rfa) }}"
                    class="flex flex-col gap-3 sm:flex-row"
                >

                    @csrf

                    <input
                        name="note"
                        required
                        maxlength="2000"
                        placeholder="Add a note to this case..."
                        class="w-full rounded-xl
                               border border-slate-300
                               bg-white px-4 py-3 text-sm
                               outline-none
                               focus:border-blue-500
                               focus:ring-4 focus:ring-blue-100"
                    >

                    <button
                        type="submit"
                        class="shrink-0 rounded-xl
                               bg-slate-950 px-5 py-3
                               text-sm font-semibold
                               text-white shadow-sm
                               transition
                               hover:bg-slate-800"
                    >
                        Add Note
                    </button>

                </form>

            </div>

        @endif


        <div class="divide-y divide-slate-100">

            @forelse ($rfa->activities as $activity)

                @php
                    $dot = match ($activity->accent()) {
                        'blue' => 'bg-blue-500',
                        'indigo' => 'bg-indigo-500',
                        'violet' => 'bg-violet-500',
                        'emerald' => 'bg-emerald-500',
                        'amber' => 'bg-amber-500',
                        default => 'bg-slate-400',
                    };
                @endphp

                <article class="flex gap-4 px-6 py-5">

                    <div
                        class="mt-1.5 h-2.5 w-2.5
                               shrink-0 rounded-full
                               {{ $dot }}"
                    ></div>


                    <div class="min-w-0 flex-1">

                        <div
                            class="flex flex-wrap
                                   items-baseline gap-x-3 gap-y-1"
                        >

                            <p
                                class="font-semibold
                                       text-slate-900"
                            >
                                {{ $activity->title }}
                            </p>

                            <p class="text-xs text-slate-400">
                                {{ $activity->actorName() }}
                                &middot;
                                {{ $activity->created_at?->format('M d, Y g:i A') }}
                            </p>

                        </div>


                        @if ($activity->description)

                            <p
                                class="mt-2 text-sm
                                       leading-6 text-slate-600"
                            >
                                {{ $activity->description }}
                            </p>

                        @endif


                        @if ($activity->changes)

                            <ul
                                class="mt-3 space-y-1
                                       text-xs text-slate-500"
                            >

                                @foreach ($activity->changes as $change)

                                    <li>

                                        <span
                                            class="font-semibold
                                                   text-slate-700"
                                        >
                                            {{ $change['label'] }}:
                                        </span>

                                        <span class="text-slate-400">
                                            {{ $change['from'] ?? 'not set' }}
                                        </span>

                                        &rarr;

                                        <span
                                            class="font-medium
                                                   text-slate-700"
                                        >
                                            {{ $change['to'] ?? 'not set' }}
                                        </span>

                                    </li>

                                @endforeach

                            </ul>

                        @endif

                    </div>

                </article>

            @empty

                <div
                    class="px-6 py-14
                           text-center text-sm
                           text-slate-500"
                >
                    No workflow activity has been recorded yet. Changes made from
                    this screen will appear here.
                </div>

            @endforelse

        </div>

    </section>

</div>

@endsection
