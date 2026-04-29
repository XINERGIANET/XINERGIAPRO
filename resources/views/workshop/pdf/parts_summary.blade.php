@php
    $fmt = fn ($n) => 'S/ ' . number_format((float) $n, 2, '.', ',');
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
    $rowNum = 0;
@endphp
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Resumen de Repuestos {{ $docNumber }}</title>
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
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th, .data-table td { border: 1px solid #000; padding: 4px 5px; vertical-align: top; }
        .data-table th { font-size: 9pt; background: #e6e6e6; }
        .data-table .c-num { text-align: right; white-space: nowrap; }
        .data-table .c-cant { text-align: center; width: 48px; }
        .data-table .c-item { width: 36px; text-align: center; }
        .sec-bar td { background: #bfbfbf; font-weight: 800; text-align: center; font-size: 10pt; }
        .sec-bar .sum-cell { text-align: right; font-weight: 800; }
        .tot-bar td { background: #d9d9d9; font-weight: 800; }
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
                <div class="title-bar">RESUMEN DE REPUESTOS</div>
                <div class="title-sub">{{ $docNumber }}</div>
                <div class="date-right">{{ $dateLine }}</div>
            </td>
        </tr>
    </table>

    <p class="señor"><strong>SEÑOR :</strong> {{ $clientName !== '' ? $clientName : '________________________________' }}</p>
    <p class="intro">Se detalla a continuación el resumen de repuestos utilizados o solicitados para la unidad vehicular referenciada.</p>

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

    <table class="data-table" cellspacing="0">
        <thead>
            <tr>
                <th class="c-item">ITEM</th>
                <th class="c-cant">CANT.</th>
                <th>CÓDIGO / PRODUCTO</th>
                <th>DESCRIPCIÓN</th>
            </tr>
        </thead>
        <tbody>
            <tr class="sec-bar">
                <td colspan="4">REPUESTOS</td>
            </tr>
            @forelse ($partLines as $d)
                @php $rowNum++; @endphp
                <tr>
                    <td class="c-item">{{ $rowNum }}</td>
                    <td class="c-cant">{{ rtrim(rtrim(number_format((float) $d->qty, 2, '.', ''), '0'), '.') }}</td>
                    <td>{{ $d->product?->code ?? '—' }}</td>
                    <td>{{ $d->description }}</td>
                </tr>
            @empty
                <tr>
                    <td class="c-item">—</td>
                    <td class="c-cant">—</td>
                    <td>—</td>
                    <td style="color:#666;">(Sin repuestos)</td>
                </tr>
            @endforelse


        </tbody>
    </table>

    <table class="foot-grid" cellspacing="0">
        <tr>
            <td style="width:50%;">
                <p style="margin:0 0 4px;"><strong>TECNICO RESPONSABLE:</strong> {{ $technicianLine !== '' ? $technicianLine : '________________' }}</p>
                <p style="margin:0; font-size:9.5pt;">Sin otro particular, quedamos de Ud.</p>
            </td>
        </tr>
    </table>
</body>
</html>
