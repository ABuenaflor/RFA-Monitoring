@extends('layouts.app')


@section(
    'title',
    'SEADO Assignment | RFA Monitoring System'
)


@section(
    'page_heading',
    'SEADO Assignment'
)


@section('content')

@php
    $maxHandled = max(1, (int) $roster->max('total'));
@endphp

<div class="space-y-7">

    {{-- ========================================================= --}}
    {{-- INTRO + PROVINCE FILTER --}}
    {{-- ========================================================= --}}

    <section class="flex flex-col justify-between gap-4 lg:flex-row lg:items-end">

        <p class="max-w-3xl text-sm leading-6 text-slate-500">
            RFAs handled by each SEADO, and the open cases that still have no
            SEADO. <strong class="text-slate-700">Assign to a SEADO</strong>
            lists the {{ \App\Http\Controllers\SeadoAssignmentController::SUGGESTIONS }}
            SEADOs in the case's office who have handled the fewest RFAs, so new
            work goes to the lightest caseload.
        </p>

        <form method="GET" action="{{ route('seado-assignment.index') }}" class="flex items-center gap-2">

            <label for="office" class="text-sm font-semibold text-slate-700">Province</label>

            <select
                id="office"
                name="office"
                onchange="this.form.submit()"
                class="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm"
            >
                <option value="">All provinces / offices</option>

                @foreach ($offices as $label => $code)
                    <option value="{{ $code }}" @selected($officeFilter === $code)>
                        {{ $label }} ({{ $code }})
                    </option>
                @endforeach
            </select>

        </form>

    </section>


    {{-- ========================================================= --}}
    {{-- SUMMARY --}}
    {{-- ========================================================= --}}

    <section class="grid grid-cols-2 gap-4 xl:grid-cols-4">

        @foreach ([
            'SEADOs' => $rosterTotals['seados'],
            'RFAs Handled' => $rosterTotals['handled'],
            'Open With a SEADO' => $rosterTotals['open'],
            'Awaiting a SEADO' => $queue->total(),
        ] as $label => $value)

            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-bold uppercase tracking-wider text-slate-400">{{ $label }}</p>
                <p class="mt-3 text-3xl font-bold text-slate-950">{{ number_format($value) }}</p>
                <p class="mt-1 text-xs text-slate-400">{{ $officeLabel ?? 'All offices' }}</p>
            </div>

        @endforeach

    </section>


    {{-- ========================================================= --}}
    {{-- SEADO ROULETTE --}}
    {{-- ========================================================= --}}

    <section
        id="seado-roulette"
        x-data="seadoRoulette(@js($roulette))"
        class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"
    >

        <div class="flex flex-col justify-between gap-2 lg:flex-row lg:items-end">

            <div>
                <p class="text-xs font-bold uppercase tracking-[0.15em] text-blue-600">SEADO Roulette</p>

                <h2 class="mt-1 text-lg font-bold text-slate-950">
                    Draw one of the {{ $roulette->count() }} least-handled SEADOs
                </h2>

                <p class="mt-1 max-w-3xl text-sm leading-6 text-slate-500">
                    Shuffles the {{ $roulette->count() }} SEADOs with the fewest RFAs handled
                    in {{ $officeLabel ?? 'all offices' }} and spins the wheel to pick one at random.
                    SEADOs on the exclusion list are skipped and the next least-handled
                    take their place. The draw is not saved — assign the case from the list below.
                </p>
            </div>

        </div>

        @if ($roulette->isEmpty())

            <p class="mt-6 rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-800">
                @if ($roster->isEmpty())
                    No SEADOs found{{ $officeLabel ? ' for ' . $officeLabel : '' }}, so there is nothing to draw.
                @else
                    Every SEADO{{ $officeLabel ? ' in ' . $officeLabel : '' }} is excluded, so there is nothing to draw.
                    Remove someone from the exclusion list below.
                @endif
            </p>

        @else

            <div class="mt-6 grid grid-cols-1 items-center gap-8 lg:grid-cols-[auto_1fr]">

                {{-- Wheel --}}

                <div class="relative mx-auto h-72 w-72 sm:h-80 sm:w-80">

                    {{-- Pointer --}}
                    <div class="absolute left-1/2 top-0 z-20 -translate-x-1/2 -translate-y-1">
                        <div class="h-0 w-0 border-x-[14px] border-t-[24px] border-x-transparent border-t-slate-950 drop-shadow"></div>
                    </div>

                    <div
                        x-ref="wheel"
                        @transitionend.self="finish()"
                        class="relative h-full w-full overflow-hidden rounded-full border-8 border-slate-950 shadow-xl"
                        :style="`background: ${gradient()}; transform: rotate(${rotation}deg); transition: ${transition};`"
                        role="img"
                        :aria-label="`Roulette wheel with ${segments.length} SEADOs`"
                    >

                        <template x-for="(seado, index) in segments" :key="seado.name">
                            <div
                                class="absolute left-1/2 top-1/2 flex h-8 w-1/2 origin-left items-center justify-end pr-5"
                                :style="`transform: translateY(-50%) rotate(${labelAngle(index)}deg)`"
                            >
                                <span
                                    class="max-w-[7.5rem] truncate text-xs font-bold text-white drop-shadow sm:max-w-[8.5rem]"
                                    x-text="shortName(seado.name)"
                                ></span>
                            </div>
                        </template>

                    </div>

                    {{-- Hub --}}
                    <div class="absolute left-1/2 top-1/2 z-10 flex h-14 w-14 -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full border-4 border-slate-950 bg-white text-xs font-bold text-slate-950 shadow">
                        <span x-text="phase === 'idle' ? 'SPIN' : '…'">SPIN</span>
                    </div>

                </div>


                {{-- Candidates + button --}}

                <div>

                    <ol class="space-y-2">

                        <template x-for="(seado, index) in segments" :key="seado.name">
                            <li
                                class="flex items-center gap-3 rounded-xl border p-3 transition"
                                :class="highlight === seado.name
                                    ? 'border-blue-500 bg-blue-50 ring-2 ring-blue-200'
                                    : 'border-slate-200'"
                            >
                                <span class="h-4 w-4 shrink-0 rounded-full" :style="`background: ${color(index)}`"></span>

                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-sm font-semibold text-slate-900" x-text="seado.name"></span>
                                    <span class="block text-xs text-slate-500" x-text="`${seado.open} open · ${seado.disposed} disposed`"></span>
                                </span>

                                <span class="text-right">
                                    <span class="block text-lg font-bold text-slate-950" x-text="seado.total"></span>
                                    <span class="block text-[10px] uppercase text-slate-400">handled</span>
                                </span>
                            </li>
                        </template>

                    </ol>

                    <button
                        type="button"
                        @click="spin()"
                        :disabled="phase !== 'idle'"
                        class="mt-5 inline-flex w-full items-center justify-center gap-2 rounded-xl bg-blue-600 px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700 disabled:cursor-wait disabled:opacity-60 sm:w-auto"
                    >
                        <span x-show="phase === 'idle'">Shuffle &amp; Spin</span>
                        <span x-cloak x-show="phase === 'shuffling'">Shuffling…</span>
                        <span x-cloak x-show="phase === 'spinning'">Spinning…</span>
                    </button>

                    <p class="sr-only" aria-live="polite" x-text="winner ? `Chosen SEADO: ${winner.name}` : ''"></p>

                </div>

            </div>


            {{-- Result pop-up --}}

            <template x-teleport="body">

                <div
                    x-cloak
                    x-show="winner && showResult"
                    x-transition.opacity
                    @keydown.escape.window="showResult = false"
                    class="fixed inset-0 z-[60] flex items-center justify-center bg-slate-950/60 p-4 backdrop-blur-sm"
                >

                    <div
                        @click.outside="showResult = false"
                        role="dialog"
                        aria-modal="true"
                        aria-labelledby="roulette-result-title"
                        x-show="showResult"
                        x-transition.scale.90
                        class="w-full max-w-md rounded-2xl bg-white p-6 text-center shadow-2xl"
                    >

                        <p class="text-xs font-bold uppercase tracking-[0.15em] text-blue-600">Chosen SEADO</p>

                        <div
                            class="mx-auto mt-4 flex h-16 w-16 items-center justify-center rounded-full text-xl font-bold text-white shadow-lg"
                            :style="`background: ${winner ? color(segments.findIndex(s => s.name === winner.name)) : '#2563eb'}`"
                            x-text="winner ? initials(winner.name) : ''"
                        ></div>

                        <h3 id="roulette-result-title" class="mt-4 text-xl font-bold text-slate-950" x-text="winner?.name"></h3>

                        <p class="mt-1 text-sm text-slate-500" x-text="winner?.offices?.join(', ') || 'No office on record'"></p>

                        <dl class="mt-5 grid grid-cols-3 gap-3">
                            <div class="rounded-xl bg-slate-50 p-3">
                                <dt class="text-[10px] font-semibold uppercase text-slate-400">Handled</dt>
                                <dd class="mt-1 text-xl font-bold text-slate-950" x-text="winner?.total"></dd>
                            </div>
                            <div class="rounded-xl bg-slate-50 p-3">
                                <dt class="text-[10px] font-semibold uppercase text-slate-400">Open</dt>
                                <dd class="mt-1 text-xl font-bold text-slate-950" x-text="winner?.open"></dd>
                            </div>
                            <div class="rounded-xl bg-slate-50 p-3">
                                <dt class="text-[10px] font-semibold uppercase text-slate-400">Disposed</dt>
                                <dd class="mt-1 text-xl font-bold text-slate-950" x-text="winner?.disposed"></dd>
                            </div>
                        </dl>

                        <p x-show="winner && ! winner.has_account" class="mt-4 text-xs text-amber-700">
                            No system account yet, so this SEADO will not get in-app alerts.
                        </p>

                        <div class="mt-6 flex justify-center gap-2">
                            <button
                                type="button"
                                @click="showResult = false; $nextTick(() => spin())"
                                class="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
                            >
                                Spin Again
                            </button>
                            <button
                                type="button"
                                @click="showResult = false"
                                class="rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700"
                            >
                                Done
                            </button>
                        </div>

                    </div>

                </div>

            </template>

        @endif


        {{-- Exclusion list: shared by everyone who uses this page --}}

        <div class="mt-8 rounded-xl border border-slate-200 bg-slate-50 p-5">

            <div class="flex flex-col justify-between gap-1 sm:flex-row sm:items-end">
                <div>
                    <h3 class="text-sm font-bold text-slate-950">
                        Excluded from the roulette
                        <span class="ml-1 rounded-full bg-slate-200 px-2 py-0.5 text-xs text-slate-700">{{ $exclusions->count() }}</span>
                    </h3>
                    <p class="mt-1 text-xs text-slate-500">
                        For SEADOs who should not get new cases for now (on leave, on detail, at capacity).
                        Applies in every province until removed.
                    </p>
                </div>
            </div>

            @if ($exclusions->isNotEmpty())

                <ul class="mt-4 flex flex-wrap gap-2">

                    @foreach ($exclusions as $exclusion)

                        <li class="flex items-center gap-2 rounded-full border border-slate-300 bg-white py-1 pl-3 pr-1 text-sm">

                            <span class="min-w-0">
                                <span class="font-semibold text-slate-900">{{ $exclusion->seado_name }}</span>
                                @if ($exclusion->reason)
                                    <span class="text-xs text-slate-500">· {{ $exclusion->reason }}</span>
                                @endif
                                <span class="sr-only">
                                    excluded {{ $exclusion->created_at?->format('M d, Y') }}
                                    {{ $exclusion->excluder ? 'by ' . $exclusion->excluder->name : '' }}
                                </span>
                            </span>

                            <form
                                method="POST"
                                action="{{ route('seado-assignment.exclusions.destroy', $exclusion) }}"
                            >
                                @csrf
                                @method('DELETE')

                                <button
                                    type="submit"
                                    title="Put {{ $exclusion->seado_name }} back in the roulette (excluded {{ $exclusion->created_at?->format('M d, Y') }}{{ $exclusion->excluder ? ' by ' . $exclusion->excluder->name : '' }})"
                                    aria-label="Remove {{ $exclusion->seado_name }} from the exclusion list"
                                    class="flex h-6 w-6 items-center justify-center rounded-full text-slate-400 transition hover:bg-rose-50 hover:text-rose-600"
                                >
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M18 6 6 18" />
                                    </svg>
                                </button>
                            </form>

                        </li>

                    @endforeach

                </ul>

            @else

                <p class="mt-4 text-sm text-slate-500">No SEADOs are excluded.</p>

            @endif

            <form
                method="POST"
                action="{{ route('seado-assignment.exclusions.store') }}"
                class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto]"
            >
                @csrf

                <div>
                    <label for="exclude-seado" class="sr-only">SEADO to exclude</label>
                    <select
                        id="exclude-seado"
                        name="seado_name"
                        required
                        @disabled($excludable->isEmpty())
                        class="w-full rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm"
                    >
                        <option value="">Choose a SEADO{{ $officeLabel ? ' in ' . $officeLabel : '' }}…</option>
                        @foreach ($excludable as $name)
                            <option value="{{ $name }}" @selected(old('seado_name') === $name)>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="exclude-reason" class="sr-only">Reason (optional)</label>
                    <input
                        id="exclude-reason"
                        name="reason"
                        maxlength="255"
                        value="{{ old('reason') }}"
                        placeholder="Reason (optional), e.g. on leave until Oct 20"
                        class="w-full rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-100"
                    >
                </div>

                <button
                    type="submit"
                    @disabled($excludable->isEmpty())
                    class="rounded-xl bg-slate-950 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800 disabled:opacity-50"
                >
                    Exclude
                </button>
            </form>

        </div>

    </section>


    {{-- ========================================================= --}}
    {{-- AWAITING ASSIGNMENT --}}
    {{-- ========================================================= --}}

    <section id="awaiting" class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">

        <div class="border-b border-slate-100 px-6 py-5">

            <p class="text-xs font-bold uppercase tracking-[0.15em] text-blue-600">New Work</p>

            <h2 class="mt-1 text-lg font-bold text-slate-950">Open RFAs awaiting a SEADO</h2>

            <form
                method="GET"
                action="{{ route('seado-assignment.index') }}#awaiting"
                class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-4"
            >

                <input type="hidden" name="office" value="{{ $officeFilter }}">

                <input
                    name="search"
                    value="{{ $search }}"
                    placeholder="Docket, reference, party..."
                    aria-label="Search"
                    class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-100 md:col-span-2"
                >

                <select
                    name="source_status"
                    aria-label="Source status"
                    class="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm"
                >
                    <option value="">All source statuses</option>
                    @foreach ($sourceStatuses as $option)
                        <option value="{{ $option }}" @selected($sourceStatus === $option)>{{ $option }}</option>
                    @endforeach
                </select>

                <div class="flex gap-2">
                    <button type="submit" class="flex-1 rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800">
                        Filter
                    </button>
                    <a
                        href="{{ route('seado-assignment.index', array_filter(['office' => $officeFilter])) }}#awaiting"
                        class="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
                    >
                        Clear
                    </a>
                </div>

            </form>

        </div>

        <div class="overflow-x-auto">

            <table class="w-full min-w-[900px] divide-y divide-slate-200">

                <thead class="bg-slate-50">
                    <tr>
                        @foreach (['Case', 'Filed', 'Office', 'Parties', 'Status', ''] as $header)
                            <th class="px-4 py-3 text-left text-xs font-bold uppercase text-slate-500">{{ $header }}</th>
                        @endforeach
                    </tr>
                </thead>

                <tbody class="divide-y divide-slate-100">

                    @forelse ($queue as $rfa)

                        @php
                            $options = $suggestions->get((string) $rfa->office, collect());
                        @endphp

                        <tr class="align-top transition hover:bg-slate-50/80">

                            <td class="px-4 py-4">
                                <a href="{{ route('rfas.show', $rfa) }}" class="text-sm font-semibold text-blue-700 hover:underline">
                                    {{ $rfa->displayReference() }}
                                </a>
                                <p class="mt-1 text-xs text-slate-400">
                                    {{ \Illuminate\Support\Str::headline($rfa->mode_of_filing ?: 'mode not set') }}
                                </p>
                            </td>

                            <td class="whitespace-nowrap px-4 py-4 text-sm text-slate-700">
                                {{ $rfa->date_filed?->format('M d, Y') ?? '—' }}
                            </td>

                            <td class="px-4 py-4 text-sm text-slate-700">{{ $rfa->office ?: '—' }}</td>

                            <td class="px-4 py-4 text-sm">
                                <p class="font-medium text-slate-900">{{ $rfa->requesting_party ?: '—' }}</p>
                                <p class="mt-1 text-xs text-slate-500">vs. {{ $rfa->responding_party ?: '—' }}</p>
                            </td>

                            <td class="px-4 py-4 text-xs">
                                <span class="rounded-full bg-slate-100 px-2.5 py-1 font-semibold text-slate-700">
                                    {{ $rfa->statusLabel() }}
                                </span>
                                @if ($rfa->source_case_status)
                                    <p class="mt-2 text-slate-400">Source: {{ $rfa->source_case_status }}</p>
                                @endif
                            </td>

                            <td class="px-4 py-4 text-right" x-data="{ open: false }">

                                <button
                                    type="button"
                                    @click="open = true"
                                    class="whitespace-nowrap rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700"
                                >
                                    Assign to a SEADO
                                </button>

                                {{-- Five least-handled SEADOs for this case's office --}}

                                <template x-teleport="body">

                                    <div
                                        x-cloak
                                        x-show="open"
                                        x-transition.opacity
                                        @keydown.escape.window="open = false"
                                        class="fixed inset-0 z-[60] flex items-center justify-center bg-slate-950/60 p-4 backdrop-blur-sm"
                                    >

                                        <form
                                            method="POST"
                                            action="{{ route('seado-assignment.assign', $rfa) }}"
                                            @click.outside="open = false"
                                            role="dialog"
                                            aria-modal="true"
                                            aria-labelledby="assign-title-{{ $rfa->id }}"
                                            class="w-full max-w-lg rounded-2xl bg-white p-6 text-left shadow-2xl"
                                        >

                                            @csrf

                                            <p class="text-xs font-bold uppercase tracking-[0.15em] text-blue-600">Assign to a SEADO</p>

                                            <h3 id="assign-title-{{ $rfa->id }}" class="mt-1 text-lg font-bold text-slate-950">
                                                {{ $rfa->displayReference() }}
                                            </h3>

                                            <p class="mt-1 text-sm text-slate-500">
                                                {{ $options->count() }} {{ str('SEADO')->plural($options->count()) }}
                                                with the fewest RFAs handled in {{ $rfa->office ?: 'all offices' }}.
                                            </p>

                                            <fieldset class="mt-4 space-y-2">

                                                <legend class="sr-only">SEADO</legend>

                                                @forelse ($options as $seado)

                                                    <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-slate-200 p-3 transition hover:bg-slate-50 has-[:checked]:border-blue-500 has-[:checked]:bg-blue-50">

                                                        <input
                                                            type="radio"
                                                            name="seado_name"
                                                            value="{{ $seado['name'] }}"
                                                            required
                                                            @checked($loop->first)
                                                            class="h-4 w-4 text-blue-600"
                                                        >

                                                        <span class="min-w-0 flex-1">
                                                            <span class="block truncate text-sm font-semibold text-slate-900">
                                                                {{ $seado['name'] }}
                                                            </span>
                                                            <span class="block text-xs text-slate-500">
                                                                {{ $seado['open'] }} open · {{ $seado['disposed'] }} disposed
                                                                @unless ($seado['has_account'])
                                                                    · no system account (no in-app alert)
                                                                @endunless
                                                            </span>
                                                        </span>

                                                        <span class="text-right">
                                                            <span class="block text-lg font-bold text-slate-950">{{ $seado['total'] }}</span>
                                                            <span class="block text-[10px] uppercase text-slate-400">handled</span>
                                                        </span>

                                                    </label>

                                                @empty

                                                    <p class="rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-800">
                                                        No SEADO handles cases in this office yet. Assign one from the case page.
                                                    </p>

                                                @endforelse

                                            </fieldset>

                                            <div class="mt-4">
                                                <label for="date-{{ $rfa->id }}" class="mb-1 block text-sm font-semibold text-slate-700">
                                                    Date assigned
                                                </label>
                                                <input
                                                    id="date-{{ $rfa->id }}"
                                                    type="date"
                                                    name="date_assigned_seado"
                                                    value="{{ $today->toDateString() }}"
                                                    max="{{ $today->toDateString() }}"
                                                    required
                                                    class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm"
                                                >
                                                <p class="mt-1 text-xs text-slate-400">Starts PCT 4 (SEADO Assignment → 1st Conference).</p>
                                            </div>

                                            <div class="mt-6 flex justify-end gap-2">
                                                <button
                                                    type="button"
                                                    @click="open = false"
                                                    class="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
                                                >
                                                    Cancel
                                                </button>
                                                <button
                                                    type="submit"
                                                    @disabled($options->isEmpty())
                                                    class="rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700 disabled:opacity-50"
                                                >
                                                    Assign
                                                </button>
                                            </div>

                                        </form>

                                    </div>

                                </template>

                            </td>

                        </tr>

                    @empty

                        <tr>
                            <td colspan="6" class="px-6 py-14 text-center text-sm text-slate-500">
                                No open RFAs are waiting for a SEADO{{ $officeLabel ? ' in ' . $officeLabel : '' }}.
                            </td>
                        </tr>

                    @endforelse

                </tbody>

            </table>

        </div>

        @if ($queue->hasPages())
            <div class="border-t border-slate-100 px-6 py-4">
                {{ $queue->fragment('awaiting')->links() }}
            </div>
        @endif

    </section>


    {{-- ========================================================= --}}
    {{-- WORKLOAD --}}
    {{-- ========================================================= --}}

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">

        <div class="border-b border-slate-100 px-6 py-5">
            <p class="text-xs font-bold uppercase tracking-[0.15em] text-blue-600">Workload</p>
            <h2 class="mt-1 text-lg font-bold text-slate-950">RFAs handled per SEADO</h2>
            <p class="mt-1 text-sm text-slate-500">
                Fewest handled first{{ $officeLabel ? ', counting ' . $officeLabel . ' cases only' : '' }}.
            </p>
        </div>

        <div class="max-h-[36rem] overflow-auto">

            <table class="w-full min-w-[760px] divide-y divide-slate-200">

                <thead class="sticky top-0 bg-slate-50">
                    <tr>
                        @foreach (['#', 'SEADO', 'Office', 'Handled', 'Open', 'Disposed'] as $header)
                            <th class="px-4 py-3 text-left text-xs font-bold uppercase text-slate-500">{{ $header }}</th>
                        @endforeach
                    </tr>
                </thead>

                <tbody class="divide-y divide-slate-100">

                    @forelse ($roster as $seado)

                        <tr>
                            <td class="px-4 py-3 text-sm text-slate-400">{{ $loop->iteration }}</td>

                            <td class="px-4 py-3 text-sm">
                                <span class="font-semibold text-slate-900">{{ $seado['name'] }}</span>
                                @if ($seado['has_account'])
                                    <span class="ml-1 rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-semibold text-emerald-700">Account</span>
                                @endif
                            </td>

                            <td class="px-4 py-3 text-xs text-slate-500">{{ implode(', ', $seado['offices']) ?: '—' }}</td>

                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <span class="w-10 text-sm font-bold text-slate-950">{{ $seado['total'] }}</span>
                                    <span class="h-2 w-32 overflow-hidden rounded-full bg-slate-100">
                                        <span class="block h-full rounded-full bg-blue-500" style="width: {{ round($seado['total'] / $maxHandled * 100) }}%"></span>
                                    </span>
                                </div>
                            </td>

                            <td class="px-4 py-3 text-sm text-slate-700">{{ $seado['open'] }}</td>

                            <td class="px-4 py-3 text-sm text-slate-700">{{ $seado['disposed'] }}</td>
                        </tr>

                    @empty

                        <tr>
                            <td colspan="6" class="px-6 py-14 text-center text-sm text-slate-500">
                                No SEADOs found{{ $officeLabel ? ' for ' . $officeLabel : '' }}.
                            </td>
                        </tr>

                    @endforelse

                </tbody>

            </table>

        </div>

    </section>

</div>

@endsection
