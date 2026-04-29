@if($correctiveServicesEnabled)
    <x-ui.modal
        x-data="{ open: false }"
        x-on:open-service-type-modal.window="open = true"
        :isOpen="false"
        class="max-w-md">
        <div class="p-6">
            <div class="mb-5 text-center">
                <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-orange-100 text-orange-600">
                    <i class="ri-motorbike-line text-3xl"></i>
                </div>
                <h3 class="text-xl font-bold text-slate-800">Nueva Orden de Servicio</h3>
                <p class="mt-2 text-sm text-slate-500">Seleccione el tipo de mantenimiento que se realizará al vehículo.</p>
            </div>

            <div class="grid grid-cols-1 gap-4">
                <a href="{{ route('workshop.maintenance-board.create', ['service_type' => 'preventivo']) }}" 
                   class="group flex items-center gap-4 rounded-2xl border-2 border-slate-100 bg-white p-4 transition-all hover:border-orange-500 hover:bg-orange-50">
                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-orange-100 text-orange-600 group-hover:bg-orange-600 group-hover:text-white">
                        <i class="ri-tools-line text-2xl"></i>
                    </div>
                    <div class="text-left">
                        <p class="font-bold text-slate-800">Servicio Preventivo</p>
                        <p class="text-xs text-slate-500">Mantenimiento regular y rutinario.</p>
                    </div>
                    <i class="ri-arrow-right-s-line ml-auto text-xl text-slate-300"></i>
                </a>

                <a href="{{ route('workshop.maintenance-board.create', ['service_type' => 'correctivo']) }}" 
                   class="group flex items-center gap-4 rounded-2xl border-2 border-slate-100 bg-white p-4 transition-all hover:border-rose-500 hover:bg-rose-50">
                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-rose-100 text-rose-600 group-hover:bg-rose-600 group-hover:text-white">
                        <i class="ri-error-warning-line text-2xl"></i>
                    </div>
                    <div class="text-left">
                        <p class="font-bold text-slate-800">Servicio Correctivo</p>
                        <p class="text-xs text-slate-500">Reparación por fallas o averías específicas.</p>
                    </div>
                    <i class="ri-arrow-right-s-line ml-auto text-xl text-slate-300"></i>
                </a>
            </div>

            <div class="mt-6">
                <button type="button" @click="open = false" 
                        class="w-full rounded-xl border border-slate-200 py-3 text-sm font-bold text-slate-500 transition-all hover:bg-slate-50 hover:text-slate-700">
                    Cancelar
                </button>
            </div>
        </div>
    </x-ui.modal>
@endif
