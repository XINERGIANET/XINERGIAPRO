<?php
require 'vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$paymentMethodsRaw = \App\Models\PaymentMethod::where('status', true)->orderBy('order_num')->get();

$paymentMethodOptions = $paymentMethodsRaw->map(function ($method) {
    $desc = strtolower($method->description ?? '');
    $kind = 'other';
    if (str_contains($desc, 'tarjeta') || str_contains($desc, 'niubiz') || str_contains($desc, 'visa') || str_contains($desc, 'mastercard') || str_contains($desc, 'pago link')) {
        $kind = 'card';
    } elseif (str_contains($desc, 'yape') || str_contains($desc, 'plin') || str_contains($desc, 'billetera')) {
        $kind = 'wallet';
    } elseif (str_contains($desc, 'transferencia') || str_contains($desc, 'deposito') || str_contains($desc, 'cuenta')) {
        $kind = 'transfer';
    } elseif (str_contains($desc, 'efectivo') || str_contains($desc, 'cash')) {
        $kind = 'cash';
    }
    
    return [
        'id' => (int) $method->id,
        'description' => (string) ($method->description ?? ''),
        'kind' => $kind,
    ];
})->values();

$paymentGatewayOptionsByMethod = [];
foreach ($paymentMethodOptions as $method) {
    if ($method['kind'] === 'card') {
        $paymentGatewayOptionsByMethod[$method['id']] = \App\Models\PaymentGateways::query()
            ->where('status', true)
            ->orderBy('id')
            ->get();
    }
}

echo "\n--- OUTPUT START ---\n";
echo json_encode($paymentGatewayOptionsByMethod);
echo "\n--- OUTPUT END ---\n";
