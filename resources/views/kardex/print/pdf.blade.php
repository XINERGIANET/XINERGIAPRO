<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kardex de productos</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            margin: 0;
            color: #111827;
            font-size: 11px;
        }
        .report-wrap {
            width: 100%;
        }
        .report-title {
            text-align: center;
            margin-bottom: 2px;
            font-size: 34px;
            font-weight: 700;
            color: #0f172a;
        }
        .report-subtitle {
            text-align: center;
            margin-bottom: 12px;
            font-size: 18px;
            color: #334155;
        }
        .report-subtitle strong { color: #0f172a; }
        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        thead th {
            background: #ff5f54;
            color: #ffffff;
            text-align: center;
            font-weight: 700;
            padding: 7px 4px;
            border-right: 1px solid #ffffff55;
            line-height: 1.15;
        }
        thead th:last-child {
            border-right: 0;
        }
        tbody td {
            padding: 7px 5px;
            border-bottom: 1px solid #d1d5db;
            vertical-align: middle;
            line-height: 1.2;
        }
        .text-left { text-align: left; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .small { font-size: 10px; color: #334155; }
        .muted { color: #64748b; }
        .w-product { width: 15%; }
        .w-type { width: 8%; }
        .w-unit { width: 8%; }
        .w-stock { width: 8%; }
        .w-qty { width: 8%; }
        .w-balance { width: 8%; }
        .w-price { width: 8%; }
        .w-currency { width: 6%; }
        .w-total { width: 6%; }
        .w-date { width: 8%; }
        .w-origin { width: 11%; }
        .w-state { width: 8%; }
        .w-info { width: 10%; }
    </style>
</head>
<body>
    <div class="report-wrap">
        <div class="report-title">Kárdex de productos</div>
        <div class="report-subtitle">
            <strong>Desde</strong> {{ $dateFrom }}
            <strong>hasta</strong> {{ $dateTo }}
        </div>

        <table>
            <thead>
                <tr>
                    <th class="w-product">Producto</th>
                    <th class="w-type">Tipo</th>
                    <th class="w-unit">Unidad</th>
                    <th class="w-stock">Stock<br>anterior</th>
                    <th class="w-qty">Cantidad</th>
                    <th class="w-balance">Stock<br>actual</th>
                    <th class="w-price">P.<br>unitario</th>
                    <th class="w-currency">Moneda</th>
                    <th class="w-total">Total</th>
                    <th class="w-date">Fecha</th>
                    <th class="w-origin">Origen</th>
                    <th class="w-state">Situación</th>
                    <th class="w-info">Info. movimiento</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($movements as $movement)
                    @php
                        $date = !empty($movement['date']) ? \Carbon\Carbon::parse($movement['date']) : null;
                        $situationCode = (string) ($movement['situation'] ?? 'E');
                        $situationLabel = match ($situationCode) {
                            'A' => 'Anulado',
                            'I' => 'Inactivo',
                            default => 'Activado',
                        };
                    @endphp
                    <tr>
                        <td class="text-left">
                            {{ $movement['product_code'] ?? '-' }} - {{ $movement['product_description'] ?? '-' }}
                        </td>
                        <td class="text-left">{{ $movement['type'] ?? '-' }}</td>
                        <td class="text-left">{{ $movement['unit'] ?? '-' }}</td>
                        <td class="text-center">{{ number_format((float) ($movement['previous_stock'] ?? 0), 0) }}</td>
                        <td class="text-center">{{ number_format((float) ($movement['quantity'] ?? 0), 0) }}</td>
                        <td class="text-center">{{ number_format((float) ($movement['balance'] ?? 0), 0) }}</td>
                        <td class="text-right">{{ number_format((float) ($movement['unit_price'] ?? 0), 2) }}</td>
                        <td class="text-center">{{ $movement['currency'] ?? 'PEN' }}</td>
                        <td class="text-right">{{ number_format((float) ($movement['total'] ?? 0), 2) }}</td>
                        <td class="text-center">
                            @if ($date)
                                {{ $date->format('Y-m-d') }}<br>
                                {{ $date->format('h:i:s A') }}
                            @else
                                -
                            @endif
                        </td>
                        <td class="text-left">{{ $movement['origin'] ?? '-' }}</td>
                        <td class="text-center">{{ $situationLabel }}</td>
                        <td class="text-left small">
                            {{ $movement['number'] ?? '-' }}
                            @if(!empty($movement['operation_label']))
                                <br><span class="muted">({{ $movement['operation_label'] }})</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="13" class="text-center muted" style="padding: 16px 8px;">
                            No hay movimientos para los filtros seleccionados.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</body>
</html>
