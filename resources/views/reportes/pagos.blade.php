{{-- resources/views/reportes/pagos.blade.php --}}
@extends('layouts.app')
@section('title','Reportes ▸ Pagos')
@section('crumb','Reportes ▸ Pagos')

@push('head')
<style>
  .filters .form-control,.filters .form-select{ background:var(--surface) }
  .toolbar{ display:flex; flex-wrap:wrap; gap:.5rem; align-items:center }
  .toolbar .spacer{ flex:1 1 auto }
  .tiny{ font-size:.9rem; color:var(--muted) }
  .skeleton{ border:1px dashed var(--border); border-radius:var(--radius); padding:1rem; color:var(--muted); text-align:center }

  /* chips de rango rápido */
  .quick-range .btn{
    border:1px solid var(--border); background:var(--surface);
    border-radius:999px; padding:.25rem .6rem
  }
  .quick-range .btn:hover{ background:var(--surface-2) }

  /* ===== tabla ===== */
  .rpt-pagos .table thead th{
    position:sticky; top:0; z-index:1;
    background:color-mix(in oklab,var(--surface-2) 55%,transparent); color:var(--ink)
  }
  [data-theme="dark"] .rpt-pagos .table thead th{
    background:color-mix(in oklab,var(--surface-2) 40%,transparent)
  }
  .rpt-pagos .table tbody tr:nth-child(even){
    background:color-mix(in oklab,var(--surface-2) 22%,transparent)
  }
  .rpt-pagos .page-link{
    border-color:var(--border); background:var(--surface); color:var(--ink)
  }
  .rpt-pagos .page-link:hover{
    background:color-mix(in oklab,var(--brand) 10%,transparent);
    border-color:color-mix(in oklab,var(--brand) 30%,transparent)
  }
  .rpt-pagos .page-item.active .page-link{ background:var(--brand); border-color:var(--brand); color:#fff }
  .rpt-pagos .page-item.disabled .page-link{ color:var(--muted); background:var(--surface) }
</style>
@endpush

@section('content')
<div class="card pad">

  {{-- ===== Filtros ===== --}}
  <form id="filtros" class="row g-2 align-items-end filters">
    <div class="col-6 col-md-2">
      <label class="form-label">Desde</label>
      <input type="date" name="from" class="form-control" value="{{ $from }}">
    </div>
    <div class="col-6 col-md-2">
      <label class="form-label">Hasta</label>
      <input type="date" name="to" class="form-control" value="{{ $to }}">
    </div>

    <div class="col-12 col-md-3">
      <label class="form-label d-flex align-items-center justify-content-between">
        <span>Gestor</span>
        <span class="quick-range d-none d-md-inline-flex gap-1">
          <button type="button" class="btn btn-sm" data-range="hoy">Hoy</button>
          <button type="button" class="btn btn-sm" data-range="7">7d</button>
          <button type="button" class="btn btn-sm" data-range="30">30d</button>
        </span>
      </label>
      <input type="text" name="gestor" class="form-control" placeholder="Nombre/alias" value="{{ $gestor }}">
    </div>

    <div class="col-6 col-md-2">
      <label class="form-label">Status</label>
      <input type="text" name="status" class="form-control" placeholder="ej. APLICADO" value="{{ $status }}">
    </div>

    <div class="col-12 col-md-3">
      <label class="form-label">Buscar</label>
      <div class="input-group">
        <input type="text" name="q" class="form-control" placeholder="DNI / Operación / Cliente" value="{{ $q }}">
        <button class="btn btn-outline-secondary" id="btnBuscar"><i class="bi bi-search"></i></button>
      </div>
    </div>

    <div class="col-12">
      <div class="toolbar mt-1">
        <button class="btn btn-outline-secondary" id="btnLimpiar" type="button">Limpiar</button>
        <div class="spacer"></div>
        <div id="summary" class="tiny"></div>
        <a class="btn btn-danger" id="btnExport" href="#"><i class="bi bi-download me-1"></i> Exportar</a>
      </div>
    </div>
  </form>

  <hr class="my-3">

  {{-- ===== Tabla ===== --}}
  <div id="tablaPagos">
    <div id="pagMeta" data-page="{{ $rows->currentPage() }}" data-total="{{ $rows->total() }}"></div>

    <div class="rpt-pagos">
      <div class="table-responsive">
        <table class="table align-middle">
          <thead>
            <tr>
              <th>DNI</th>
              <th>Operación</th>
              <th>Entidad</th>
              <th>Equipos</th>
              <th>Cliente</th>
              <th>Producto</th>
              <th>Moneda</th>
              <th>F. Pago</th>
              <th class="text-end">Monto Pagado</th>
              <th>Gestor</th>
            </tr>
          </thead>
          <tbody>
            @forelse($rows as $r)
              <tr>
                <td class="text-nowrap">{{ $r->dni }}</td>
                <td class="text-nowrap">{{ $r->operacion }}</td>
                <td>{{ $r->entidad }}</td>
                <td>{{ $r->equipos }}</td>
                <td>{{ $r->nombre_cliente }}</td>
                <td>{{ $r->producto }}</td>
                <td>{{ $r->moneda }}</td>
                <td class="text-nowrap">{{ optional($r->fecha_de_pago)->format('Y-m-d') }}</td>
                <td class="text-end">{{ number_format((float)$r->pagado_en_soles, 2) }}</td>
                <td>{{ $r->gestor }}</td>
              </tr>
            @empty
              <tr><td colspan="10" class="text-secondary">Sin resultados.</td></tr>
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
@endsection

@push('scripts')
<script>
(function(){
  const $form      = document.getElementById('filtros');
  const $tabla     = document.getElementById('tablaPagos');
  const $btnBuscar = document.getElementById('btnBuscar');
  const $btnLimpiar= document.getElementById('btnLimpiar');
  const $btnExport = document.getElementById('btnExport');
  const $summary   = document.getElementById('summary');

  const baseUrl   = "{{ route('reportes.pagos') }}";
  const exportUrl = "{{ route('reportes.pagos.export') }}";

  function buildQuery(extra={}){
    const fd = new FormData($form), p = new URLSearchParams();
    for (const [k,v] of fd.entries()) if(v) p.set(k,v);
    for (const k in extra) if(Object.prototype.hasOwnProperty.call(extra,k) && extra[k]!=null) p.set(k, extra[k]);
    return p.toString();
  }

  function setQuickRange(days){
    const d = new Date();
    const end = d.toISOString().slice(0,10);
    if(days==='hoy'){
      $form.from.value = end; $form.to.value = end; return;
    }
    d.setDate(d.getDate()-(+days));
    const start = d.toISOString().slice(0,10);
    $form.from.value = start; $form.to.value = end;
  }

  async function loadData(url=null){
    try{
      if(!url){ url = baseUrl + '?' + buildQuery(); }
      $tabla.innerHTML = '<div class="skeleton">Cargando…</div>';

      // Traemos la página completa y extraemos #tablaPagos (igual que en PDP)
      const text = await fetch(url, {headers:{'X-Requested-With':'XMLHttpRequest'}}).then(r=>r.text());
      const doc  = new DOMParser().parseFromString(text, 'text/html');
      const frag = doc.querySelector('#tablaPagos');
      $tabla.innerHTML = frag ? frag.innerHTML : text;

      hookPagination(); updateExport(); updateSummary();
      history.replaceState(null,'', baseUrl + '?' + buildQuery());
    }catch(e){
      console.error(e);
      $tabla.innerHTML = '<div class="text-danger p-3">Ocurrió un error al cargar los datos.</div>';
    }
  }

  function hookPagination(){
    $tabla.querySelectorAll('.pagination a').forEach(a=>{
      a.addEventListener('click', ev=>{
        ev.preventDefault();
        loadData(a.getAttribute('href'));
      });
    });
  }

  function updateExport(){ $btnExport.href = exportUrl + '?' + buildQuery(); }

  function updateSummary(){
    const m = $tabla.querySelector('#pagMeta');
    $summary.textContent = m ? `Página ${m.dataset.page} · ${m.dataset.total} resultados` : '';
  }

  // eventos
  $btnBuscar.addEventListener('click', e=>{ e.preventDefault(); loadData(); });
  $btnLimpiar.addEventListener('click', ()=>{ $form.reset(); loadData(); });
  $form.querySelectorAll('input').forEach(el=>{
    el.addEventListener('keydown', e=>{ if(e.key==='Enter'){ e.preventDefault(); loadData(); }});
    el.addEventListener('change', ()=> updateExport());
  });

  document.querySelectorAll('.quick-range .btn').forEach(b=>{
    b.addEventListener('click', ()=>{ setQuickRange(b.dataset.range); loadData(); });
  });

  updateExport(); updateSummary();
})();
</script>
@endpush
