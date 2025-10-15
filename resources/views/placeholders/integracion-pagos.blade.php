@extends('layouts.app')

@section('title','Integración ▸ Subir Pagos')
@section('crumb','Integración ▸ Subir Pagos')

@push('head')
<style>
  .ui-compact .card.pad{ padding:14px 16px; border-radius:14px }
  .ui-compact .form-control,
  .ui-compact .form-select{ font-size:.92rem; padding:.4rem .6rem; height:auto; background:var(--surface); border-color:var(--border) }
  .ui-compact .form-control:focus,
  .ui-compact .form-select:focus{ border-color:var(--brand); box-shadow:0 0 0 .25rem color-mix(in oklab, var(--brand) 22%, transparent) }
  .ui-compact .btn{ --bs-btn-padding-y:.36rem; --bs-btn-padding-x:.75rem; --bs-btn-border-radius:.55rem; font-size:.92rem }
  .ui-compact .btn-primary{ background:var(--brand); border-color:var(--brand) }
  .ui-compact .btn-primary:hover{ background:color-mix(in oklab, var(--brand) 85%, black); border-color:color-mix(in oklab, var(--brand) 85%, black) }

  .upload-card a{ color:var(--brand) } .upload-card a:hover{ color:color-mix(in oklab, var(--brand) 85%, black) }

  .pill{display:inline-flex;align-items:center;gap:.35rem;border:1px solid var(--border);background:var(--surface);
        border-radius:999px;padding:.18rem .6rem;font-size:.8rem}
  .mini{font-size:.9rem;color:var(--muted)}
  .ok{color:#0a7a3d} .err{color:#b42318} .warn{color:#8a6a00}

  #precheckBoxPagos{ background:color-mix(in oklab, var(--surface-2) 35%, transparent); border:1px dashed var(--border); border-radius:12px; padding:.6rem .75rem }
</style>
@endpush

@section('content')
  {{-- ALERTAS --}}
  @if(session('ok'))
    <div class="alert alert-success"><i class="bi bi-check-circle me-1"></i>{!! nl2br(e(session('ok'))) !!}</div>
  @endif
  @if(session('warn'))
    <div class="alert alert-warning"><pre class="mb-0" style="white-space:pre-wrap">{{ session('warn') }}</pre></div>
  @endif
  @if($errors->any())
    <div class="alert alert-danger">{{ $errors->first() }}</div>
  @endif

  @php
    $ultimoLote = $ultimoLote ?? ($ultimoLotePropia ?? null);
    $pagos      = $pagos ?? ($pagosPropia ?? collect());
  @endphp

  <div class="ui-compact">
    {{-- Subida --}}
    <div class="card pad mb-3 upload-card">
      <h5 class="mb-2 d-flex align-items-center gap-2"><i class="bi bi-upload"></i><span>Subida de archivo</span></h5>
      <p class="text-secondary mb-2">
        Formato aceptado: <strong>CSV UTF-8</strong>, delimitado por <strong>coma</strong>.
        <a href="{{ route('integracion.pagos.template') }}">Descargar plantilla</a>.
      </p>

      <form method="POST" action="{{ route('integracion.pagos.import') }}" enctype="multipart/form-data" class="row g-2 align-items-end" id="formImportPagos">
        @csrf
        <div class="col-lg-7">
          <label class="form-label">Archivo CSV</label>
          <input type="file" name="archivo" id="csvFilePagos" class="form-control" accept=".csv,text/csv" required>
          <div class="form-text">
            Encabezados esperados:
            <span class="pill"><i class="bi bi-card-checklist"></i> Fecha</span>
            <span class="pill">DNI</span>
            <span class="pill">Nombre</span>
            <span class="pill">Operación</span>
            <span class="pill">Monto</span>
            <span class="pill">Agente</span>
            <span class="pill">Cosecha</span>
            <span class="pill">Cuenta_Recaudo</span>
            <span class="pill">Entidad Financiera</span>
          </div>
        </div>
        <div class="col-lg-3">
          <button class="btn btn-primary w-100" id="btnImportPagos" disabled>
            <i class="bi bi-cloud-upload me-1"></i> Importar
          </button>
        </div>

        {{-- Pre-check --}}
        <div class="col-12 d-none" id="precheckBoxPagos">
          <hr class="my-2">
          <div class="d-flex flex-wrap align-items-center gap-3">
            <div><i class="bi bi-check-circle-fill ok me-1"></i><span class="mini" id="hdrMsgPagos" aria-live="polite">Validando encabezados…</span></div>
            <div><i class="bi bi-123 warn me-1"></i><span class="mini" id="typeMsgPagos" aria-live="polite">Tipos por muestra: —</span></div>
          </div>
          <div class="table-responsive mt-2 d-none" id="issuesWrapPagos">
            <table class="table table-sm align-middle mb-0">
              <thead><tr><th>Fila</th><th>Columna</th><th>Valor</th><th>Detalle</th></tr></thead>
              <tbody id="issuesBodyPagos"></tbody>
            </table>
          </div>
        </div>
      </form>
    </div>

    {{-- Último lote --}}
    <div class="card pad">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <h5 class="mb-0 d-flex align-items-center gap-2"><i class="bi bi-clock-history"></i> <span>Último lote importado</span></h5>
        @if($ultimoLote)
          <span class="text-secondary small">
            Lote #{{ $ultimoLote->id }} · {{ $ultimoLote->created_at->format('Y-m-d H:i') }} · {{ $ultimoLote->total_registros }} registros
          </span>
        @endif
      </div>

      @if(!$ultimoLote)
        <div class="text-secondary">Aún no hay importaciones.</div>
      @else
        <div class="table-responsive">
          <table class="table align-middle">
            <thead>
              <tr>
                <th>Fecha</th>
                <th>DNI</th>
                <th>Operación</th>
                <th>Nombre</th>
                <th>Entidad Financiera</th>
                <th class="text-end">Monto</th>
                <th>Agente</th>
                <th>Cosecha</th>
                <th>Cuenta_Recaudo</th>
              </tr>
            </thead>
            <tbody>
              @forelse($pagos as $p)
                <tr>
                  <td class="text-nowrap">{{ optional($p->fecha)->format('Y-m-d') }}</td>
                  <td class="text-nowrap">{{ $p->dni }}</td>
                  <td class="text-nowrap">{{ $p->operacion }}</td>
                  <td>{{ $p->nombre_cliente }}</td>
                  <td>{{ $p->entidad }}</td>
                  <td class="text-end">{{ number_format((float)$p->monto_pagado, 2) }}</td>
                  <td>{{ $p->gestor }}</td>
                  <td>{{ $p->cosecha }}</td>
                  <td>{{ $p->cuenta_recaudo }}</td>
                </tr>
              @empty
                <tr><td colspan="9" class="text-secondary">Sin datos para mostrar.</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      @endif
    </div>
  </div>
@endsection

@push('scripts')
<script>
(function(){
  // Encabezados EXACTOS
  const HEADERS = ["Fecha","DNI","Nombre","Operación","Monto","Agente","Cosecha","Cuenta_Recaudo","Entidad Financiera"];

  const $file = document.getElementById('csvFilePagos');
  const $btn  = document.getElementById('btnImportPagos');
  const $box  = document.getElementById('precheckBoxPagos');
  const $hdr  = document.getElementById('hdrMsgPagos');
  const $typ  = document.getElementById('typeMsgPagos');
  const $wrap = document.getElementById('issuesWrapPagos');
  const $body = document.getElementById('issuesBodyPagos');

  function splitCSV(line, sep){
    const out=[]; let cur=''; let q=false;
    for(let i=0;i<line.length;i++){
      const c=line[i];
      if(c==='"'){ q=!q } else if(c===sep && !q){ out.push(cur); cur='' } else { cur+=c }
    }
    out.push(cur); return out.map(s=>s.replace(/^"|"$/g,'').trim());
  }
  function parseCSV(text){
    text=(text||'').replace(/\r/g,'');
    const lines=text.split(/\n+/).filter(Boolean);
    if(!lines.length) return {rows:[],sep:','};
    const sep=(lines[0].split(';').length > lines[0].split(',').length) ? ';' : ',';
    return {rows:lines.map(l=>splitCSV(l,sep)), sep};
  }
  const isNumber=v=>{ if(v===''||v==null) return true; const x=(v+'').replace(/\s/g,'').replace(/,/g,'.'); return /^-?\d+(\.\d+)?$/.test(x) }
  const parseDate=v=>{ if(!v) return null; const s=v.trim(); let m=s.match(/^(\d{4})-(\d{2})-(\d{2})$/); if(m) return s; m=s.match(/^(\d{2})\/(\d{2})\/(\d{4})$/); if(m) return `${m[3]}-${m[2]}-${m[1]}`; return null }
  const isDate=v=> v==='' || parseDate(v)!=null;

  $file?.addEventListener('change', ev=>{
    const file = ev.target.files?.[0];
    if(!file){ $btn.disabled=true; return }
    const reader=new FileReader();
    reader.onload=e=>{
      const {rows}=parseCSV(e.target.result||'');
      if(!rows.length){ $btn.disabled=true; return }

      $box.classList.remove('d-none'); $body.innerHTML=''; $wrap.classList.add('d-none');

      const header=rows[0];
      const missing=HEADERS.filter(h=>!header.includes(h));
      const extra=header.filter(h=>!HEADERS.includes(h));
      const headerOk=missing.length===0;

      $hdr.innerHTML = headerOk
        ? `Encabezados: OK (<span class="ok">${header.length}</span>)`
        : `Encabezados: faltan <span class="err">${missing.join(', ')||'-'}</span>${extra.length?`, extra: <span class='warn'>${extra.join(', ')}</span>`:''}`;

      let issues=[], sampled=0;
      for(let r=1; r<Math.min(rows.length,51); r++){
        const row=rows[r]; if(!row||!row.length) continue; sampled++;
        HEADERS.forEach((h,idx)=>{
          const val=(row[idx]??'').trim(); let ok=true, detail='';
          if(h==='Fecha'){ ok=isDate(val); if(!ok) detail='Fecha inválida. Use YYYY-MM-DD o DD/MM/YYYY' }
          else if(h==='Monto'){ ok=isNumber(val); if(!ok) detail='Número inválido' }
          if(!ok){ issues.push({r:r+1,col:h,val,detail}) }
        });
      }

      $typ.textContent = `Tipos por muestra: ${sampled} fila(s) verificadas, ${issues.length} posible(s) problema(s)`;
      if(issues.length){
        $wrap.classList.remove('d-none');
        $body.innerHTML = issues.slice(0,80).map(it=>`<tr><td>${it.r}</td><td>${it.col}</td><td>${(it.val||'').replace(/</g,'&lt;')}</td><td>${it.detail}</td></tr>`).join('');
      }
      $btn.disabled = !headerOk;
    };
    reader.readAsText(file,'UTF-8');
  });
})();
</script>
@endpush
