@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Historial de Vehiculo" />

    @include('workshop.vehicles._history_content')
@endsection
