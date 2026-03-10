@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Historial de Cliente" />

    @include('workshop.clients._history_content')
@endsection
