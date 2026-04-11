@extends('layouts.modal-fragment', ['title' => 'Historial del cliente | Xinergia PRO'])

@section('content')
    <div class="p-4 sm:p-6">
        @include('workshop.clients._history_content')
    </div>
@endsection
