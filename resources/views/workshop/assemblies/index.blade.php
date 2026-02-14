@extends('layouts.app')

@section('content')
<div x-data="{}">
    <x-common.page-breadcrumb pageTitle="Armados Taller" />

    <x-common.component-card title="Armado / Ensamblaje" desc="Registro mensual de armados y costos por tipo de vehiculo.">
        @if (session('status'))
            <div class="mb-4 rounded-lg border border-green-300 bg-green-50 p-3 text-sm text-green-700">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="mb-4 rounded-lg border border-red-300 bg-red-50 p-3 text-sm text-red-700">{{ $errors->first() }}</div>
        @endif

        <div class="mb-4 flex flex-wrap items-center gap-2">
            <x-ui.button size="md" variant="primary" type="button" style="background-color:#00A389;color:#fff" @click="$dispatch('open-assembly-modal')">
                <i class="ri-add-line"></i><span>Nuevo armado</span>
            </x-ui.button>
            <x-ui.link-button size="md" variant="primary" href="{{ route('workshop.assemblies.export', ['month' => $month]) }}" style="background-color:#166534;color:#fff">
                <i class="ri-file-excel-2-line"></i><span>Exportar CSV</span>
            </x-ui.link-button>
        </div>

        <form method="GET" action="{{ route('workshop.assemblies.index') }}" class="mb-4 grid grid-cols-1 gap-2 rounded-xl border border-gray-200 bg-white p-4 md:grid-cols-5 dark:border-gray-800 dark:bg-white/[0.02]">
            <input type="month" name="month" value="{{ $month }}" class="h-11 rounded-lg border border-gray-300 px-3 text-sm">
            <input name="brand_company" value="{{ $brandCompany }}" placeholder="Empresa/Marca" class="h-11 rounded-lg border border-gray-300 px-3 text-sm">
            <input name="vehicle_type" value="{{ $vehicleType }}" placeholder="Tipo vehiculo" class="h-11 rounded-lg border border-gray-300 px-3 text-sm">
            <button class="h-11 rounded-lg bg-[#244BB3] px-4 text-sm font-medium text-white">Filtrar</button>
            <a href="{{ route('workshop.assemblies.index') }}" class="h-11 rounded-lg border border-gray-300 px-4 text-sm font-medium text-gray-700 inline-flex items-center justify-center">Limpiar</a>
        </form>

        <div class="mb-4 grid grid-cols-1 gap-4 md:grid-cols-2">
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-white/[0.02]">
                <h2 class="mb-2 text-base font-semibold">Tabla de costos por tipo</h2>
                <div class="table-responsive rounded-xl border border-gray-200 dark:border-gray-800">
                    <table class="w-full min-w-[500px]">
                        <thead>
                            <tr>
                                <th style="background-color:#63B7EC" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-white first:rounded-tl-xl">Empresa/Marca</th>
                                <th style="background-color:#63B7EC" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-white">Tipo</th>
                                <th style="background-color:#63B7EC" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-white last:rounded-tr-xl">Costo Unitario</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($costTable as $cost)
                                <tr class="border-t border-gray-100 dark:border-gray-800">
                                    <td class="px-4 py-3 text-sm">{{ $cost->brand_company }}</td>
                                    <td class="px-4 py-3 text-sm">{{ $cost->vehicle_type }}</td>
                                    <td class="px-4 py-3 text-sm">{{ number_format((float) $cost->unit_cost, 6) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="px-4 py-4 text-sm text-gray-500">Sin costos configurados.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-white/[0.02]">
                <h2 class="mb-2 text-base font-semibold">Resumen mensual por tipo</h2>
                <div class="table-responsive rounded-xl border border-gray-200 dark:border-gray-800">
                    <table class="w-full min-w-[650px]">
                        <thead>
                            <tr>
                                <th style="background-color:#63B7EC" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-white first:rounded-tl-xl">Empresa/Marca</th>
                                <th style="background-color:#63B7EC" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-white">Tipo</th>
                                <th style="background-color:#63B7EC" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-white">Cantidad</th>
                                <th style="background-color:#63B7EC" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-white last:rounded-tr-xl">Costo Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($summaryByType as $row)
                                <tr class="border-t border-gray-100 dark:border-gray-800">
                                    <td class="px-4 py-3 text-sm">{{ $row->brand_company }}</td>
                                    <td class="px-4 py-3 text-sm">{{ $row->vehicle_type }}</td>
                                    <td class="px-4 py-3 text-sm">{{ (int) $row->total_qty }}</td>
                                    <td class="px-4 py-3 text-sm">{{ number_format((float) $row->total_cost, 2) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="px-4 py-4 text-sm text-gray-500">Sin registros en el mes.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-white/[0.02]">
            <h2 class="mb-2 text-base font-semibold">Detalle de armados</h2>
            <div class="table-responsive rounded-xl border border-gray-200 dark:border-gray-800">
                <table class="w-full min-w-[950px]">
                    <thead>
                        <tr>
                            <th style="background-color:#63B7EC" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-white first:rounded-tl-xl">Fecha</th>
                            <th style="background-color:#63B7EC" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-white">Empresa/Marca</th>
                            <th style="background-color:#63B7EC" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-white">Tipo</th>
                            <th style="background-color:#63B7EC" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-white">Cantidad</th>
                            <th style="background-color:#63B7EC" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-white">Costo U.</th>
                            <th style="background-color:#63B7EC" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-white">Costo T.</th>
                            <th style="background-color:#63B7EC" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-white last:rounded-tr-xl">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($assemblies as $assembly)
                            <tr class="border-t border-gray-100 dark:border-gray-800">
                                <td class="px-4 py-3 text-sm">{{ optional($assembly->assembled_at)->format('Y-m-d') }}</td>
                                <td class="px-4 py-3 text-sm">{{ $assembly->brand_company }}</td>
                                <td class="px-4 py-3 text-sm">{{ $assembly->vehicle_type }}</td>
                                <td class="px-4 py-3 text-sm">{{ $assembly->quantity }}</td>
                                <td class="px-4 py-3 text-sm">{{ number_format((float) $assembly->unit_cost, 6) }}</td>
                                <td class="px-4 py-3 text-sm">{{ number_format((float) $assembly->total_cost, 2) }}</td>
                                <td class="px-4 py-3 text-sm">
                                    <form method="POST" action="{{ route('workshop.assemblies.destroy', $assembly) }}" onsubmit="return confirm('Eliminar registro de armado?')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="rounded-lg bg-red-700 px-3 py-1.5 text-xs font-medium text-white">Eliminar</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-4 py-4 text-sm text-gray-500">Sin registros.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $assemblies->links() }}</div>
        </div>
    </x-common.component-card>

    <x-ui.modal x-data="{ open: false }" @open-assembly-modal.window="open = true" :isOpen="false" :showCloseButton="false" class="max-w-4xl">
        <div class="p-6 sm:p-8">
            <div class="mb-6 flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Nuevo registro de armado</h3>
                <button type="button" @click="open = false" class="flex h-11 w-11 items-center justify-center rounded-full bg-gray-100 text-gray-400 hover:bg-gray-200 hover:text-gray-700">
                    <i class="ri-close-line text-xl"></i>
                </button>
            </div>

            <form method="POST" action="{{ route('workshop.assemblies.store') }}" class="grid grid-cols-1 gap-2 md:grid-cols-2">
                @csrf
                <input name="brand_company" class="h-11 rounded-lg border border-gray-300 px-3 text-sm" placeholder="Empresa/Marca" required>
                <input name="vehicle_type" class="h-11 rounded-lg border border-gray-300 px-3 text-sm" placeholder="Tipo vehiculo" required>
                <input type="number" min="1" name="quantity" class="h-11 rounded-lg border border-gray-300 px-3 text-sm" value="1" required>
                <input type="number" min="0" step="0.000001" name="unit_cost" class="h-11 rounded-lg border border-gray-300 px-3 text-sm" placeholder="Costo unitario">
                <input type="date" name="assembled_at" class="h-11 rounded-lg border border-gray-300 px-3 text-sm md:col-span-2" value="{{ now()->toDateString() }}" required>
                <textarea name="notes" rows="2" class="rounded-lg border border-gray-300 px-3 py-2 text-sm md:col-span-2" placeholder="Observaciones"></textarea>
                <div class="md:col-span-2 mt-2 flex gap-2">
                    <x-ui.button type="submit" size="md" variant="primary"><i class="ri-save-line"></i><span>Guardar</span></x-ui.button>
                    <x-ui.button type="button" size="md" variant="outline" @click="open = false"><i class="ri-close-line"></i><span>Cancelar</span></x-ui.button>
                </div>
            </form>
        </div>
    </x-ui.modal>
</div>
@endsection
