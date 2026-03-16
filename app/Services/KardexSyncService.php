<?php

namespace App\Services;

use App\Models\Kardex;
use App\Models\Movement;
use App\Models\ProductBranch;

class KardexSyncService
{
    public function syncMovement(Movement $movement): void
    {
        $movement->loadMissing([
            'documentType',
            'movementType',
            'salesMovement.details.unit',
            'purchaseMovement.details.unit',
            'warehouseMovement.details.unit',
        ]);

        $this->deleteMovement($movement->id);

        if ($movement->salesMovement && $movement->status === 'A') {
            $currency = (string) ($movement->salesMovement->currency ?? 'PEN');
            $exchangeRate = (float) ($movement->salesMovement->exchange_rate ?? 1);

            foreach ($movement->salesMovement->details->sortBy('id') as $detail) {
                $quantity = (float) $detail->quantity;
                if ($quantity <= 0 || !$detail->product_id || !$detail->unit_id) {
                    continue;
                }

                $unitPrice = $quantity > 0 ? round(((float) $detail->amount) / $quantity, 6) : 0;
                $this->createEntry($movement, [
                    'detalle_id' => $detail->id,
                    'producto_id' => (int) $detail->product_id,
                    'unidad_id' => (int) $detail->unit_id,
                    'cantidad' => -$quantity,
                    'preciounitario' => $unitPrice,
                    'moneda' => $currency,
                    'tipocambio' => $exchangeRate,
                    'total' => (float) $detail->amount,
                ]);
            }

            return;
        }

        if ($movement->purchaseMovement && $movement->purchaseMovement->affects_kardex === 'S') {
            $currency = (string) ($movement->purchaseMovement->currency ?? 'PEN');
            $exchangeRate = (float) ($movement->purchaseMovement->exchange_rate ?? 1);

            foreach ($movement->purchaseMovement->details->sortBy('id') as $detail) {
                $quantity = (float) $detail->quantity;
                if ($quantity <= 0 || !$detail->product_id || !$detail->unit_id) {
                    continue;
                }

                $unitPrice = (float) $detail->amount;
                $this->createEntry($movement, [
                    'detalle_id' => $detail->id,
                    'producto_id' => (int) $detail->product_id,
                    'unidad_id' => (int) $detail->unit_id,
                    'cantidad' => $quantity,
                    'preciounitario' => $unitPrice,
                    'moneda' => $currency,
                    'tipocambio' => $exchangeRate,
                    'total' => round($quantity * $unitPrice, 6),
                ]);
            }

            return;
        }

        if ($movement->warehouseMovement) {
            $docName = strtolower((string) ($movement->documentType?->name ?? ''));
            $isEntry = str_starts_with((string) $movement->number, 'E-')
                || str_contains($docName, 'entrada')
                || str_contains($docName, 'entry');

            foreach ($movement->warehouseMovement->details->sortBy('id') as $detail) {
                $quantity = (float) $detail->quantity;
                if ($quantity <= 0 || !$detail->product_id || !$detail->unit_id) {
                    continue;
                }

                $productBranch = ProductBranch::query()
                    ->where('branch_id', (int) $movement->branch_id)
                    ->where('product_id', (int) $detail->product_id)
                    ->first();

                $unitPrice = (float) ($productBranch?->avg_cost ?? $productBranch?->price ?? $productBranch?->purchase_price ?? 0);
                $signedQuantity = $isEntry ? $quantity : -$quantity;

                $this->createEntry($movement, [
                    'detalle_id' => $detail->id,
                    'producto_id' => (int) $detail->product_id,
                    'unidad_id' => (int) $detail->unit_id,
                    'cantidad' => $signedQuantity,
                    'preciounitario' => $unitPrice,
                    'moneda' => 'PEN',
                    'tipocambio' => 1,
                    'total' => round($quantity * $unitPrice, 6),
                ]);
            }
        }
    }

    public function deleteMovement(int $movementId): void
    {
        Kardex::query()->where('movimiento_id', $movementId)->delete();
    }

    private function createEntry(Movement $movement, array $payload): void
    {
        $lastStock = (float) (Kardex::query()
            ->where('producto_id', $payload['producto_id'])
            ->where('sucursal_id', (int) $movement->branch_id)
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->value('stockactual') ?? 0);

        $currentStock = round($lastStock + (float) $payload['cantidad'], 6);

        Kardex::query()->create([
            'detalle_id' => $payload['detalle_id'] ?? null,
            'producto_id' => $payload['producto_id'],
            'unidad_id' => $payload['unidad_id'],
            'cantidad' => $payload['cantidad'],
            'preciounitario' => $payload['preciounitario'] ?? 0,
            'moneda' => $payload['moneda'] ?? 'PEN',
            'tipocambio' => $payload['tipocambio'] ?? 1,
            'total' => $payload['total'] ?? 0,
            'fecha' => $movement->moved_at ?? now(),
            'situacion' => 'E',
            'usuario_id' => $movement->user_id,
            'usuario' => $movement->user_name ?: 'Sistema',
            'movimiento_id' => $movement->id,
            'tipomovimiento_id' => $movement->movement_type_id,
            'tipodocumento_id' => $movement->document_type_id,
            'sucursal_id' => $movement->branch_id,
            'stockanterior' => $lastStock,
            'stockactual' => $currentStock,
        ]);
    }
}
