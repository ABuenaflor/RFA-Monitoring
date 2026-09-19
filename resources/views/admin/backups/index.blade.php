@extends('layouts.app')


@section(
    'title',
    'Database Backups | RFA Monitoring System'
)


@section(
    'page_heading',
    'Database Backups'
)


@section('page_actions')

    @can('backup.manage')

        <form
            method="POST"
            action="{{ route('admin.backups.store') }}"
        >

            @csrf

            <button
                type="submit"
                @disabled(! $supported)
                class="inline-flex items-center
                       justify-center
                       rounded-xl
                       bg-blue-600 px-4 py-2.5
                       text-sm font-semibold
                       text-white shadow-sm
                       transition
                       hover:bg-blue-700
                       disabled:cursor-not-allowed
                       disabled:bg-slate-300"
            >
                Create Backup
            </button>

        </form>

    @endcan

@endsection


@section('content')

<div class="space-y-7">

    <section>

        <p
            class="max-w-4xl text-sm
                   leading-6 text-slate-500"
        >
            A backup is a complete SQL dump of the
            <span class="font-semibold">{{ $databaseName }}</span>
            database, written to
            <span class="font-mono text-xs">storage/app/private/backups</span>.
            Restoring is deliberately not automated — overwriting a live
            database is a decision to make at a console, not behind a button.
        </p>

    </section>


    {{-- ========================================================= --}}
    {{-- STATUS --}}
    {{-- ========================================================= --}}

    @unless ($supported)

        <section
            class="rounded-2xl
                   border border-amber-200
                   bg-amber-50 p-6 shadow-sm"
        >

            <p
                class="text-xs font-bold
                       uppercase tracking-[0.15em]
                       text-amber-700"
            >
                Unavailable
            </p>

            <h2
                class="mt-1 text-lg
                       font-bold text-amber-950"
            >
                Backups require a MySQL connection
            </h2>

            <p
                class="mt-3 text-sm leading-6
                       text-amber-900"
            >
                The active database connection uses the
                <span class="font-mono">{{ $driver }}</span> driver. The backup
                writer produces MySQL-compatible SQL and is disabled on other
                connections rather than producing a dump that cannot be
                restored.
            </p>

        </section>

    @endunless


    <section
        class="grid grid-cols-2 gap-4
               xl:grid-cols-4"
    >

        <div
            class="rounded-2xl
                   border border-slate-200
                   bg-white p-5 shadow-sm"
        >

            <p
                class="text-xs font-bold
                       uppercase tracking-wider
                       text-slate-400"
            >
                Backups Held
            </p>

            <p
                class="mt-3 text-3xl
                       font-bold text-slate-950"
            >
                {{ number_format($backups->total()) }}
            </p>

        </div>


        <div
            class="rounded-2xl
                   border border-slate-200
                   bg-white p-5 shadow-sm"
        >

            <p
                class="text-xs font-bold
                       uppercase tracking-wider
                       text-slate-400"
            >
                Latest Backup
            </p>

            <p
                class="mt-3 text-lg
                       font-bold text-slate-950"
            >
                {{ $latest?->created_at?->format('M d, Y') ?? 'None yet' }}
            </p>

            <p class="mt-1 text-xs text-slate-400">
                {{ $latest?->created_at?->diffForHumans() ?? 'No backup has been taken' }}
            </p>

        </div>


        <div
            class="rounded-2xl
                   border border-slate-200
                   bg-white p-5 shadow-sm"
        >

            <p
                class="text-xs font-bold
                       uppercase tracking-wider
                       text-slate-400"
            >
                Latest Size
            </p>

            <p
                class="mt-3 text-3xl
                       font-bold text-slate-950"
            >
                {{ $latest?->humanSize() ?? '—' }}
            </p>

        </div>


        <div
            @class([
                'rounded-2xl border p-5 shadow-sm',
                'border-rose-200 bg-rose-50' => $missingFiles > 0,
                'border-emerald-200 bg-emerald-50' => $missingFiles === 0,
            ])
        >

            <p
                class="text-xs font-bold
                       uppercase tracking-wider
                       opacity-70"
            >
                Missing Files
            </p>

            <p class="mt-3 text-3xl font-bold">
                {{ number_format($missingFiles) }}
            </p>

            <p class="mt-1 text-xs opacity-70">
                Register entries with no file on disk
            </p>

        </div>

    </section>


    {{-- ========================================================= --}}
    {{-- REGISTER --}}
    {{-- ========================================================= --}}

    <section
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
                class="text-xs font-bold
                       uppercase tracking-[0.15em]
                       text-blue-600"
            >
                Backup Register
            </p>

            <h2
                class="mt-1 text-lg
                       font-bold text-slate-950"
            >
                Available Dumps
            </h2>

        </div>


        <div class="overflow-x-auto">

            <table
                class="w-full min-w-[1050px]
                       divide-y divide-slate-200"
            >

                <thead class="bg-slate-50">

                    <tr>

                        @foreach ([
                            'File',
                            'Created',
                            'By',
                            'Contents',
                            'Size',
                            'Status',
                            'Actions',
                        ] as $header)

                            <th
                                class="px-4 py-3
                                       text-left text-xs
                                       font-bold uppercase
                                       text-slate-500"
                            >
                                {{ $header }}
                            </th>

                        @endforeach

                    </tr>

                </thead>


                <tbody class="divide-y divide-slate-100">

                    @forelse ($backups as $backup)

                        <tr class="transition hover:bg-slate-50/80">

                            <td
                                class="px-4 py-4
                                       font-mono text-xs
                                       text-slate-700"
                            >
                                {{ $backup->filename }}
                            </td>


                            <td
                                class="whitespace-nowrap px-4 py-4
                                       text-sm text-slate-600"
                            >
                                {{ $backup->created_at?->format('M d, Y g:i A') }}
                            </td>


                            <td
                                class="px-4 py-4 text-sm
                                       text-slate-600"
                            >
                                {{ $backup->creator?->name ?? 'System' }}
                            </td>


                            <td
                                class="px-4 py-4 text-sm
                                       text-slate-600"
                            >
                                {{ number_format($backup->table_count) }} tables
                                &middot;
                                {{ number_format($backup->row_count) }} rows
                            </td>


                            <td
                                class="px-4 py-4 text-sm
                                       font-semibold
                                       text-slate-900"
                            >
                                {{ $backup->humanSize() }}
                            </td>


                            <td class="px-4 py-4">

                                @if (! $backup->fileExists())

                                    <span
                                        class="rounded-full
                                               bg-rose-50 px-3 py-1
                                               text-xs font-semibold
                                               text-rose-700"
                                    >
                                        File Missing
                                    </span>

                                @else

                                    <span
                                        class="rounded-full
                                               bg-emerald-50 px-3 py-1
                                               text-xs font-semibold
                                               text-emerald-700"
                                    >
                                        Available
                                    </span>

                                @endif

                            </td>


                            <td class="px-4 py-4">

                                @can('backup.manage')

                                    <div
                                        class="flex items-center gap-3
                                               text-sm font-semibold"
                                    >

                                        @if ($backup->fileExists())

                                            <a
                                                href="{{ route('admin.backups.download', $backup) }}"
                                                class="text-blue-600
                                                       transition
                                                       hover:text-blue-700"
                                            >
                                                Download
                                            </a>

                                        @endif

                                        <form
                                            method="POST"
                                            action="{{ route('admin.backups.destroy', $backup) }}"
                                            onsubmit="return confirm('Delete this backup file? This cannot be undone.');"
                                        >

                                            @csrf
                                            @method('DELETE')

                                            <button
                                                type="submit"
                                                class="text-rose-600
                                                       transition
                                                       hover:text-rose-700"
                                            >
                                                Delete
                                            </button>

                                        </form>

                                    </div>

                                @else

                                    <span class="text-sm text-slate-400">
                                        View only
                                    </span>

                                @endcan

                            </td>

                        </tr>

                    @empty

                        <tr>

                            <td
                                colspan="7"
                                class="px-6 py-14
                                       text-center text-sm
                                       text-slate-500"
                            >
                                No backups have been taken yet.
                            </td>

                        </tr>

                    @endforelse

                </tbody>

            </table>

        </div>


        @if ($backups->hasPages())

            <div
                class="border-t border-slate-100
                       px-6 py-4"
            >
                {{ $backups->links() }}
            </div>

        @endif

    </section>


    {{-- ========================================================= --}}
    {{-- RECOVERY --}}
    {{-- ========================================================= --}}

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
            Recovery
        </p>

        <h2
            class="mt-1 text-lg
                   font-bold text-violet-950"
        >
            Restore procedure
        </h2>

        <p
            class="mt-3 text-sm leading-6
                   text-violet-900"
        >
            Restoring replaces the contents of the live database. It is run
            deliberately from a console, against a database you have confirmed
            is the intended target.
        </p>


        <ol
            class="mt-4 space-y-3 text-sm
                   leading-6 text-violet-900"
        >

            <li>
                <span class="font-semibold">1.</span>
                Take a fresh backup first, so the current state can be
                recovered if the restore is wrong.
            </li>

            <li>
                <span class="font-semibold">2.</span>
                Download the dump, or locate it under
                <span class="font-mono text-xs">storage/app/private/backups</span>.
            </li>

            <li>
                <span class="font-semibold">3.</span>
                Stop the application so nothing writes during the restore.
            </li>

            <li>
                <span class="font-semibold">4.</span>
                Run:

                <div
                    class="mt-2 overflow-x-auto
                           rounded-xl bg-violet-950
                           px-4 py-3
                           font-mono text-xs
                           text-violet-100"
                >
                    mysql -u &lt;user&gt; -p {{ $databaseName }} &lt; rfa_backup_YYYY-MM-DD_HHMMSS.sql
                </div>
            </li>

            <li>
                <span class="font-semibold">5.</span>
                Run <span class="font-mono text-xs">php artisan migrate</span>
                and
                <span class="font-mono text-xs">php artisan optimize:clear</span>,
                then verify the record count on the dashboard.
            </li>

        </ol>

    </section>

</div>

@endsection
