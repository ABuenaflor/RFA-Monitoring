@extends('layouts.app')


@section(
    'title',
    'Edit User | RFA Monitoring System'
)


@section(
    'page_heading',
    'Edit User'
)


@section('content')

<div class="space-y-7">

    {{-- ========================================================= --}}
    {{-- ACCOUNT SUMMARY --}}
    {{-- ========================================================= --}}

    <section
        class="rounded-2xl
               border border-slate-200
               bg-white p-6 shadow-sm"
    >

        <div
            class="flex flex-col justify-between
                   gap-5 lg:flex-row lg:items-center"
        >

            <div class="flex items-center gap-4">

                <span
                    class="flex h-14 w-14
                           items-center justify-center
                           rounded-2xl bg-blue-600
                           text-lg font-bold text-white"
                >
                    {{ $user->initials() }}
                </span>

                <div>

                    <h2
                        class="text-xl font-bold
                               text-slate-950"
                    >
                        {{ $user->name }}
                    </h2>

                    <p
                        class="mt-1 text-sm
                               text-slate-500"
                    >
                        {{ $user->email }}
                    </p>

                </div>

            </div>


            <dl
                class="grid grid-cols-2 gap-4
                       text-sm sm:grid-cols-3"
            >

                <div>

                    <dt
                        class="text-xs font-bold
                               uppercase text-slate-400"
                    >
                        Created
                    </dt>

                    <dd
                        class="mt-1 font-semibold
                               text-slate-800"
                    >
                        {{ $user->created_at?->format('M d, Y') ?? '—' }}
                    </dd>

                </div>


                <div>

                    <dt
                        class="text-xs font-bold
                               uppercase text-slate-400"
                    >
                        Last Login
                    </dt>

                    <dd
                        class="mt-1 font-semibold
                               text-slate-800"
                    >
                        {{ $user->last_login_at?->format('M d, Y g:i A') ?? 'Never' }}
                    </dd>

                </div>


                <div>

                    <dt
                        class="text-xs font-bold
                               uppercase text-slate-400"
                    >
                        Last IP
                    </dt>

                    <dd
                        class="mt-1 font-semibold
                               text-slate-800"
                    >
                        {{ $user->last_login_ip ?? '—' }}
                    </dd>

                </div>

            </dl>

        </div>

    </section>


    <form
        method="POST"
        action="{{ route('admin.access.update', $user) }}"
    >

        @csrf
        @method('PUT')

        @include('admin.access._form', [
            'user' => $user,
            'roles' => $roles,
            'offices' => $offices,
        ])

    </form>

</div>

@endsection
