{{-- resources/views/reportes/cna.blade.php --}}
@extends('layouts.app')
@section('title','Reportes ▸ CNA')
@section('crumb','Reportes ▸ CNA')

@push('head')
<style>
  .ui-compact .card.pad{padding:14px 16px;border-radius:14px}
  .ui-compact .form-control{font-size:.92rem;padding:.4rem .6rem;height:auto;background:var(--surface);border-color:var(--border)}
  .ui-compact .form-control:focus{border-color:var(--brand);box-shadow:0 0 0 .25rem color-mix(in oklab,var(--brand) 22%,transparent)}
  .ui-compact .btn{--bs-btn-padding-y:.36rem;--bs-btn-padding-x:.7rem;--bs-btn-border-radius:.55rem;font-size:.92rem}
  .filters .form-label{font-weight:600;color:var(--muted);font-size:.85rem;margin-bottom:.2rem}

  .rpt-cna .table{font-size:.93rem}
  .rpt-cna .table thead th{
    position:sticky; top:0; z-index:1;
    background:color-mix(in oklab,var(--surface-2) 55%,transparent);
    border-bottom:1px solid var(--border);
  }
  [data-theme="dark"] .rpt-cna .table thead th{background:color-mix(in oklab,var(--surface-2) 40%,transparent)}
  .rpt-cna .table> :not(caption)>*>*{padding:.5rem .6rem}
  .rpt-cna .table tbody tr:nth-child(even){background:color-mix(in oklab,var(--surface-2) 22%,transparent)}
  .rpt-cna .table tbody tr:hover{background:color-mix(in oklab,var(--brand) 10%,transparent)}
  .nowrap{white-space:nowrap}
  .text-mono{font-variant-numeric:tabular-nums}

  .skeleton{border:1px dashed var(--border);border-radius:12px;padding:1rem;color:var(--muted);text-align:center}
  .skeleton .spin{display:inline-block;width:1rem;height:1rem;border:.18rem solid var(--border);border-top-color:var(--brand);border-radius:50%;animation:sp 1s linear infinite;vertical-align:middle;margin-right:.4rem}
  @keyframes sp{to{transform:rotate(360deg)}}

  .pager-wrap{gap:.5rem}
  .tiny{font-size:.88rem;color:var(--muted)}
</style>
@endpush

@section('content')
<div class="ui-compact">
  <div class="card pad">
    {{-- Filtros --}}
    <form id="filtros" class="row g-2 align-items-end filters">
      <div class="col-6 col-md-2">
        <label class="form-label">Desde</label>
        <input type="date" name="from" class="form-control" value="{{ $from }}">
      </div>
      <div class="col-6 col-md-2">
        <label class="form-label">Hasta</label>
        <input type="date" name="to" class="form-control" value="{{ $to }}">
      </div>
      <div class="col-6 col-md-3">
        <label class="form-label">Estado</label>
        <input type="text" name="estado" class="form-control" placeholder="aprobado / pendiente / ..." value="{{ $estado }}">
      </div>
      <div class="col-6 col-md-2">
        <label class="form-label">Gestor</label>
        <input type="text" name="negociador" class="form-control" value="{{ $negociador ?? '' }}">
      </div>
      <div class="col-12 col-md-3">
        <label class="form-label">Buscar</label>
        <div class="input-group">
          <input type="text" name="q" class="form-control" placeholder="DNI / nro carta / operación / cuenta / titular" value="{{ $q }}">
          <button class="btn btn-outline-secondary" id="btnBuscar"><i class="bi bi-search"></i></button>
        </div>
      </div>

      <div class="col-12 d-flex align-items-center mt-1">
        <button class="btn btn-outline-secondary" id="btnLimpiar" type="button">Limpiar</button>
        <div class="ms-auto d-flex align-items-center gap-2 pager-wrap">
          <div class="tiny" id="summary" aria-live="polite"></div>
          <a class="btn btn-danger" id="btnExport" href="#"><i class="bi bi-download me-1"></i> Exportar</a>
        </div>
      </div>
    </form>

    <hr class="my-3">

    {{-- Tabla --}}
    <div id="tablaCna">
      <div id="pagMeta" data-page="{{ $rows->currentPage() }}" data-total="{{ $rows->total() }}"></div>

      <div class="rpt-cna">
        <div class="table-responsive">
          <table class="table table-sm align-middle">
            <thead>
              <tr>
                <th>Id</th>
                <th>Documento</th>
                <th>Cliente</th>
                <th>Cna_Nro</th>
                <th>Cna_Fec</th>
                <th>Fondo_Inv</th>
                <th>Año_Mes</th>
                <th class="text-end">Cna_Imp</th>
                <th>Nro_Cuenta</th>
                <th>Nro_Operación</th>
                <th>Gestor</th>
                <th>Estado</th>
                <th>Gen_Gestor</th>
                <th>Apr_Gestor</th>
              </tr>
            </thead>
            <tbody>
            @forelse($rows as $r)
              <tr>
                <td class="text-mono">{{ $r->id }}</td>
                <td class="nowrap text-mono">{{ $r->documento }}</td>
                <td class="nowrap">{{ $r->cliente }}</td>
                <td class="nowrap">{{ $r->cna_nro }}</td>
                <td class="nowrap text-mono">{{ $r->cna_fec }}</td>
                <td class="nowrap">{{ $r->fondo_inv }}</td>
                <td class="nowrap text-mono">{{ $r->anio_mes }}</td>
                <td class="text-end text-mono">{{ $r->cna_imp!==null ? number_format((float)$r->cna_imp,2) : '' }}</td>
                <td class="nowrap text-mono">{{ $r->nro_cuenta }}</td>
                <td class="nowrap text-mono">{{ $r->nro_operacion }}</td>
                <td class="nowrap">{{ $r->gestor }}</td>
                <td class="nowrap">{{ $r->estado }}</td>
                <td class="nowrap">{{ $r->gen_gestor }}</td>
                <td class="nowrap">{{ $r->apr_gestor }}</td>
              </tr>
            @empty
              <tr><td colspan="14" class="text-secondary">Sin resultados.</td></tr>
            @endforelse
            </tbody>
          </table>
        </div>

        <div class="d-flex justify-content-between align-items-center mt-2">
          <div class="small text-muted">
            Mostrando {{ $rows->firstItem() ?? 0 }}–{{ $rows->lastItem() ?? 0 }} de {{ $rows->total() }}.
          </div>
          {{ $rows->onEachSide(1)->withQueryString()->links('pagination::bootstrap-5') }}
        </div>
      </div>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
(function(){
  const $form = document.getElementById('filtros');
  const $tabla = document.getElementById('tablaCna');
  const $btnBuscar = document.getElementById('btnBuscar');
  const $btnLimpiar = document.getElementById('btnLimpiar');
  const $btnExport = document.getElementById('btnExport');
  const $summary = document.getElementById('summary');

  const baseUrl   = "{{ route('reportes.cna') }}";
  const exportUrl = "{{ route('reportes.cna.export') }}";

  function buildQuery(extra={}) {
    const fd = new FormData($form), p = new URLSearchParams();
    for (const [k,v] of fd.entries()) if(v) p.set(k,v);
    for (const k in extra) if(Object.prototype.hasOwnProperty.call(extra,k) && extra[k]!==undefined && extra[k]!==null) p.set(k,extra[k]);
    return p.toString();
  }

  async function loadData(url=null){
    try{
      let target;
      if(!url){ target = baseUrl + '?' + buildQuery({partial:1}); }
      else {
        const u = new URL(url, location.origin);
        u.searchParams.set('partial','1');
        target = u.pathname + '?' + u.searchParams.toString();
      }

      $tabla.setAttribute('aria-busy','true');
      $tabla.innerHTML = '<div class="skeleton"><span class="spin"></span> Cargando…</div>';

      const text = await fetch(target, {headers:{'X-Requested-With':'XMLHttpRequest'}}).then(r=>r.text());
      const doc  = new DOMParser().parseFromString(text, 'text/html');
      const frag = doc.querySelector('#tablaCna');
      $tabla.innerHTML = frag ? frag.innerHTML : text;
      $tabla.removeAttribute('aria-busy');

      hookPagination(); updateExport(); updateSummary();
      history.replaceState(null,'', baseUrl + '?' + buildQuery());
    }catch(e){
      console.error(e);
      $tabla.removeAttribute('aria-busy');
      $tabla.innerHTML = '<div class="text-danger p-3">Error al cargar.</div>';
    }
  }

  function hookPagination(){
    $tabla.querySelectorAll('.pagination a').forEach(a=>{
      a.addEventListener('click', e=>{ e.preventDefault(); loadData(a.href); });
    });
  }

  function updateExport(){ $btnExport.href = exportUrl + '?' + buildQuery(); }

  function updateSummary(){
    const m = $tabla.querySelector('#pagMeta');
    const page = m?.dataset.page ?? '';
    const total = m?.dataset.total ?? '';
    $summary.textContent = (page && total) ? `Página ${page} · ${total} resultado(s)` : '';
  }

  // Eventos
  $btnBuscar.addEventListener('click', e=>{ e.preventDefault(); loadData(); });
  $btnLimpiar.addEventListener('click', ()=>{ $form.reset(); loadData(); });
  $form.querySelectorAll('input').forEach(el=>{
    el.addEventListener('keydown', e=>{ if(e.key==='Enter'){ e.preventDefault(); loadData(); }});
    el.addEventListener('change', ()=> updateExport());
  });

  // Init
  updateExport(); updateSummary();
})();
</script>
@endpush
