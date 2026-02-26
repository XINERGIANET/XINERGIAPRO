@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Nueva compra" />

    <x-common.component-card title="Registrar compra" desc="Ingresa una nueva compra con su detalle de productos.">
        <form method="POST" action="{{ route('admin.purchases.store', $viewId ? ['view_id' => $viewId] : []) }}" class="space-y-6">
            @csrf
            @include('purchases._form', ['purchase' => null])

            <div class="flex flex-wrap gap-3">
                <button type="submit" class="rounded-lg bg-[#244BB3] px-5 py-3 text-sm font-semibold text-white">
                    <i class="ri-save-line mr-2"></i>Guardar compra
                </button>
                <a href="{{ route('admin.purchases.index', $viewId ? ['view_id' => $viewId] : []) }}"
                   class="rounded-lg border border-gray-300 px-5 py-3 text-sm font-semibold text-gray-700">
                    Cancelar
                </a>
            </div>
        </form>
    </x-common.component-card>
@endsection

