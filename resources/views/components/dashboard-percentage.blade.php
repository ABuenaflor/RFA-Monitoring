@props([
    'label',
    'count',
    'percentage',
    'type',
])

@php

    $styles = match ($type) {
        'pending' => [
            'dot' => 'bg-amber-500',
            'bar' => 'bg-amber-500',
        ],

        'ongoing' => [
            'dot' => 'bg-blue-600',
            'bar' => 'bg-blue-600',
        ],

        'disposed' => [
            'dot' => 'bg-emerald-600',
            'bar' => 'bg-emerald-600',
        ],

        default => [
            'dot' => 'bg-slate-500',
            'bar' => 'bg-slate-500',
        ],
    };

@endphp


<div
    class="rounded-xl
           border border-slate-200
           p-5"
>

    <div
        class="flex items-center
               justify-between gap-4"
    >

        <div
            class="flex items-center
                   gap-3"
        >

            <span
                class="h-3 w-3
                       rounded-full
                       {{ $styles['dot'] }}"
            ></span>

            <div>

                <p
                    class="text-sm font-semibold
                           text-slate-700"
                >
                    {{ $label }}
                </p>

                <p
                    class="mt-0.5 text-xs
                           text-slate-400"
                >
                    {{ number_format($count) }}
                    records
                </p>

            </div>

        </div>


        <span
            class="text-lg font-bold
                   text-slate-950"
        >
            {{ $percentage }}%
        </span>

    </div>


    <div
        class="mt-4 h-2
               overflow-hidden
               rounded-full
               bg-slate-100"
    >

        <div
            class="h-full rounded-full
                   {{ $styles['bar'] }}"

            style="
                width: {{ $percentage }}%
            "
        ></div>

    </div>

</div>