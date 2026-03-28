<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>OS {{ $order->movement?->number }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 22px 26px;
            font-family: "Courier New", Courier, monospace;
            color: #2d3748;
            font-size: 13px;
            line-height: 1.3;
        }
        .page-title { text-align: center; margin-bottom: 12px; }
        .page-title h1 { margin: 0; font-size: 42px; letter-spacing: 1px; font-weight: 700; }
        .page-title h2 { margin: 2px 0 0; font-size: 28px; letter-spacing: 1px; font-weight: 700; }

        .row-2 { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        .row-2 td { width: 50%; vertical-align: top; padding-right: 16px; }
        .row-2 td:last-child { padding-right: 0; }

        .line-item { margin: 2px 0; white-space: nowrap; overflow: hidden; }
        .label { display: inline-block; min-width: 94px; font-weight: 700; }
        .fill {
            display: inline-block;
            border-bottom: 1px solid #4a5568;
            min-height: 16px;
            vertical-align: bottom;
            padding: 0 3px;
        }
        .w-xs { width: 90px; }
        .w-sm { width: 140px; }
        .w-md { width: 210px; }
        .w-lg { width: 290px; }
        .w-xl { width: 360px; }

        .section-title {
            margin: 18px 0 8px;
            font-size: 21px;
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        .grid-3 { width: 100%; border-collapse: collapse; }
        .grid-3 td { width: 33.33%; vertical-align: top; padding-right: 18px; }
        .grid-3 td:last-child { padding-right: 0; }

        .check-line { margin: 2px 0; white-space: nowrap; overflow: hidden; }
        .check-box {
            display: inline-block;
            width: 12px;
            text-align: center;
            margin-right: 6px;
            font-weight: 700;
        }

        .obs-line {
            border-bottom: 1px solid #4a5568;
            height: 22px;
            margin-bottom: 6px;
        }
        .damage-block {
            margin-bottom: 10px;
            page-break-inside: avoid;
        }
        .damage-title {
            margin-bottom: 4px;
            font-weight: 700;
            text-transform: uppercase;
        }
        .damage-photos {
            margin-top: 6px;
            font-size: 0;
        }
        .damage-photos img {
            display: inline-block;
            width: 118px;
            height: 88px;
            margin: 0 8px 8px 0;
            border: 1px solid #cbd5e0;
            object-fit: cover;
        }

        .table-econ { width: 100%; border-collapse: collapse; margin-top: 6px; }
        .table-econ th, .table-econ td { border: 1px solid #718096; padding: 5px 6px; }
        .table-econ th { text-align: left; font-size: 12px; text-transform: uppercase; background: #f7fafc; }
        .table-econ td.num { text-align: right; }
        .totals { margin-top: 6px; text-align: right; font-weight: 700; }

        .signature-wrap { margin-top: 16px; width: 100%; border-collapse: collapse; }
        .signature-wrap td { width: 50%; vertical-align: bottom; padding-right: 18px; }
        .signature-wrap td:last-child { padding-right: 0; }
        .signature-box {
            height: 84px;
            display: flex;
            align-items: flex-end;
            justify-content: center;
            margin-bottom: 6px;
        }
        .signature-box img { max-width: 230px; max-height: 78px; }
        .signature-line {
            border-top: 1px solid #4a5568;
            text-align: center;
            padding-top: 4px;
            font-weight: 700;
        }
    </style>
</head>
<body>
@php
    $clientName = trim(($order->client?->first_name ?? '') . ' ' . ($order->client?->last_name ?? ''));
    $services = $order->details->where('line_type', 'SERVICE')->values();
    $inventory = $order->intakeInventory->values();
    $damages = $order->damages->values();
    $damageSides = collect([
        'FRONT' => 'Frontal',
        'RIGHT' => 'Derecho',
        'LEFT' => 'Izquierdo',
        'BACK' => 'Posterior',
    ])->map(function ($label, $side) use ($damages) {
        $damage = $damages->first(fn ($item) => strtoupper((string) $item->side) === $side);
        $photos = collect($damage?->photos ?? [])
            ->map(function ($photo) {
                $path = (string) ($photo->photo_path ?? '');
                if ($path === '') {
                    return null;
                }

                $publicStoragePath = public_path('storage/' . ltrim($path, '/'));
                if (is_file($publicStoragePath)) {
                    return 'file:///' . str_replace('\\', '/', $publicStoragePath);
                }

                return asset('storage/' . ltrim($path, '/'));
            })
            ->filter()
            ->values();

        if ($photos->isEmpty() && $damage?->photo_path) {
            $fallbackPath = (string) $damage->photo_path;
            $publicStoragePath = public_path('storage/' . ltrim($fallbackPath, '/'));
            if (is_file($publicStoragePath)) {
                $photos = collect(['file:///' . str_replace('\\', '/', $publicStoragePath)]);
            } else {
                $photos = collect([asset('storage/' . ltrim($fallbackPath, '/'))]);
            }
        }

        return [
            'label' => $label,
            'damage' => $damage,
            'photos' => $photos,
        ];
    })->values();
    $damageSidesWithData = $damageSides->filter(function ($sideData) {
        $damage = $sideData['damage'];
        $photos = $sideData['photos'];

        return trim((string) ($damage?->description ?? '')) !== ''
            || trim((string) ($damage?->severity ?? '')) !== ''
            || $photos->isNotEmpty();
    })->values();

    $signaturePath = (string) ($order->intake_client_signature_path ?? '');
    $signatureUrl = null;
    if ($signaturePath !== '') {
        $publicStoragePath = public_path('storage/' . ltrim($signaturePath, '/'));
        if (is_file($publicStoragePath)) {
            $signatureUrl = 'file:///' . str_replace('\\', '/', $publicStoragePath);
        } else {
            $signatureUrl = asset('storage/' . ltrim($signaturePath, '/'));
        }
    }
@endphp

<div class="page-title">
    <h1>ORDEN DE SERVICIO</h1>
 
</div>

<table class="row-2">
    <tr>
        <td>
            <div class="line-item"><span class="label">Marca:</span><span class="fill w-lg">{{ $order->vehicle?->brand ?? '' }}</span></div>
            <div class="line-item"><span class="label">Modelo:</span><span class="fill w-sm">{{ $order->vehicle?->model ?? '' }}</span> <span class="label" style="min-width:56px;">Color:</span><span class="fill w-sm">{{ $order->vehicle?->color ?? '' }}</span></div>
            <div class="line-item"><span class="label">Km:</span><span class="fill w-sm">{{ number_format((float) ($order->mileage_in ?? 0), 0, '.', ',') }}</span> <span class="label" style="min-width:56px;">Placa:</span><span class="fill w-sm">{{ $order->vehicle?->plate ?? '' }}</span></div>
            <div class="line-item"><span class="label">N Serie:</span><span class="fill w-lg">{{ $order->vehicle?->serial_number ?? '' }}</span></div>
            <div class="line-item"><span class="label">Ingreso en grua?:</span><span class="fill w-xs">{{ $order->tow_in ? 'Si' : 'No' }}</span></div>
        </td>
        <td>
            <div class="line-item"><span class="label">Ingreso:</span><span class="fill w-xl">{{ optional($order->intake_date)->format('Y-m-d H:i:s') }}</span></div>
            <div class="line-item"><span class="label">Salida:</span><span class="fill w-xl">{{ optional($order->delivery_date)->format('Y-m-d H:i:s') ?: '' }}</span></div>
            <div class="line-item"><span class="label">Nombre:</span><span class="fill w-xl">{{ $clientName }}</span></div>
            <div class="line-item"><span class="label">Telefono:</span><span class="fill w-xl">{{ $order->client?->phone ?? '' }}</span></div>
            <div class="line-item"><span class="label">Email:</span><span class="fill w-xl">{{ $order->client?->email ?? '' }}</span></div>
        </td>
    </tr>
</table>

<div class="section-title">Trabajo a realizar</div>
<table class="grid-3">
    <tr>
        @for($col = 0; $col < 3; $col++)
            <td>
                @for($i = $col * 4; $i < ($col * 4) + 4; $i++)
                    @php $line = $services[$i] ?? null; @endphp
                    <div class="check-line">
                        <span class="check-box">{{ $line ? '[x]' : '[ ]' }}</span>
                        {{ $line?->description ?? '' }}
                    </div>
                @endfor
            </td>
        @endfor
    </tr>
</table>

<div class="section-title">Diagnostico</div>
@if(trim((string) $order->diagnosis_text) !== '')
    <div style="margin-bottom:10px;">{{ $order->diagnosis_text }}</div>
@else
    <div class="obs-line"></div>
    <div class="obs-line"></div>
@endif

<div class="section-title">Observaciones</div>
@if(trim((string) $order->observations) !== '')
    <div style="margin-bottom:10px;">{{ $order->observations }}</div>
@else
    <div class="obs-line"></div>
    <div class="obs-line"></div>
    <div class="obs-line"></div>
@endif

<div class="section-title">Inventario</div>
<table class="grid-3">
    <tr>
        @for($col = 0; $col < 3; $col++)
            <td>
                @for($i = $col * 6; $i < ($col * 6) + 6; $i++)
                    @php $item = $inventory[$i] ?? null; @endphp
                    <div class="check-line">
                        <span class="check-box">{{ $item ? ($item->present ? '[x]' : '[ ]') : '[ ]' }}</span>
                        {{ $item?->item_key ?? '' }}
                    </div>
                @endfor
            </td>
        @endfor
    </tr>
</table>

<div class="section-title">Danos pre-existentes de la unidad</div>
@if($damageSidesWithData->isEmpty())
    <div class="obs-line"></div>
@else
    @foreach($damageSidesWithData as $sideData)
        @php
            $damage = $sideData['damage'];
            $photos = $sideData['photos'];
        @endphp
        <div class="damage-block">
            <div class="damage-title">{{ $sideData['label'] }}</div>
            <div class="line-item">
                <span class="label">Descripcion:</span>
                <span class="fill w-xl">{{ $damage?->description ?? '' }}</span>
            </div>
            <div class="line-item">
                <span class="label">Severidad:</span>
                <span class="fill w-sm">{{ $damage?->severity ?? '' }}</span>
            </div>
            @if($photos->isNotEmpty())
                <div class="damage-photos">
                    @foreach($photos as $photoUrl)
                        <img src="{{ $photoUrl }}" alt="Dano {{ $sideData['label'] }}">
                    @endforeach
                </div>
            @endif
        </div>
    @endforeach
@endif

<div class="section-title">Detalle economico</div>
<table class="table-econ">
    <thead>
        <tr>
            <th style="width:13%;">Tipo</th>
            <th style="width:47%;">Descripcion</th>
            <th style="width:12%;">Cantidad</th>
            <th style="width:14%;">P. Unitario</th>
            <th style="width:14%;">Total</th>
        </tr>
    </thead>
    <tbody>
        @foreach($order->details as $line)
            <tr>
                <td>{{ $line->line_type }}</td>
                <td>{{ $line->description }}</td>
                <td class="num">{{ number_format((float) $line->qty, 2) }}</td>
                <td class="num">S/ {{ number_format((float) $line->unit_price, 2) }}</td>
                <td class="num">S/ {{ number_format((float) $line->total, 2) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
<div class="totals">
    Subtotal: S/ {{ number_format((float) $order->subtotal, 2) }} |
    IGV: S/ {{ number_format((float) $order->tax, 2) }} |
    Total: S/ {{ number_format((float) $order->total, 2) }}
</div>

<table class="signature-wrap">
    <tr>
        <td>
            <div class="signature-box">
                @if($signatureUrl)
                    <img src="{{ $signatureUrl }}" alt="Firma cliente entrada">
                @endif
            </div>
            <div class="signature-line">FIRMA DEL CLIENTE (ENTRADA)</div>
        </td>
        <td>
            <div class="signature-box"></div>
            <div class="signature-line">FIRMA DEL CLIENTE (SALIDA)</div>
        </td>
    </tr>
</table>

</body>
</html>
