<?php

namespace App\Http\Controllers;

use App\Support\SimpleXlsxExporter;
use App\Support\WorkshopAuthorization;
use App\Models\Branch;
use App\Models\WarehouseMovementDetail;
use App\Models\WorkshopMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class WorkshopExportController extends Controller
{
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            $routeName = (string) optional($request->route())->getName();
            if (str_starts_with($routeName, 'workshop.')) {
                WorkshopAuthorization::ensureAllowed($routeName);
            }
            return $next($request);
        });
    }

    public function salesMonthlyCsv(Request $request): StreamedResponse|BinaryFileResponse
    {
        [$branchId] = $this->resolveContext();
        $month = (string) $request->input('month', now()->format('Y-m'));
        $customerType = strtolower((string) $request->input('customer_type', 'all'));

        $rows = DB::table('sales_movements')
            ->join('movements', 'movements.id', '=', 'sales_movements.movement_id')
            ->leftJoin('people', 'people.id', '=', 'movements.person_id')
            ->leftJoin('document_types', 'document_types.id', '=', 'movements.document_type_id')
            ->selectRaw("
                movements.moved_at as fecha,
                movements.number as numero,
                COALESCE(movements.person_name, '') as cliente,
                COALESCE(people.document_number, '') as documento,
                COALESCE(people.person_type, '') as tipo_cliente,
                COALESCE(document_types.name, '') as comprobante,
                sales_movements.subtotal,
                sales_movements.tax,
                sales_movements.total
            ")
            ->where('sales_movements.branch_id', $branchId)
            ->whereRaw("to_char(movements.moved_at, 'YYYY-MM') = ?", [$month])
            ->when($customerType !== 'all', function ($query) use ($customerType) {
                if ($customerType === 'natural') {
                    $query->whereRaw("UPPER(COALESCE(people.person_type,'')) IN ('NATURAL','PERSONA NATURAL','PN')");
                } elseif ($customerType === 'corporativo') {
                    $query->whereRaw("UPPER(COALESCE(people.person_type,'')) IN ('JURIDICA','PERSONA JURIDICA','EMPRESA','CORPORATIVO','PJ')");
                }
            })
            ->orderBy('movements.moved_at')
            ->get();

        $dataRows = $rows->map(function ($row) {
            return [
                $row->fecha,
                $row->numero,
                $row->cliente,
                $row->documento,
                $row->tipo_cliente,
                $row->comprobante,
                number_format((float) $row->subtotal, 2, '.', ''),
                number_format((float) $row->tax, 2, '.', ''),
                number_format((float) $row->total, 2, '.', ''),
            ];
        })->all();
        $header = ['Fecha', 'Numero', 'Cliente', 'Documento', 'Tipo Cliente', 'Comprobante', 'Subtotal', 'IGV', 'Total'];
        $xlsx = SimpleXlsxExporter::build('Ventas', $header, $dataRows);
        if ($xlsx) {
            return response()->download($xlsx, "registro_ventas_{$month}_{$customerType}.xlsx")->deleteFileAfterSend(true);
        }

        return $this->downloadCsv("registro_ventas_{$month}_{$customerType}.csv", $header, $dataRows);
    }

    public function purchasesMonthlyCsv(Request $request): StreamedResponse|BinaryFileResponse
    {
        [$branchId, $companyId] = $this->resolveContext();
        $month = (string) $request->input('month', now()->format('Y-m'));
        $supplierId = (int) $request->input('supplier_id', 0);
        $documentKind = strtoupper(trim((string) $request->input('document_kind', '')));
        $scopeBranchId = (int) $request->input('branch_id', $branchId);
        $isAdmin = ((int) (auth()->user()?->profile_id ?? 0) === 1) || str_contains(strtoupper((string) (auth()->user()?->profile?->name ?? '')), 'ADMIN');
        if (!$isAdmin) {
            $scopeBranchId = $branchId;
        } else {
            $allowedBranch = DB::table('branches')
                ->where('id', $scopeBranchId)
                ->where('company_id', $companyId)
                ->exists();
            if (!$allowedBranch) {
                $scopeBranchId = $branchId;
            }
        }

        $rows = DB::table('workshop_purchase_records')
            ->join('movements', 'movements.id', '=', 'workshop_purchase_records.movement_id')
            ->join('warehouse_movements', 'warehouse_movements.movement_id', '=', 'movements.id')
            ->join('warehouse_movement_details', 'warehouse_movement_details.warehouse_movement_id', '=', 'warehouse_movements.id')
            ->leftJoin('products', 'products.id', '=', 'warehouse_movement_details.product_id')
            ->leftJoin('people', 'people.id', '=', 'workshop_purchase_records.supplier_person_id')
            ->selectRaw("
                workshop_purchase_records.issued_at as fecha,
                workshop_purchase_records.document_kind as documento_tipo,
                CONCAT(COALESCE(workshop_purchase_records.series,''), '-', workshop_purchase_records.document_number) as numero,
                COALESCE(movements.person_name, '') as proveedor,
                COALESCE(people.document_number, '') as proveedor_doc,
                workshop_purchase_records.currency as moneda,
                workshop_purchase_records.igv_rate as igv_rate,
                COALESCE(products.description, warehouse_movement_details.product_snapshot->>'description', '') as producto,
                warehouse_movement_details.quantity as cantidad,
                COALESCE(pb.avg_cost, pb.price, 0) as costo_unitario
            ")
            ->leftJoin('product_branch as pb', function ($join) {
                $join->on('pb.product_id', '=', 'warehouse_movement_details.product_id')
                    ->on('pb.branch_id', '=', 'warehouse_movement_details.branch_id');
            })
            ->where('workshop_purchase_records.branch_id', $scopeBranchId)
            ->whereRaw("to_char(workshop_purchase_records.issued_at, 'YYYY-MM') = ?", [$month])
            ->when($supplierId > 0, fn ($query) => $query->where('workshop_purchase_records.supplier_person_id', $supplierId))
            ->when($documentKind !== '', fn ($query) => $query->where('workshop_purchase_records.document_kind', $documentKind))
            ->orderBy('workshop_purchase_records.issued_at')
            ->get();

        $dataRows = $rows->map(function ($row) {
            $qty = (float) $row->cantidad;
            $unitCost = (float) $row->costo_unitario;
            return [
                $row->fecha,
                $row->documento_tipo,
                $row->numero,
                $row->proveedor,
                $row->proveedor_doc,
                $row->moneda,
                number_format((float) $row->igv_rate, 4, '.', ''),
                $row->producto,
                number_format($qty, 6, '.', ''),
                number_format($unitCost, 6, '.', ''),
                number_format($qty * $unitCost, 6, '.', ''),
            ];
        })->all();
        $header = ['Fecha', 'Tipo Doc', 'Numero', 'Proveedor', 'Doc Proveedor', 'Moneda', 'IGV %', 'Producto', 'Cantidad', 'Costo Unitario', 'Subtotal'];
        $xlsx = SimpleXlsxExporter::build('Compras', $header, $dataRows);
        if ($xlsx) {
            return response()->download($xlsx, "registro_compras_{$month}_suc_{$scopeBranchId}.xlsx")->deleteFileAfterSend(true);
        }

        return $this->downloadCsv("registro_compras_{$month}_suc_{$scopeBranchId}.csv", $header, $dataRows);
    }

    public function workshopOrdersCsv(Request $request): StreamedResponse|BinaryFileResponse
    {
        [$branchId] = $this->resolveContext();
        $dateFrom = (string) $request->input('date_from', now()->startOfMonth()->toDateString());
        $dateTo = (string) $request->input('date_to', now()->toDateString());

        $rows = WorkshopMovement::query()
            ->with(['movement', 'vehicle', 'client'])
            ->where('branch_id', $branchId)
            ->whereDate('intake_date', '>=', $dateFrom)
            ->whereDate('intake_date', '<=', $dateTo)
            ->orderBy('intake_date')
            ->get();

        $dataRows = $rows->map(function (WorkshopMovement $row) {
            return [
                optional($row->intake_date)->format('Y-m-d H:i:s'),
                (string) ($row->movement?->number ?? ''),
                $row->status,
                trim((string) (($row->client?->first_name ?? '') . ' ' . ($row->client?->last_name ?? ''))),
                trim((string) (($row->vehicle?->brand ?? '') . ' ' . ($row->vehicle?->model ?? ''))),
                (string) ($row->vehicle?->plate ?? ''),
                (string) ($row->mileage_in ?? ''),
                (string) ($row->mileage_out ?? ''),
                number_format((float) $row->subtotal, 2, '.', ''),
                number_format((float) $row->tax, 2, '.', ''),
                number_format((float) $row->total, 2, '.', ''),
                number_format((float) $row->paid_total, 2, '.', ''),
            ];
        })->all();
        $header = ['Fecha Ingreso', 'OS', 'Estado', 'Cliente', 'Vehiculo', 'Placa', 'KM In', 'KM Out', 'Subtotal', 'IGV', 'Total', 'Pagado'];
        $xlsx = SimpleXlsxExporter::build('Ordenes', $header, $dataRows);
        if ($xlsx) {
            return response()->download($xlsx, "ordenes_taller_{$dateFrom}_{$dateTo}.xlsx")->deleteFileAfterSend(true);
        }

        return $this->downloadCsv("ordenes_taller_{$dateFrom}_{$dateTo}.csv", $header, $dataRows);
    }

    public function productivityCsv(Request $request): StreamedResponse|BinaryFileResponse
    {
        [$branchId] = $this->resolveContext();
        $dateFrom = (string) $request->input('date_from', now()->startOfMonth()->toDateString());
        $dateTo = (string) $request->input('date_to', now()->toDateString());

        $rows = DB::table('workshop_movement_details')
            ->join('workshop_movements', 'workshop_movements.id', '=', 'workshop_movement_details.workshop_movement_id')
            ->join('people', 'people.id', '=', 'workshop_movement_details.technician_person_id')
            ->selectRaw("
                CONCAT(people.first_name, ' ', people.last_name) as tecnico,
                COUNT(*) as lineas,
                COUNT(DISTINCT workshop_movements.id) as ordenes,
                COALESCE(SUM(workshop_movement_details.total),0) as facturado
            ")
            ->where('workshop_movements.branch_id', $branchId)
            ->whereDate('workshop_movements.intake_date', '>=', $dateFrom)
            ->whereDate('workshop_movements.intake_date', '<=', $dateTo)
            ->whereNotNull('workshop_movement_details.technician_person_id')
            ->groupBy('people.first_name', 'people.last_name')
            ->orderByDesc('ordenes')
            ->get();

        $dataRows = $rows->map(fn ($row) => [
            $row->tecnico,
            (int) $row->lineas,
            (int) $row->ordenes,
            number_format((float) $row->facturado, 2, '.', ''),
        ])->all();
        $header = ['Tecnico', 'Lineas', 'Ordenes', 'Facturado'];
        $xlsx = SimpleXlsxExporter::build('Productividad', $header, $dataRows);
        if ($xlsx) {
            return response()->download($xlsx, "productividad_tecnicos_{$dateFrom}_{$dateTo}.xlsx")->deleteFileAfterSend(true);
        }

        return $this->downloadCsv("productividad_tecnicos_{$dateFrom}_{$dateTo}.csv", $header, $dataRows);
    }

    public function kardexProductCsv(Request $request): StreamedResponse|BinaryFileResponse
    {
        [$branchId] = $this->resolveContext();
        $validated = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
        ]);

        $dateFrom = $validated['date_from'] ?? now()->startOfMonth()->toDateString();
        $dateTo = $validated['date_to'] ?? now()->toDateString();

        $rows = WarehouseMovementDetail::query()
            ->with(['warehouseMovement.movement.documentType', 'product'])
            ->where('branch_id', $branchId)
            ->where('product_id', (int) $validated['product_id'])
            ->whereHas('warehouseMovement.movement', function ($query) use ($dateFrom, $dateTo) {
                $query->whereDate('moved_at', '>=', $dateFrom)->whereDate('moved_at', '<=', $dateTo);
            })
            ->orderBy('id')
            ->get()
            ->map(function (WarehouseMovementDetail $detail) {
                $movement = $detail->warehouseMovement?->movement;
                $stockAction = strtolower((string) ($movement?->documentType?->stock ?? 'none'));
                $qty = (float) $detail->quantity;
                $entry = $stockAction === 'add' ? $qty : 0;
                $exit = $stockAction === 'subtract' ? $qty : 0;

                return [
                    optional($movement?->moved_at)->format('Y-m-d H:i:s'),
                    (string) ($movement?->number ?? ''),
                    (string) ($movement?->documentType?->name ?? ''),
                    number_format($entry, 6, '.', ''),
                    number_format($exit, 6, '.', ''),
                    (string) ($detail->comment ?? ''),
                ];
            });

        $dataRows = $rows->all();
        $header = ['Fecha', 'Movimiento', 'Documento', 'Ingreso', 'Salida', 'Comentario'];
        $xlsx = SimpleXlsxExporter::build('Kardex', $header, $dataRows);
        if ($xlsx) {
            return response()->download($xlsx, "kardex_producto_{$validated['product_id']}_{$dateFrom}_{$dateTo}.xlsx")->deleteFileAfterSend(true);
        }

        return $this->downloadCsv("kardex_producto_{$validated['product_id']}_{$dateFrom}_{$dateTo}.csv", $header, $dataRows);
    }

    private function resolveContext(): array
    {
        $branchId = (int) session('branch_id');
        if ($branchId <= 0) {
            abort(403, 'No hay sucursal activa.');
        }
        $companyId = (int) Branch::query()->where('id', $branchId)->value('company_id');
        if ($companyId <= 0) {
            abort(403, 'No se encontro empresa para la sucursal.');
        }

        return [$branchId, $companyId];
    }

    private function downloadCsv(string $filename, array $header, array $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($header, $rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $header);
            foreach ($rows as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
