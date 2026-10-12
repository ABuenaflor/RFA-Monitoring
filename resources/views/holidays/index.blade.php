@extends('layouts.app')


@section(
    'title',
    'Holidays & Events | RFA Monitoring System'
)


@section(
    'page_heading',
    'Holidays & Events'
)


@section('content')

@php
    $canManage = auth()->user()->can('holidays.manage');

    $form = $editing
        ? [
            'action' => route('holidays.update', $editing),
            'date' => old('date', $editing->date->format('Y-m-d')),
            'name' => old('name', $editing->name),
            'type' => old('type', $editing->type),
            'notes' => old('notes', $editing->notes),
        ]
        : [
            'action' => route('holidays.store'),
            'date' => old('date'),
            'name' => old('name'),
            'type' => old('type', \App\Models\Holiday::TYPE_HOLIDAY),
            'notes' => old('notes'),
        ];
@endphp

<div class="space-y-7">

    {{-- ========================================================= --}}
    {{-- DESCRIPTION --}}
    {{-- ========================================================= --}}

    <section class="flex flex-col justify-between gap-4 lg:flex-row lg:items-end">

        <div>

            <p class="max-w-3xl text-sm leading-6 text-slate-500">
                Non-working dates the PCT clock skips. When PCT counts working
                days, Saturdays, Sundays and every date listed here are not
                counted toward any checkpoint limit.
            </p>

            <p
                @class([
                    'mt-3 inline-flex rounded-full px-3 py-1 text-xs font-semibold',
                    'bg-emerald-50 text-emerald-700' => $countsWorkingDays,
                    'bg-amber-50 text-amber-700' => ! $countsWorkingDays,
                ])
            >
                @if ($countsWorkingDays)
                    PCT is counting working days — these dates are skipped
                @else
                    PCT is counting calendar days — these dates are listed but not skipped
                @endif
            </p>

        </div>

        <form method="GET" action="{{ route('holidays.index') }}" class="flex items-center gap-2">

            <label for="year" class="text-sm font-semibold text-slate-700">Year</label>

            <select
                id="year"
                name="year"
                onchange="this.form.submit()"
                class="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm"
            >
                @foreach ($years as $option)
                    <option value="{{ $option }}" @selected($option === $year)>{{ $option }}</option>
                @endforeach
            </select>

        </form>

    </section>


    {{-- ========================================================= --}}
    {{-- ADD / EDIT --}}
    {{-- ========================================================= --}}

    @if ($canManage)

        <section
            id="holiday-form"
            class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"
        >

            <p class="text-xs font-bold uppercase tracking-[0.15em] text-blue-600">
                {{ $editing ? 'Edit Entry' : 'Add Entry' }}
            </p>

            <h2 class="mt-1 text-lg font-bold text-slate-950">
                {{ $editing ? 'Edit holiday or event' : 'Add a holiday or event' }}
            </h2>

            <form method="POST" action="{{ $form['action'] }}" class="mt-5 grid grid-cols-1 gap-5 md:grid-cols-4">

                @csrf

                @if ($editing)
                    @method('PUT')
                @endif

                <div>
                    <label for="date" class="mb-2 block text-sm font-semibold text-slate-700">
                        {{ $editing ? 'Date' : 'Date (from)' }}
                    </label>

                    <input
                        id="date"
                        name="date"
                        type="date"
                        required
                        value="{{ $form['date'] }}"
                        class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-100"
                    >
                </div>

                @unless ($editing)

                    <div>
                        <label for="end_date" class="mb-2 block text-sm font-semibold text-slate-700">
                            To <span class="font-normal text-slate-400">(optional, multi-day)</span>
                        </label>

                        <input
                            id="end_date"
                            name="end_date"
                            type="date"
                            value="{{ old('end_date') }}"
                            class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-100"
                        >
                    </div>

                @endunless

                <div @class(['md:col-span-2' => ! $editing, 'md:col-span-3' => $editing])>
                    <label for="name" class="mb-2 block text-sm font-semibold text-slate-700">Name</label>

                    <input
                        id="name"
                        name="name"
                        required
                        maxlength="150"
                        value="{{ $form['name'] }}"
                        placeholder="e.g. Independence Day, Regional Office Seminar"
                        class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-100"
                    >
                </div>

                <div>
                    <label for="type" class="mb-2 block text-sm font-semibold text-slate-700">Type</label>

                    <select
                        id="type"
                        name="type"
                        class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm"
                    >
                        @foreach ($types as $value => $label)
                            <option value="{{ $value }}" @selected($form['type'] === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="md:col-span-3">
                    <label for="notes" class="mb-2 block text-sm font-semibold text-slate-700">
                        Notes <span class="font-normal text-slate-400">(optional)</span>
                    </label>

                    <input
                        id="notes"
                        name="notes"
                        maxlength="1000"
                        value="{{ $form['notes'] }}"
                        placeholder="Proclamation number, affected offices, etc."
                        class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-100"
                    >
                </div>

                <div class="flex items-center gap-2 md:col-span-4">

                    <button
                        type="submit"
                        class="rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700"
                    >
                        {{ $editing ? 'Save Changes' : 'Add to Calendar' }}
                    </button>

                    @if ($editing)
                        <a
                            href="{{ route('holidays.index', ['year' => $year]) }}"
                            class="rounded-xl border border-slate-300 bg-white px-5 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
                        >
                            Cancel
                        </a>
                    @endif

                </div>

            </form>

        </section>

    @endif


    {{-- ========================================================= --}}
    {{-- LIST --}}
    {{-- ========================================================= --}}

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">

        <div class="flex flex-col justify-between gap-2 border-b border-slate-100 px-6 py-5 sm:flex-row sm:items-end">

            <div>
                <p class="text-xs font-bold uppercase tracking-[0.15em] text-blue-600">Calendar {{ $year }}</p>
                <h2 class="mt-1 text-lg font-bold text-slate-950">Non-working dates</h2>
            </div>

            <p class="text-sm text-slate-500">
                {{ $holidays->count() }} {{ str('entry')->plural($holidays->count()) }}
                · {{ $weekdayCount }} on a weekday
            </p>

        </div>

        @forelse ($byMonth as $month => $entries)

            <div class="border-b border-slate-100 last:border-b-0">

                <p class="bg-slate-50 px-6 py-2 text-xs font-bold uppercase tracking-wider text-slate-500">
                    {{ $month }}
                </p>

                <ul class="divide-y divide-slate-100">

                    @foreach ($entries as $holiday)

                        <li class="flex flex-col gap-3 px-6 py-4 sm:flex-row sm:items-center">

                            <div class="flex w-16 shrink-0 flex-col items-center rounded-xl border border-slate-200 py-1.5">
                                <span class="text-[10px] font-bold uppercase text-slate-400">{{ $holiday->date->format('D') }}</span>
                                <span class="text-xl font-bold text-slate-950">{{ $holiday->date->format('d') }}</span>
                            </div>

                            <div class="min-w-0 flex-1">

                                <p class="font-semibold text-slate-900">{{ $holiday->name }}</p>

                                <p class="mt-1 flex flex-wrap items-center gap-2 text-xs text-slate-500">

                                    <span
                                        @class([
                                            'rounded-full px-2 py-0.5 font-semibold',
                                            'bg-rose-50 text-rose-700' => $holiday->type === \App\Models\Holiday::TYPE_HOLIDAY,
                                            'bg-violet-50 text-violet-700' => $holiday->type !== \App\Models\Holiday::TYPE_HOLIDAY,
                                        ])
                                    >
                                        {{ $holiday->typeLabel() }}
                                    </span>

                                    @if ($holiday->date->isWeekend())
                                        <span class="text-slate-400">Falls on a weekend — no effect on PCT</span>
                                    @endif

                                    @if ($holiday->notes)
                                        <span>{{ $holiday->notes }}</span>
                                    @endif

                                </p>

                            </div>

                            @if ($canManage)

                                <div class="flex items-center gap-3 text-sm font-semibold">

                                    <a
                                        href="{{ route('holidays.index', ['year' => $year, 'edit' => $holiday->id]) }}#holiday-form"
                                        class="text-blue-600 transition hover:text-blue-700"
                                    >
                                        Edit
                                    </a>

                                    <form
                                        method="POST"
                                        action="{{ route('holidays.destroy', $holiday) }}"
                                        onsubmit="return confirm('Remove {{ addslashes($holiday->name) }}? PCT will count this date again.');"
                                    >
                                        @csrf
                                        @method('DELETE')

                                        <button type="submit" class="text-rose-600 transition hover:text-rose-700">
                                            Delete
                                        </button>
                                    </form>

                                </div>

                            @endif

                        </li>

                    @endforeach

                </ul>

            </div>

        @empty

            <p class="px-6 py-14 text-center text-sm text-slate-500">
                No holidays or events recorded for {{ $year }}.
                @if ($canManage)
                    Add one above.
                @endif
            </p>

        @endforelse

    </section>

</div>

@endsection
