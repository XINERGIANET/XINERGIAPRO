<?php

namespace App\Http\Requests\Workshop;

use Illuminate\Foundation\Http\FormRequest;

class StoreExternalQuotationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $items = $this->input('items', []);
        if (!is_array($items)) {
            $this->merge(['items' => []]);

            return;
        }

        $filtered = [];
        foreach ($items as $row) {
            if (!is_array($row)) {
                continue;
            }
            $desc = trim((string) ($row['description'] ?? ''));
            if ($desc === '') {
                continue;
            }
            $filtered[] = $row;
        }

        $this->merge(['items' => array_values($filtered)]);
    }

    public function rules(): array
    {
        return [
            'client_person_id' => ['required', 'integer', 'exists:people,id'],
            'vehicle_id' => ['nullable', 'integer', 'exists:vehicles,id'],
            'quotation_delivery_time' => ['nullable', 'string', 'max:255'],
            'quotation_offer_validity' => ['nullable', 'string', 'max:255'],
            'quotation_service_warranty' => ['nullable', 'string', 'max:255'],
            'quotation_delivery_place' => ['nullable', 'string', 'max:500'],
            'quotation_prices_note' => ['nullable', 'string', 'max:255'],
            'quotation_payment_condition' => ['nullable', 'string', 'max:500'],
            'quotation_bank_account_bcp' => ['nullable', 'string', 'max:64'],
            'quotation_bank_cci' => ['nullable', 'string', 'max:64'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.line_type' => ['required', 'in:PART,LABOR,SERVICE'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.qty' => ['required', 'numeric', 'min:0.000001'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.product_id' => ['nullable', 'integer', 'exists:products,id'],
            'items.*.service_id' => ['nullable', 'integer', 'exists:workshop_services,id'],
            'items.*.tax_rate_id' => ['nullable', 'integer', 'exists:tax_rates,id'],
            'items.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
