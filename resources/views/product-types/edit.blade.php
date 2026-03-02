@extends('layouts.app')

@section('content')
    <div x-data="{}">
        <x-common.page-breadcrumb pageTitle="Editar tipo de producto" />

        <x-common.component-card title="Editar tipo de producto" desc="Actualiza la configuracion del tipo de producto.">
            @if ($errors->any())
                <div class="mb-5 rounded-lg border border-error-500/30 bg-error-500/10 px-4 py-3 text-sm text-error-700 dark:border-error-500/50 dark:bg-error-500/20 dark:text-error-300">
                    <p class="mb-2 font-semibold">Revisa los campos</p>
                    <ul class="list-disc list-inside space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('product-types.update', $productType) }}" class="space-y-6">
                @csrf
                @method('PUT')
                @if (!empty($viewId))
                    <input type="hidden" name="view_id" value="{{ $viewId }}">
                @endif

                @include('product-types._form', ['productType' => $productType])

                <div class="flex flex-wrap gap-3">
                    <x-ui.button type="submit" size="md" variant="primary">
                        <i class="ri-save-line"></i>
                        <span>Actualizar</span>
                    </x-ui.button>
                    <x-ui.link-button size="md" variant="outline" href="{{ route('product-types.index', !empty($viewId) ? ['view_id' => $viewId] : []) }}">
                        <i class="ri-close-line"></i>
                        <span>Cancelar</span>
                    </x-ui.link-button>
                </div>
            </form>
        </x-common.component-card>
    </div>
@endsection
