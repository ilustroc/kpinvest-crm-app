@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Navegación de paginación" class="flex w-full items-center justify-between pt-4">
        
        <div class="flex flex-1 justify-between sm:hidden">
            @if ($paginator->onFirstPage())
                <span class="relative inline-flex items-center rounded-md border border-kp-border bg-slate-50 px-4 py-2 text-sm font-bold text-kp-muted cursor-not-allowed">
                    Anterior
                </span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" class="relative inline-flex items-center rounded-md border border-kp-border bg-white px-4 py-2 text-sm font-bold text-kp-ink transition hover:bg-slate-50">
                    Anterior
                </a>
            @endif

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" class="relative ml-3 inline-flex items-center rounded-md border border-kp-border bg-white px-4 py-2 text-sm font-bold text-kp-ink transition hover:bg-slate-50">
                    Siguiente
                </a>
            @else
                <span class="relative ml-3 inline-flex items-center rounded-md border border-kp-border bg-slate-50 px-4 py-2 text-sm font-bold text-kp-muted cursor-not-allowed">
                    Siguiente
                </span>
            @endif
        </div>

        <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
            <div>
                <span class="isolate inline-flex rounded-md shadow-sm">
                    
                    @if ($paginator->onFirstPage())
                        <span aria-disabled="true" aria-label="Anterior" class="relative inline-flex items-center rounded-l-md border border-kp-border bg-slate-50 px-2 py-2 text-kp-muted cursor-not-allowed">
                            <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" />
                            </svg>
                        </span>
                    @else
                        <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="relative inline-flex items-center rounded-l-md border border-kp-border bg-white px-2 py-2 text-kp-ink transition hover:bg-slate-50 focus:z-20">
                            <span class="sr-only">Anterior</span>
                            <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" />
                            </svg>
                        </a>
                    @endif

                    @foreach ($elements as $element)
                        
                        @if (is_string($element))
                            <span aria-disabled="true" class="relative -ml-px inline-flex items-center border border-kp-border bg-white px-4 py-2 text-sm font-semibold text-kp-muted">
                                {{ $element }}
                            </span>
                        @endif

                        @if (is_array($element))
                            @foreach ($element as $page => $url)
                                @if ($page == $paginator->currentPage())
                                    <span aria-current="page" class="relative -ml-px z-10 inline-flex items-center border border-kp-ink bg-kp-ink px-4 py-2 text-sm font-bold text-white">
                                        {{ $page }}
                                    </span>
                                @else
                                    <a href="{{ $url }}" class="relative -ml-px inline-flex items-center border border-kp-border bg-white px-4 py-2 text-sm font-bold text-kp-ink transition hover:bg-slate-50 focus:z-20">
                                        {{ $page }}
                                    </a>
                                @endif
                            @endforeach
                        @endif
                    @endforeach

                    @if ($paginator->hasMorePages())
                        <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="relative -ml-px inline-flex items-center rounded-r-md border border-kp-border bg-white px-2 py-2 text-kp-ink transition hover:bg-slate-50 focus:z-20">
                            <span class="sr-only">Siguiente</span>
                            <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                            </svg>
                        </a>
                    @else
                        <span aria-disabled="true" aria-label="Siguiente" class="relative -ml-px inline-flex items-center rounded-r-md border border-kp-border bg-slate-50 px-2 py-2 text-kp-muted cursor-not-allowed">
                            <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                            </svg>
                        </span>
                    @endif
                    
                </span>
            </div>
        </div>
    </nav>
@endif