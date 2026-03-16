<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Kardex;
use App\Models\Product;
use App\Models\ProductType;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class KardexController extends Controller
{
    public function index(Request $request)
    {
        $data = $this->buildReportData($request);
        return view('kardex.index', $data);
    }

    public function pdf(Request $request)
    {
        $data = $this->buildReportData($request);
        $html = view('kardex.print.pdf', $data)->render();
        $pdfBinary = $this->renderPdfWithWkhtmltopdf($html, 'A4', [
            '--orientation', 'Landscape',
            '--margin-top', '8',
            '--margin-right', '8',
            '--margin-bottom', '8',
            '--margin-left', '8',
        ]);

        if ($pdfBinary === null) {
            return view('kardex.print.pdf', $data);
        }

        return response($pdfBinary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="kardex.pdf"',
        ]);
    }

    private function buildReportData(Request $request): array
    {
        $viewId = $request->input('view_id');
        $search = trim((string) $request->input('search', ''));
        $productId = $request->input('product_id') ?? 'all';
        $categoryId = $request->input('category_id') ?? 'all';
        $productTypeId = $request->input('product_type_id') ?? 'all';
        $situation = $request->input('situation') ?? 'all';
        $branchId = $request->session()->get('branch_id');
        $dateFrom = (string) ($request->input('date_from') ?? now()->startOfMonth()->format('Y-m-d H:i'));
        $dateTo = (string) ($request->input('date_to') ?? now()->format('Y-m-d H:i'));
        try {
            $dateFromParsed = Carbon::createFromFormat('Y-m-d H:i', $dateFrom);
        } catch (\Throwable) {
            $dateFromParsed = Carbon::parse($dateFrom);
        }
        try {
            $dateToParsed = Carbon::createFromFormat('Y-m-d H:i', $dateTo);
        } catch (\Throwable) {
            $dateToParsed = Carbon::parse($dateTo);
        }

        $products = Product::where('kardex', 'S')->with(['baseUnit', 'category'])->orderBy('description')->get();
        $categories = Category::query()->orderBy('description')->get(['id', 'description']);
        $productTypes = ProductType::query()
            ->when($branchId, fn ($query) => $query->where('branch_id', (int) $branchId))
            ->orderBy('name')
            ->get(['id', 'name']);
        $product = ($productId && $productId !== 'all' && is_numeric($productId)) ? Product::find($productId) : null;
        $branch = $branchId ? Branch::find($branchId) : null;
        $showAllProducts = ($productId === 'all');

        $movements = Kardex::query()
            ->with([
                'product.category',
                'unit',
                'movement.movementType',
                'movement.documentType',
                'movement.warehouseMovement',
                'movement.salesMovement',
                'movement.purchaseMovement',
            ])
            ->when($branchId, fn ($query) => $query->where('sucursal_id', (int) $branchId))
            ->when(!$showAllProducts && $product, fn ($query) => $query->where('producto_id', (int) $product->id))
            ->when($search !== '', function ($query) use ($search) {
                $query->whereHas('product', function ($productQuery) use ($search) {
                    $productQuery->where(function ($inner) use ($search) {
                        $inner->where('code', 'ILIKE', "%{$search}%")
                            ->orWhere('description', 'ILIKE', "%{$search}%");
                    });
                });
            })
            ->when($categoryId !== 'all' && is_numeric($categoryId), function ($query) use ($categoryId) {
                $query->whereHas('product', function ($productQuery) use ($categoryId) {
                    $productQuery->where('category_id', (int) $categoryId);
                });
            })
            ->when($productTypeId !== 'all' && is_numeric($productTypeId), function ($query) use ($productTypeId) {
                $query->whereHas('product', function ($productQuery) use ($productTypeId) {
                    $productQuery->where('product_type_id', (int) $productTypeId);
                });
            })
            ->when($situation !== 'all', fn ($query) => $query->where('situacion', (string) $situation))
            ->whereBetween('fecha', [
                $dateFromParsed->format('Y-m-d H:i:s'),
                $dateToParsed->format('Y-m-d H:i:s'),
            ])
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->get()
            ->map(function (Kardex $row) use ($viewId) {
                $movement = $row->movement;
                $quantity = abs((float) $row->cantidad);
                $unitPrice = $row->preciounitario !== null ? (float) $row->preciounitario : null;
                $typeName = $movement?->documentType?->name ?? 'Movimiento';

                $operationUrl = null;
                $operationLabel = null;
                if ($movement) {
                    if ($movement->salesMovement) {
                        $operationUrl = route('admin.sales.edit', array_merge([$movement->salesMovement->id], $viewId ? ['view_id' => $viewId] : []));
                        $operationLabel = 'Ver venta';
                    } elseif ($movement->purchaseMovement) {
                        $operationUrl = route('admin.purchases.edit', array_merge([$movement->purchaseMovement->id], $viewId ? ['view_id' => $viewId] : []));
                        $operationLabel = 'Ver compra';
                    } elseif ($movement->warehouseMovement) {
                        $operationUrl = route('warehouse_movements.show', array_merge(['warehouseMovement' => $movement->warehouseMovement->id], $viewId ? ['view_id' => $viewId] : []));
                        $operationLabel = 'Ver movimiento';
                    }
                }

                return [
                    'id' => $row->id,
                    'date' => $row->fecha?->format('Y-m-d H:i:s'),
                    'number' => $movement?->number ?? '-',
                    'type' => $typeName,
                    'entry' => (float) $row->cantidad > 0 ? (float) $row->cantidad : 0,
                    'exit' => (float) $row->cantidad < 0 ? abs((float) $row->cantidad) : 0,
                    'unit' => $row->unit?->description ?? $row->unit?->abbreviation ?? '-',
                    'unit_price' => $unitPrice,
                    'origin' => ($movement?->movementType?->description ?? 'Movimiento') . ' - ' . ($movement?->number ?? '-'),
                    'previous_stock' => (float) $row->stockanterior,
                    'quantity' => $quantity,
                    'balance' => (float) $row->stockactual,
                    'currency' => $row->moneda ?: 'PEN',
                    'product_code' => $row->product?->code ?? '-',
                    'product_description' => $row->product?->description ?? '-',
                    'category' => $row->product?->category?->description ?? 'Sin categoria',
                    'situation' => (string) ($row->situacion ?? 'E'),
                    'total' => ($unitPrice ?? 0) * $quantity,
                    'operation_url' => $operationUrl,
                    'operation_label' => $operationLabel,
                ];
            })
            ->values();

        $summary = [
            'records' => $movements->count(),
            'entries' => (float) $movements->sum('entry'),
            'exits' => (float) $movements->sum('exit'),
            'valuation' => (float) $movements->sum(function (array $movement) {
                return ((float) ($movement['unit_price'] ?? 0)) * ((float) ($movement['quantity'] ?? 0));
            }),
        ];

        return compact(
            'viewId',
            'search',
            'productId',
            'categoryId',
            'productTypeId',
            'situation',
            'branchId',
            'dateFrom',
            'dateTo',
            'products',
            'categories',
            'productTypes',
            'product',
            'branch',
            'movements',
            'showAllProducts',
            'summary'
        );
    }

    private function renderPdfWithWkhtmltopdf(string $html, ?string $pageSize = 'A4', array $extraArgs = []): ?string
    {
        $binary = $this->resolveWkhtmltopdfBinary();
        if (!$binary) {
            return null;
        }

        $tmpDir = storage_path('app/tmp');
        if (!is_dir($tmpDir)) {
            @mkdir($tmpDir, 0775, true);
        }

        $htmlFile = tempnam($tmpDir, 'kardex_html_');
        $pdfFile = tempnam($tmpDir, 'kardex_pdf_');

        if ($htmlFile === false || $pdfFile === false) {
            return null;
        }

        $htmlPath = $htmlFile . '.html';
        $pdfPath = $pdfFile . '.pdf';
        @rename($htmlFile, $htmlPath);
        @rename($pdfFile, $pdfPath);
        file_put_contents($htmlPath, $html);

        $args = array_merge([
            $binary,
            '--enable-local-file-access',
            '--disable-javascript',
            '--load-error-handling', 'ignore',
            '--load-media-error-handling', 'ignore',
            '--encoding', 'utf-8',
            '--margin-top', '10',
            '--margin-right', '10',
            '--margin-bottom', '10',
            '--margin-left', '10',
        ], $extraArgs);

        if (!empty($pageSize)) {
            $args[] = '--page-size';
            $args[] = $pageSize;
        }

        $args[] = $htmlPath;
        $args[] = $pdfPath;
        $process = new Process($args);

        try {
            $process->setTimeout(120);
            $process->run();
            if (!file_exists($pdfPath) || filesize($pdfPath) <= 0) {
                Log::warning('wkhtmltopdf fallo al generar PDF kardex', [
                    'error' => $process->getErrorOutput(),
                    'output' => $process->getOutput(),
                ]);
                return null;
            }

            $content = file_get_contents($pdfPath);
            return $content === false ? null : $content;
        } catch (\Throwable $e) {
            Log::warning('Error ejecutando wkhtmltopdf kardex: ' . $e->getMessage());
            return null;
        } finally {
            @unlink($htmlPath);
            @unlink($pdfPath);
        }
    }

    private function resolveWkhtmltopdfBinary(): ?string
    {
        $candidates = array_filter([
            env('WKHTML_PDF_BINARY'),
            env('WKHTMLTOPDF_BINARY'),
            '/usr/bin/wkhtmltopdf',
            '/usr/local/bin/wkhtmltopdf',
            '/opt/bin/wkhtmltopdf',
            '/opt/wkhtmltopdf/bin/wkhtmltopdf',
            '/var/www/Snappy/wkhtmltopdf',
            base_path('wkhtmltopdf/bin/wkhtmltopdf.exe'),
            'C:\Program Files\wkhtmltopdf\bin\wkhtmltopdf.exe',
            'C:\Snappy\wkhtmltopdf.exe',
        ]);

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && file_exists($candidate) && is_executable($candidate)) {
                return $candidate;
            }
        }

        return null;
    }
}
