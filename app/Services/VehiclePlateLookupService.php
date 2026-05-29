<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Http;

class VehiclePlateLookupService
{
    public function lookupByPlate(string $rawPlate): array
    {
        $plate = $this->normalizePlate($rawPlate);
        if (strlen($plate) < 5) {
            return [
                'status' => false,
                'message' => 'Ingrese una placa valida.',
            ];
        }

        $enabled = filter_var((string) config('vehicle_lookup.enabled', ''), FILTER_VALIDATE_BOOLEAN);
        $url = trim((string) config('vehicle_lookup.url', ''));
        $timeout = (int) config('vehicle_lookup.timeout', 15);

        if (!$enabled || $url === '') {
            return [
                'status' => false,
                'message' => 'Consulta por placa no configurada. Define VEHICLE_PLATE_LOOKUP_* en .env.',
            ];
        }

        $driver = trim((string) config('vehicle_lookup.driver', 'json_pe'));
        if ($driver === 'json_pe') {
            $vehicleResult = $this->lookupVehicleJsonPe($plate, $timeout);
        } else {
            $vehicleResult = $this->lookupVehicleLegacy($plate, $url, $timeout);
        }

        if (!$vehicleResult['status']) {
            return $vehicleResult;
        }

        $soat = $this->lookupSoatJsonPe($plate, $timeout);
        $vehicleResult['soat_vencimiento'] = $soat['soat_vencimiento'];
        $vehicleResult['soat_status'] = $soat['soat_status'];
        $vehicleResult['soat_message'] = $soat['soat_message'];
        $vehicleResult['soat_company'] = $soat['soat_company'];
        $vehicleResult['soat_policy_number'] = $soat['soat_policy_number'];

        if ($soat['soat_message'] !== '') {
            $vehicleResult['message'] = trim((string) ($vehicleResult['message'] ?? ''))
                . ($vehicleResult['message'] ? ' ' : '')
                . $soat['soat_message'];
        }

        return $vehicleResult;
    }

    public function normalizePlate(string $rawPlate): string
    {
        $plate = strtoupper(trim($rawPlate));

        return preg_replace('/[^A-Z0-9]/', '', $plate) ?? '';
    }

    private function lookupVehicleJsonPe(string $plate, int $timeout): array
    {
        $url = trim((string) config('vehicle_lookup.url', ''));
        $token = trim((string) config('vehicle_lookup.token', ''));
        if ($token === '') {
            return [
                'status' => false,
                'message' => 'Falta VEHICLE_PLATE_LOOKUP_TOKEN en .env (Bearer de json.pe).',
            ];
        }

        $bodyKey = trim((string) config('vehicle_lookup.body_plate_key', 'placa'));
        if ($bodyKey === '') {
            $bodyKey = 'placa';
        }

        try {
            $response = Http::timeout($timeout > 0 ? $timeout : 15)
                ->withToken($token)
                ->acceptJson()
                ->post($url, [$bodyKey => strtolower($plate)]);
        } catch (\Throwable $e) {
            return [
                'status' => false,
                'message' => 'No se pudo conectar al proveedor json.pe.',
            ];
        }

        $payload = (array) $response->json();
        $apiMessage = trim((string) ($payload['message'] ?? ''));

        if (!$response->successful()) {
            return [
                'status' => false,
                'message' => $apiMessage !== '' ? $apiMessage : 'El proveedor no pudo resolver la placa.',
            ];
        }

        if (!(bool) ($payload['success'] ?? false)) {
            return [
                'status' => false,
                'message' => $apiMessage !== '' ? $apiMessage : 'No se encontro informacion para esa placa.',
            ];
        }

        $data = (array) ($payload['data'] ?? []);
        $brand = trim((string) ($data['marca'] ?? ''));
        $model = trim((string) ($data['modelo'] ?? ''));
        $color = trim((string) ($data['color'] ?? ''));
        $vin = trim((string) ($data['vin'] ?? ''));
        $engineNumber = trim((string) ($data['motor'] ?? ''));
        $serie = trim((string) ($data['serie'] ?? ''));
        $serialNumber = $serie;
        $chassisNumber = ($serie !== '' && $serie !== $vin) ? $serie : '';

        if ($brand === '' && $model === '' && $color === '' && $vin === '' && $engineNumber === '' && $serie === '') {
            return [
                'status' => false,
                'message' => 'No se encontraron datos vehiculares para esa placa.',
            ];
        }

        return [
            'status' => true,
            'message' => $apiMessage !== '' ? $apiMessage : 'Datos de placa encontrados.',
            'plate' => strtoupper((string) ($data['placa'] ?? $plate)),
            'brand' => $brand,
            'model' => $model,
            'year' => '',
            'color' => $color,
            'vin' => $vin,
            'engine_number' => $engineNumber,
            'chassis_number' => $chassisNumber,
            'serial_number' => $serialNumber,
            'raw' => $data,
        ];
    }

    private function lookupSoatJsonPe(string $plate, int $timeout): array
    {
        $default = [
            'soat_vencimiento' => null,
            'soat_status' => 'no_encontrado',
            'soat_message' => 'No se encontro SOAT para esta placa.',
            'soat_company' => null,
            'soat_policy_number' => null,
        ];

        if (!filter_var((string) config('vehicle_lookup.soat_enabled', true), FILTER_VALIDATE_BOOLEAN)) {
            $default['soat_status'] = 'deshabilitado';
            $default['soat_message'] = '';

            return $default;
        }

        $token = trim((string) config('vehicle_lookup.token', ''));
        $soatUrl = trim((string) config('vehicle_lookup.soat_url', 'https://api.json.pe/api/soat'));
        if ($token === '' || $soatUrl === '') {
            $default['soat_status'] = 'no_consultado';
            $default['soat_message'] = '';

            return $default;
        }

        $bodyKey = trim((string) config('vehicle_lookup.body_plate_key', 'placa'));
        if ($bodyKey === '') {
            $bodyKey = 'placa';
        }

        try {
            $response = Http::timeout($timeout > 0 ? $timeout : 15)
                ->withToken($token)
                ->acceptJson()
                ->post($soatUrl, [$bodyKey => strtolower($plate)]);
        } catch (\Throwable $e) {
            return [
                'soat_vencimiento' => null,
                'soat_status' => 'error',
                'soat_message' => 'No se pudo consultar SOAT; datos del vehiculo cargados.',
                'soat_company' => null,
                'soat_policy_number' => null,
            ];
        }

        $payload = (array) $response->json();
        if (!$response->successful() || !(bool) ($payload['success'] ?? false)) {
            return [
                'soat_vencimiento' => null,
                'soat_status' => 'no_encontrado',
                'soat_message' => 'No se encontro SOAT para esta placa.',
                'soat_company' => null,
                'soat_policy_number' => null,
            ];
        }

        $data = (array) ($payload['data'] ?? []);
        $endDate = $this->parseFlexibleDate((string) ($data['fecha_fin'] ?? ''));
        $estado = strtoupper(trim((string) ($data['estado'] ?? '')));
        $company = trim((string) ($data['nombre_compania'] ?? ''));
        $policy = trim((string) ($data['numero_poliza'] ?? ''));

        if ($endDate === null && $estado === '' && $company === '' && $policy === '') {
            return $default;
        }

        $isExpired = $estado === 'VENCIDO';
        if (!$isExpired && $endDate !== null) {
            $isExpired = Carbon::parse($endDate)->endOfDay()->isPast();
        }

        $formattedEnd = $endDate ? Carbon::parse($endDate)->format('d/m/Y') : null;
        if ($isExpired) {
            $message = $formattedEnd
                ? "SOAT vencido (vencio el {$formattedEnd})."
                : 'SOAT vencido.';
            $status = 'vencido';
        } elseif ($endDate !== null) {
            $message = "SOAT vigente hasta el {$formattedEnd}.";
            $status = 'vigente';
        } else {
            $message = 'SOAT encontrado sin fecha de vencimiento.';
            $status = 'sin_fecha';
        }

        if ($company !== '') {
            $message .= " Compania: {$company}.";
        }

        return [
            'soat_vencimiento' => $endDate,
            'soat_status' => $status,
            'soat_message' => $message,
            'soat_company' => $company !== '' ? $company : null,
            'soat_policy_number' => $policy !== '' ? $policy : null,
        ];
    }

    private function lookupVehicleLegacy(string $plate, string $url, int $timeout): array
    {
        $token = trim((string) config('vehicle_lookup.token', ''));
        $plateKey = trim((string) config('vehicle_lookup.query_plate_key', 'numero'));
        $tokenKey = trim((string) config('vehicle_lookup.query_token_key', 'token'));

        $query = [$plateKey !== '' ? $plateKey : 'numero' => $plate];
        if ($token !== '') {
            $query[$tokenKey !== '' ? $tokenKey : 'token'] = $token;
        }

        try {
            $response = Http::timeout($timeout > 0 ? $timeout : 15)->get($url, $query);
        } catch (\Throwable $e) {
            return [
                'status' => false,
                'message' => 'No se pudo conectar al proveedor de consulta vehicular.',
            ];
        }

        if (!$response->successful()) {
            return [
                'status' => false,
                'message' => 'El proveedor no pudo resolver la placa.',
            ];
        }

        $payload = (array) $response->json();
        $source = (array) ($payload['resultado'] ?? $payload['result'] ?? $payload['data'] ?? $payload['vehicle'] ?? $payload);

        $brand = $this->plateLookupValue($source, ['marca', 'brand', 'vehicle.brand', 'data.marca']);
        $model = $this->plateLookupValue($source, ['modelo', 'model', 'vehicle.model', 'data.modelo']);
        $year = $this->plateLookupValue($source, ['anio', 'año', 'year', 'fabricacion', 'vehicle.year', 'data.anio']);
        $color = $this->plateLookupValue($source, ['color', 'vehicle.color', 'data.color']);
        $vin = $this->plateLookupValue($source, ['vin', 'nro_vin', 'numero_vin', 'vehicle.vin', 'data.vin']);
        $engineNumber = $this->plateLookupValue($source, ['motor', 'numero_motor', 'nro_motor', 'engine_number', 'vehicle.engine_number', 'data.motor']);
        $chassisNumber = $this->plateLookupValue($source, ['chasis', 'chassis', 'numero_chasis', 'nro_chasis', 'vehicle.chassis_number', 'data.chasis']);
        $serialNumber = $this->plateLookupValue($source, ['serie', 'serial', 'serial_number', 'vehicle.serial_number', 'data.serie']);

        if (
            $brand === ''
            && $model === ''
            && $year === ''
            && $color === ''
            && $vin === ''
            && $engineNumber === ''
            && $chassisNumber === ''
            && $serialNumber === ''
        ) {
            return [
                'status' => false,
                'message' => 'No se encontraron datos vehiculares para esa placa.',
            ];
        }

        return [
            'status' => true,
            'message' => 'Datos de placa encontrados.',
            'plate' => $plate,
            'brand' => $brand,
            'model' => $model,
            'year' => $year,
            'color' => $color,
            'vin' => $vin,
            'engine_number' => $engineNumber,
            'chassis_number' => $chassisNumber,
            'serial_number' => $serialNumber,
            'raw' => $source,
        ];
    }

    private function parseFlexibleDate(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        foreach (['d/m/Y', 'j/n/Y', 'Y-m-d', 'd-m-Y'] as $format) {
            try {
                return Carbon::createFromFormat($format, $value)->format('Y-m-d');
            } catch (\Throwable $e) {
            }
        }

        try {
            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function plateLookupValue(array $source, array $keys): string
    {
        foreach ($keys as $key) {
            if (!str_contains($key, '.')) {
                $value = trim((string) ($source[$key] ?? ''));
                if ($value !== '') {
                    return $value;
                }
                continue;
            }

            $segments = explode('.', $key);
            $cursor = $source;
            foreach ($segments as $segment) {
                if (!is_array($cursor) || !array_key_exists($segment, $cursor)) {
                    $cursor = null;
                    break;
                }
                $cursor = $cursor[$segment];
            }

            $value = trim((string) ($cursor ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }
}
