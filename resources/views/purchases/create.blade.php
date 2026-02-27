@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Nueva compra" />

    <x-common.component-card title="Compras | Nuevo" desc="Registra una compra con su detalle, totales e impacto de stock/caja.">
        <form method="POST" action="{{ route('admin.purchases.store', $viewId ? ['view_id' => $viewId] : []) }}" class="space-y-6">
            @csrf
            @include('purchases._form', ['purchase' => null])

            <div class="flex flex-wrap items-center justify-end gap-3 border-t border-gray-200 pt-5">
                <a href="{{ route('admin.purchases.index', $viewId ? ['view_id' => $viewId] : []) }}"
                   class="inline-flex items-center rounded-lg border border-gray-300 px-5 py-3 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                    <i class="ri-close-line mr-2"></i>Cancelar
                </a>
                <button type="submit" class="inline-flex items-center rounded-lg bg-[#244BB3] px-5 py-3 text-sm font-semibold text-white hover:bg-[#1f3f98]">
                    <i class="ri-save-line mr-2"></i>Guardar compra
                </button>
            </div>
        </form>
    </x-common.component-card>
@endsection
