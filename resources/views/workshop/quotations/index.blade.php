@extends('layouts.app')

@section('content')
<div class="p-4 md:p-6 2xl:p-10">
    <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <h2 class="text-title-md2 font-bold text-black dark:text-white">
            Gestión de Cotizaciones (Taller)
        </h2>
    </div>

    <div class="rounded-sm border border-stroke bg-white px-5 pt-6 pb-2.5 shadow-default dark:border-strokedark dark:bg-boxdark sm:px-7.5 xl:pb-1">
        <div class="mb-5">
            <form action="{{ route('admin.sales.quotations.index') }}" method="GET" class="flex flex-wrap items-end gap-4">
                <input type="hidden" name="view_id" value="{{ request('view_id') }}">
                
                <div class="flex-1 min-w-[250px]">
                    <label class="mb-2.5 block text-black dark:text-white font-medium">Buscar (N°, Placa, Cliente)</label>
                    <input type="text" name="search" value="{{ $search }}" placeholder="Ej: OS-001..." 
                        class="w-full rounded border-[1.5px] border-stroke bg-transparent py-2.5 px-5 font-medium outline-none transition focus:border-primary active:border-primary dark:border-form-strokedark dark:bg-form-input">
                </div>

                <div class="min-w-[200px]">
                    <label class="mb-2.5 block text-black dark:text-white font-medium">Cliente</label>
                    <select name="client_id" class="w-full rounded border-[1.5px] border-stroke bg-transparent py-2.5 px-5 font-medium outline-none transition focus:border-primary active:border-primary dark:border-form-strokedark dark:bg-form-input">
                        <option value="">Todos los clientes</option>
                        @foreach($clients as $client)
                            <option value="{{ $client->id }}" {{ (int)$clientId === (int)$client->id ? 'selected' : '' }}>
                                {{ $client->first_name }} {{ $client->last_name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <button type="submit" class="flex justify-center rounded bg-primary py-2.5 px-6 font-medium text-gray hover:bg-opacity-90">
                    <i class="ri-search-line mr-2"></i> Filtrar
                </button>
            </form>
        </div>

        <div class="max-w-full overflow-x-auto">
            <table class="w-full table-auto">
                <thead>
                    <tr class="bg-gray-2 text-left dark:bg-meta-4">
                        <th class="py-4 px-4 font-medium text-black dark:text-white">OS / Número</th>
                        <th class="py-4 px-4 font-medium text-black dark:text-white">Vehículo</th>
                        <th class="py-4 px-4 font-medium text-black dark:text-white">Cliente</th>
                        <th class="py-4 px-4 font-medium text-black dark:text-white">Estado / Aprobación</th>
                        <th class="py-4 px-4 font-medium text-black dark:text-white">Rechazos</th>
                        <th class="py-4 px-4 font-medium text-black dark:text-white">Total</th>
                        <th class="py-4 px-4 font-medium text-black dark:text-white">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($quotations as $quotation)
                        <tr>
                            <td class="border-b border-[#eee] py-5 px-4 dark:border-strokedark">
                                <h5 class="font-medium text-black dark:text-white">{{ $quotation->movement?->number ?? 'N/A' }}</h5>
                                <p class="text-xs">{{ $quotation->intake_date?->format('d/m/Y H:i') }}</p>
                            </td>
                            <td class="border-b border-[#eee] py-5 px-4 dark:border-strokedark">
                                <p class="text-black dark:text-white">{{ $quotation->vehicle?->plate }}</p>
                                <p class="text-xs">{{ $quotation->vehicle?->brand }} {{ $quotation->vehicle?->model }}</p>
                            </td>
                            <td class="border-b border-[#eee] py-5 px-4 dark:border-strokedark">
                                <p class="text-black dark:text-white">{{ $quotation->client?->first_name }} {{ $quotation->client?->last_name }}</p>
                            </td>
                            <td class="border-b border-[#eee] py-5 px-4 dark:border-strokedark">
                                <span class="inline-flex rounded-full bg-opacity-10 py-1 px-3 text-sm font-medium 
                                    {{ $quotation->status === 'approved' ? 'bg-success text-success' : 'bg-warning text-warning' }}">
                                    @switch($quotation->status)
                                        @case('awaiting_approval') Esperando aprobación @break
                                        @case('approved') Aprobada @break
                                        @case('diagnosis') En diagnóstico @break
                                        @default {{ $quotation->status }}
                                    @endswitch
                                </span>
                            </td>
                            <td class="border-b border-[#eee] py-5 px-4 dark:border-strokedark text-center">
                                @if($quotation->has_rejections)
                                    <span class="inline-flex rounded-full bg-danger bg-opacity-10 py-1 px-3 text-sm font-medium text-danger animate-pulse" title="Existen items eliminados/rechazados">
                                        <i class="ri-error-warning-line mr-1"></i> {{ $quotation->deletedDetails->count() }}
                                    </span>
                                @else
                                    <span class="text-gray-400 text-xs">-</span>
                                @endif
                            </td>
                            <td class="border-b border-[#eee] py-5 px-4 dark:border-strokedark">
                                <p class="font-bold text-black dark:text-white">S/ {{ number_format($quotation->total, 2) }}</p>
                            </td>
                            <td class="border-b border-[#eee] py-5 px-4 dark:border-strokedark">
                                <div class="flex items-center space-x-3.5">
                                    <a href="{{ route('workshop.orders.show', $quotation->id) }}" class="hover:text-primary" title="Ver Detalle OS">
                                        <i class="ri-eye-line text-xl"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-10 text-center text-gray-400">
                                No se encontraron cotizaciones con los filtros aplicados.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">
            {{ $quotations->links() }}
        </div>
    </div>
</div>
@endsection
