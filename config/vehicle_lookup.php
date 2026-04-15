<?php

return [
    'enabled' => env('VEHICLE_PLATE_LOOKUP_ENABLED', false),
    'driver' => env('VEHICLE_PLATE_LOOKUP_DRIVER', 'json_pe'),
    'url' => env('VEHICLE_PLATE_LOOKUP_URL', 'https://api.json.pe/api/placa'),
    'token' => env('VEHICLE_PLATE_LOOKUP_TOKEN', ''),
    'timeout' => (int) env('VEHICLE_PLATE_LOOKUP_TIMEOUT', 15),
    'body_plate_key' => env('VEHICLE_PLATE_LOOKUP_BODY_PLATE_KEY', 'placa'),
    'query_plate_key' => env('VEHICLE_PLATE_LOOKUP_QUERY_PLATE_KEY', 'numero'),
    'query_token_key' => env('VEHICLE_PLATE_LOOKUP_QUERY_TOKEN_KEY', 'token'),
];
