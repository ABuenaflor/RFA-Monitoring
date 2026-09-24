@extends('layouts.app')


@section(
    'title',
    'System Settings | RFA Monitoring System'
)


@section(
    'page_heading',
    'System Settings'
)


@section('content')

<div class="space-y-7">

    <section>

        <p
            class="max-w-4xl text-sm
                   leading-6 text-slate-500"
        >
            A closed list of controlled settings. Business rules are not here on
            purpose: the five PCT checkpoint limits are policy, not
            configuration, and cannot be changed from the interface.
        </p>


        @if ($lastChanged)

            <p
                class="mt-3 text-xs
                       text-slate-400"
            >
                Last changed
                {{ $lastChanged->updated_at?->diffForHumans() }}
                by {{ $lastChanged->editor?->name ?? 'System' }}.
            </p>

        @endif

    </section>


    <form
        method="POST"
        action="{{ route('admin.settings.update') }}"
        class="space-y-7"
    >

        @csrf
        @method('PUT')


        @foreach ($groups as $groupName => $definitions)

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
                    {{ $groupName }}
                </p>

                <h2
                    class="mt-1 text-lg
                           font-bold text-slate-950"
                >
                    {{ match ($groupName) {
                        'Identity' => 'How the system names itself on reports',
                        'Operations' => 'Everyday defaults',
                        'Retention' => 'How long history is kept',
                        default => $groupName,
                    } }}
                </h2>


                <div
                    class="mt-6 grid grid-cols-1
                           gap-5 md:grid-cols-2"
                >

                    @foreach ($definitions as $key => $definition)

                        <div
                            @class([
                                'md:col-span-2' =>
                                    $key === \App\Support\SystemSettings::REPORT_FOOTER_NOTE,
                            ])
                        >

                            <label
                                for="{{ $key }}"
                                class="mb-2 block text-sm
                                       font-semibold text-slate-700"
                            >
                                {{ $definition['label'] }}
                            </label>


                            @if (isset($definition['options']))

                                <select
                                    id="{{ $key }}"
                                    name="settings[{{ $key }}]"
                                    class="w-full rounded-xl
                                           border border-slate-300
                                           bg-white px-4 py-3 text-sm"
                                >

                                    @foreach ($definition['options'] as $option)

                                        <option
                                            value="{{ $option }}"
                                            @selected((string) old('settings.' . $key, $values[$key]) === (string) $option)
                                        >
                                            {{ $option }}
                                        </option>

                                    @endforeach

                                </select>

                            @else

                                <input
                                    id="{{ $key }}"
                                    name="settings[{{ $key }}]"
                                    type="{{ $definition['type'] === 'integer' ? 'number' : 'text' }}"
                                    value="{{ old('settings.' . $key, $values[$key]) }}"
                                    class="w-full rounded-xl
                                           border border-slate-300
                                           px-4 py-3 text-sm
                                           outline-none
                                           focus:border-blue-500
                                           focus:ring-4 focus:ring-blue-100"
                                >

                            @endif


                            <p
                                class="mt-2 text-xs
                                       leading-5 text-slate-400"
                            >
                                {{ $definition['help'] }}
                            </p>

                            <p
                                class="mt-1 font-mono
                                       text-[11px] text-slate-300"
                            >
                                {{ $key }}
                            </p>

                        </div>

                    @endforeach

                </div>

            </section>

        @endforeach


        <div class="flex items-center gap-2">

            <button
                type="submit"
                class="rounded-xl bg-blue-600
                       px-5 py-3
                       text-sm font-semibold
                       text-white shadow-sm
                       transition
                       hover:bg-blue-700"
            >
                Save Settings
            </button>

            <a
                href="{{ route('dashboard') }}"
                class="rounded-xl
                       border border-slate-300
                       bg-white px-5 py-3
                       text-sm font-semibold
                       text-slate-700
                       transition
                       hover:bg-slate-50"
            >
                Cancel
            </a>

        </div>

    </form>


    {{-- ========================================================= --}}
    {{-- FIXED RULES --}}
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
            Fixed Business Rules
        </p>

        <h2
            class="mt-1 text-lg
                   font-bold text-slate-950"
        >
            Not configurable
        </h2>


        <dl
            class="mt-5 grid grid-cols-1
                   gap-4 md:grid-cols-3"
        >

            @foreach (
                collect(\App\Services\PctService::definitions())
                    ->values()
                    ->mapWithKeys(fn ($definition, $index) => [
                        'PCT ' . ($index + 1) => $definition['label']
                            . ' — ' . $definition['limit_label']
                            . ', calendar days, deadline day compliant',
                    ])
                as $label => $rule
            )

                <div
                    class="rounded-xl
                           border border-slate-200
                           bg-white p-4"
                >

                    <dt
                        class="text-xs font-bold
                               uppercase text-slate-400"
                    >
                        {{ $label }}
                    </dt>

                    <dd
                        class="mt-2 text-sm
                               leading-6 text-slate-700"
                    >
                        {{ $rule }}
                    </dd>

                </div>

            @endforeach

        </dl>

    </section>

</div>

@endsection
