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
        .fuel-pdf-wrap {
            margin: 8px 0 12px;
            page-break-inside: avoid;
        }
        .fuel-pdf-title {
            font-weight: 700;
            text-transform: uppercase;
            margin-bottom: 4px;
        }
        .fuel-pdf-box {
            display: inline-block;
            width: 220px;
            vertical-align: middle;
        }
        .fuel-pdf-value {
            display: inline-block;
            margin-left: 14px;
            vertical-align: middle;
            font-weight: 700;
            font-size: 16px;
        }

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
    $additionalAccessories = $order->additionalAccessories->values();
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
    $fuelLevelRaw = $order->fuel_level;
    $fuelLevel = $fuelLevelRaw === null ? null : max(0, min(100, (int) $fuelLevelRaw));
    $fuelNeedleLevel = $fuelLevel ?? 50;
    $fuelAngle = 220 + $fuelNeedleLevel;
    $fuelRadians = deg2rad($fuelAngle);
    $fuelNeedleX = 160 + cos($fuelRadians) * 82;
    $fuelNeedleY = 150 + sin($fuelRadians) * 82;
@endphp

<div class="page-title">
    <h1>{{ ($order->service_type === 'correctivo' && $order->status === 'draft') ? 'COTIZACIÓN' : 'ORDEN DE SERVICIO' }}</h1>
</div>

<table class="row-2">
    <tr>
        <td>
            <div class="line-item"><span class="label">Marca:</span><span class="fill w-lg">{{ $order->vehicle?->brand ?? '' }}</span></div>
            <div class="line-item"><span class="label">Modelo:</span><span class="fill w-sm">{{ $order->vehicle?->model ?? '' }}</span> <span class="label" style="min-width:56px;">Color:</span><span class="fill w-sm">{{ $order->vehicle?->color ?? '' }}</span></div>
            <div class="line-item"><span class="label">Km:</span><span class="fill w-sm">{{ number_format((float) ($order->mileage_in ?? 0), 0, '.', ',') }}</span> <span class="label" style="min-width:56px;">Placa:</span><span class="fill w-sm">{{ $order->vehicle?->plate ?? '' }}</span></div>
            <div class="line-item"><span class="label">Combustible:</span><span class="fill w-sm">{{ $fuelLevel === null ? '' : ($fuelLevel . '%') }}</span></div>
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

<div class="fuel-pdf-wrap">
    <div class="fuel-pdf-title">Nivel de combustible</div>
    <div class="fuel-pdf-box">
        <svg viewBox="0 0 320 180" width="220" height="124" xmlns="http://www.w3.org/2000/svg">
            <path d="M 40 116 A 140 140 0 0 1 280 116" fill="none" stroke="#1f2937" stroke-width="16" stroke-linecap="round"/>
            <path d="M 40 116 A 140 140 0 0 1 280 116" fill="none" stroke="#4b5563" stroke-width="8" stroke-linecap="round"/>
            @for($tick = 0; $tick <= 10; $tick++)
                @php
                    $angle = 220 + ($tick * 10);
                    $rad = deg2rad($angle);
                    $outerX = 160 + cos($rad) * 140;
                    $outerY = 150 + sin($rad) * 140;
                    $innerLength = in_array($tick, [0, 5, 10], true) ? 114 : 124;
                    $innerX = 160 + cos($rad) * $innerLength;
                    $innerY = 150 + sin($rad) * $innerLength;
                @endphp
                <line x1="{{ $outerX }}" y1="{{ $outerY }}" x2="{{ $innerX }}" y2="{{ $innerY }}"
                      stroke="#111827"
                      stroke-width="{{ in_array($tick, [0, 5, 10], true) ? 7 : 3 }}"
                      stroke-linecap="round"/>
            @endfor
            <text x="30" y="158" font-size="34" font-weight="900" fill="#b91c1c">E</text>
            <text x="270" y="158" font-size="34" font-weight="900" fill="#1f2937">F</text>
            <g transform="translate(160 62)">
                <rect x="-12" y="-22" width="24" height="42" rx="3" fill="#1f2937"/>
                <rect x="-7" y="-28" width="14" height="6" rx="2" fill="#1f2937"/>
                <path d="M 14 -12 C 29 -8 22 12 33 13" fill="none" stroke="#1f2937" stroke-width="4" stroke-linecap="round"/>
                <circle cx="34" cy="13" r="3" fill="#1f2937"/>
            </g>
            <line x1="160" y1="150" x2="{{ $fuelNeedleX }}" y2="{{ $fuelNeedleY }}" stroke="#dc2626" stroke-width="9" stroke-linecap="round"/>
            <circle cx="160" cy="150" r="28" fill="#27272a"/>
            <circle cx="160" cy="150" r="9" fill="#111827"/>
        </svg>
    </div>
    <div class="fuel-pdf-value">{{ $fuelLevel === null ? 'Sin registrar' : ($fuelLevel . '%') }}</div>
</div>

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

@if($additionalAccessories->isNotEmpty())
    <div class="section-title">Accesorios adicionales</div>
    <table class="grid-3">
        <tr>
            @for($col = 0; $col < 3; $col++)
                <td>
                    @for($i = $col * 6; $i < ($col * 6) + 6; $i++)
                        @php $item = $additionalAccessories[$i] ?? null; @endphp
                        <div class="check-line">
                            <span class="check-box">{{ $item ? ($item->present ? '[x]' : '[ ]') : '[ ]' }}</span>
                            {{ $item?->name ?? '' }}
                        </div>
                    @endfor
                </td>
            @endfor
        </tr>
    </table>
@endif

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
