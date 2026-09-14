@php
    $user = $user ?? null;

    $isEdit = $user !== null;
@endphp


<div class="space-y-7">

    {{-- ========================================================= --}}
    {{-- IDENTITY --}}
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
            Account Identity
        </p>

        <h2
            class="mt-1 text-lg
                   font-bold text-slate-950"
        >
            Who this account belongs to
        </h2>


        <div
            class="mt-6 grid grid-cols-1
                   gap-5 md:grid-cols-2"
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
                    value="{{ old('name', $user->name ?? '') }}"
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
                    value="{{ old('email', $user->email ?? '') }}"
                    class="w-full rounded-xl
                           border border-slate-300
                           px-4 py-3 text-sm
                           outline-none
                           focus:border-blue-500
                           focus:ring-4 focus:ring-blue-100"
                >

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
                   text-violet-600"
        >
            Role &amp; Assignment
        </p>

        <h2
            class="mt-1 text-lg
                   font-bold text-slate-950"
        >
            What this account may do
        </h2>


        <div
            class="mt-6 grid grid-cols-1
                   gap-5 md:grid-cols-3"
        >

            <div>

                <label
                    for="role_id"
                    class="mb-2 block text-sm
                           font-semibold text-slate-700"
                >
                    Role
                </label>

                <select
                    id="role_id"
                    name="role_id"
                    required
                    class="w-full rounded-xl
                           border border-slate-300
                           bg-white px-4 py-3 text-sm"
                >

                    <option value="">Select a role</option>

                    @foreach ($roles as $role)

                        <option
                            value="{{ $role->id }}"
                            @selected((int) old('role_id', $user->role_id ?? 0) === $role->id)
                        >
                            {{ $role->name }}
                        </option>

                    @endforeach

                </select>

                <p
                    class="mt-2 text-xs
                           text-slate-400"
                >
                    Permissions come from the role, not from this screen.
                </p>

            </div>


            <div>

                <label
                    for="office"
                    class="mb-2 block text-sm
                           font-semibold text-slate-700"
                >
                    Office
                </label>

                <input
                    id="office"
                    name="office"
                    list="office-options"
                    value="{{ old('office', $user->office ?? '') }}"
                    placeholder="e.g. PFO Albay"
                    class="w-full rounded-xl
                           border border-slate-300
                           px-4 py-3 text-sm
                           outline-none
                           focus:border-blue-500
                           focus:ring-4 focus:ring-blue-100"
                >

                <datalist id="office-options">

                    @foreach ($offices as $office)
                        <option value="{{ $office }}"></option>
                    @endforeach

                </datalist>

            </div>


            <div>

                <label
                    for="position"
                    class="mb-2 block text-sm
                           font-semibold text-slate-700"
                >
                    Position
                </label>

                <input
                    id="position"
                    name="position"
                    value="{{ old('position', $user->position ?? '') }}"
                    placeholder="e.g. SEADO"
                    class="w-full rounded-xl
                           border border-slate-300
                           px-4 py-3 text-sm
                           outline-none
                           focus:border-blue-500
                           focus:ring-4 focus:ring-blue-100"
                >

            </div>

        </div>


        <div class="mt-5">

            <label
                for="status"
                class="mb-2 block text-sm
                       font-semibold text-slate-700"
            >
                Account Status
            </label>

            <select
                id="status"
                name="status"
                required
                class="w-full max-w-xs
                       rounded-xl
                       border border-slate-300
                       bg-white px-4 py-3 text-sm"
            >

                <option
                    value="active"
                    @selected(old('status', $user->status ?? 'active') === 'active')
                >
                    Active
                </option>

                <option
                    value="inactive"
                    @selected(old('status', $user->status ?? 'active') === 'inactive')
                >
                    Inactive
                </option>

            </select>

            <p
                class="mt-2 text-xs
                       text-slate-400"
            >
                An inactive account cannot sign in and is signed out of any
                existing session on its next request.
            </p>

        </div>

    </section>


    {{-- ========================================================= --}}
    {{-- CREDENTIALS --}}
    {{-- ========================================================= --}}

    <section
        class="rounded-2xl
               border border-slate-200
               bg-white p-6 shadow-sm"
    >

        <p
            class="text-xs font-bold
                   uppercase tracking-[0.15em]
                   text-emerald-600"
        >
            Credentials
        </p>

        <h2
            class="mt-1 text-lg
                   font-bold text-slate-950"
        >
            {{ $isEdit ? 'Reset password (optional)' : 'Initial password' }}
        </h2>

        <p
            class="mt-2 text-sm leading-6
                   text-slate-500"
        >
            Minimum 10 characters, including at least one letter and one number.

            @if ($isEdit)
                Leave both fields blank to keep the current password.
            @endif
        </p>


        <div
            class="mt-6 grid grid-cols-1
                   gap-5 md:grid-cols-2"
        >

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
                    autocomplete="new-password"
                    @if (! $isEdit) required @endif
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
                    for="password_confirmation"
                    class="mb-2 block text-sm
                           font-semibold text-slate-700"
                >
                    Confirm Password
                </label>

                <input
                    id="password_confirmation"
                    name="password_confirmation"
                    type="password"
                    autocomplete="new-password"
                    @if (! $isEdit) required @endif
                    class="w-full rounded-xl
                           border border-slate-300
                           px-4 py-3 text-sm
                           outline-none
                           focus:border-blue-500
                           focus:ring-4 focus:ring-blue-100"
                >

            </div>

        </div>


        <label
            class="mt-5 flex items-center gap-3
                   text-sm text-slate-600"
        >

            <input
                type="checkbox"
                name="must_change_password"
                value="1"
                class="rounded border-slate-300"
                @checked(old('must_change_password', $user->must_change_password ?? true))
            >

            Require a password change at next sign-in

        </label>

    </section>


    {{-- ========================================================= --}}
    {{-- ACTIONS --}}
    {{-- ========================================================= --}}

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
            {{ $isEdit ? 'Save Changes' : 'Create User' }}
        </button>

        <a
            href="{{ route('admin.access.index') }}"
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

</div>
