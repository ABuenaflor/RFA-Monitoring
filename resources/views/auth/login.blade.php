<!DOCTYPE html>

<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Sign In | RFA Monitoring System
    </title>

    @vite([
        'resources/css/app.css',
        'resources/js/app.js'
    ])

</head>


<body
    class="min-h-screen
           bg-slate-100
           text-slate-900
           antialiased"
>

<main
    class="grid min-h-screen
           lg:grid-cols-[1.1fr_0.9fr]"
>

    {{-- ========================================================= --}}
    {{-- BRAND PANEL --}}
    {{-- ========================================================= --}}

    <section
        class="hidden
               bg-slate-950 p-12
               text-white
               lg:flex lg:flex-col
               lg:justify-between"
    >

        <div>

            <p
                class="text-xs font-bold
                       uppercase tracking-[0.2em]
                       text-blue-300"
            >
                DOLE Region V
            </p>

            <h1
                class="mt-5 max-w-xl
                       text-4xl font-bold"
            >
                RFA Monitoring System
            </h1>

            <p
                class="mt-4 max-w-xl
                       text-sm leading-7
                       text-slate-300"
            >
                Secure access for case monitoring, PCT compliance, reports,
                administration, and audit-ready workflow management.
            </p>

        </div>


        <div
            class="grid grid-cols-3 gap-3
                   text-xs text-slate-300"
        >

            <div
                class="rounded-2xl
                       border border-slate-700
                       bg-slate-900/60 p-4"
            >
                <p class="font-bold text-white">PCT</p>

                <p class="mt-1">
                    3-day checkpoints and 30-day disposition monitoring
                </p>
            </div>


            <div
                class="rounded-2xl
                       border border-slate-700
                       bg-slate-900/60 p-4"
            >
                <p class="font-bold text-white">RBAC</p>

                <p class="mt-1">
                    Role-controlled operational access
                </p>
            </div>


            <div
                class="rounded-2xl
                       border border-slate-700
                       bg-slate-900/60 p-4"
            >
                <p class="font-bold text-white">Audit</p>

                <p class="mt-1">
                    Traceable changes and actions
                </p>
            </div>

        </div>

    </section>


    {{-- ========================================================= --}}
    {{-- SIGN-IN FORM --}}
    {{-- ========================================================= --}}

    <section
        class="flex items-center
               justify-center
               p-6 sm:p-10"
    >

        <div
            class="w-full max-w-md
                   rounded-2xl
                   border border-slate-200
                   bg-white p-7
                   shadow-sm"
        >

            <p
                class="text-xs font-bold
                       uppercase tracking-[0.15em]
                       text-blue-600"
            >
                Secure Access
            </p>

            <h2
                class="mt-2 text-2xl
                       font-bold text-slate-950"
            >
                Sign in to continue
            </h2>

            <p
                class="mt-2 text-sm leading-6
                       text-slate-500"
            >
                Use your authorized RFA Monitoring System account.
            </p>


            {{-- STATUS MESSAGE --}}

            @if (session('status'))

                <div
                    class="mt-5 rounded-xl
                           border border-emerald-200
                           bg-emerald-50
                           px-4 py-3
                           text-sm font-medium
                           text-emerald-800"
                >
                    {{ session('status') }}
                </div>

            @endif


            {{-- ERRORS --}}

            @if ($errors->any())

                <div
                    class="mt-5 rounded-xl
                           border border-rose-200
                           bg-rose-50
                           px-4 py-3"
                >

                    <p
                        class="text-xs font-bold
                               uppercase tracking-wider
                               text-rose-700"
                    >
                        Sign-in failed
                    </p>

                    <ul
                        class="mt-2 space-y-1
                               text-sm text-rose-800"
                    >
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>

                </div>

            @endif


            <form
                method="POST"
                action="{{ route('login') }}"
                class="mt-7 space-y-5"
            >

                @csrf


                <div>

                    <label
                        for="email"
                        class="mb-2 block text-sm
                               font-semibold text-slate-700"
                    >
                        Email
                    </label>

                    <input
                        id="email"
                        name="email"
                        type="email"
                        value="{{ old('email') }}"
                        required
                        autofocus
                        autocomplete="username"
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
                        for="password"
                        class="mb-2 block text-sm
                               font-semibold text-slate-700"
                    >
                        Password
                    </label>

                    <input
                        id="password"
                        name="password"
                        type="password"
                        required
                        autocomplete="current-password"
                        class="w-full rounded-xl
                               border border-slate-300
                               px-4 py-3 text-sm
                               outline-none
                               focus:border-blue-500
                               focus:ring-4 focus:ring-blue-100"
                    >

                </div>


                <label
                    class="flex items-center gap-3
                           text-sm text-slate-600"
                >

                    <input
                        type="checkbox"
                        name="remember"
                        value="1"
                        class="rounded border-slate-300"
                    >

                    Keep me signed in on this device

                </label>


                <button
                    type="submit"
                    class="w-full rounded-xl
                           bg-blue-600 px-5 py-3
                           text-sm font-semibold
                           text-white shadow-sm
                           transition
                           hover:bg-blue-700"
                >
                    Sign In
                </button>

            </form>


            <div
                class="mt-6 rounded-xl
                       border border-slate-200
                       bg-slate-50
                       px-4 py-3
                       text-xs leading-5
                       text-slate-500"
            >
                Accounts are issued by a system administrator. Repeated failed
                sign-in attempts are rate limited.
            </div>

        </div>

    </section>

</main>

</body>

</html>
