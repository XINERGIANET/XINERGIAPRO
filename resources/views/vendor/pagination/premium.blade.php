<nav role="navigation" aria-label="{{ __('Pagination Navigation') }}" class="flex items-center justify-between">
    <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-end">
        <style>
            .pagination-arrow {
                color: #9CA3AF;
                transition: color 0.2s;
            }
            .pagination-arrow:hover:not(.cursor-default) {
                color: #334155 !important;
            }
            .pagination-arrow-disabled {
                color: #D1D5DB;
            }
        </style>
        <div>
            <span class="relative z-0 inline-flex gap-2 items-center">
                {{-- Previous Page Link --}}
                @if ($paginator->onFirstPage())
                    <span aria-disabled="true">
                        <span class="relative inline-flex items-center h-8 w-8 justify-center pagination-arrow-disabled cursor-default" aria-hidden="true">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"></path></svg>
                        </span>
                    </span>
                @else
                    <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="relative inline-flex items-center h-8 w-8 justify-center pagination-arrow transition ease-in-out duration-150">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"></path></svg>
                    </a>
                @endif

                {{-- Pagination Elements --}}
                <div class="flex items-center gap-1">
                    @foreach ($elements as $element)
                        {{-- "Three Dots" Separator --}}
                        @if (is_string($element))
                            <span aria-disabled="true">
                                <span class="relative inline-flex items-center h-9 w-9 justify-center text-sm font-medium text-gray-500 cursor-default">{{ $element }}</span>
                            </span>
                        @endif

                        {{-- Array Of Links --}}
                        @if (is_array($element))
                            @foreach ($element as $page => $url)
                                @if ($page == $paginator->currentPage())
                                    <span aria-current="page">
                                        <span class="relative inline-flex items-center h-9 w-9 justify-center text-[13px] font-bold rounded-lg leading-5 cursor-default text-white bg-[#334155] shadow-sm">{{ $page }}</span>
                                    </span>
                                @else
                                    <a href="{{ $url }}" class="relative inline-flex items-center h-9 w-9 justify-center text-[13px] font-medium text-[#64748b] hover:text-[#334155] transition ease-in-out duration-150" aria-label="{{ __('Go to page :page', ['page' => $page]) }}">
                                        {{ $page }}
                                    </a>
                                @endif
                            @endforeach
                        @endif
                    @endforeach
                </div>

                {{-- Next Page Link --}}
                @if ($paginator->hasMorePages())
                    <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="relative inline-flex items-center h-8 w-8 justify-center pagination-arrow transition ease-in-out duration-150">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                    </a>
                @else
                    <span aria-disabled="true">
                        <span class="relative inline-flex items-center h-8 w-8 justify-center pagination-arrow-disabled cursor-default" aria-hidden="true">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                        </span>
                    </span>
                @endif
            </span>
        </div>
    </div>
</nav>
