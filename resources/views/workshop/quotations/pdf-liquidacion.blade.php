@php
    $fmt = fn ($n) => 'S/ ' . number_format((float) $n, 2, '.', ',');
    $ruc = trim((string) ($branch?->ruc ?? $branch?->company?->tax_id ?? ''));
    $clientName = trim((string) (($quotation->client->first_name ?? '') . ' ' . ($quotation->client->last_name ?? '')));
    $veh = $quotation->vehicle;
    $vehicleNote = trim((string) ($quotation->quotation_vehicle_note ?? ''));
    if ($veh) {
        $marca = $veh->brand ?? '—';
        $modelo = $veh->model ?? '—';
        $placa = $veh->plate ?? '—';
        $color = (string) ($veh->color ?? '');
        $km = $quotation->mileage_in;
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
        $modelo = $vehicleNote !== '' ? $vehicleNote : '—';
        $placa = '—';
        $color = '';
        $kmStr = '—';
    }
    $intro = (string) ($terms['quotation_intro'] ?? 'Estimado agradecemos de ante mano su peticion y estamos enviando formalmente nuestra proforma según lo solicitado, con referencia a la siguiente unidad.');
    $pricesLine = (string) ($terms['prices_note'] ?? 'Precios expresados en Soles');
    $validityLine = (string) ($terms['offer_validity'] ?? '7 dias habiles');
    $bcpCuenta = (string) ($terms['bank_account_bcp'] ?? '');
    $bcpCci = (string) ($terms['bank_cci'] ?? '');
    $bbvaCuenta = (string) ($terms['bank_account_bbva'] ?? '');
    $bbvaCci = (string) ($terms['bank_cci_bbva'] ?? '');
    $nextMaint = (string) ($terms['next_maintenance'] ?? '');
    $rowNum = 0;
@endphp
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Liquidación {{ $docNumber }}</title>
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
        .data-table .c-num, .data-table .c-pu, .data-table .c-imp { text-align: right; white-space: nowrap; }
        .data-table .c-cant { text-align: center; width: 48px; }
        .data-table .c-item { width: 36px; text-align: center; }
        .sec-bar td { background: #bfbfbf; font-weight: 800; text-align: center; font-size: 10pt; }
        .sec-bar .sum-cell { text-align: right; font-weight: 800; }
        .tot-bar td { background: #d9d9d9; font-weight: 800; }
        .box { border: 1px solid #000; padding: 8px 10px; margin-top: 10px; }
        .box h4 { margin: 0 0 6px; font-size: 10pt; }
        .cond-list { margin: 0; padding-left: 18px; }
        .cond-list li { margin: 2px 0; }
        .bank-t { width: 100%; border-collapse: collapse; margin-top: 4px; font-size: 9.5pt; }
        .bank-t th, .bank-t td { border: 1px solid #000; padding: 3px 5px; }
        .foot-grid { width: 100%; border-collapse: collapse; margin-top: 8px; }
        .foot-grid td { vertical-align: top; padding: 4px; }
        .atte { text-align: right; font-size: 10pt; margin-top: 8px; }
        .mt-wrap { min-height: 22px; border-bottom: 1px solid #000; margin: 2px 0 8px; }
        .obs-box { min-height: 64px; border: 1px solid #ccc; margin-top: 4px; padding: 4px; }
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
                <div class="title-bar">LIQUIDACIÓN DE SERVICIO</div>
                <div class="title-sub">{{ $docNumber }}</div>
                <div class="date-right">{{ $dateLine }}</div>
            </td>
        </tr>
    </table>

    <p class="señor"><strong>SEÑOR :</strong> {{ $clientName !== '' ? $clientName : '________________________________' }}</p>
    <p class="intro">{{ $intro }}</p>

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
                <th>DETALLE</th>
                <th class="c-pu">P.U.</th>
                <th class="c-imp">IMPORTE</th>
            </tr>
        </thead>
        <tbody>
            <tr class="sec-bar">
                <td colspan="4">SERVICIOS A REALIZAR:</td>
                <td class="sum-cell">{{ $fmt($subtotalServices) }}</td>
            </tr>
            @forelse ($serviceLines as $d)
                @php $rowNum++; @endphp
                <tr>
                    <td class="c-item">{{ $rowNum }}</td>
                    <td class="c-cant">{{ rtrim(rtrim(number_format((float) $d->qty, 2, '.', ''), '0'), '.') }}</td>
                    <td>{{ $d->description }}</td>
                    <td class="c-pu">{{ $fmt($d->unit_price) }}</td>
                    <td class="c-imp">{{ $fmt($d->total) }}</td>
                </tr>
            @empty
                <tr>
                    <td class="c-item">—</td>
                    <td class="c-cant">—</td>
                    <td style="color:#666;">(Sin lineas de servicio)</td>
                    <td class="c-pu">{{ $fmt(0) }}</td>
                    <td class="c-imp">{{ $fmt(0) }}</td>
                </tr>
            @endforelse

            <tr class="sec-bar">
                <td colspan="4">REPUESTOS</td>
                <td class="sum-cell">{{ $fmt($subtotalParts) }}</td>
            </tr>
            @forelse ($partLines as $d)
                @php $rowNum++; @endphp
                <tr>
                    <td class="c-item">{{ $rowNum }}</td>
                    <td class="c-cant">{{ rtrim(rtrim(number_format((float) $d->qty, 2, '.', ''), '0'), '.') }}</td>
                    <td>{{ $d->description }}</td>
                    <td class="c-pu">{{ $fmt($d->unit_price) }}</td>
                    <td class="c-imp">{{ $fmt($d->total) }}</td>
                </tr>
            @empty
                <tr>
                    <td class="c-item">—</td>
                    <td class="c-cant">—</td>
                    <td style="color:#666;">(Sin repuestos)</td>
                    <td class="c-pu">{{ $fmt(0) }}</td>
                    <td class="c-imp">{{ $fmt(0) }}</td>
                </tr>
            @endforelse

            <tr class="tot-bar">
                <td colspan="4" style="text-align: right; padding-right: 8px;">TOTAL</td>
                <td class="c-imp">{{ $fmt($quotation->total) }}</td>
            </tr>
        </tbody>
    </table>

    <div class="box">
        <p style="margin:0 0 4px;"><strong>PROXIMO MANTENIMIENTO:</strong></p>
        <div class="mt-wrap">{!! $nextMaint !== '' ? nl2br(e($nextMaint)) : '&nbsp;' !!}</div>

        <p style="margin:0 0 4px;"><strong>Condiciones:</strong></p>
        <ol class="cond-list">
            <li>{{ $pricesLine }}</li>
            <li>Válido por {{ $validityLine }}.</li>
            <li>
                Cuentas bancarias:
                <table class="bank-t" cellspacing="0">
                    <thead>
                        <tr>
                            <th>BANCO</th>
                            <th>N° CUENTA</th>
                            <th>CUENTA INTERBANCARIA (CCI)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @if ($bcpCuenta !== '' || $bcpCci !== '')
                            <tr>
                                <td>BCP</td>
                                <td>{{ $bcpCuenta !== '' ? $bcpCuenta : '—' }}</td>
                                <td>{{ $bcpCci !== '' ? $bcpCci : '—' }}</td>
                            </tr>
                        @endif
                        @if ($bbvaCuenta !== '' || $bbvaCci !== '')
                            <tr>
                                <td>BBVA</td>
                                <td>{{ $bbvaCuenta !== '' ? $bbvaCuenta : '—' }}</td>
                                <td>{{ $bbvaCci !== '' ? $bbvaCci : '—' }}</td>
                            </tr>
                        @endif
                        @if (($bcpCuenta === '' && $bcpCci === '') && ($bbvaCuenta === '' && $bbvaCci === ''))
                            <tr>
                                <td colspan="3" style="text-align:center; color:#666;">(Indique sus cuentas en términos comerciales de la cotización)</td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </li>
        </ol>
    </div>

    <table class="foot-grid" cellspacing="0">
        <tr>
            <td style="width:50%;">
                <p style="margin:0 0 4px;"><strong>TECNICO RESPONSABLE:</strong> {{ $technicianLine !== '' ? $technicianLine : '________________' }}</p>
                <p style="margin:0; font-size:9.5pt;">Sin otro particular, quedamos de Ud.</p>
            </td>
        </tr>
        <tr>
            <td>
                <p style="margin:8px 0 4px;"><strong>OBSERVACIONES:</strong></p>
                <div class="obs-box">{!! $quotation->observations ? nl2br(e($quotation->observations)) : '&nbsp;' !!}</div>
            </td>
        </tr>
    </table>
    <p class="atte">Atentamente</p>
</body>
</html>
