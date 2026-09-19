@extends('layouts.app')


@section(
    'title',
    'Add Role | RFA Monitoring System'
)


@section(
    'page_heading',
    'Add Role'
)


@section('content')

<form
    method="POST"
    action="{{ route('admin.roles.store') }}"
>

    @csrf

    @include('admin.roles._form', [
        'role' => null,
        'groups' => $groups,
        'granted' => [],
    ])

</form>

@endsection
