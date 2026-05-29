@extends('layouts.app')

@section('content')
<div
    x-data="partsReplacementStatusData(@js($pairsPayload), @js(old('report_notes', $order->parts_replacement_report_notes ?? '')))"
    class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8"
>
    <x-common.page-breadcrumb
        pageTitle="Estado de Repuestos"
        :crumbs="[
            ['label' => 'Tablero de Mantenimiento', 'url' => route('workshop.maintenance-board.index', array_filter(['view_id' => $viewId ?? null]))],
            ['label' => 'Estado de Repuestos'],
        ]"
    />

    <x-common.component-card>
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-gray-900">Estado de Repuestos</h1>
                <p class="mt-1 text-sm text-gray-500">
                    OS {{ $order->movement?->number ?? ('#' . $order->id) }}
                    — {{ trim(($order->vehicle?->brand ?? '') . ' ' . ($order->vehicle?->model ?? '')) ?: 'Vehículo' }}
                    — Placa {{ $order->vehicle?->plate ?: 'S/PLACA' }}
                </p>
                <p class="mt-2 text-sm text-gray-600">
                    Relaciona cada repuesto retirado con su repuesto nuevo. Sube todas las fotos que necesites desde cámara o archivos.
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('workshop.pdf.parts-replacement-status', $order) }}"
                   target="_blank"
                   rel="noopener"
                   class="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50">
                    <i class="ri-file-pdf-line text-red-600"></i>
                    Ver informe PDF
                </a>
                <a href="{{ route('workshop.maintenance-board.index', array_filter(['view_id' => $viewId ?? null, 'status' => 'in_progress'])) }}"
                   class="inline-flex items-center gap-2 rounded-xl border border-gray-300 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                    <i class="ri-arrow-left-line"></i>
                    Volver al tablero
                </a>
            </div>
        </div>

        @if (session('status'))
            <div class="mt-4 rounded-lg border border-green-300 bg-green-50 p-3 text-sm text-green-700">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="mt-4 rounded-lg border border-red-300 bg-red-50 p-3 text-sm text-red-700">{{ $errors->first() }}</div>
        @endif

        <form
            method="POST"
            action="{{ route('workshop.maintenance-board.parts-replacement-status.store', $order) }}"
            enctype="multipart/form-data"
            class="mt-6 space-y-6"
            data-turbo="false"
        >
            @csrf
            @if (!empty($viewId))
                <input type="hidden" name="view_id" value="{{ $viewId }}">
            @endif

            <template x-for="photoId in deletePhotoIds" :key="`delete-photo-${photoId}`">
                <input type="hidden" name="delete_photo_ids[]" :value="photoId">
            </template>

            <div class="rounded-xl border border-gray-200 bg-white p-4">
                <label class="mb-1 block text-sm font-semibold text-gray-800">Observaciones generales del informe</label>
                <textarea
                    name="report_notes"
                    rows="3"
                    x-model="reportNotes"
                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"
                    placeholder="Notas adicionales que aparecerán en el informe PDF (opcional)"
                ></textarea>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900">Repuestos retirados vs. nuevos</h2>
                    <p class="text-xs text-gray-500">Cada bloque representa un par: lo que tenía el vehículo y lo que se instalará.</p>
                </div>
                <button type="button" @click="addPair()" class="inline-flex items-center gap-2 rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-2 text-sm font-semibold text-indigo-700 hover:bg-indigo-100">
                    <i class="ri-add-line"></i>
                    Agregar repuesto
                </button>
            </div>

            <div class="space-y-5">
                <template x-for="(pair, pindex) in pairs" :key="pair.uid">
                    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-gray-50/70">
                        <div class="flex items-center justify-between gap-3 border-b border-gray-200 bg-white px-4 py-3">
                            <h3 class="text-sm font-bold uppercase tracking-wide text-gray-800">
                                Repuesto <span x-text="pindex + 1"></span>
                            </h3>
                            <button type="button" @click="removePair(pindex)" class="inline-flex items-center gap-1 rounded-lg border border-rose-200 bg-rose-50 px-2.5 py-1.5 text-xs font-semibold text-rose-700 hover:bg-rose-100" x-show="pairs.length > 1">
                                <i class="ri-delete-bin-line"></i>
                                Quitar
                            </button>
                        </div>

                        <div class="grid gap-4 p-4 lg:grid-cols-2">
                            <input type="hidden" :name="`pairs[${pindex}][id]`" :value="pair.id || ''">

                            <div class="rounded-xl border border-rose-200 bg-rose-50/40 p-4">
                                <p class="mb-3 text-xs font-bold uppercase tracking-wide text-rose-800">Repuesto retirado (usado)</p>
                                <label class="mb-1 block text-xs font-semibold text-gray-700">Nombre / descripción</label>
                                <input type="text" x-model="pair.old_part_name" :name="`pairs[${pindex}][old_part_name]`" class="mb-3 h-10 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm" placeholder="Ej. Pastilla de freno delantera izquierda">
                                <label class="mb-1 block text-xs font-semibold text-gray-700">Observaciones</label>
                                <textarea x-model="pair.old_part_notes" :name="`pairs[${pindex}][old_part_notes]`" rows="2" class="mb-3 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm" placeholder="Estado, desgaste, código, etc."></textarea>

                                <div class="mb-2 flex flex-wrap gap-2">
                                    <input type="file" class="hidden" :id="`old-file-${pair.uid}`" :name="`pairs[${pindex}][old_photos][]`" accept="image/*" multiple @change="accumulateFiles(pindex, 'old', $event)">
                                    <button type="button" @click="openFilePicker('old', true, pair.uid)" class="inline-flex items-center gap-1 rounded-lg bg-rose-700 px-3 py-2 text-xs font-semibold text-white hover:bg-rose-800">
                                        <i class="ri-camera-line"></i> Cámara
                                    </button>
                                    <button type="button" @click="openFilePicker('old', false, pair.uid)" class="inline-flex items-center gap-1 rounded-lg border border-rose-300 bg-white px-3 py-2 text-xs font-semibold text-rose-800 hover:bg-rose-50">
                                        <i class="ri-folder-image-line"></i> Archivos
                                    </button>
                                </div>

                                <div class="flex flex-wrap gap-2" x-show="(pair.old_photos || []).length > 0">
                                    <template x-for="(photo, photoIndex) in pair.old_photos" :key="`old-saved-${photo.id}`">
                                        <div class="relative h-20 w-20 overflow-hidden rounded-lg border border-gray-200 bg-white">
                                            <a :href="photo.url" target="_blank" rel="noopener">
                                                <img :src="photo.url" alt="Repuesto retirado" class="h-full w-full object-cover">
                                            </a>
                                            <button type="button" @click="markPhotoForDelete(pindex, 'old', photoIndex, photo.id)" class="absolute right-1 top-1 rounded-full bg-black/60 px-1.5 py-0.5 text-[10px] font-bold text-white">×</button>
                                        </div>
                                    </template>
                                </div>
                                <div class="mt-2 flex flex-wrap gap-2" x-show="(pair.old_new_previews || []).length > 0">
                                    <template x-for="(preview, previewIndex) in pair.old_new_previews" :key="`old-new-${pair.uid}-${previewIndex}`">
                                        <div class="h-20 w-20 overflow-hidden rounded-lg border border-dashed border-rose-300 bg-white">
                                            <img :src="preview.url" :alt="preview.name" class="h-full w-full object-cover">
                                        </div>
                                    </template>
                                </div>
                                <p class="mt-2 text-[11px] text-gray-500">Puedes subir ilimitadas fotos del repuesto retirado.</p>
                            </div>

                            <div class="rounded-xl border border-emerald-200 bg-emerald-50/40 p-4">
                                <p class="mb-3 text-xs font-bold uppercase tracking-wide text-emerald-800">Repuesto nuevo (instalado / por instalar)</p>
                                <label class="mb-1 block text-xs font-semibold text-gray-700">Nombre / descripción</label>
                                <input type="text" x-model="pair.new_part_name" :name="`pairs[${pindex}][new_part_name]`" class="mb-3 h-10 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm" placeholder="Ej. Pastilla Brembo nueva">
                                <label class="mb-1 block text-xs font-semibold text-gray-700">Observaciones</label>
                                <textarea x-model="pair.new_part_notes" :name="`pairs[${pindex}][new_part_notes]`" rows="2" class="mb-3 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm" placeholder="Marca, código, lote, etc."></textarea>

                                <div class="mb-2 flex flex-wrap gap-2">
                                    <input type="file" class="hidden" :id="`new-file-${pair.uid}`" :name="`pairs[${pindex}][new_photos][]`" accept="image/*" multiple @change="accumulateFiles(pindex, 'new', $event)">
                                    <button type="button" @click="openFilePicker('new', true, pair.uid)" class="inline-flex items-center gap-1 rounded-lg bg-emerald-700 px-3 py-2 text-xs font-semibold text-white hover:bg-emerald-800">
                                        <i class="ri-camera-line"></i> Cámara
                                    </button>
                                    <button type="button" @click="openFilePicker('new', false, pair.uid)" class="inline-flex items-center gap-1 rounded-lg border border-emerald-300 bg-white px-3 py-2 text-xs font-semibold text-emerald-800 hover:bg-emerald-50">
                                        <i class="ri-folder-image-line"></i> Archivos
                                    </button>
                                </div>

                                <div class="flex flex-wrap gap-2" x-show="(pair.new_photos || []).length > 0">
                                    <template x-for="(photo, photoIndex) in pair.new_photos" :key="`new-saved-${photo.id}`">
                                        <div class="relative h-20 w-20 overflow-hidden rounded-lg border border-gray-200 bg-white">
                                            <a :href="photo.url" target="_blank" rel="noopener">
                                                <img :src="photo.url" alt="Repuesto nuevo" class="h-full w-full object-cover">
                                            </a>
                                            <button type="button" @click="markPhotoForDelete(pindex, 'new', photoIndex, photo.id)" class="absolute right-1 top-1 rounded-full bg-black/60 px-1.5 py-0.5 text-[10px] font-bold text-white">×</button>
                                        </div>
                                    </template>
                                </div>
                                <div class="mt-2 flex flex-wrap gap-2" x-show="(pair.new_new_previews || []).length > 0">
                                    <template x-for="(preview, previewIndex) in pair.new_new_previews" :key="`new-new-${pair.uid}-${previewIndex}`">
                                        <div class="h-20 w-20 overflow-hidden rounded-lg border border-dashed border-emerald-300 bg-white">
                                            <img :src="preview.url" :alt="preview.name" class="h-full w-full object-cover">
                                        </div>
                                    </template>
                                </div>
                                <p class="mt-2 text-[11px] text-gray-500">Puedes subir ilimitadas fotos del repuesto nuevo.</p>
                            </div>
                        </div>
                    </div>
                </template>
            </div>

            <div class="flex flex-wrap gap-3 border-t border-gray-200 pt-5">
                <x-ui.button type="submit" size="md" variant="primary">
                    <i class="ri-save-line"></i>
                    <span>Guardar y continuar editando</span>
                </x-ui.button>
                <a href="{{ route('workshop.pdf.parts-replacement-status', $order) }}"
                   target="_blank"
                   rel="noopener"
                   class="inline-flex h-11 items-center justify-center gap-2 rounded-xl border border-slate-300 bg-white px-5 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                    <i class="ri-eye-line"></i>
                    Previsualizar informe PDF
                </a>
                <a href="{{ route('workshop.maintenance-board.index', array_filter(['view_id' => $viewId ?? null, 'status' => 'in_progress'])) }}"
                   class="inline-flex h-11 items-center justify-center gap-2 rounded-xl border border-gray-300 bg-white px-5 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                    <i class="ri-close-line"></i>
                    Cancelar
                </a>
            </div>
        </form>
    </x-common.component-card>
</div>

<script>
function partsReplacementStatusData(initialPairs, initialReportNotes) {
    const normalizePair = (pair) => ({
        uid: `pair-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`,
        id: pair?.id ?? null,
        old_part_name: pair?.old_part_name ?? '',
        new_part_name: pair?.new_part_name ?? '',
        old_part_notes: pair?.old_part_notes ?? '',
        new_part_notes: pair?.new_part_notes ?? '',
        old_photos: Array.isArray(pair?.old_photos) ? pair.old_photos : [],
        new_photos: Array.isArray(pair?.new_photos) ? pair.new_photos : [],
        old_new_previews: [],
        new_new_previews: [],
        old_pending_files: [],
        new_pending_files: [],
    });

    return {
        pairs: (Array.isArray(initialPairs) && initialPairs.length ? initialPairs : [{}]).map(normalizePair),
        reportNotes: initialReportNotes || '',
        deletePhotoIds: [],
        addPair() {
            this.pairs.push(normalizePair({}));
        },
        removePair(index) {
            if (this.pairs.length <= 1) return;
            this.pairs.splice(index, 1);
        },
        markPhotoForDelete(pairIndex, side, photoIndex, photoId) {
            if (photoId) {
                this.deletePhotoIds.push(Number(photoId));
            }
            const key = side === 'old' ? 'old_photos' : 'new_photos';
            this.pairs[pairIndex][key].splice(photoIndex, 1);
        },
        openFilePicker(side, useCamera, uid) {
            const inputEl = document.getElementById(`${side}-file-${uid}`);
            if (!inputEl) return;
            if (useCamera) {
                inputEl.setAttribute('capture', 'environment');
            } else {
                inputEl.removeAttribute('capture');
            }
            inputEl.click();
        },
        accumulateFiles(pairIndex, side, event) {
            const inputEl = event.target;
            const files = Array.from(inputEl.files || []);

            if (!files.length) return;

            const pendingKey = side === 'old' ? 'old_pending_files' : 'new_pending_files';
            const previewKey = side === 'old' ? 'old_new_previews' : 'new_new_previews';

            if (!Array.isArray(this.pairs[pairIndex][pendingKey])) {
                this.pairs[pairIndex][pendingKey] = [];
            }
            if (!Array.isArray(this.pairs[pairIndex][previewKey])) {
                this.pairs[pairIndex][previewKey] = [];
            }

            files.forEach((file) => {
                this.pairs[pairIndex][pendingKey].push(file);
                this.pairs[pairIndex][previewKey].push({
                    name: file.name,
                    url: URL.createObjectURL(file),
                });
            });

            const dt = new DataTransfer();
            this.pairs[pairIndex][pendingKey].forEach((file) => dt.items.add(file));
            inputEl.files = dt.files;
        },
    };
}
</script>
@endsection
