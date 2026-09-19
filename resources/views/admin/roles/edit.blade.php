@extends('layouts.app')


@section(
    'title',
    'Edit Role | RFA Monitoring System'
)


@section(
    'page_heading',
    'Edit Role'
)


@section('content')

<form
    method="POST"
    action="{{ route('admin.roles.update', $role) }}"
>

    @csrf
    @method('PUT')

    @include('admin.roles._form', [
        'role' => $role,
        'groups' => $groups,
        'granted' => $granted,
    ])

</form>

@endsection
