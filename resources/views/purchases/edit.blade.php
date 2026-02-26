@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Editar compra" />

    <x-common.component-card title="Editar compra" desc="Actualiza cabecera y detalle de la compra.">
        <form method="POST" action="{{ route('admin.purchases.update', array_merge([$purchase], $viewId ? ['view_id' => $viewId] : [])) }}" class="space-y-6">
            @csrf
            @method('PUT')
            @include('purchases._form', ['purchase' => $purchase])

            <div class="flex flex-wrap gap-3">
                <button type="submit" class="rounded-lg bg-[#244BB3] px-5 py-3 text-sm font-semibold text-white">
                    <i class="ri-save-line mr-2"></i>Guardar cambios
                </button>
                <a href="{{ route('admin.purchases.index', $viewId ? ['view_id' => $viewId] : []) }}"
                   class="rounded-lg border border-gray-300 px-5 py-3 text-sm font-semibold text-gray-700">
                    Cancelar
                </a>
            </div>
        </form>
    </x-common.component-card>
@endsection

