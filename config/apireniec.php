<?php

return [
    // Endpoint base para consulta DNI (PeruDevs).
    'url' => env('APIRENIEC_URL', 'https://api.perudevs.com/api/v1/dni/simple'),
    // Endpoint base para consulta RUC (PeruDevs).
    'ruc_url' => env('APIRENIEC_URL_RUC', 'https://api.perudevs.com/api/v1/ruc'),
    // KEY de PeruDevs.
    'key' => env('APIRENIEC_KEY', 'cGVydWRldnMucHJvZHVjdGlvbi5maXRjb2RlcnMuNjlhMGJlN2YwNGEyNjc2MDk2ZjkzZDYz'),
];
