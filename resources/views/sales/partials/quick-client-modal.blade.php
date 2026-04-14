    <div id="quick-client-modal" class="fixed inset-0 z-[100000] hidden overflow-hidden p-3 sm:p-6">
        <div id="quick-client-modal-backdrop" class="fixed inset-0 h-full w-full bg-gray-400/30 backdrop-blur-[32px]"></div>
        <div class="relative flex min-h-full items-center justify-center">
            <div class="w-full max-w-4xl rounded-[28px] bg-white shadow-2xl">
                <div class="flex items-center justify-between border-b border-slate-200 px-6 py-5 sm:px-8">
                    <h3 class="text-lg font-semibold text-gray-800">Registrar cliente</h3>
                    <button type="button" id="quick-client-close-button"
                        class="flex h-11 w-11 items-center justify-center rounded-full bg-gray-100 text-gray-400 hover:bg-gray-200 hover:text-gray-700">
                        <i class="ri-close-line text-xl"></i>
                    </button>
                </div>

                <div class="p-6 sm:p-8">
                    <div id="quick-client-error"
                        class="mb-3 hidden rounded-lg border border-red-300 bg-red-50 px-3 py-2 text-xs text-red-700"></div>

                    <form id="quick-client-form" class="grid grid-cols-1 gap-4 md:grid-cols-4">
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700">Tipo de persona</label>
                            <x-form.select-autocomplete
                                name=""
                                selectId="quick-client-person-type"
                                value="DNI"
                                :options="[
                                    ['value' => 'DNI', 'label' => 'DNI'],
                                    ['value' => 'RUC', 'label' => 'RUC'],
                                    ['value' => 'CARNET DE EXTRANGERIA', 'label' => 'CARNET DE EXTRANGERIA'],
                                    ['value' => 'PASAPORTE', 'label' => 'PASAPORTE'],
                                ]"
                                placeholder="Tipo de persona"
                                inputClass="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm"
                                required
                            />
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700">Documento</label>
                            <div class="flex items-center gap-2">
                                <input id="quick-client-document-number"
                                    class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm"
                                    placeholder="Documento" required>
                                <button type="button" id="quick-client-search-button"
                                    class="inline-flex h-11 shrink-0 items-center justify-center rounded-lg bg-[#334155] px-4 text-sm font-medium text-white hover:bg-[#1f3f98] disabled:opacity-60">
                                    <i class="ri-search-line"></i>
                                </button>
                            </div>
                        </div>
                        <div>
                            <label id="quick-client-first-name-label"
                                class="mb-1.5 block text-sm font-medium text-gray-700">Nombres</label>
                            <input id="quick-client-first-name"
                                class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm"
                                placeholder="Nombres / Razon social" required>
                        </div>
                        <div id="quick-client-last-name-wrap">
                            <label id="quick-client-last-name-label"
                                class="mb-1.5 block text-sm font-medium text-gray-700">Apellidos</label>
                            <input id="quick-client-last-name"
                                class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm" placeholder="Apellidos">
                        </div>

                        <div>
                            <label id="quick-client-date-label" class="mb-1.5 block text-sm font-medium text-gray-700">Fecha
                                de nacimiento</label>
                            <div class="flex items-center gap-2">
                                <input id="quick-client-date" type="date"
                                    class="h-11 min-w-0 flex-1 rounded-lg border border-gray-300 px-3 text-sm">
                                <button type="button" id="quick-client-date-picker-btn"
                                    class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-lg border border-gray-300 bg-gray-50 text-gray-600 hover:bg-gray-100"
                                    aria-label="Abrir calendario" title="Abrir calendario">
                                    <i class="ri-calendar-line text-xl"></i>
                                </button>
                            </div>
                        </div>
                        <div id="quick-client-gender-wrap">
                            <label class="mb-1.5 block text-sm font-medium text-gray-700">Genero</label>
                            <select id="quick-client-gender"
                                class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm">
                                <option value="">Seleccione genero</option>
                                <option value="MASCULINO">MASCULINO</option>
                                <option value="FEMENINO">FEMENINO</option>
                                <option value="OTRO">OTRO</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700">Telefono</label>
                            <input id="quick-client-phone"
                                class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm"
                                placeholder="Ingrese el telefono">
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700">Email</label>
                            <input id="quick-client-email" type="email"
                                class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm"
                                placeholder="Ingrese el email">
                        </div>

                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700">Direccion</label>
                            <input id="quick-client-address"
                                class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm"
                                placeholder="Direccion (opcional)">
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700">Departamento</label>
                            <select id="quick-client-department"
                                class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm">
                                <option value="">Seleccione departamento</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700">Provincia</label>
                            <select id="quick-client-province"
                                class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm">
                                <option value="">Seleccione provincia</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700">Distrito</label>
                            <select id="quick-client-district"
                                class="h-11 w-full rounded-lg border border-gray-300 px-3 text-sm">
                                <option value="">Seleccione distrito</option>
                            </select>
                        </div>

                        <div class="md:col-span-4">
                            <label class="mb-1.5 block text-sm font-medium text-gray-700">Roles</label>
                            <div
                                class="inline-flex items-center gap-2 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700">
                                <input type="checkbox" checked disabled
                                    class="h-4 w-4 rounded border-gray-300 text-brand-500">
                                <span>Cliente</span>
                            </div>
                        </div>

                        <div class="md:col-span-4 mt-2 flex gap-2">
                            <button id="quick-client-save-button" type="submit"
                                class="inline-flex h-11 items-center gap-2 rounded-xl px-4 text-sm font-semibold text-white"
                                style="background-color:#00A389;color:#fff;">
                                <i class="ri-save-line"></i>
                                <span id="quick-client-save-label">Guardar cliente</span>
                            </button>
                            <button id="quick-client-cancel-button" type="button"
                                class="inline-flex h-11 items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                                <i class="ri-close-line"></i>
                                <span>Cancelar</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
