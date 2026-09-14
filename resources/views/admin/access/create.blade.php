@extends('layouts.app')


@section(
    'title',
    'Add User | RFA Monitoring System'
)


@section(
    'page_heading',
    'Add User'
)


@section('content')

<form
    method="POST"
    action="{{ route('admin.access.store') }}"
>

    @csrf

    @include('admin.access._form', [
        'user' => null,
        'roles' => $roles,
        'offices' => $offices,
    ])

</form>

@endsection
