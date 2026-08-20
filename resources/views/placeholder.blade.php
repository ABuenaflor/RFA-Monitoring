@extends('layouts.app')


@section(
    'title',
    $pageTitle . ' | RFA Monitoring System'
)


@section(
    'page_heading',
    $pageTitle
)


@section('content')

<div
    class="mx-auto max-w-5xl"
>

    <div
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
                class="text-xs font-semibold
                       uppercase tracking-[0.15em]
                       text-blue-600"
            >
                RFA Monitoring System
            </p>

            <h2
                class="mt-2 text-2xl
                       font-bold tracking-tight
                       text-slate-950"
            >
                {{ $pageTitle }}
            </h2>

        </div>


        <div
            class="px-6 py-12"
        >

            <div
                class="mx-auto max-w-xl
                       text-center"
            >

                <div
                    class="mx-auto flex
                           h-16 w-16
                           items-center
                           justify-center
                           rounded-2xl
                           bg-blue-50
                           text-blue-600"
                >

                    <svg
                        class="h-8 w-8"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.7"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="
                                M8 7V3
                                m8 4V3
                                M5 11h14
                                M6 5h12
                                a2 2 0 0 1 2 2
                                v12
                                a2 2 0 0 1-2 2
                                H6
                                a2 2 0 0 1-2-2
                                V7
                                a2 2 0 0 1 2-2
                            "
                        />
                    </svg>

                </div>


                <h3
                    class="mt-5 text-xl
                           font-bold text-slate-950"
                >
                    {{ $pageTitle }}
                </h3>


                <p
                    class="mt-3 text-sm
                           leading-6 text-slate-500"
                >
                    {{ $pageDescription }}
                </p>


                <div
                    class="mt-6 inline-flex
                           rounded-full
                           bg-slate-100
                           px-4 py-2
                           text-xs font-semibold
                           text-slate-600"
                >
                    Scheduled for {{ $phase }}
                </div>

            </div>

        </div>

    </div>

</div>

@endsection