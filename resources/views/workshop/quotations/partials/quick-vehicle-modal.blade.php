<div id="quick-vehicle-modal" class="fixed inset-0 z-[100000] hidden overflow-hidden p-3 sm:p-6">
    <div id="quick-vehicle-modal-backdrop" class="fixed inset-0 h-full w-full bg-gray-400/30 backdrop-blur-[32px]"></div>
    <div class="relative flex min-h-full items-center justify-center">
        <div class="w-full max-w-3xl rounded-[28px] bg-white shadow-2xl">
            <div class="flex items-center justify-between border-b border-slate-200 px-6 py-5 sm:px-8">
                <h3 class="text-lg font-semibold text-gray-800">Registrar vehículo</h3>
                <button type="button" id="quick-vehicle-close-button"
                    class="flex h-11 w-11 items-center justify-center rounded-full bg-gray-100 text-gray-400 hover:bg-gray-200 hover:text-gray-700">
                    <i class="ri-close-line text-xl"></i>
                </button>
            </div>

            <div class="p-6 sm:p-8">
                <div id="quick-vehicle-error"
                    class="mb-3 hidden rounded-lg border border-red-300 bg-red-50 px-3 py-2 text-xs text-red-700"></div>

                <form id="quick-vehicle-form" class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <input type="hidden" id="quick-vehicle-client-person-id" name="client_person_id" value="">

                    @if ($vehicleTypes->isEmpty())
                        <div class="md:col-span-2 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
                            No hay tipos de vehículo activos en esta sucursal. Configúrelos en Taller &gt; tipos de vehículo.
                        </div>
                    @else
                        <div class="md:col-span-2">
                            <label class="mb-1.5 block text-[10px] font-black uppercase tracking-widest text-slate-500">Tipo de vehículo</label>
                            <select id="quick-vehicle-type-id" name="vehicle_type_id" required
                                class="h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-xs font-bold text-slate-700 focus:border-[#465fff] focus:outline-none">
                                @foreach ($vehicleTypes as $vt)
                                    <option value="{{ $vt->id }}" @selected((int) $defaultVehicleTypeId === (int) $vt->id)>{{ $vt->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="mb-1.5 block text-[10px] font-black uppercase tracking-widest text-slate-500">Marca</label>
                            <input id="quick-vehicle-brand" type="text" required maxlength="255"
                                class="h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-xs font-medium text-slate-700 focus:border-[#465fff] focus:outline-none" placeholder="Marca">
                        </div>
                        <div>
                            <label class="mb-1.5 block text-[10px] font-black uppercase tracking-widest text-slate-500">Modelo</label>
                            <input id="quick-vehicle-model" type="text" required maxlength="255"
                                class="h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-xs font-medium text-slate-700 focus:border-[#465fff] focus:outline-none" placeholder="Modelo">
                        </div>
                        <div>
                            <label class="mb-1.5 block text-[10px] font-black uppercase tracking-widest text-slate-500">Año</label>
                            <input id="quick-vehicle-year" type="number" min="1900" max="2100"
                                class="h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-xs font-medium text-slate-700 focus:border-[#465fff] focus:outline-none" placeholder="Opcional">
                        </div>
                        <div>
                            <label class="mb-1.5 block text-[10px] font-black uppercase tracking-widest text-slate-500">Color</label>
                            <input id="quick-vehicle-color" type="text" maxlength="100"
                                class="h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-xs font-medium text-slate-700 focus:border-[#465fff] focus:outline-none" placeholder="Opcional">
                        </div>
                        <div>
                            <label class="mb-1.5 block text-[10px] font-black uppercase tracking-widest text-slate-500">Placa</label>
                            <input id="quick-vehicle-plate" type="text" maxlength="255"
                                class="h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-xs font-medium text-slate-700 focus:border-[#465fff] focus:outline-none" placeholder="Placa, VIN o motor">
                        </div>
                        <div>
                            <label class="mb-1.5 block text-[10px] font-black uppercase tracking-widest text-slate-500">VIN</label>
                            <input id="quick-vehicle-vin" type="text" maxlength="255"
                                class="h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-xs font-medium text-slate-700 focus:border-[#465fff] focus:outline-none" placeholder="Opcional">
                        </div>
                        <div>
                            <label class="mb-1.5 block text-[10px] font-black uppercase tracking-widest text-slate-500">Nº motor</label>
                            <input id="quick-vehicle-engine-number" type="text" maxlength="255"
                                class="h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-xs font-medium text-slate-700 focus:border-[#465fff] focus:outline-none" placeholder="Opcional">
                        </div>
                        <div>
                            <label class="mb-1.5 block text-[10px] font-black uppercase tracking-widest text-slate-500">KM actual</label>
                            <input id="quick-vehicle-current-mileage" type="number" min="0" value="0"
                                class="h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-xs font-medium text-slate-700 focus:border-[#465fff] focus:outline-none" placeholder="0">
                        </div>
                    @endif

                    <div class="md:col-span-2 flex flex-wrap items-center justify-end gap-3 border-t border-slate-100 pt-4">
                        <button type="button" id="quick-vehicle-cancel-button"
                            class="h-11 rounded-xl border border-slate-200 px-6 text-xs font-black uppercase tracking-widest text-slate-600 hover:bg-slate-50">
                            Cancelar
                        </button>
                        @if (!$vehicleTypes->isEmpty())
                            <button type="submit" id="quick-vehicle-save-button"
                                class="inline-flex h-11 items-center justify-center rounded-xl bg-[#465fff] px-6 text-xs font-black uppercase tracking-widest text-white shadow-lg shadow-indigo-500/25 hover:bg-indigo-600">
                                <span id="quick-vehicle-save-label">Guardar vehículo</span>
                            </button>
                        @endif
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
