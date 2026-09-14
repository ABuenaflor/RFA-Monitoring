{{--
|--------------------------------------------------------------------------
| Flash & Validation Feedback
|--------------------------------------------------------------------------
|
| Rendered once per page, directly above the page content.
|
--}}

@if (session('status'))

    <div
        class="no-print
               rounded-2xl
               border border-emerald-200
               bg-emerald-50
               px-5 py-4
               shadow-sm"
    >

        <p
            class="text-xs font-bold
                   uppercase tracking-[0.15em]
                   text-emerald-700"
        >
            Success
        </p>

        <p
            class="mt-1 text-sm
                   font-medium text-emerald-900"
        >
            {{ session('status') }}
        </p>

    </div>

@endif


@if (session('warning'))

    <div
        class="no-print
               rounded-2xl
               border border-amber-200
               bg-amber-50
               px-5 py-4
               shadow-sm"
    >

        <p
            class="text-xs font-bold
                   uppercase tracking-[0.15em]
                   text-amber-700"
        >
            Action Required
        </p>

        <p
            class="mt-1 text-sm
                   font-medium text-amber-900"
        >
            {{ session('warning') }}
        </p>

    </div>

@endif


@if ($errors->any())

    <div
        class="no-print
               rounded-2xl
               border border-rose-200
               bg-rose-50
               px-5 py-4
               shadow-sm"
    >

        <p
            class="text-xs font-bold
                   uppercase tracking-[0.15em]
                   text-rose-700"
        >
            Please review
        </p>

        <ul
            class="mt-2 space-y-1
                   text-sm text-rose-900"
        >

            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach

        </ul>

    </div>

@endif
