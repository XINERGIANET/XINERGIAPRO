<?php

namespace App\Http\Requests\Workshop;

use App\Models\Product;
use App\Models\ProductBranch;
use Illuminate\Foundation\Http\FormRequest;

class StoreWorkshopLineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'line_type' => ['required', 'in:SERVICE,LABOR,PART,OTHER'],
            'service_id' => ['nullable', 'integer', 'exists:workshop_services,id'],
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'description' => ['required', 'string', 'max:255'],
            'qty' => ['required', 'numeric', 'min:0.000001'],
            'unit_price' => ['required', 'numeric', 'min:0'],
            'tax_rate_id' => ['nullable', 'integer', 'exists:tax_rates,id'],
            'technician_person_id' => ['nullable', 'integer', 'exists:people,id'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $lineType = (string) $this->input('line_type');
            if ($lineType !== 'PART') {
                return;
            }

            $productId = (int) $this->input('product_id');
            if ($productId <= 0) {
                $validator->errors()->add('product_id', 'Debe seleccionar un repuesto para linea PART.');
                return;
            }

            $product = Product::query()->find($productId);
            if (!$product || strtoupper((string) $product->type) !== 'PRODUCT') {
                $validator->errors()->add('product_id', 'El item seleccionado no es un repuesto valido.');
                return;
            }

            $branchId = (int) session('branch_id');
            if ($branchId > 0) {
                $exists = ProductBranch::query()
                    ->where('branch_id', $branchId)
                    ->where('product_id', $productId)
                    ->exists();
                if (!$exists) {
                    $validator->errors()->add('product_id', 'El repuesto no esta configurado en la sucursal actual.');
                }
            }
        });
    }
}

