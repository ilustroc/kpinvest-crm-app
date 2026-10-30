@props([
    'paginator',
])

@if(method_exists($paginator, 'links'))
    <div data-pagination {{ $attributes->merge(['class' => 'flex flex-col gap-2 border-t border-kp-border px-4 py-2 sm:flex-row sm:items-center sm:justify-between']) }}>
        <div class="text-[11.5px] font-medium text-kp-muted">
            Mostrando {{ method_exists($paginator, 'firstItem') ? ($paginator->firstItem() ?? 0) : 0 }}-{{ method_exists($paginator, 'lastItem') ? ($paginator->lastItem() ?? 0) : 0 }}
            @if(method_exists($paginator, 'total'))
                de {{ number_format($paginator->total()) }}.
            @endif
        </div>
        <div class="report-pagination">
            {{ $paginator->onEachSide(1)->withQueryString()->links() }}
        </div>
    </div>
@endif
