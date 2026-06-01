@if ($sale->electronic_invoice_status === 'SENT')
    <div class="relative group">
        <span
            class="inline-flex h-10 w-10 items-center justify-center rounded-xl border-0 shadow-none"
            style="background-color: #10b981; color: #ffffff;"
            aria-label="Enviado a SUNAT"
        >
            <i class="ri-checkbox-circle-fill"></i>
        </span>
        <span class="pointer-events-none absolute bottom-full left-1/2 z-[100] mb-3 -translate-x-1/2 whitespace-nowrap rounded-md bg-gray-900 px-2.5 py-1 text-xs text-white opacity-0 shadow-xl transition group-hover:opacity-100">
            Enviado SUNAT
            <span class="absolute top-full left-1/2 -ml-1 border-4 border-transparent border-t-gray-900"></span>
        </span>
    </div>





    @if ($sale->electronic_invoice_external_id || $sale->electronic_invoice_xml_url)
        <div class="relative group">
            <x-ui.link-button
                size="icon"
                variant="primary"
                href="{{ route('admin.sales.electronic.xml.download', $sale->id) }}"
                className="rounded-xl border-0 shadow-none"
                style="background-color: #2563eb; color: #ffffff;"
                onmouseover="this.style.backgroundColor='#1d4ed8'"
                onmouseout="this.style.backgroundColor='#2563eb'"
                aria-label="Descargar XML"
            >
                <i class="ri-download-2-line"></i>
            </x-ui.link-button>
            <span class="pointer-events-none absolute bottom-full left-1/2 z-[100] mb-3 -translate-x-1/2 whitespace-nowrap rounded-md bg-gray-900 px-2.5 py-1 text-xs text-white opacity-0 shadow-xl transition group-hover:opacity-100">
                Descargar XML
                <span class="absolute top-full left-1/2 -ml-1 border-4 border-transparent border-t-gray-900"></span>
            </span>
        </div>

       
      
    @endif

    @if ($sale->electronic_invoice_external_id || $sale->electronic_invoice_cdr_url)
        <div class="relative group">
            <x-ui.link-button
                size="icon"
                variant="primary"
                href="{{ route('admin.sales.electronic.cdr.download', $sale->id) }}"
                className="rounded-xl border-0 shadow-none"
                style="background-color: #059669; color: #ffffff;"
                onmouseover="this.style.backgroundColor='#047857'"
                onmouseout="this.style.backgroundColor='#059669'"
                aria-label="Descargar CDR"
            >
                <i class="ri-file-download-line"></i>
            </x-ui.link-button>
            <span class="pointer-events-none absolute bottom-full left-1/2 z-[100] mb-3 -translate-x-1/2 whitespace-nowrap rounded-md bg-gray-900 px-2.5 py-1 text-xs text-white opacity-0 shadow-xl transition group-hover:opacity-100">
                Descargar CDR
                <span class="absolute top-full left-1/2 -ml-1 border-4 border-transparent border-t-gray-900"></span>
            </span>
        </div>

        
    @endif
@elseif ($sale->electronic_invoice_status === 'ERROR')
    <div class="relative group">
        <span
            class="inline-flex h-10 w-10 items-center justify-center rounded-xl border-0 shadow-none"
            style="background-color: #ef4444; color: #ffffff;"
            title="{{ $sale->electronicInvoiceErrorMessage() }}"
            aria-label="Error SUNAT"
        >
            <i class="ri-close-circle-fill"></i>
        </span>
        <span class="pointer-events-none absolute bottom-full left-1/2 z-[100] mb-3 max-w-[220px] -translate-x-1/2 whitespace-normal rounded-md bg-gray-900 px-2.5 py-1 text-left text-xs text-white opacity-0 shadow-xl transition group-hover:opacity-100">
            Error SUNAT
            <span class="absolute top-full left-1/2 -ml-1 border-4 border-transparent border-t-gray-900"></span>
        </span>
    </div>
@elseif ($sale->electronic_invoice_status)
    <div class="relative group">
        <span
            class="inline-flex h-10 w-10 items-center justify-center rounded-xl border-0 shadow-none"
            style="background-color: #94a3b8; color: #ffffff;"
            aria-label="{{ $sale->electronic_invoice_status }}"
        >
            <i class="ri-time-line"></i>
        </span>
        <span class="pointer-events-none absolute bottom-full left-1/2 z-[100] mb-3 -translate-x-1/2 whitespace-nowrap rounded-md bg-gray-900 px-2.5 py-1 text-xs text-white opacity-0 shadow-xl transition group-hover:opacity-100">
            {{ $sale->electronic_invoice_status }}
            <span class="absolute top-full left-1/2 -ml-1 border-4 border-transparent border-t-gray-900"></span>
        </span>
    </div>
@endif
