@if ($paginator->hasPages())
  <nav role="navigation" aria-label="{{ __('Pagination Navigation') }}"
       class="flex items-center justify-between gap-3">

    {{-- ===== Mobile (sm-) ===== --}}
    <div class="flex flex-1 justify-between sm:hidden">
      {{-- Prev --}}
      @if ($paginator->onFirstPage())
        <span class="inline-flex items-center rounded-xl border border-slate-200 bg-white/70 px-3 py-2 text-sm font-semibold text-slate-400 shadow-sm cursor-not-allowed select-none">
          {!! __('pagination.previous') !!}
        </span>
      @else
        <a href="{{ $paginator->previousPageUrl() }}"
           class="inline-flex items-center rounded-xl border border-slate-200 bg-white/70 px-3 py-2 text-sm font-semibold text-slate-700 shadow-sm
                  hover:bg-emerald-50 hover:text-emerald-700
                  focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-emerald-500/20 focus-visible:border-emerald-400/60 transition">
          {!! __('pagination.previous') !!}
        </a>
      @endif

      {{-- Next --}}
      @if ($paginator->hasMorePages())
        <a href="{{ $paginator->nextPageUrl() }}"
           class="inline-flex items-center rounded-xl border border-slate-200 bg-white/70 px-3 py-2 text-sm font-semibold text-slate-700 shadow-sm
                  hover:bg-emerald-50 hover:text-emerald-700
                  focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-emerald-500/20 focus-visible:border-emerald-400/60 transition">
          {!! __('pagination.next') !!}
        </a>
      @else
        <span class="inline-flex items-center rounded-xl border border-slate-200 bg-white/70 px-3 py-2 text-sm font-semibold text-slate-400 shadow-sm cursor-not-allowed select-none">
          {!! __('pagination.next') !!}
        </span>
      @endif
    </div>

    {{-- ===== Desktop (sm+) ===== --}}
    <div class="hidden flex-1 sm:flex sm:items-center sm:justify-between gap-3">

      {{-- Controls --}}
      <div class="inline-flex items-center gap-2">
        <div class="inline-flex overflow-hidden rounded-2xl border border-slate-200 bg-white/70 shadow-sm divide-x divide-slate-200 rtl:flex-row-reverse">

          {{-- Prev --}}
          @if ($paginator->onFirstPage())
            <span aria-disabled="true" aria-label="{{ __('pagination.previous') }}"
                  class="inline-flex items-center justify-center px-3 py-2 text-slate-400 cursor-not-allowed select-none">
              <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd"/>
              </svg>
            </span>
          @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="{{ __('pagination.previous') }}"
               class="inline-flex items-center justify-center px-3 py-2 text-slate-600
                      hover:bg-emerald-50 hover:text-emerald-700
                      focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-emerald-500/20 transition">
              <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd"/>
              </svg>
            </a>
          @endif

          {{-- Pages --}}
          <div class="inline-flex items-center">
            @foreach ($elements as $element)

              {{-- Dots --}}
              @if (is_string($element))
                <span aria-disabled="true"
                      class="inline-flex items-center justify-center min-w-[44px] px-3 py-2 text-sm font-semibold text-slate-400 select-none">
                  {{ $element }}
                </span>
              @endif

              {{-- Links --}}
              @if (is_array($element))
                @foreach ($element as $page => $url)
                  @if ($page == $paginator->currentPage())
                    {{-- ACTIVA: NO depende de Tailwind para el fondo --}}
                    <span aria-current="page"
                          style="background: var(--brand);"
                          class="inline-flex items-center justify-center min-w-[44px] px-3 py-2 text-sm font-extrabold text-white select-none">
                      {{ $page }}
                    </span>
                  @else
                    <a href="{{ $url }}" aria-label="{{ __('Go to page :page', ['page' => $page]) }}"
                       class="inline-flex items-center justify-center min-w-[44px] px-3 py-2 text-sm font-semibold text-slate-700
                              hover:bg-emerald-50 hover:text-emerald-700
                              focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-emerald-500/20 transition">
                      {{ $page }}
                    </a>
                  @endif
                @endforeach
              @endif

            @endforeach
          </div>

          {{-- Next --}}
          @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="{{ __('pagination.next') }}"
               class="inline-flex items-center justify-center px-3 py-2 text-slate-600
                      hover:bg-emerald-50 hover:text-emerald-700
                      focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-emerald-500/20 transition">
              <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/>
              </svg>
            </a>
          @else
            <span aria-disabled="true" aria-label="{{ __('pagination.next') }}"
                  class="inline-flex items-center justify-center px-3 py-2 text-slate-400 cursor-not-allowed select-none">
              <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/>
              </svg>
            </span>
          @endif

        </div>
      </div>
    </div>
  </nav>
@endif