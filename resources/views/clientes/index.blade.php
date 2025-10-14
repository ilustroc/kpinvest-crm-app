@extends('layouts.app')
@section('title','Buscar Cliente')
@section('crumb','Buscar Cliente')

@push('head')
<style>
  /* ====== Marca KP: verde primario + azul acento ====== */
  :root{
    --brand:#00a81c;        /* verde KP */
    --brand-ink:#008517;    /* hover/ink */
    --accent:#0b4ea2;       /* azul KP */
    --accent-ink:#093f82;

    /* estos vienen del layout; los declaramos por si el cache de CSS tarda */
    --surface:#ffffff; --surface-2:#f3f6fb; --border:#e8ecf3;
    --ink:#151a23; --muted:#6d7b8a;
  }

  /* ========= Filtros / toolbar ========= */
  .cli-filters .form-control,
  .cli-filters .form-select{
    background:var(--surface);
    border-color:var(--border);
  }
  .cli-filters .form-control::placeholder{ color:var(--muted) }
  .cli-filters .input-group-text{
    background:var(--surface);
    border-color:var(--border);
    color:var(--muted);
  }

  /* Chips de estado */
  .cli-toolbar .chip{ min-height:auto; padding:.45rem .65rem }
  .cli-toolbar .chip .t{ gap:.45rem; font-weight:600; font-size:.9rem }
  .cli-toolbar .chip .s{ font-size:.85rem; color:var(--ink) }

  /* Acciones (copiar / export) */
  .cli-actions .btn{ height:36px }
  .cli-actions .btn-outline-primary{ color:var(--brand); border-color:var(--brand) }
  .cli-actions .btn-outline-primary:hover{
    color:#fff; border-color:var(--brand-ink);
    background:var(--brand-ink);
  }

  /* ========= Tabla ========= */
  .cli-table.table> :not(caption)>*>*{ padding:.55rem .75rem } /* compacto */

  .cli-table thead th{
    position: sticky; top: 0; z-index: 2;
    background: color-mix(in oklab, var(--surface-2) 55%, transparent);
    color: var(--ink);
    text-transform: uppercase; font-size:.82rem; letter-spacing:.3px;
    border-bottom:1px solid var(--border);
    box-shadow: 0 3px 8px rgba(15,23,42,.06); /* leve sombra al fijarse */
  }

  .cli-table tbody td{ color: var(--ink) }
  .cli-table tbody tr:nth-child(even){
    background: color-mix(in oklab, var(--surface-2) 16%, transparent);
  }
  .cli-table tbody tr:hover{
    /* mezcla un toque de azul para contraste con el verde primario */
    background: color-mix(in oklab, var(--accent) 10%, var(--brand) 4%);
    transition: background .15s ease;
  }

  /* Fila clicable (además del botón Ver) */
  .cli-row{ cursor:pointer }
  .cli-row:focus-visible{
    outline:3px solid color-mix(in oklab, var(--accent) 40%, transparent);
    outline-offset:-3px; border-radius:4px
  }

  /* Anchos útiles: DNI / Operación fijos, Titular elipsis */
  .cli-table tbody td:nth-child(2){ width: 9rem }   /* DNI */
  .cli-table tbody td:nth-child(3){ width: 10rem }  /* Operación */
  .cli-table tbody td:nth-child(4){
    max-width: 38rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; /* Titular */
  }
  @media (max-width: 1200px){ .cli-table tbody td:nth-child(4){ max-width: 26rem } }
  @media (max-width: 992px) { .cli-table tbody td:nth-child(4){ max-width: 18rem } }
  @media (max-width: 576px) { .cli-table tbody td:nth-child(4){ max-width: 12rem } }

  /* ========= Paginación (pills + marca) ========= */
  .cli-pager .pagination{ margin-bottom:0; gap:.25rem }
  .cli-pager .page-item .page-link{
    border-color: var(--border);
    background: var(--surface);
    color: var(--ink);
    border-radius: 999px;
    padding:.42rem .75rem;
  }
  .cli-pager .page-item .page-link:hover{
    background: color-mix(in oklab, var(--accent) 14%, transparent);
    border-color: color-mix(in oklab, var(--accent) 30%, transparent);
    color: var(--ink);
  }
  .cli-pager .page-item.active .page-link{
    background: var(--brand);
    border-color: var(--brand);
    color: #fff;
  }
  .cli-pager .page-item.disabled .page-link{
    color: var(--muted);
    background: var(--surface);
  }
  .cli-pager .page-link:focus{
    box-shadow:0 0 0 .25rem color-mix(in oklab, var(--accent) 22%, transparent);
  }

  /* ========= Resaltado de coincidencias ========= */
  mark.cli-hit{
    background: color-mix(in oklab, var(--brand) 22%, transparent);
    color: inherit; padding:0 .15em; border-radius:.25rem
  }

  /* Scrollbar del contenedor de tabla */
  .table-responsive::-webkit-scrollbar{ height:10px }
  .table-responsive::-webkit-scrollbar-thumb{
    background: color-mix(in oklab, var(--accent) 24%, transparent); border-radius:10px
  }

  /* Coherencia primario (fallback si layout cachea) */
  .btn-primary{ background:var(--brand); border-color:var(--brand) }
  .btn-primary:hover{ background:var(--brand-ink); border-color:var(--brand-ink) }
</style>
@endpush

@section('content')
<div class="card pad">

  {{-- Filtros --}}
  <form method="GET" action="{{ route('clientes.index') }}" class="row g-2 align-items-end cli-filters" id="cliForm">
    <div class="col-12 col-lg-6">
      <label class="form-label">Buscar</label>
      <div class="input-group">
        <span class="input-group-text"><i class="bi bi-search"></i></span>
        <input name="q"
               value="{{ $q ?? request('q') }}"
               class="form-control"
               placeholder="DNI / Operación / Titular / Cartera"
               aria-label="Buscar por DNI, Operación, Titular o Cartera">
        <button class="btn btn-primary" title="Buscar">
          <i class="bi bi-arrow-right-short"></i>
        </button>
      </div>
      <div class="form-text">Pulsa Enter para buscar. Coincide en cualquiera de las columnas visibles.</div>
    </div>

    <div class="col-6 col-md-3 col-lg-2">
      <label class="form-label">Por página</label>
      <select name="pp" class="form-select" aria-label="Registros por página">
        @foreach([10,20,50,100] as $n)
          <option value="{{ $n }}" {{ request('pp',20)==$n?'selected':'' }}>{{ $n }}</option>
        @endforeach
      </select>
    </div>

    <div class="col-6 col-md-3 col-lg-2 d-grid">
      <label class="form-label d-none d-md-block">&nbsp;</label>
      <a class="btn btn-outline-secondary" href="{{ route('clientes.index') }}">
        Limpiar
      </a>
    </div>
  </form>

  {{-- Toolbar de estado + Acciones --}}
  <div class="d-flex flex-wrap justify-content-between gap-2 mt-2">
    <div class="cli-toolbar d-flex flex-wrap gap-2">
      <div class="chip">
        <div class="t"><i class="bi bi-people"></i><span>Resultados</span></div>
        <div class="s" id="cliCount">{{ number_format($clientes->total()) }}</div>
      </div>
      <div class="chip">
        <div class="t"><i class="bi bi-list-ol"></i><span>Página</span></div>
        <div class="s">{{ $clientes->currentPage() }} / {{ max($clientes->lastPage(),1) }}</div>
      </div>
      @if(($q ?? request('q')))
        <div class="chip">
          <div class="t"><i class="bi bi-funnel"></i><span>Búsqueda</span></div>
          <div class="s">“{{ $q ?? request('q') }}”</div>
        </div>
      @endif
    </div>

    <div class="cli-actions d-flex flex-wrap gap-2">
      <button class="btn btn-outline-secondary btn-sm" id="btnCopy" type="button" title="Copiar tabla al portapapeles">
        <i class="bi bi-clipboard me-1"></i> Copiar tabla
      </button>
      <button class="btn btn-outline-primary btn-sm" id="btnCsv" type="button" title="Descargar CSV con las filas de esta página">
        <i class="bi bi-file-earmark-spreadsheet me-1"></i> CSV (esta hoja)
      </button>
    </div>
  </div>

  {{-- Tabla --}}
  <div class="table-responsive mt-2">
    <table class="table align-middle cli-table" id="cliTable">
      <thead>
        <tr>
          <th>Cartera</th>
          <th>DNI</th>
          <th>Operación</th>
          <th>Titular</th>
          <th></th>
        </tr>
      </thead>
      <tbody id="cliBody" data-q="{{ trim($q ?? '') }}">
        @forelse($clientes as $c)
          @php($href = route('clientes.show',$c->dni))
          <tr class="cli-row" tabindex="0" data-href="{{ $href }}">
            <td class="text-nowrap">{{ $c->cartera }}</td>
            <td class="text-nowrap">{{ $c->dni }}</td>
            <td class="text-nowrap">{{ $c->operacion }}</td>
            <td>{{ $c->titular }}</td>
            <td class="text-end">
              <a class="btn btn-sm btn-outline-primary" href="{{ $href }}" title="Ver detalle del cliente">
                Ver
              </a>
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="5" class="text-center text-secondary py-5">
              <div class="d-inline-flex flex-column align-items-center">
                <i class="bi bi-search" style="font-size:2rem; opacity:.6"></i>
                <div class="mt-1">Sin resultados</div>
              </div>
            </td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>

  {{-- Paginación --}}
  <div class="d-flex justify-content-between align-items-center mt-2 cli-pager">
    <div class="small text-secondary" id="cliPagerMeta" aria-live="polite">
      Mostrando {{ $clientes->firstItem() ?? 0 }}–{{ $clientes->lastItem() ?? 0 }} de {{ $clientes->total() }}.
    </div>
    {{ $clientes->onEachSide(1)->withQueryString()->links('pagination::bootstrap-5') }}
  </div>
</div>
@endsection

@push('scripts')
<script>
  // ======= Fila clicable (enter/click) =======
  (function attachRowHandlers(){
    document.querySelectorAll('.cli-row').forEach(tr=>{
      const href = tr.dataset.href;
      if(!href) return;
      tr.addEventListener('click', e=>{
        if(e.target.closest('a,button')) return;
        window.location.href = href;
      });
      tr.addEventListener('keydown', e=>{
        if(e.key === 'Enter'){ window.location.href = href; }
      });
    });
  })();

  // ======= Resaltado de coincidencias (múltiples palabras) =======
  (function highlightMatches(){
    const body = document.getElementById('cliBody');
    if(!body) return;
    const q = (body.dataset.q || '').trim();
    if(!q) return;

    const parts = Array.from(new Set(q.split(/\s+/).filter(Boolean)));
    if(!parts.length) return;
    const esc = parts.map(p => p.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'));
    const rx = new RegExp('(' + esc.join('|') + ')', 'ig');

    body.querySelectorAll('td').forEach(td=>{
      if(td.querySelector('a,button')) return;
      const txt = td.textContent;
      if(!txt) return;
      td.innerHTML = txt.replace(rx, '<mark class="cli-hit">$1</mark>');
    });
  })();

  // ======= Copiar tabla al portapapeles =======
  document.getElementById('btnCopy')?.addEventListener('click', async ()=>{
    try{
      const table = document.getElementById('cliTable');
      const rows = [...table.querySelectorAll('tbody tr')];
      if(!rows.length) return;
      const head = [...table.querySelectorAll('thead th')].map(th=>th.innerText.trim()).slice(0,4);
      const data = rows.map(r => {
        const tds = r.querySelectorAll('td');
        return [tds[0]?.innerText.trim(), tds[1]?.innerText.trim(), tds[2]?.innerText.trim(), tds[3]?.innerText.trim()];
      });
      const lines = [head.join('\t'), ...data.map(arr=>arr.join('\t'))].join('\n');
      await navigator.clipboard.writeText(lines);
      alert('Tabla copiada al portapapeles.');
    }catch(e){ alert('No se pudo copiar.'); }
  });

  // ======= Descargar CSV (esta hoja) =======
  document.getElementById('btnCsv')?.addEventListener('click', ()=>{
    const table = document.getElementById('cliTable');
    const rows = [...table.querySelectorAll('tbody tr')];
    if(!rows.length) return;
    const head = [...table.querySelectorAll('thead th')].map(th=>th.innerText.trim()).slice(0,4);
    const csvEsc = v => `"${(v??'').toString().replace(/"/g,'""')}"`;
    const data = rows.map(r => {
      const tds = r.querySelectorAll('td');
      return [tds[0]?.innerText, tds[1]?.innerText, tds[2]?.innerText, tds[3]?.innerText].map(csvEsc).join(',');
    });
    const csv = head.map(csvEsc).join(',') + '\n' + data.join('\n');
    const blob = new Blob([csv], {type:'text/csv;charset=utf-8;'});
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url; a.download = 'clientes_hoja.csv';
    document.body.appendChild(a); a.click();
    a.remove(); URL.revokeObjectURL(url);
  });
</script>
@endpush