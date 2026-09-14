@extends('layouts.app')


@section(
    'title',
    'My Account | RFA Monitoring System'
)


@section(
    'page_heading',
    'My Account'
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

                    <p
                        class="text-xs font-bold
                               uppercase tracking-[0.15em]
                               text-blue-600"
                    >
                        Signed In As
                    </p>

                    <h2
                        class="mt-1 text-xl
                               font-bold text-slate-950"
                    >
                        {{ $user->name }}
                    </h2>

                    <p
                        class="mt-1 text-sm
                               text-slate-500"
                    >
                        {{ $user->roleName() }}

                        @if ($user->office)
                            &middot; {{ $user->office }}
                        @endif
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
                        Status
                    </dt>

                    <dd class="mt-1">

                        <span
                            class="rounded-full
                                   bg-emerald-50 px-3 py-1
                                   text-xs font-semibold
                                   text-emerald-700"
                        >
                            {{ $user->statusLabel() }}
                        </span>

                    </dd>

                </div>


                <div>

                    <dt
                        class="text-xs font-bold
                               uppercase text-slate-400"
                    >
                        Position
                    </dt>

                    <dd
                        class="mt-1 font-semibold
                               text-slate-800"
                    >
                        {{ $user->position ?? '—' }}
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
                        {{ $user->last_login_at?->format('M d, Y g:i A') ?? 'First session' }}
                    </dd>

                </div>

            </dl>

        </div>

    </section>


    {{-- ========================================================= --}}
    {{-- PROFILE --}}
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
            Profile
        </p>

        <h2
            class="mt-1 text-lg
                   font-bold text-slate-950"
        >
            Contact details
        </h2>

        <p
            class="mt-2 text-sm leading-6
                   text-slate-500"
        >
            Role, office, and account status are set by a system administrator.
        </p>


        <form
            method="POST"
            action="{{ route('account.update') }}"
            class="mt-6"
        >

            @csrf
            @method('PUT')


            <div
                class="grid grid-cols-1 gap-5
                       md:grid-cols-2"
            >

                <div>

                    <label
                        for="name"
                        class="mb-2 block text-sm
                               font-semibold text-slate-700"
                    >
                        Full Name
                    </label>

                    <input
                        id="name"
                        name="name"
                        required
                        value="{{ old('name', $user->name) }}"
                        class="w-full rounded-xl
                               border border-slate-300
                               px-4 py-3 text-sm
                               outline-none
                               focus:border-blue-500
                               focus:ring-4 focus:ring-blue-100"
                    >

                </div>


                <div>

                    <label
                        for="email"
                        class="mb-2 block text-sm
                               font-semibold text-slate-700"
                    >
                        Email Address
                    </label>

                    <input
                        id="email"
                        name="email"
                        type="email"
                        required
                        value="{{ old('email', $user->email) }}"
                        class="w-full rounded-xl
                               border border-slate-300
                               px-4 py-3 text-sm
                               outline-none
                               focus:border-blue-500
                               focus:ring-4 focus:ring-blue-100"
                    >

                </div>

            </div>


            <button
                type="submit"
                class="mt-6 rounded-xl
                       bg-blue-600 px-5 py-3
                       text-sm font-semibold
                       text-white shadow-sm
                       transition
                       hover:bg-blue-700"
            >
                Save Profile
            </button>

        </form>

    </section>


    {{-- ========================================================= --}}
    {{-- PASSWORD --}}
    {{-- ========================================================= --}}

    <section
        @class([
            'rounded-2xl border p-6 shadow-sm',

            'border-amber-200 bg-amber-50'
                => $user->must_change_password,

            'border-slate-200 bg-white'
                => ! $user->must_change_password,
        ])
    >

        <p
            @class([
                'text-xs font-bold uppercase tracking-[0.15em]',

                'text-amber-700' => $user->must_change_password,
                'text-emerald-600' => ! $user->must_change_password,
            ])
        >
            Password
        </p>

        <h2
            class="mt-1 text-lg
                   font-bold text-slate-950"
        >
            @if ($user->must_change_password)
                A new password is required before continuing
            @else
                Change password
            @endif
        </h2>

        <p
            class="mt-2 text-sm leading-6
                   text-slate-600"
        >
            Minimum 10 characters, including at least one letter and one number.
        </p>


        <form
            method="POST"
            action="{{ route('account.password') }}"
            class="mt-6"
        >

            @csrf
            @method('PUT')


            <div
                class="grid grid-cols-1 gap-5
                       md:grid-cols-3"
            >

                <div>

                    <label
                        for="current_password"
                        class="mb-2 block text-sm
                               font-semibold text-slate-700"
                    >
                        Current Password
                    </label>

                    <input
                        id="current_password"
                        name="current_password"
                        type="password"
                        required
                        autocomplete="current-password"
                        class="w-full rounded-xl
                               border border-slate-300
                               bg-white px-4 py-3 text-sm
                               outline-none
                               focus:border-blue-500
                               focus:ring-4 focus:ring-blue-100"
                    >

                </div>


                <div>

                    <label
                        for="password"
                        class="mb-2 block text-sm
                               font-semibold text-slate-700"
                    >
                        New Password
                    </label>

                    <input
                        id="password"
                        name="password"
                        type="password"
                        required
                        autocomplete="new-password"
                        class="w-full rounded-xl
                               border border-slate-300
                               bg-white px-4 py-3 text-sm
                               outline-none
                               focus:border-blue-500
                               focus:ring-4 focus:ring-blue-100"
                    >

                </div>


                <div>

                    <label
                        for="password_confirmation"
                        class="mb-2 block text-sm
                               font-semibold text-slate-700"
                    >
                        Confirm New Password
                    </label>

                    <input
                        id="password_confirmation"
                        name="password_confirmation"
                        type="password"
                        required
                        autocomplete="new-password"
                        class="w-full rounded-xl
                               border border-slate-300
                               bg-white px-4 py-3 text-sm
                               outline-none
                               focus:border-blue-500
                               focus:ring-4 focus:ring-blue-100"
                    >

                </div>

            </div>


            <button
                type="submit"
                class="mt-6 rounded-xl
                       bg-slate-950 px-5 py-3
                       text-sm font-semibold
                       text-white shadow-sm
                       transition
                       hover:bg-slate-800"
            >
                Change Password
            </button>

        </form>

    </section>

</div>

@endsection
