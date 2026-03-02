{{-- resources/views/autorizacion/partials/_header.blade.php --}}
<div class="autz-card">
    <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
        <div>
            <div class="flex items-center gap-2">
                <span class="autz-ic">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-5 w-5">
                        <path d="M12 3l8 4v6c0 5-3.5 9-8 9s-8-4-8-9V7l8-4z"></path>
                        <path d="M9 12l2 2 4-4"></path>
                    </svg>
                </span>
                <h1 class="text-sm sm:text-base font-extrabold tracking-tight text-slate-900">
                    {{ $isSupervisor ? 'Bandeja del Supervisor' : 'Bandeja del Administrador' }}
                </h1>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <div class="autz-seg" role="tablist" aria-label="Secciones">
                <button type="button" class="autz-seg-btn is-active" data-tab-btn="promesas">
                    Promesas <span class="autz-pill">{{ number_format($countProm,0) }}</span>
                </button>
                <button type="button" class="autz-seg-btn" data-tab-btn="cna">
                    CNA <span class="autz-pill">{{ number_format($countCna,0) }}</span>
                </button>
            </div>

            <div class="flex-1"></div>

            @if(session('ok'))
                <div class="autz-alert autz-alert-ok">
                    <span class="autz-alert-ic">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-5 w-5">
                            <path d="M9 12l2 2 4-4"></path>
                            <path d="M12 22a10 10 0 1 0-10-10 10 10 0 0 0 10 10Z"></path>
                        </svg>
                    </span>
                    <div class="font-semibold">{{ session('ok') }}</div>
                </div>
            @endif

            @if($errors->any())
                <div class="autz-alert autz-alert-bad">
                    <span class="autz-alert-ic">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-5 w-5">
                            <path d="M12 9v4"></path><path d="M12 17h.01"></path>
                            <path d="M10.3 3.6 2.3 17.5A2 2 0 0 0 4 20h16a2 2 0 0 0 1.7-2.5L13.7 3.6a2 2 0 0 0-3.4 0Z"></path>
                        </svg>
                    </span>
                    <div class="font-semibold">{{ $errors->first() }}</div>
                </div>
            @endif
        </div>
    </div>
</div>