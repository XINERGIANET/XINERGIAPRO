@php
    $ruc = trim((string) ($branch?->ruc ?? $branch?->company?->tax_id ?? ''));
    $clientName = trim((string) (($order->client->first_name ?? '') . ' ' . ($order->client->last_name ?? '')));
    $veh = $order->vehicle;
    if ($veh) {
        $marca = $veh->brand ?? '—';
        $modelo = $veh->model ?? '—';
        $placa = $veh->plate ?? '—';
        $color = (string) ($veh->color ?? '');
        $km = $order->mileage_in;
        if ($km === null || $km === '') {
            $km = $veh->current_mileage;
        }
        $kmStr = $km !== null && $km !== '' ? (string) $km : '';
        if ($kmStr !== '' && !str_contains(strtolower($kmStr), 'km')) {
            $kmStr .= ' KM';
        } elseif ($kmStr === '') {
            $kmStr = '—';
        }
    } else {
        $marca = '—';
        $modelo = '—';
        $placa = '—';
        $color = '—';
        $kmStr = '—';
    }
    $reportNotes = trim((string) ($order->parts_replacement_report_notes ?? ''));
@endphp
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Informe Estado de Repuestos {{ $docNumber }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 14px 18px 22px;
            font-family: Arial, Helvetica, sans-serif;
            color: #111;
            font-size: 10.5pt;
            line-height: 1.25;
        }
        .top-wrap { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        .top-wrap td { vertical-align: top; }
        .logo-box { width: 88px; padding-right: 8px; }
        .logo-box img { display: block; max-width: 80px; max-height: 80px; object-fit: contain; }
        .co-name { font-weight: 800; font-size: 11pt; text-transform: uppercase; margin: 0 0 2px; }
        .co-line { margin: 0; font-size: 9.5pt; }
        .title-bar { background: #4a4a4a; color: #fff; text-align: center; padding: 7px 10px; font-weight: 800; letter-spacing: 0.04em; font-size: 12.5pt; }
        .title-sub { text-align: center; font-size: 11pt; font-weight: 700; margin-top: 4px; }
        .date-right { text-align: right; font-size: 9.5pt; margin-top: 4px; }
        .señor { margin: 10px 0 6px; font-size: 10.5pt; }
        .intro { text-align: justify; margin: 0 0 10px; font-size: 10pt; }
        .veh-table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        .veh-table td { border: 1px solid #000; padding: 4px 6px; }
        .veh-table .lbl { font-weight: 800; width: 22%; }
        .pair-block { margin-bottom: 16px; page-break-inside: avoid; border: 1px solid #000; }
        .pair-head { background: #bfbfbf; padding: 6px 8px; font-weight: 800; text-align: center; font-size: 10.5pt; }
        .pair-grid { width: 100%; border-collapse: collapse; }
        .pair-grid td { width: 50%; vertical-align: top; border-top: 1px solid #000; padding: 8px; }
        .pair-grid td + td { border-left: 1px solid #000; }
        .side-title { font-weight: 800; font-size: 10pt; margin: 0 0 6px; text-transform: uppercase; }
        .side-title.old { color: #7f1d1d; }
        .side-title.new { color: #14532d; }
        .side-name { font-weight: 700; margin: 0 0 4px; }
        .side-notes { font-size: 9.5pt; margin: 0 0 8px; color: #333; white-space: pre-wrap; }
        .photo-grid { font-size: 0; }
        .photo-grid img {
            display: inline-block;
            width: 112px;
            height: 84px;
            margin: 0 6px 6px 0;
            border: 1px solid #666;
            object-fit: cover;
        }
        .no-photo { font-size: 9pt; color: #666; font-style: italic; }
        .notes-box { margin-top: 12px; border: 1px solid #000; padding: 8px; }
        .notes-box strong { display: block; margin-bottom: 4px; }
        .foot-grid { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .foot-grid td { vertical-align: top; padding: 4px; }
    </style>
</head>
<body>
    <table class="top-wrap" cellpadding="0" cellspacing="0">
        <tr>
            <td class="logo-box">
                @if (!empty($logoFileUri))
                    <img src="{{ $logoFileUri }}" alt="Logo">
                @endif
            </td>
            <td>
                <p class="co-name">{{ $companyName }}</p>
                <p class="co-line">RUC: {{ $ruc !== '' ? $ruc : '—' }}</p>
                <p class="co-line">{{ $address !== '' ? $address : '—' }}</p>
                <p class="co-line">TELEFONO: {{ $branchPhone !== '' ? $branchPhone : '—' }}</p>
                <p class="co-line">Correo: {{ $branchEmail !== '' ? $branchEmail : '—' }}</p>
            </td>
            <td style="width: 38%; text-align: right;">
                <div class="title-bar">INFORME DE ESTADO DE REPUESTOS</div>
                <div class="title-sub">{{ $docNumber }}</div>
                <div class="date-right">{{ $dateLine }}</div>
            </td>
        </tr>
    </table>

    <p class="señor"><strong>SEÑOR :</strong> {{ $clientName !== '' ? $clientName : '________________________________' }}</p>
    <p class="intro">Se documenta el estado de los repuestos retirados del vehículo y los repuestos nuevos instalados o por instalar, con evidencia fotográfica comparativa por cada ítem.</p>

    <table class="veh-table" cellspacing="0">
        <tr>
            <td class="lbl">MARCA:</td>
            <td>{{ $marca }}</td>
            <td class="lbl">PLACA:</td>
            <td>{{ $placa }}</td>
        </tr>
        <tr>
            <td class="lbl">MODELO:</td>
            <td>{{ $modelo }}</td>
            <td class="lbl">COLOR:</td>
            <td>{{ $color !== '' ? $color : '—' }}</td>
        </tr>
        <tr>
            <td class="lbl">KILOMETRAJE:</td>
            <td colspan="3">{{ $kmStr }}</td>
        </tr>
    </table>

    @forelse ($replacementPairs as $index => $pair)
        <div class="pair-block">
            <div class="pair-head">REPUESTO {{ $index + 1 }} — COMPARATIVO RETIRADO / NUEVO</div>
            <table class="pair-grid" cellspacing="0">
                <tr>
                    <td>
                        <p class="side-title old">Repuesto retirado (usado)</p>
                        <p class="side-name">{{ $pair['old_part_name'] !== '' ? $pair['old_part_name'] : '—' }}</p>
                        @if($pair['old_part_notes'] !== '')
                            <p class="side-notes">{{ $pair['old_part_notes'] }}</p>
                        @endif
                        @if($pair['old_photos']->isNotEmpty())
                            <div class="photo-grid">
                                @foreach ($pair['old_photos'] as $photoUri)
                                    <img src="{{ $photoUri }}" alt="Repuesto retirado">
                                @endforeach
                            </div>
                        @else
                            <p class="no-photo">Sin fotografías del repuesto retirado.</p>
                        @endif
                    </td>
                    <td>
                        <p class="side-title new">Repuesto nuevo (instalado / por instalar)</p>
                        <p class="side-name">{{ $pair['new_part_name'] !== '' ? $pair['new_part_name'] : '—' }}</p>
                        @if($pair['new_part_notes'] !== '')
                            <p class="side-notes">{{ $pair['new_part_notes'] }}</p>
                        @endif
                        @if($pair['new_photos']->isNotEmpty())
                            <div class="photo-grid">
                                @foreach ($pair['new_photos'] as $photoUri)
                                    <img src="{{ $photoUri }}" alt="Repuesto nuevo">
                                @endforeach
                            </div>
                        @else
                            <p class="no-photo">Sin fotografías del repuesto nuevo.</p>
                        @endif
                    </td>
                </tr>
            </table>
        </div>
    @empty
        <div class="notes-box">
            <strong>Sin registros de repuestos</strong>
            <span>No se registraron pares de repuestos con evidencia fotográfica para esta orden.</span>
        </div>
    @endforelse

    @if($reportNotes !== '')
        <div class="notes-box">
            <strong>Observaciones generales del informe</strong>
            <div style="white-space: pre-wrap;">{{ $reportNotes }}</div>
        </div>
    @endif

    <table class="foot-grid" cellspacing="0">
        <tr>
            <td style="width:50%;">
                <p style="margin:0 0 4px;"><strong>TECNICO RESPONSABLE:</strong> {{ $technicianLine !== '' ? $technicianLine : '________________' }}</p>
                <p style="margin:0; font-size:9.5pt;">Documento generado como evidencia del cambio de repuestos durante la reparación.</p>
            </td>
        </tr>
    </table>
</body>
</html>
