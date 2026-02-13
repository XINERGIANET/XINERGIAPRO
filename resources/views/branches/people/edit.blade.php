@extends('layouts.app')

@section('content')
    @php
        $viewId = request('view_id');
    @endphp
    <x-common.page-breadcrumb pageTitle="Personal" />

    <x-ui.modal
        x-data="{
            open: true,
            close() {
                if (window.Turbo && typeof window.Turbo.visit === 'function') {
                    window.Turbo.visit('{{ $viewId ? route('admin.companies.branches.people.index', [$company, $branch, 'view_id' => $viewId]) : route('admin.companies.branches.people.index', [$company, $branch]) }}', { action: 'replace' });
                } else {
                    window.location.href = '{{ $viewId ? route('admin.companies.branches.people.index', [$company, $branch, 'view_id' => $viewId]) : route('admin.companies.branches.people.index', [$company, $branch]) }}';
                }
            }
        }"
        :isOpen="true"
        :showCloseButton="false"
        class="max-w-6xl"
    >
        <div class="p-6 sm:p-8">
            <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-4">
                    <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-brand-50 text-brand-500 dark:bg-brand-500/10">
                        <i class="ri-team-line text-2xl"></i>
                    </div>
                    <div>
                        <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Editar personal</h3>
                        <p class="mt-1 text-sm text-gray-500">Actualiza la informacion del personal.</p>
                    </div>
                </div>
                <a
                    href="{{ $viewId ? route('admin.companies.branches.people.index', [$company, $branch, 'view_id' => $viewId]) : route('admin.companies.branches.people.index', [$company, $branch]) }}"
                    onclick="if (window.Turbo && typeof window.Turbo.visit === 'function') { window.Turbo.visit('{{ $viewId ? route('admin.companies.branches.people.index', [$company, $branch, 'view_id' => $viewId]) : route('admin.companies.branches.people.index', [$company, $branch]) }}', { action: 'replace' }); return false; }"
                    class="flex h-11 w-11 items-center justify-center rounded-full bg-gray-100 text-gray-400 transition-colors hover:bg-gray-200 hover:text-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white"
                    aria-label="Cerrar"
                >
                    <i class="ri-close-line text-xl"></i>
                </a>
            </div>

            @if ($errors->any())
                <div class="mb-5">
                    <x-ui.alert variant="error" title="Revisa los campos" message="Hay errores en el formulario, corrige los datos e intenta nuevamente." />
                </div>
            @endif

            <form method="POST" action="{{ $viewId ? route('admin.companies.branches.people.update', [$company, $branch, $person]) . '?view_id=' . $viewId : route('admin.companies.branches.people.update', [$company, $branch, $person]) }}" class="space-y-6">
                @csrf
                @method('PUT')
                @if ($viewId)
                    <input type="hidden" name="view_id" value="{{ $viewId }}">
                @endif

                @include('branches.people._form', ['person' => $person])

                <div class="flex flex-wrap gap-3">
                    <x-ui.button type="submit" size="md" variant="primary">
                        <i class="ri-save-line"></i>
                        <span>Actualizar</span>
                    </x-ui.button>
                    <x-ui.link-button
                        size="md"
                        variant="outline"
                        href="{{ $viewId ? route('admin.companies.branches.people.index', [$company, $branch, 'view_id' => $viewId]) : route('admin.companies.branches.people.index', [$company, $branch]) }}"
                        onclick="if (window.Turbo && typeof window.Turbo.visit === 'function') { window.Turbo.visit('{{ $viewId ? route('admin.companies.branches.people.index', [$company, $branch, 'view_id' => $viewId]) : route('admin.companies.branches.people.index', [$company, $branch]) }}', { action: 'replace' }); return false; }"
                    >
                        <i class="ri-close-line"></i>
                        <span>Cancelar</span>
                    </x-ui.link-button>
                </div>
            </form>
        </div>
    </x-ui.modal>
@endsection
