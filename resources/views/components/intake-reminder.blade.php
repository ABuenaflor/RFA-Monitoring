{{--
| Missing intake dates reminder.
|
| Raised at sign-in (AppServiceProvider) for the open cases this user
| handles as SEADO that still lack a Date Interviewed or Date TA/NORES. Each case
| links to its page, where a required form asks for both dates. Dismissing
| hides it until the next sign-in.
--}}

@php
    $reminderUser = auth()->user();

    $reminderQuery = session('intake_reminder') && $reminderUser
        ? \App\Models\Rfa::query()
            ->missingIntakeDates()
            ->handledBySeado($reminderUser)
        : null;

    $reminderTotal = $reminderQuery ? (clone $reminderQuery)->count() : 0;

    $reminderCases = $reminderTotal > 0
        ? $reminderQuery
            ->orderBy('date_filed')
            ->orderBy('id')
            ->limit(50)
            ->get()
        : collect();
@endphp

@if ($reminderTotal > 0)

    <div
        x-data="{
            open: true,
            dismiss() {
                this.open = false;

                fetch(@js(route('intake-reminder.dismiss')), {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': @js(csrf_token()),
                        'Accept': 'application/json',
                    },
                });
            },
        }"
        x-show="open"
        x-transition:enter="transition duration-300 ease-out"
        x-transition:enter-start="translate-y-4 opacity-0"
        x-transition:enter-end="translate-y-0 opacity-100"
        x-transition:leave="transition duration-200 ease-in"
        x-transition:leave-end="translate-y-4 opacity-0"
        role="alertdialog"
        aria-labelledby="intake-reminder-title"
        aria-describedby="intake-reminder-body"
        class="fixed bottom-4 right-4 z-50
               flex max-h-[min(32rem,calc(100vh-2rem))]
               w-[calc(100vw-2rem)] max-w-sm flex-col
               overflow-hidden rounded-2xl
               border border-amber-300 bg-white
               shadow-2xl ring-4 ring-amber-100"
    >

        <div class="flex items-start gap-3 border-b border-amber-200 bg-amber-50 px-4 py-3">

            <span class="relative mt-0.5 flex h-3 w-3 shrink-0">
                <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-amber-400 opacity-75 motion-reduce:hidden"></span>
                <span class="relative inline-flex h-3 w-3 rounded-full bg-amber-500"></span>
            </span>

            <div class="min-w-0 flex-1">
                <p id="intake-reminder-title" class="text-sm font-bold text-amber-900">
                    {{ $reminderTotal === 1 ? '1 case needs dates' : $reminderTotal . ' cases need dates' }}
                </p>
                <p id="intake-reminder-body" class="mt-0.5 text-xs leading-5 text-amber-800">
                    Enter the Date Interviewed and Date TA/NORES. Click a case to open it.
                </p>
            </div>

            <button
                type="button"
                @click="dismiss()"
                class="-mr-1 rounded-lg p-1 text-amber-700 transition hover:bg-amber-100"
                aria-label="Remind me at my next sign-in"
                title="Remind me at my next sign-in"
            >
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" d="M6 6l12 12M18 6 6 18" />
                </svg>
            </button>

        </div>

        <ul class="min-h-0 flex-1 divide-y divide-slate-100 overflow-y-auto">

            @foreach ($reminderCases as $reminderCase)

                <li>
                    <a
                        href="{{ route('rfas.show', $reminderCase) }}"
                        class="block px-4 py-3 transition hover:bg-slate-50 focus:bg-slate-50 focus:outline-none"
                    >
                        <span class="block truncate text-sm font-semibold text-slate-900">
                            {{ $reminderCase->requesting_party ?: 'No complainant on record' }}
                        </span>

                        <span class="block truncate text-xs text-slate-600">
                            {{ $reminderCase->responding_party ?: 'No company on record' }}
                        </span>

                        <span class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-0.5 text-[11px] text-slate-500">
                            <span class="font-mono">{{ $reminderCase->docket_no ?: $reminderCase->reference_no }}</span>
                            <span aria-hidden="true">·</span>
                            <span>Filed {{ $reminderCase->date_filed?->format('M d, Y') ?? '—' }}</span>
                        </span>

                        <span class="mt-1.5 flex flex-wrap gap-1">
                            @if ($reminderCase->date_interview === null)
                                <span class="rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-semibold text-amber-800">Date Interviewed</span>
                            @endif
                            @if ($reminderCase->date_ta_nores === null)
                                <span class="rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-semibold text-amber-800">Date TA/NORES</span>
                            @endif
                        </span>
                    </a>
                </li>

            @endforeach

        </ul>

        @if ($reminderTotal > $reminderCases->count())
            <p class="border-t border-slate-100 bg-slate-50 px-4 py-2 text-[11px] text-slate-500">
                Showing the {{ $reminderCases->count() }} oldest of {{ $reminderTotal }}.
            </p>
        @endif

    </div>

@endif
