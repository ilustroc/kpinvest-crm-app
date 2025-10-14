@extends('layouts.app')

@section('title','Integración ▸ Subir Pagos')
@section('crumb','Integración ▸ Subir Pagos')

@push('head')
<style>
  /* Tabs compactas + accesibles */
  .tabs{display:flex;gap:8px;margin-bottom:12px}
  .tabs .tab-btn{
    padding:.42rem .8rem;
    border:1px solid var(--border);
    background:var(--surface);
    border-radius:999px;
    cursor:pointer;
    font-weight:700;
    font-size:.92rem;
    color:var(--ink);
    transition:.15s;
  }
  .tabs .tab-btn:hover{
    border-color:color-mix(in oklab,var(--brand) 30%,transparent);
    background:color-mix(in oklab,var(--brand) 10%,transparent);
  }
  .tabs .tab-btn.active{
    border-color:color-mix(in oklab,var(--brand) 40%,transparent);
    background:color-mix(in oklab,var(--brand) 18%,transparent);
    color:var(--brand-ink);
  }
  .tab-btn:focus-visible{
    outline:3px solid color-mix(in oklab,var(--brand) 45%,transparent);
    outline-offset:2px
  }
  .tab-panel{display:none}
  .tab-panel.show{display:block}
</style>
@endpush

@section('content')
  {{-- ALERTAS GLOBALES --}}
  @if(session('ok'))
    <div class="alert alert-success"><i class="bi bi-check-circle me-1"></i>{!! nl2br(e(session('ok'))) !!}</div>
  @endif
  @if(session('warn'))
    <div class="alert alert-warning"><pre class="mb-0" style="white-space:pre-wrap">{{ session('warn') }}</pre></div>
  @endif
  @if($errors->any())
    <div class="alert alert-danger">{{ $errors->first() }}</div>
  @endif

  {{-- TABS (accesibles, sin recargar) --}}
  <div class="tabs" role="tablist" aria-label="Tipos de integración">
    <button id="tab-propia" class="tab-btn active" data-tab="propia" role="tab" aria-selected="true" aria-controls="panel-propia">
      <i class="bi bi-file-spreadsheet me-1"></i> PROPIA
    </button>
    <button id="tab-cusco" class="tab-btn" data-tab="cusco" role="tab" aria-selected="false" aria-controls="panel-cusco">
      <i class="bi bi-bank me-1"></i> Caja Cusco ▸ Castigada
    </button>
    <button id="tab-extrajudicial" class="tab-btn" data-tab="extrajudicial" role="tab" aria-selected="false" aria-controls="panel-extrajudicial">
      <i class="bi bi-briefcase me-1"></i> Caja Cusco ▸ Extrajudicial
    </button>
  </div>

  {{-- PANEL: PROPIA --}}
  <div id="panel-propia" class="tab-panel show" role="tabpanel" aria-labelledby="tab-propia">
    @include('placeholders.pagos.propia', [
      'ultimoLotePropia' => $ultimoLotePropia ?? null,
      'pagosPropia' => $pagosPropia ?? collect(),
    ])
  </div>

  {{-- PANEL: CAJA CUSCO ▸ CASTIGADA --}}
  <div id="panel-cusco" class="tab-panel" role="tabpanel" aria-labelledby="tab-cusco">
    @include('placeholders.pagos.castigada', [
      'ultimoLoteCusco' => $ultimoLoteCusco ?? null,
      'pagosCusco' => $pagosCusco ?? collect(),
    ])
  </div>

  {{-- PANEL: CAJA CUSCO ▸ EXTRAJUDICIAL --}}
  <div id="panel-extrajudicial" class="tab-panel" role="tabpanel" aria-labelledby="tab-extrajudicial">
    @include('placeholders.pagos.extrajudicial')
  </div>
@endsection

@push('scripts')
<script>
  // Tabs accesibles + recordar selección + soporte de #hash
  (function(){
    const TKEY='pay.tab';
    const tabs=[...document.querySelectorAll('.tab-btn')];
    const panels={
      propia:document.getElementById('panel-propia'),
      cusco:document.getElementById('panel-cusco'),
      extrajudicial:document.getElementById('panel-extrajudicial')
    };

    function activate(key, persist){
      // botones
      tabs.forEach(b=>{
        const on=b.dataset.tab===key;
        b.classList.toggle('active', on);
        b.setAttribute('aria-selected', on ? 'true' : 'false');
        if(on) b.focus({preventScroll:true});
      });
      // paneles
      Object.entries(panels).forEach(([k,p])=>{
        const show=k===key;
        p.classList.toggle('show', show);
        p.hidden = !show;
      });
      if(persist){ localStorage.setItem(TKEY, key); history.replaceState(null,'', '#'+key); }
    }

    // Click / teclado
    tabs.forEach(btn=>{
      btn.addEventListener('click', ()=>activate(btn.dataset.tab, true));
      btn.addEventListener('keydown', e=>{
        if(e.key==='Enter' || e.key===' '){ e.preventDefault(); activate(btn.dataset.tab,true); }
        // navegación con flechas
        if(e.key==='ArrowRight' || e.key==='ArrowLeft'){
          e.preventDefault();
          const dir = e.key==='ArrowRight' ? 1 : -1;
          const idx = tabs.indexOf(btn);
          const next = tabs[(idx+dir+tabs.length)%tabs.length];
          next.focus();
        }
      });
    });

    // Restaurar: hash > localStorage > propia
    const fromHash=(location.hash||'').replace('#','');
    const initial = ['propia','cusco','extrajudicial'].includes(fromHash)
      ? fromHash
      : (localStorage.getItem(TKEY) || 'propia');
    activate(initial,false);
  })();
</script>
@endpush
