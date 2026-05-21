<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\BranchElectronicBillingConfig;
use App\Models\Movement;
use App\Models\TaxRate;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class ApisunatService
{
    /** Catálogo 53: descuento global que afecta la base imponible del IGV (requerido con anticipos). */
    private const SUNAT_ADVANCE_GLOBAL_DISCOUNT_CODE = '02';

    /** Catálogo 51 (cbc:ProfileID): venta interna – anticipos. */
    private const SUNAT_OPERATION_ADVANCE_CODE = '0104';

    /** Catálogo 51 (cbc:ProfileID): venta interna. */
    private const SUNAT_OPERATION_STANDARD_CODE = '0101';

    public function isEligibleDocument(Movement $sale): bool
    {
        $docName = mb_strtolower(trim((string) ($sale->documentType?->name ?? '')), 'UTF-8');

        return str_contains($docName, 'boleta') || str_contains($docName, 'factura');
    }

    public function resolveConfigForBranch(?Branch $branch): ?BranchElectronicBillingConfig
    {
        if (! $branch) {
            return null;
        }

        $branch->loadMissing('electronicBillingConfig');
        $config = $branch->electronicBillingConfig;

        if (! $config) {
            return null;
        }

        return $config;
    }

    public function isConfiguredForBranch(?Branch $branch): bool
    {
        $config = $this->resolveConfigForBranch($branch);

        if (! $config || ! $config->enabled) {
            return false;
        }

        return $this->resolveApiUrl($config) !== ''
            && trim((string) $config->persona_id) !== ''
            && trim((string) $config->persona_token) !== '';
    }

    public function emitSale(Movement $sale): array
    {
        $sale->loadMissing([
            'documentType',
            'person',
            'branch',
            'salesMovement.details.taxRate',
            'orderMovement.details.taxRate',
        ]);

        if (! $this->isEligibleDocument($sale)) {
            return [
                'status' => 'SKIPPED',
                'message' => 'El tipo de documento no requiere envío electrónico.',
            ];
        }

        if ($sale->electronic_invoice_external_id) {
            return [
                'status' => 'SENT',
                'message' => 'El comprobante electrónico ya fue emitido.',
                'data' => $this->movementElectronicData($sale),
            ];
        }

        $branch = $sale->branch;
        $config = $this->resolveConfigForBranch($branch);

        if (! $config || ! $config->enabled) {
            throw new \RuntimeException('La sucursal no tiene configurada la facturación electrónica.');
        }

        $catalog = $this->resolveDocumentCatalog($sale, $config);
        $customerDocument = $this->resolveCustomerDocument($sale, $catalog['type']);
        $customerDocType = $this->resolveCustomerDocumentType($customerDocument, $catalog['type']);
        $totals = $this->resolveMovementTotals($sale);
        $apiUrl = $this->resolveApiUrl($config);
        $number = $this->fetchSuggestedCorrelativeNumber($config, $catalog, $apiUrl);

        $this->assertFinalSaleAdvancesReadyForSunat($sale);

        return $this->sendSaleBillToApisunat(
            $sale,
            $config,
            $catalog,
            $customerDocument,
            $customerDocType,
            $totals,
            $number,
            true,
            'Comprobante enviado correctamente a Apisunat.'
        );
    }

    /**
     * Reenvía a SUNAT. Si el correlativo local ya existe en Apisunat, usa el siguiente disponible.
     */
    public function reemitSale(Movement $sale): array
    {
        $sale->loadMissing([
            'documentType',
            'person',
            'branch',
            'salesMovement.details.taxRate',
            'orderMovement.details.taxRate',
        ]);

        if (! $this->isEligibleDocument($sale)) {
            return [
                'status' => 'SKIPPED',
                'message' => 'El tipo de documento no requiere envío electrónico.',
            ];
        }

        $branch = $sale->branch;
        $config = $this->resolveConfigForBranch($branch);

        if (! $config || ! $config->enabled) {
            throw new \RuntimeException('La sucursal no tiene configurada la facturación electrónica.');
        }

        $catalog = $this->resolveDocumentCatalog($sale, $config);
        $this->assertFinalSaleAdvancesReadyForSunat($sale);
        $billingCorrelative = $this->resolveBillingCorrelativeForResend($sale, $catalog['serie']);
        $catalog['serie'] = $billingCorrelative['serie'];
        $apiUrl = $this->resolveApiUrl($config);

        $customerDocument = $this->resolveCustomerDocument($sale, $catalog['type']);
        $customerDocType = $this->resolveCustomerDocumentType($customerDocument, $catalog['type']);
        $totals = $this->resolveMovementTotals($sale);

        $electronicStatus = strtoupper(trim((string) ($sale->electronic_invoice_status ?? '')));
        $initialNumber = $billingCorrelative['number'];
        if ($electronicStatus === 'ERROR' || $electronicStatus === '') {
            $initialNumber = $this->fetchSuggestedCorrelativeNumber($config, $catalog, $apiUrl);
        }

        return $this->sendSaleBillToApisunat(
            $sale,
            $config,
            $catalog,
            $customerDocument,
            $customerDocType,
            $totals,
            $initialNumber,
            true,
            'Comprobante reenviado correctamente a Apisunat.'
        );
    }

    public function persistEmittedElectronicData(Movement $sale, array $result): void
    {
        if (($result['status'] ?? '') !== 'SENT' || empty($result['data'])) {
            return;
        }

        $data = $result['data'];
        $sale->loadMissing('salesMovement');
        $isAdvanceInvoice = (bool) ($sale->salesMovement?->is_advance ?? false);
        $responsePayload = $data['response'] ?? [];
        if (! is_array($responsePayload)) {
            $responsePayload = [];
        }
        $responsePayload['sunat_profile_id'] = $isAdvanceInvoice
            ? self::SUNAT_OPERATION_ADVANCE_CODE
            : self::SUNAT_OPERATION_STANDARD_CODE;
        $responsePayload['sunat_operation_list_id'] = $isAdvanceInvoice
            ? self::SUNAT_OPERATION_ADVANCE_CODE
            : self::SUNAT_OPERATION_STANDARD_CODE;
        $responsePayload['sunat_advance_ready'] = $isAdvanceInvoice;
        $responsePayload['sunat_linkable_advance'] = $isAdvanceInvoice;

        $sale->update([
            'electronic_invoice_provider' => $data['provider'] ?? 'apisunat',
            'electronic_invoice_status' => 'SENT',
            'electronic_invoice_external_id' => $data['external_id'] ?? null,
            'electronic_invoice_series' => $data['series'] ?? null,
            'electronic_invoice_number' => $data['correlative'] ?? null,
            'electronic_invoice_file_name' => $data['file_name'] ?? null,
            'electronic_invoice_pdf_ticket_url' => $data['pdf_ticket_80mm'] ?? null,
            'electronic_invoice_pdf_a4_url' => $data['pdf_a4'] ?? null,
            'electronic_invoice_xml_url' => $data['xml_url'] ?? null,
            'electronic_invoice_cdr_url' => $data['cdr_url'] ?? null,
            'electronic_invoice_response' => $responsePayload,
        ]);

        if ($sale->salesMovement) {
            $sale->salesMovement->update([
                'billing_status' => 'INVOICED',
                'billing_number' => $data['correlative'] ?? $sale->salesMovement->billing_number,
                'series' => $data['series'] ?? $sale->salesMovement->series,
            ]);
        }
    }

    /**
     * @return array{serie: string, number: string}
     */
    private function resolveBillingCorrelativeForResend(Movement $sale, string $defaultSerie): array
    {
        $serie = trim((string) ($sale->salesMovement?->series ?? $sale->electronic_invoice_series ?? ''));
        if ($serie === '' || $serie === '001') {
            $serie = trim($defaultSerie);
        }

        $billingRaw = trim((string) ($sale->salesMovement?->billing_number ?? $sale->electronic_invoice_number ?? ''));
        if ($billingRaw === '' && $sale->isSalesInvoice()) {
            $billingRaw = trim((string) $sale->salesDocumentNumber());
        }

        $digits = preg_replace('/\D+/', '', $billingRaw) ?: '';
        if ($digits === '') {
            throw new \RuntimeException('La venta no tiene serie/correlativo registrado para reenviar el comprobante electrónico.');
        }

        return [
            'serie' => $serie,
            'number' => str_pad($digits, 8, '0', STR_PAD_LEFT),
        ];
    }

    private function sendSaleBillToApisunat(
        Movement $sale,
        BranchElectronicBillingConfig $config,
        array $catalog,
        string $customerDocument,
        string $customerDocType,
        array $totals,
        string $initialNumber,
        bool $retryOnDuplicate,
        string $successMessage
    ): array {
        $branch = $sale->branch;
        $apiUrl = $this->resolveApiUrl($config);
        $originalNumber = $this->normalizeCorrelativeNumber($initialNumber);
        $number = $originalNumber;
        $maxAttempts = $retryOnDuplicate ? 6 : 1;
        $lastError = '';

        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            $fileName = trim((string) ($branch?->ruc ?? '0')).'-'.$catalog['type'].'-'.$catalog['serie'].'-'.$number;
            $documentBody = $this->buildDocumentBody($sale, $catalog, $customerDocument, $customerDocType, $totals, $number);
            $this->validateDocumentBodyForSunat($documentBody);

            $sendResp = Http::timeout(35)->post($apiUrl.'/personas/v1/sendBill', [
                'personaId' => (string) $config->persona_id,
                'personaToken' => (string) $config->persona_token,
                'fileName' => $fileName,
                'documentBody' => $documentBody,
            ]);

            if (! $sendResp->failed()) {
                $result = $sendResp->object();
                $documentId = trim((string) data_get($result, 'documentId', ''));
                if ($documentId === '') {
                    throw new \RuntimeException('Apisunat no devolvió documentId.');
                }

                $extraDocumentData = $this->getDocumentById($documentId, $branch);
                $urls = $this->extractDocumentUrls($extraDocumentData);
                $previousCorrelative = $originalNumber !== $number ? $originalNumber : null;

                if ($previousCorrelative !== null) {
                    $successMessage .= ' Se actualizó el correlativo interno de '
                        .$catalog['serie'].'-'.$previousCorrelative.' a '.$catalog['serie'].'-'.$number.'.';
                }

                return $this->buildEmitSuccessPayload(
                    $catalog,
                    $number,
                    $fileName,
                    $documentId,
                    $sendResp,
                    $extraDocumentData,
                    $urls,
                    $apiUrl,
                    $successMessage,
                    $previousCorrelative
                );
            }

            $lastError = (string) (data_get($sendResp->object(), 'error.message')
                ?: data_get($sendResp->json(), 'error.message')
                ?: $sendResp->body());

            if (! $retryOnDuplicate || ! $this->isDuplicateCorrelativeError($lastError)) {
                break;
            }

            $nextNumber = $this->fetchSuggestedCorrelativeNumber($config, $catalog, $apiUrl);
            if ($nextNumber === $number) {
                $nextNumber = $this->bumpCorrelativeNumber($number);
            }
            $number = $nextNumber;
        }

        $actionLabel = str_contains(mb_strtolower($successMessage, 'UTF-8'), 'reenvi')
            ? 'reenviando'
            : 'enviando';

        throw new \RuntimeException('Error '.$actionLabel.' comprobante a Apisunat: '.$lastError);
    }

    private function fetchSuggestedCorrelativeNumber(
        BranchElectronicBillingConfig $config,
        array $catalog,
        string $apiUrl
    ): string {
        $correlativeResp = Http::timeout(20)->post($apiUrl.'/personas/lastDocument', [
            'personaId' => (string) $config->persona_id,
            'personaToken' => (string) $config->persona_token,
            'type' => $catalog['type'],
            'serie' => $catalog['serie'],
        ]);

        if ($correlativeResp->failed()) {
            throw new \RuntimeException('Error consultando correlativo en Apisunat: '.$correlativeResp->body());
        }

        $suggestedNumber = trim((string) data_get($correlativeResp->object(), 'suggestedNumber', ''));
        if ($suggestedNumber === '' || ! ctype_digit($suggestedNumber)) {
            throw new \RuntimeException('Apisunat devolvió un correlativo inválido.');
        }

        return $this->normalizeCorrelativeNumber($suggestedNumber);
    }

    private function normalizeCorrelativeNumber(string $number): string
    {
        $digits = preg_replace('/\D+/', '', $number) ?: '';

        return str_pad($digits !== '' ? $digits : '1', 8, '0', STR_PAD_LEFT);
    }

    private function bumpCorrelativeNumber(string $number): string
    {
        $digits = (int) (preg_replace('/\D+/', '', $number) ?: '0');

        return str_pad((string) ($digits + 1), 8, '0', STR_PAD_LEFT);
    }

    private function isDuplicateCorrelativeError(string $message): bool
    {
        $normalized = mb_strtolower($message, 'UTF-8');

        return str_contains($normalized, 'numeración repetida')
            || str_contains($normalized, 'numeracion repetida')
            || str_contains($normalized, 'número repetido')
            || str_contains($normalized, 'numero repetido')
            || str_contains($normalized, 'ya existe')
            || str_contains($normalized, 'repetid');
    }

    private function buildEmitSuccessPayload(
        array $catalog,
        string $number,
        string $fileName,
        string $documentId,
        \Illuminate\Http\Client\Response $sendResp,
        array $extraDocumentData,
        array $urls,
        string $apiUrl,
        string $message = 'Comprobante enviado correctamente a Apisunat.',
        ?string $previousCorrelative = null
    ): array {
        $data = [
            'provider' => 'apisunat',
            'external_id' => $documentId,
            'series' => $catalog['serie'],
            'correlative' => $number,
            'full_number' => $catalog['serie'].'-'.$number,
            'file_name' => $fileName.'.pdf',
            'pdf_ticket_80mm' => $apiUrl.'/documents/'.$documentId.'/getPDF/ticket80mm/'.$fileName.'.pdf',
            'pdf_a4' => $apiUrl.'/documents/'.$documentId.'/getPDF/A4/'.$fileName.'.pdf',
            'xml_url' => $urls['xml_url'] ?? null,
            'cdr_url' => $urls['cdr_url'] ?? null,
            'response' => [
                'send' => $sendResp->json(),
                'document' => $extraDocumentData,
            ],
        ];

        if ($previousCorrelative !== null) {
            $data['previous_correlative'] = $previousCorrelative;
            $data['correlative_changed'] = true;
        }

        return [
            'status' => 'SENT',
            'message' => $message,
            'data' => $data,
        ];
    }

    public function consultDocument(?Branch $branch, string $document): array
    {
        $document = trim($document);
        $config = $this->resolveConfigForBranch($branch);

        if (! $config || ! $config->enabled) {
            throw new \RuntimeException('La sucursal no tiene configurada la consulta documental.');
        }

        $apiUrl = $this->resolveApiUrl($config);
        if (strlen($document) === 8) {
            $url = $apiUrl.'/personas/'.trim((string) $config->persona_id).'/getDNI?dni='.$document.'&personaToken='.rawurlencode((string) $config->persona_token);
        } elseif (strlen($document) === 11) {
            $url = $apiUrl.'/personas/'.trim((string) $config->persona_id).'/getRUC?ruc='.$document.'&personaToken='.rawurlencode((string) $config->persona_token);
        } else {
            throw new \RuntimeException('Documento inválido.');
        }

        $response = Http::timeout(20)->get($url);
        if ($response->failed()) {
            throw new \RuntimeException('No se pudo consultar el documento.');
        }

        return (array) ($response->json('data') ?? []);
    }

    public function getDocumentById(string $documentId, ?Branch $branch = null): array
    {
        $apiUrl = $this->resolveApiUrl($this->resolveConfigForBranch($branch));
        $response = Http::timeout(20)->get($apiUrl.'/documents/'.$documentId.'/getById');

        if ($response->failed()) {
            throw new \RuntimeException('No se pudo consultar el comprobante electrónico.');
        }

        return $response->json() ?? [];
    }

    public function extractDocumentUrls(array $payload): array
    {
        return [
            'xml_url' => $this->findUrlByKeyword($payload, ['xml']),
            'cdr_url' => $this->findUrlByKeyword($payload, ['cdr']),
            'pdf_a4_url' => $this->findUrlByKeyword($payload, ['pdf', 'a4']),
            'pdf_ticket_url' => $this->findUrlByKeyword($payload, ['pdf', 'ticket']),
        ];
    }

    private function movementElectronicData(Movement $sale): array
    {
        return [
            'provider' => $sale->electronic_invoice_provider,
            'external_id' => $sale->electronic_invoice_external_id,
            'series' => $sale->electronic_invoice_series,
            'full_number' => $sale->electronic_invoice_number,
            'file_name' => $sale->electronic_invoice_file_name,
            'pdf_ticket_80mm' => $sale->electronic_invoice_pdf_ticket_url,
            'pdf_a4' => $sale->electronic_invoice_pdf_a4_url,
            'xml_url' => $sale->electronic_invoice_xml_url,
            'cdr_url' => $sale->electronic_invoice_cdr_url,
            'response' => $sale->electronic_invoice_response,
        ];
    }

    private function resolveApiUrl(?BranchElectronicBillingConfig $config): string
    {
        $url = trim((string) ($config?->api_url ?: config('apisunat.url')));

        return rtrim($url, '/');
    }

    private function resolveDocumentCatalog(Movement $sale, BranchElectronicBillingConfig $config): array
    {
        $docName = mb_strtolower(trim((string) ($sale->documentType?->name ?? '')), 'UTF-8');

        if (str_contains($docName, 'factura')) {
            return [
                'type' => '01',
                'serie' => trim((string) ($config->series_factura ?: config('apisunat.series.factura', 'F001'))),
            ];
        }

        if (str_contains($docName, 'boleta')) {
            return [
                'type' => '03',
                'serie' => trim((string) ($config->series_boleta ?: config('apisunat.series.boleta', 'B001'))),
            ];
        }

        throw new \RuntimeException('Solo boleta y factura pueden enviarse a Apisunat.');
    }

    private function resolveCustomerDocument(Movement $sale, string $documentTypeCode): string
    {
        $document = preg_replace('/\D+/', '', (string) ($sale->person?->document_number ?? '')) ?: '';

        if ($documentTypeCode === '01') {
            if (strlen($document) !== 11) {
                throw new \RuntimeException('La factura requiere un cliente con RUC válido.');
            }

            return $document;
        }

        return $document !== '' ? $document : '0';
    }

    private function resolveCustomerDocumentType(string $document, string $documentTypeCode): string
    {
        if ($documentTypeCode === '01') {
            return '6';
        }

        if (strlen($document) === 11) {
            return '6';
        }
        if (strlen($document) === 8) {
            return '1';
        }

        return '0';
    }

    private function resolveMovementTotals(Movement $sale): array
    {
        $subtotal = round((float) ($sale->salesMovement?->subtotal ?? $sale->orderMovement?->subtotal ?? 0), 2);
        $tax = round((float) ($sale->salesMovement?->tax ?? $sale->orderMovement?->tax ?? 0), 2);
        $total = round((float) ($sale->salesMovement?->total ?? $sale->orderMovement?->total ?? 0), 2);

        return compact('subtotal', 'tax', 'total');
    }

    private function buildDocumentBody(Movement $sale, array $catalog, string $customerDocument, string $customerDocType, array $totals, string $number): array
    {
        $branch = $sale->branch;
        $customerName = trim((string) ($sale->person_name ?: 'CLIENTES VARIOS'));
        $details = $this->resolveDetailsForSale($sale);
        $defaultTaxPercent = $this->resolveDefaultTaxPercentForBranch($branch);
        $sale->loadMissing('salesMovement');
        $isAdvanceInvoice = (bool) ($sale->salesMovement?->is_advance ?? false);
        $operationTypeCode = $isAdvanceInvoice
            ? self::SUNAT_OPERATION_ADVANCE_CODE
            : self::SUNAT_OPERATION_STANDARD_CODE;

        $documentBody = [
            'cbc:UBLVersionID' => ['_text' => '2.1'],
            'cbc:CustomizationID' => ['_text' => '2.0'],
            'cbc:ProfileID' => $operationTypeCode,
            'cbc:ID' => ['_text' => $catalog['serie'].'-'.$number],
            'cbc:IssueDate' => ['_text' => now()->format('Y-m-d')],
            'cbc:IssueTime' => ['_text' => now()->format('H:i:s')],
            'cbc:InvoiceTypeCode' => $this->sunatInvoiceTypeCodeNode(
                $catalog['type'],
                $this->resolveSunatInvoiceTypeListId($catalog['type'], $isAdvanceInvoice)
            ),
            'cbc:Note' => [],
            'cbc:DocumentCurrencyCode' => ['_text' => 'PEN'],
            'cac:AccountingSupplierParty' => [
                'cac:Party' => [
                    'cac:PartyIdentification' => [
                        'cbc:ID' => [
                            '_attributes' => ['schemeID' => '6'],
                            '_text' => trim((string) ($branch?->ruc ?? '0')),
                        ],
                    ],
                    'cac:PartyLegalEntity' => [
                        'cbc:RegistrationName' => ['_text' => trim((string) ($branch?->legal_name ?? config('app.name')))],
                        'cac:RegistrationAddress' => [
                            'cbc:AddressTypeCode' => ['_text' => '0000'],
                            'cac:AddressLine' => [
                                'cbc:Line' => ['_text' => trim((string) ($branch?->address ?? '-'))],
                            ],
                        ],
                    ],
                ],
            ],
            'cac:AccountingCustomerParty' => [
                'cac:Party' => [
                    'cac:PartyIdentification' => [
                        'cbc:ID' => [
                            '_attributes' => ['schemeID' => $customerDocType],
                            '_text' => $customerDocument,
                        ],
                    ],
                    'cac:PartyLegalEntity' => [
                        'cbc:RegistrationName' => [
                            '_text' => $customerName !== '' ? $customerName : 'CLIENTES VARIOS',
                        ],
                    ],
                ],
            ],
            'cac:InvoiceLine' => [],
        ];

        if ($catalog['type'] === '01') {
            $documentBody['cac:PaymentTerms'] = [[
                'cbc:ID' => ['_text' => 'FormaPago'],
                'cbc:PaymentMeansID' => ['_text' => 'Contado'],
            ]];
        }

        $lineIndex = 1;
        $headerSubtotal = 0.0;
        $headerTax = 0.0;
        $headerTotal = 0.0;
        foreach ($details as $detail) {
            $qty = (float) ($detail->quantity ?? 0);
            $courtesyQty = (float) ($detail->courtesy_quantity ?? 0);
            $billableQty = max(0, $qty - min($qty, $courtesyQty));
            if ($billableQty <= 0) {
                continue;
            }

            $lineTotal = round((float) ($detail->amount ?? 0), 2);
            if ($lineTotal <= 0) {
                continue;
            }

            $taxPercent = $defaultTaxPercent;
            $taxFactor = $taxPercent > 0 ? ($taxPercent / 100) : 0.18;
            $lineSubtotal = round($taxFactor > 0 ? ($lineTotal / (1 + $taxFactor)) : $lineTotal, 2);
            $lineIgv = round($lineTotal - $lineSubtotal, 2);
            $grossUnitPrice = round($lineTotal / $billableQty, 2);
            $unitValue = round($lineSubtotal / $billableQty, 2);

            $description = trim((string) ($detail->description ?? 'Producto'));
            $complements = collect($detail->complements ?? [])
                ->filter(fn ($value) => trim((string) $value) !== '')
                ->map(fn ($value) => trim((string) $value))
                ->values();
            if ($complements->isNotEmpty()) {
                $description .= ' - '.implode(', ', $complements->all());
            }

            $documentBody['cac:InvoiceLine'][] = [
                'cbc:ID' => ['_text' => $lineIndex],
                'cbc:InvoicedQuantity' => [
                    '_attributes' => ['unitCode' => 'NIU'],
                    '_text' => $billableQty,
                ],
                'cbc:LineExtensionAmount' => [
                    '_attributes' => ['currencyID' => 'PEN'],
                    '_text' => $lineSubtotal,
                ],
                'cac:PricingReference' => [
                    'cac:AlternativeConditionPrice' => [
                        'cbc:PriceAmount' => [
                            '_attributes' => ['currencyID' => 'PEN'],
                            '_text' => $grossUnitPrice,
                        ],
                        'cbc:PriceTypeCode' => ['_text' => '01'],
                    ],
                ],
                'cac:TaxTotal' => [
                    'cbc:TaxAmount' => [
                        '_attributes' => ['currencyID' => 'PEN'],
                        '_text' => $lineIgv,
                    ],
                    'cac:TaxSubtotal' => [[
                        'cbc:TaxableAmount' => [
                            '_attributes' => ['currencyID' => 'PEN'],
                            '_text' => $lineSubtotal,
                        ],
                        'cbc:TaxAmount' => [
                            '_attributes' => ['currencyID' => 'PEN'],
                            '_text' => $lineIgv,
                        ],
                        'cac:TaxCategory' => [
                            'cbc:Percent' => ['_text' => round($taxFactor * 100, 2)],
                            'cbc:TaxExemptionReasonCode' => ['_text' => '10'],
                            'cac:TaxScheme' => [
                                'cbc:ID' => ['_text' => '1000'],
                                'cbc:Name' => ['_text' => 'IGV'],
                                'cbc:TaxTypeCode' => ['_text' => 'VAT'],
                            ],
                        ],
                    ]],
                ],
                'cac:Item' => [
                    'cbc:Description' => ['_text' => $description],
                ],
                'cac:Price' => [
                    'cbc:PriceAmount' => [
                        '_attributes' => ['currencyID' => 'PEN'],
                        '_text' => $unitValue,
                    ],
                ],
            ];

            $headerSubtotal += $lineSubtotal;
            $headerTax += $lineIgv;
            $headerTotal += $lineTotal;

            $lineIndex++;
        }

        $headerSubtotal = round($headerSubtotal, 2);
        $headerTax = round($headerTax, 2);
        $headerTotal = round($headerTotal, 2);

        if ($headerTotal <= 0) {
            $headerSubtotal = round((float) ($totals['subtotal'] ?? 0), 2);
            $headerTax = round((float) ($totals['tax'] ?? 0), 2);
            $headerTotal = round((float) ($totals['total'] ?? 0), 2);
        }

        $documentBody['cac:TaxTotal'] = [
            'cbc:TaxAmount' => [
                '_attributes' => ['currencyID' => 'PEN'],
                '_text' => $headerTax,
            ],
            'cac:TaxSubtotal' => [
                'cbc:TaxableAmount' => [
                    '_attributes' => ['currencyID' => 'PEN'],
                    '_text' => $headerSubtotal,
                ],
                'cbc:TaxAmount' => [
                    '_attributes' => ['currencyID' => 'PEN'],
                    '_text' => $headerTax,
                ],
                'cac:TaxCategory' => [
                    'cac:TaxScheme' => [
                        'cbc:ID' => ['_text' => '1000'],
                        'cbc:Name' => ['_text' => 'IGV'],
                        'cbc:TaxTypeCode' => ['_text' => 'VAT'],
                    ],
                ],
            ],
        ];

        $payableAmount = $headerTotal;
        $linkedAdvances = $isAdvanceInvoice ? [] : $this->resolveLinkedAdvancePayments($sale);

        if ($linkedAdvances !== []) {
            $supplierRuc = trim((string) ($branch?->ruc ?? '0'));
            $advanceBlocks = $this->buildSunatAdvanceBlocks($linkedAdvances, $headerSubtotal, $supplierRuc);

            $documentBody['cac:AdditionalDocumentReference'] = $advanceBlocks['references'];
            $documentBody['cac:AllowanceCharge'] = $advanceBlocks['allowance_charges'];
            $documentBody['cac:PrepaidPayment'] = $advanceBlocks['prepaid_payments'];

            $prepaidValorVentaTotal = round((float) $advanceBlocks['prepaid_valor_venta_total'], 2);
            $prepaidTaxTotal = round((float) $advanceBlocks['prepaid_tax_total'], 2);
            $prepaidInclusiveTotal = round((float) $advanceBlocks['prepaid_inclusive_total'], 2);

            $adjustedSubtotal = round(max(0, $headerSubtotal - $prepaidValorVentaTotal), 2);
            $adjustedTax = round(max(0, $headerTax - $prepaidTaxTotal), 2);
            if ($adjustedTax <= 0 && $adjustedSubtotal > 0 && $headerSubtotal > 0) {
                $taxFactor = $headerTax > 0 ? ($headerTax / $headerSubtotal) : 0.18;
                $adjustedTax = round($adjustedSubtotal * $taxFactor, 2);
            }
            $documentBody['cac:TaxTotal']['cac:TaxSubtotal']['cbc:TaxableAmount']['_text'] = $adjustedSubtotal;
            $documentBody['cac:TaxTotal']['cac:TaxSubtotal']['cbc:TaxAmount']['_text'] = $adjustedTax;
            $documentBody['cac:TaxTotal']['cbc:TaxAmount']['_text'] = $adjustedTax;

            $payableAmount = round(max(0, $headerTotal - $prepaidInclusiveTotal), 2);

            $legalMonetaryTotal = [
                'cbc:LineExtensionAmount' => [
                    '_attributes' => ['currencyID' => 'PEN'],
                    '_text' => $adjustedSubtotal,
                ],
                'cbc:TaxInclusiveAmount' => [
                    '_attributes' => ['currencyID' => 'PEN'],
                    '_text' => $headerTotal,
                ],
                'cbc:PrepaidAmount' => [
                    '_attributes' => ['currencyID' => 'PEN'],
                    '_text' => $prepaidValorVentaTotal,
                ],
                'cbc:PayableAmount' => [
                    '_attributes' => ['currencyID' => 'PEN'],
                    '_text' => $payableAmount,
                ],
            ];

            $documentBody['cac:LegalMonetaryTotal'] = $legalMonetaryTotal;

            return $documentBody;
        }

        $legalMonetaryTotal = [
            'cbc:LineExtensionAmount' => [
                '_attributes' => ['currencyID' => 'PEN'],
                '_text' => $headerSubtotal,
            ],
            'cbc:TaxInclusiveAmount' => [
                '_attributes' => ['currencyID' => 'PEN'],
                '_text' => $headerTotal,
            ],
            'cbc:PayableAmount' => [
                '_attributes' => ['currencyID' => 'PEN'],
                '_text' => $payableAmount,
            ],
        ];

        $documentBody['cac:LegalMonetaryTotal'] = $legalMonetaryTotal;

        return $documentBody;
    }

    public function isAdvanceSunatReady(Movement $advanceMovement): bool
    {
        $advanceMovement->loadMissing('salesMovement');
        if (! (bool) ($advanceMovement->salesMovement?->is_advance ?? false)) {
            return false;
        }

        if (strtoupper(trim((string) ($advanceMovement->electronic_invoice_status ?? ''))) !== 'SENT') {
            return false;
        }

        if (trim((string) ($advanceMovement->electronic_invoice_external_id ?? '')) === '') {
            return false;
        }

        $response = $this->normalizeElectronicInvoiceResponse($advanceMovement->electronic_invoice_response);

        return ! empty($response['sunat_linkable_advance']);
    }

    /**
     * @return array<int, string>
     */
    public function collectAdvanceSunatBlockingIssues(Movement $finalSale): array
    {
        $issues = [];
        foreach ($this->collectLinkedAdvanceMovements($finalSale) as $advanceMovement) {
            if ($this->isAdvanceSunatReady($advanceMovement)) {
                continue;
            }

            $fullNumber = $this->formatAdvanceFullNumber($advanceMovement);
            $status = strtoupper(trim((string) ($advanceMovement->electronic_invoice_status ?? '')));
            $statusLabel = $status !== '' ? $status : 'SIN EMITIR';

            $issues[] = 'El anticipo '.$fullNumber.' no está registrado en SUNAT como comprobante de anticipo enlazable (estado: '.$statusLabel.'). '
                .'Emita un anticipo nuevo con ProfileID 0104 (después de actualizar el sistema) y espere que quede ACEPTADO antes de facturar el saldo.';
        }

        return $issues;
    }

    public function saleHasExplicitAdvanceApplications(Movement $finalSale): bool
    {
        return DB::table('sale_advances')
            ->where('final_movement_id', (int) $finalSale->id)
            ->where('advance_movement_id', '!=', (int) $finalSale->id)
            ->exists();
    }

    /**
     * @param  array<int, array<string, mixed>>  $checkoutAdvances
     */
    public function linkCheckoutAdvancesToFinalMovement(Movement $finalMovement, array $checkoutAdvances): void
    {
        $finalMovementId = (int) $finalMovement->id;
        if ($finalMovementId <= 0 || $checkoutAdvances === []) {
            return;
        }

        $finalMovement->loadMissing('salesMovement');
        if ((bool) ($finalMovement->salesMovement?->is_advance ?? false)) {
            return;
        }

        foreach ($checkoutAdvances as $advanceRow) {
            $advanceMovementId = (int) ($advanceRow['movement_id'] ?? 0);
            if ($advanceMovementId <= 0 || $advanceMovementId === $finalMovementId) {
                continue;
            }

            $advanceMovement = Movement::query()->with('salesMovement')->find($advanceMovementId);
            if (! $advanceMovement) {
                continue;
            }

            if ($this->isConfiguredForBranch($advanceMovement->branch)
                && ! $this->isAdvanceSunatReady($advanceMovement)) {
                continue;
            }

            $appliedAmount = (float) ($advanceRow['amount'] ?? 0);
            if ($appliedAmount <= 0) {
                $appliedAmount = (float) ($advanceMovement->salesMovement?->total ?? 0);
            }
            if ($appliedAmount <= 0) {
                continue;
            }

            $exists = DB::table('sale_advances')
                ->where('final_movement_id', $finalMovementId)
                ->where('advance_movement_id', $advanceMovementId)
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('sale_advances')->insert([
                'final_movement_id' => $finalMovementId,
                'advance_movement_id' => $advanceMovementId,
                'applied_amount' => $appliedAmount,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function assertFinalSaleAdvancesReadyForSunat(Movement $finalSale): void
    {
        $finalSale->loadMissing('salesMovement');
        if ((bool) ($finalSale->salesMovement?->is_advance ?? false)) {
            return;
        }

        if (! $this->saleHasExplicitAdvanceApplications($finalSale)) {
            return;
        }

        $issues = $this->collectAdvanceSunatBlockingIssues($finalSale);
        if ($issues !== []) {
            throw new \RuntimeException(implode(' ', $issues));
        }
    }

    /**
     * @return Collection<int, Movement>
     */
    public function collectOrderAdvanceMovements(int $orderMovementId): Collection
    {
        if ($orderMovementId <= 0) {
            return collect();
        }

        $advanceMovementIds = DB::table('movements as m')
            ->join('sales_movements as sm', 'sm.movement_id', '=', 'm.id')
            ->where('m.parent_movement_id', $orderMovementId)
            ->where('sm.is_advance', true)
            ->whereNull('m.deleted_at')
            ->whereNull('sm.deleted_at')
            ->pluck('m.id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($advanceMovementIds === []) {
            return collect();
        }

        return Movement::query()
            ->with(['documentType', 'salesMovement'])
            ->whereIn('id', $advanceMovementIds)
            ->orderBy('id')
            ->get();
    }

    /**
     * @return array<int, string>
     */
    public function collectOrderAdvanceSunatBlockingIssues(int $orderMovementId): array
    {
        $issues = [];
        foreach ($this->collectOrderAdvanceMovements($orderMovementId) as $advanceMovement) {
            if ($this->isAdvanceSunatReady($advanceMovement)) {
                continue;
            }

            $fullNumber = $this->formatAdvanceFullNumber($advanceMovement);
            $status = strtoupper(trim((string) ($advanceMovement->electronic_invoice_status ?? '')));
            $statusLabel = $status !== '' ? $status : 'SIN EMITIR';

            $issues[] = 'El anticipo '.$fullNumber.' de la orden no está aceptado por SUNAT como anticipo (estado: '.$statusLabel.'). '
                .'Reenvíe ese comprobante desde Ventas (botón Reenviar SUNAT) y espere estado ACEPTADO.';
        }

        return $issues;
    }

    /**
     * @return Collection<int, Movement>
     */
    public function collectLinkedAdvanceMovements(Movement $sale): Collection
    {
        $advanceLinks = DB::table('sale_advances')
            ->where('final_movement_id', (int) $sale->id)
            ->get(['advance_movement_id', 'applied_amount']);

        $saleMovementId = (int) $sale->id;
        $advanceMovementIds = $advanceLinks
            ->pluck('advance_movement_id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0 && $id !== $saleMovementId)
            ->values()
            ->all();

        if ($advanceMovementIds === []) {
            return collect();
        }

        return Movement::query()
            ->with(['documentType', 'salesMovement'])
            ->whereIn('id', $advanceMovementIds)
            ->where('id', '!=', $saleMovementId)
            ->orderBy('id')
            ->get();
    }

    private function resolveLinkedAdvancePayments(Movement $sale): array
    {
        $sale->loadMissing('salesMovement');
        if ((bool) ($sale->salesMovement?->is_advance ?? false)) {
            return [];
        }

        if (! $this->saleHasExplicitAdvanceApplications($sale)) {
            return [];
        }

        $advanceLinks = DB::table('sale_advances')
            ->where('final_movement_id', (int) $sale->id)
            ->get(['advance_movement_id', 'applied_amount']);

        $appliedByMovementId = $advanceLinks
            ->mapWithKeys(fn ($row) => [(int) $row->advance_movement_id => (float) $row->applied_amount])
            ->all();

        $movements = $this->collectLinkedAdvanceMovements($sale)
            ->filter(fn (Movement $advanceMovement) => $this->isAdvanceSunatReady($advanceMovement));

        return $movements->map(function (Movement $advanceMovement) use ($appliedByMovementId) {
            $advanceSale = $advanceMovement->salesMovement;
            $series = trim((string) ($advanceMovement->electronic_invoice_series ?? $advanceSale?->series ?? ''));
            $correlative = trim((string) ($advanceMovement->electronic_invoice_number ?? $advanceSale?->billing_number ?? $advanceMovement->number ?? ''));
            $correlativeDigits = preg_replace('/\D+/', '', $correlative) ?: '';
            if ($correlativeDigits !== '') {
                $correlative = str_pad($correlativeDigits, 8, '0', STR_PAD_LEFT);
            }

            $fullNumber = ($series !== '' && $correlative !== '')
                ? $series . '-' . $correlative
                : trim((string) ($advanceMovement->number ?? ''));

            $docName = mb_strtolower(trim((string) ($advanceMovement->documentType?->name ?? '')), 'UTF-8');
            $amounts = $this->resolveAdvanceAmountBreakdown(
                (float) ($appliedByMovementId[(int) $advanceMovement->id] ?? 0),
                (float) ($advanceSale?->subtotal ?? 0),
                (float) ($advanceSale?->tax ?? 0),
                (float) ($advanceSale?->total ?? 0)
            );

            return [
                'movement_id' => (int) $advanceMovement->id,
                'full_number' => $fullNumber,
                'document_type_code' => str_contains($docName, 'factura') ? '02' : '03',
                'valor_venta' => $amounts['valor_venta'],
                'igv' => $amounts['igv'],
                'total' => $amounts['total'],
            ];
        })
            ->filter(fn (array $row) => $row['full_number'] !== '' && $row['valor_venta'] > 0)
            ->values()
            ->all();
    }

    /**
     * @return array{valor_venta: float, igv: float, total: float}
     */
    private function resolveAdvanceAmountBreakdown(float $appliedAmount, float $subtotal, float $tax, float $total): array
    {
        $total = round(max(0, $total), 2);
        $subtotal = round(max(0, $subtotal), 2);
        $tax = round(max(0, $tax), 2);

        if ($total <= 0 && $appliedAmount > 0) {
            $taxFactor = 0.18;
            $total = round($appliedAmount, 2);
            $subtotal = round($total / (1 + $taxFactor), 2);
            $tax = round($total - $subtotal, 2);
        } elseif ($appliedAmount > 0 && $total > 0 && abs($appliedAmount - $total) > 0.009) {
            $ratio = min(1, max(0, $appliedAmount / $total));
            $subtotal = round($subtotal * $ratio, 2);
            $tax = round($tax * $ratio, 2);
            $total = round($appliedAmount, 2);
        } elseif ($total > 0 && $subtotal <= 0) {
            $taxFactor = $tax > 0 && $total > $tax ? ($tax / max(0.01, $total - $tax)) : 0.18;
            $subtotal = round($total / (1 + $taxFactor), 2);
            $tax = round($total - $subtotal, 2);
        }

        return [
            'valor_venta' => $subtotal,
            'igv' => $tax,
            'total' => $total > 0 ? $total : round($subtotal + $tax, 2),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $linkedAdvances
     * @return array{
     *     references: array<int, array<string, mixed>>,
     *     allowance_charges: array<int, array<string, mixed>>,
     *     prepaid_payments: array<int, array<string, mixed>>,
     *     prepaid_valor_venta_total: float,
     *     prepaid_tax_total: float,
     *     prepaid_inclusive_total: float
     * }
     */
    private function buildSunatAdvanceBlocks(array $linkedAdvances, float $headerSubtotal, string $supplierRuc): array
    {
        $references = [];
        $prepaidPayments = [];
        $prepaidValorVentaTotal = 0.0;
        $prepaidTaxTotal = 0.0;
        $prepaidInclusiveTotal = 0.0;

        foreach ($linkedAdvances as $index => $advance) {
            $valorVenta = round((float) ($advance['valor_venta'] ?? 0), 2);
            $igv = round((float) ($advance['igv'] ?? 0), 2);
            $totalIncl = round((float) ($advance['total'] ?? 0), 2);

            if ($valorVenta <= 0) {
                continue;
            }

            $paymentIdentifier = str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT);
            $prepaidValorVentaTotal += $valorVenta;
            $prepaidTaxTotal += $igv;
            $prepaidInclusiveTotal += $totalIncl > 0 ? $totalIncl : round($valorVenta + $igv, 2);

            $references[] = $this->buildSunatAdvanceReferenceNode(
                (string) $advance['full_number'],
                (string) $advance['document_type_code'],
                $paymentIdentifier,
                $supplierRuc
            );

            $prepaidPayments[] = [
                'cbc:ID' => [
                    '_text' => $paymentIdentifier,
                ],
                'cbc:PaidAmount' => [
                    '_attributes' => ['currencyID' => 'PEN'],
                    '_text' => $valorVenta,
                ],
                'cbc:InstructionID' => [
                    '_attributes' => [
                        'schemeID' => '6',
                        'schemeName' => 'SUNAT:Identificador de Documento de Identidad',
                        'schemeAgencyName' => 'PE:SUNAT',
                    ],
                    '_text' => $supplierRuc,
                ],
            ];
        }

        $prepaidValorVentaTotal = round($prepaidValorVentaTotal, 2);
        $prepaidTaxTotal = round($prepaidTaxTotal, 2);
        $prepaidInclusiveTotal = round($prepaidInclusiveTotal, 2);
        $baseSubtotal = round(max(0, $headerSubtotal), 2);

        $allowanceCharges = $prepaidValorVentaTotal > 0
            ? [[
                'cbc:ChargeIndicator' => ['_text' => 'false'],
                'cbc:AllowanceChargeReasonCode' => ['_text' => self::SUNAT_ADVANCE_GLOBAL_DISCOUNT_CODE],
                'cbc:Amount' => [
                    '_attributes' => ['currencyID' => 'PEN'],
                    '_text' => $prepaidValorVentaTotal,
                ],
                'cbc:BaseAmount' => [
                    '_attributes' => ['currencyID' => 'PEN'],
                    '_text' => $baseSubtotal,
                ],
            ]]
            : [];

        return [
            'references' => $references,
            'allowance_charges' => $allowanceCharges,
            'prepaid_payments' => $prepaidPayments,
            'prepaid_valor_venta_total' => $prepaidValorVentaTotal,
            'prepaid_tax_total' => $prepaidTaxTotal,
            'prepaid_inclusive_total' => $prepaidInclusiveTotal,
        ];
    }

    private function normalizeElectronicInvoiceResponse(mixed $response): array
    {
        if (is_array($response)) {
            return $response;
        }

        if (is_string($response) && trim($response) !== '') {
            $decoded = json_decode($response, true);

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    private function formatAdvanceFullNumber(Movement $advanceMovement): string
    {
        $advanceMovement->loadMissing(['salesMovement', 'documentType']);
        $advanceSale = $advanceMovement->salesMovement;
        $series = trim((string) ($advanceMovement->electronic_invoice_series ?? $advanceSale?->series ?? ''));
        $correlative = trim((string) ($advanceMovement->electronic_invoice_number ?? $advanceSale?->billing_number ?? $advanceMovement->number ?? ''));
        $correlativeDigits = preg_replace('/\D+/', '', $correlative) ?: '';
        if ($correlativeDigits !== '') {
            $correlative = str_pad($correlativeDigits, 8, '0', STR_PAD_LEFT);
        }

        if ($series !== '' && $correlative !== '') {
            return $series.'-'.$correlative;
        }

        return trim((string) ($advanceMovement->number ?? ('#'.$advanceMovement->id)));
    }

    /**
     * Catálogo N°12 – documentos relacionados tributarios (anticipo factura/boleta).
     *
     * @return array{_attributes: array<string, string>, _text: string}
     */
    /**
     * listID en InvoiceTypeCode lo exige Apisunat. El tipo de operación SUNAT (cat. 51) va en ProfileID.
     * Factura de anticipo: ProfileID=0104 y listID=0101 (evita error SUNAT 3206).
     * Boleta de anticipo: ProfileID=0104 y listID=0104.
     */
    private function resolveSunatInvoiceTypeListId(string $documentTypeCode, bool $isAdvanceInvoice): string
    {
        if ($isAdvanceInvoice && $documentTypeCode === '03') {
            return self::SUNAT_OPERATION_ADVANCE_CODE;
        }

        return self::SUNAT_OPERATION_STANDARD_CODE;
    }

    private function sunatInvoiceTypeCodeNode(string $documentTypeCode, string $operationListId): array
    {
        return [
            '_text' => $documentTypeCode,
            '_attributes' => ['listID' => $operationListId],
        ];
    }

    /**
     * Formato Apisunat/SUNAT: DocumentStatusCode = identificador de pago (01, 02…),
     * debe coincidir con PrepaidPayment/cbc:ID (no el número F001-…).
     */
    private function buildSunatAdvanceReferenceNode(
        string $fullNumber,
        string $documentTypeCode,
        string $paymentIdentifier,
        string $supplierRuc
    ): array {
        return [
            'cbc:ID' => ['_text' => $fullNumber],
            'cbc:DocumentTypeCode' => ['_text' => $documentTypeCode],
            'cbc:DocumentStatusCode' => ['_text' => $paymentIdentifier],
            'cac:IssuerParty' => [
                'cac:PartyIdentification' => [
                    'cbc:ID' => [
                        '_attributes' => ['schemeID' => '6'],
                        '_text' => $supplierRuc,
                    ],
                ],
            ],
        ];
    }

    private function validateDocumentBodyForSunat(array $documentBody): void
    {
        $lines = data_get($documentBody, 'cac:InvoiceLine', []);
        if (! is_array($lines) || count($lines) === 0) {
            throw new \RuntimeException('No se puede emitir electrónicamente: el comprobante no tiene líneas válidas para SUNAT.');
        }

        foreach ($lines as $idx => $line) {
            $lineNumber = $idx + 1;
            $taxSchemeId = trim((string) data_get($line, 'cac:TaxTotal.cac:TaxSubtotal.0.cac:TaxCategory.cac:TaxScheme.cbc:ID._text', ''));
            $taxSchemeName = trim((string) data_get($line, 'cac:TaxTotal.cac:TaxSubtotal.0.cac:TaxCategory.cac:TaxScheme.cbc:Name._text', ''));
            $taxTypeCode = trim((string) data_get($line, 'cac:TaxTotal.cac:TaxSubtotal.0.cac:TaxCategory.cac:TaxScheme.cbc:TaxTypeCode._text', ''));
            $taxReasonCode = trim((string) data_get($line, 'cac:TaxTotal.cac:TaxSubtotal.0.cac:TaxCategory.cbc:TaxExemptionReasonCode._text', ''));
            $taxPercentRaw = data_get($line, 'cac:TaxTotal.cac:TaxSubtotal.0.cac:TaxCategory.cbc:Percent._text');
            $taxAmountRaw = data_get($line, 'cac:TaxTotal.cbc:TaxAmount._text');
            $grossUnitPriceRaw = data_get($line, 'cac:PricingReference.cac:AlternativeConditionPrice.cbc:PriceAmount._text');
            $lineSubtotalRaw = data_get($line, 'cbc:LineExtensionAmount._text');

            if ($taxSchemeId === '' || $taxSchemeName === '' || $taxTypeCode === '' || $taxReasonCode === '') {
                throw new \RuntimeException('No se puede emitir electrónicamente: el item '.$lineNumber.' no tiene tributo IGV válido. Verifique configuración tributaria del producto.');
            }

            if (! is_numeric((string) $taxPercentRaw) || ! is_numeric((string) $taxAmountRaw)) {
                throw new \RuntimeException('No se puede emitir electrónicamente: el item '.$lineNumber.' tiene datos tributarios inválidos (porcentaje/monto IGV).');
            }

            if (! is_numeric((string) $grossUnitPriceRaw) || (float) $grossUnitPriceRaw <= 0 || ! is_numeric((string) $lineSubtotalRaw) || (float) $lineSubtotalRaw <= 0) {
                throw new \RuntimeException('No se puede emitir electrónicamente: el item '.$lineNumber.' tiene importe cero o inválido para SUNAT.');
            }
        }

        $headerTaxSchemeId = trim((string) data_get($documentBody, 'cac:TaxTotal.cac:TaxSubtotal.cac:TaxCategory.cac:TaxScheme.cbc:ID._text', ''));
        if ($headerTaxSchemeId === '') {
            throw new \RuntimeException('No se puede emitir electrónicamente: el resumen tributario del comprobante está incompleto.');
        }

        $profileId = data_get($documentBody, 'cbc:ProfileID');
        $operationTypeCode = is_string($profileId)
            ? trim($profileId)
            : trim((string) data_get($profileId, '_text', ''));
        $invoiceTypeListId = trim((string) data_get($documentBody, 'cbc:InvoiceTypeCode._attributes.listID', ''));
        if ($invoiceTypeListId === '') {
            throw new \RuntimeException('No se puede emitir electrónicamente: falta listID en InvoiceTypeCode (requerido por Apisunat).');
        }
        if ($operationTypeCode === '') {
            throw new \RuntimeException('No se puede emitir electrónicamente: falta ProfileID (tipo de operación SUNAT).');
        }
    }

    private function resolveDetailsForSale(Movement $sale): Collection
    {
        if ($sale->salesMovement) {
            return $sale->salesMovement->details->where('status', '!=', 'C')->values();
        }

        if ($sale->orderMovement) {
            return $sale->orderMovement->details->where('status', '!=', 'C')->values();
        }

        return collect();
    }

    private function resolveDefaultTaxPercentForBranch(?Branch $branch): float
    {
        $taxRateId = null;

        if ($branch?->id) {
            $taxRateId = \Illuminate\Support\Facades\DB::table('branch_parameters')
                ->join('parameters as p', 'p.id', '=', 'branch_parameters.parameter_id')
                ->where('branch_parameters.branch_id', $branch->id)
                ->whereRaw('LOWER(p.description) = ?', ['igv_defecto'])
                ->whereNull('branch_parameters.deleted_at')
                ->value('branch_parameters.value');
        }

        $taxRate = $taxRateId
            ? TaxRate::query()->whereKey((int) $taxRateId)->first()
            : null;

        if (! $taxRate) {
            $taxRate = TaxRate::query()
                ->where('status', true)
                ->orderBy('order_num')
                ->first();
        }

        return $taxRate ? (float) $taxRate->tax_rate : 18.0;
    }

    private function findUrlByKeyword(array $payload, array $keywords): ?string
    {
        $urls = [];
        array_walk_recursive($payload, function ($value) use (&$urls) {
            if (is_string($value) && Str::startsWith($value, ['http://', 'https://'])) {
                $urls[] = $value;
            }
        });

        foreach ($urls as $url) {
            $normalized = Str::lower($url);
            $matched = true;
            foreach ($keywords as $keyword) {
                if (! str_contains($normalized, Str::lower($keyword))) {
                    $matched = false;
                    break;
                }
            }
            if ($matched) {
                return $url;
            }
        }

        return null;
    }
}
