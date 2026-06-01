@if ($sale->electronic_invoice_status)
    <div class="flex max-w-[220px] flex-wrap items-center justify-center gap-x-2 gap-y-1 border-t border-gray-100 pt-2 dark:border-gray-700">
        @if ($sale->electronic_invoice_status === 'SENT')
            <span class="inline-flex w-full items-center justify-center gap-1 rounded bg-emerald-50 px-2 py-0.5 text-[10px] font-semibold text-emerald-700 dark:bg-emerald-950/30 dark:text-emerald-400">
                <i class="ri-checkbox-circle-fill"></i> Enviado
            </span>
            @if ($sale->electronic_invoice_pdf_a4_url || $sale->electronic_invoice_external_id)
                <a href="{{ route('admin.sales.electronic.pdf-a4', $sale->id) }}" target="_blank" class="inline-flex items-center gap-1 text-[10px] font-bold text-rose-600 hover:text-rose-700 hover:underline" title="PDF A4 SUNAT">
                    <i class="ri-file-pdf-2-line"></i> PDF A4
                </a>
            @endif
            @if ($sale->electronic_invoice_pdf_ticket_url)
                <a href="{{ $sale->electronic_invoice_pdf_ticket_url }}" target="_blank" class="inline-flex items-center gap-1 text-[10px] font-bold text-purple-600 hover:text-purple-700 hover:underline" title="PDF Ticket SUNAT">
                    <i class="ri-printer-line"></i> PDF Ticket
                </a>
            @endif
            @if ($sale->electronic_invoice_external_id || $sale->electronic_invoice_xml_url)
                <a href="{{ route('admin.sales.electronic.xml.download', $sale->id) }}" class="inline-flex items-center gap-1 text-[10px] font-bold text-blue-600 hover:text-blue-700 hover:underline" title="Descargar XML SUNAT">
                    <i class="ri-download-2-line"></i> Descargar XML
                </a>
                @if ($sale->electronic_invoice_xml_url)
                    <a href="{{ route('admin.sales.electronic.xml', $sale->id) }}" target="_blank" class="inline-flex items-center gap-1 text-[10px] font-semibold text-slate-500 hover:text-slate-700 hover:underline" title="Abrir XML en Apisunat">
                        <i class="ri-external-link-line"></i> Ver en línea
                    </a>
                @endif
            @endif
            @if ($sale->electronic_invoice_external_id || $sale->electronic_invoice_cdr_url)
                <a href="{{ route('admin.sales.electronic.cdr.download', $sale->id) }}" class="inline-flex items-center gap-1 text-[10px] font-bold text-emerald-600 hover:text-emerald-700 hover:underline" title="Descargar CDR SUNAT">
                    <i class="ri-download-2-line"></i> Descargar CDR
                </a>
                @if ($sale->electronic_invoice_cdr_url || $sale->electronic_invoice_external_id)
                    <a href="{{ route('admin.sales.electronic.cdr', $sale->id) }}" target="_blank" class="inline-flex items-center gap-1 text-[10px] font-semibold text-slate-500 hover:text-slate-700 hover:underline" title="Abrir CDR en Apisunat">
                        <i class="ri-external-link-line"></i> Ver CDR
                    </a>
                @endif
            @endif
        @elseif ($sale->electronic_invoice_status === 'ERROR')
            <span class="inline-flex items-center gap-1 rounded bg-rose-50 px-2 py-0.5 text-[10px] font-semibold text-rose-700 dark:bg-rose-950/30 dark:text-rose-400" title="{{ $sale->electronicInvoiceErrorMessage() }}">
                <i class="ri-close-circle-fill"></i> Error SUNAT
            </span>
        @else
            <span class="inline-flex items-center gap-1 rounded bg-gray-50 px-2 py-0.5 text-[10px] font-semibold text-gray-700 dark:bg-gray-800 dark:text-gray-400">
                <i class="ri-time-line"></i> {{ $sale->electronic_invoice_status }}
            </span>
        @endif
    </div>
@endif
