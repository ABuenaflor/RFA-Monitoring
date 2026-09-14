@php
    $role = $role ?? null;

    $isEdit = $role !== null;

    $granted = $granted ?? [];

    $locked = $isEdit && $role->isAdministrator();

    $accents = [
        'blue' => 'text-blue-600',
        'indigo' => 'text-indigo-600',
        'emerald' => 'text-emerald-600',
        'amber' => 'text-amber-600',
        'violet' => 'text-violet-600',
        'rose' => 'text-rose-600',
        'slate' => 'text-slate-500',
    ];
@endphp


<div class="space-y-7">

    {{-- ========================================================= --}}
    {{-- ROLE IDENTITY --}}
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
            Role Definition
        </p>

        <h2
            class="mt-1 text-lg
                   font-bold text-slate-950"
        >
            Name and purpose
        </h2>


        <div
            class="mt-6 grid grid-cols-1
                   gap-5 md:grid-cols-3"
        >

            <div>

                <label
                    for="name"
                    class="mb-2 block text-sm
                           font-semibold text-slate-700"
                >
                    Role Name
                </label>

                <input
                    id="name"
                    name="name"
                    required
                    value="{{ old('name', $role->name ?? '') }}"
                    class="w-full rounded-xl
                           border border-slate-300
                           px-4 py-3 text-sm
                           outline-none
                           focus:border-blue-500
                           focus:ring-4 focus:ring-blue-100"
                >

            </div>


            <div class="md:col-span-2">

                <label
                    for="description"
                    class="mb-2 block text-sm
                           font-semibold text-slate-700"
                >
                    Description
                </label>

                <input
                    id="description"
                    name="description"
                    value="{{ old('description', $role->description ?? '') }}"
                    placeholder="What this role is responsible for"
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
    {{-- PERMISSION MATRIX --}}
    {{-- ========================================================= --}}

    @if ($locked)

        <section
            class="rounded-2xl
                   border border-violet-200
                   bg-violet-50 p-6 shadow-sm"
        >

            <p
                class="text-xs font-bold
                       uppercase tracking-[0.15em]
                       text-violet-700"
            >
                Permission Matrix
            </p>

            <h2
                class="mt-1 text-lg
                       font-bold text-violet-950"
            >
                Administrator holds every permission
            </h2>

            <p
                class="mt-3 text-sm leading-6
                       text-violet-800"
            >
                The administrator role is granted implicitly and cannot be
                narrowed, so the system can never be left without an account
                able to reach access control. Create a custom role instead when
                a narrower administrative profile is needed.
            </p>

        </section>

    @else

        <section class="space-y-5">

            <div>

                <p
                    class="text-xs font-bold
                           uppercase tracking-[0.15em]
                           text-violet-600"
                >
                    Permission Matrix
                </p>

                <h2
                    class="mt-1 text-lg
                           font-bold text-slate-950"
                >
                    What this role may do
                </h2>

            </div>


            @foreach ($groups as $group)

                <div
                    class="rounded-2xl
                           border border-slate-200
                           bg-white p-6 shadow-sm"
                >

                    <p
                        class="text-xs font-bold
                               uppercase tracking-[0.15em]
                               {{ $accents[$group['accent']] ?? 'text-slate-500' }}"
                    >
                        {{ $group['label'] }}
                    </p>


                    <div
                        class="mt-5 grid grid-cols-1
                               gap-3 lg:grid-cols-2"
                    >

                        @foreach ($group['permissions'] as $key => $description)

                            @php
                                $checked = in_array(
                                    $key,
                                    old('permissions', $granted),
                                    true
                                );
                            @endphp

                            <label
                                class="flex cursor-pointer
                                       items-start gap-3
                                       rounded-xl
                                       border border-slate-200
                                       bg-slate-50/60 px-4 py-3
                                       transition
                                       hover:bg-slate-50"
                            >

                                <input
                                    type="checkbox"
                                    name="permissions[]"
                                    value="{{ $key }}"
                                    @checked($checked)
                                    class="mt-0.5 rounded
                                           border-slate-300"
                                >

                                <span class="min-w-0">

                                    <span
                                        class="block text-sm
                                               font-semibold
                                               text-slate-800"
                                    >
                                        {{ $description }}
                                    </span>

                                    <span
                                        class="mt-0.5 block
                                               font-mono text-xs
                                               text-slate-400"
                                    >
                                        {{ $key }}
                                    </span>

                                </span>

                            </label>

                        @endforeach

                    </div>

                </div>

            @endforeach

        </section>

    @endif


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
            {{ $isEdit ? 'Save Role' : 'Create Role' }}
        </button>

        <a
            href="{{ route('admin.roles.index') }}"
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
