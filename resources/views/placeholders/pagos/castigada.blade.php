{{-- resources/views/placeholders/pagos/castigada.blade.php --}}
@push('head')
<style>
  /* ====== Compacto + paleta verde/azul ====== */
  .ui-compact .card.pad{ padding:14px 16px; border-radius:14px }
  .ui-compact .form-control,
  .ui-compact .form-select{ font-size:.92rem; padding:.4rem .6rem; height:auto; background:var(--surface); border-color:var(--border) }
  .ui-compact .form-control:focus,
  .ui-compact .form-select:focus{ border-color:var(--brand); box-shadow:0 0 0 .25rem color-mix(in oklab, var(--brand) 22%, transparent) }
  .ui-compact .btn{ --bs-btn-padding-y:.36rem; --bs-btn-padding-x:.75rem; --bs-btn-border-radius:.55rem; font-size:.92rem }
  .ui-compact .btn-primary{ background:var(--brand); border-color:var(--brand) }
  .ui-compact .btn-primary:hover{ background:color-mix(in oklab, var(--brand) 85%, black); border-color:color-mix(in oklab, var(--brand) 85%, black) }

  /* Vínculos con marca */
  .upload-card a{ color:var(--brand) }
  .upload-card a:hover{ color:color-mix(in oklab, var(--brand) 85%, black) }

  /* Pastillas y microtexto */
  .pill{display:inline-flex;align-items:center;gap:.35rem;border:1px solid var(--border);background:var(--surface);
        border-radius:999px;padding:.18rem .6rem;font-size:.8rem}
  .mini{font-size:.9rem;color:var(--muted)}
  .ok{color:#0a7a3d} .err{color:#b42318} .warn{color:#8a6a00}

  /* Tabla compacta + encabezado con sutil azul */
  .table> :not(caption)>*>*{ padding:.52rem .65rem }
  .spec .table thead th{
    color:var(--ink);
    background:color-mix(in oklab, var(--brand) 9%, #fff);
    border-bottom:1px solid var(--border);
    text-transform:uppercase; letter-spacing:.3px; font-size:.8rem;
  }
  .spec .table tbody td{ color:var(--ink) }

  /* Etiquetas <code> con acento verde */
  .spec code{
    background:color-mix(in oklab, var(--accent) 12%, transparent);
    color:color-mix(in oklab, var(--accent) 80%, black);
    padding:.05rem .35rem; border-radius:6px
  }

  /* Caja de pre-check */
  #precheckBoxCCC{ background:color-mix(in oklab, var(--surface-2) 35%, transparent); border:1px dashed var(--border); border-radius:12px; padding:.6rem .75rem }
</style>
@endpush

<div class="ui-compact">
  {{-- === CAJA CUSCO ▸ CASTIGADA === --}}
  <div class="card pad mb-3 upload-card">
    <h5 class="mb-2 d-flex align-items-center gap-2">
      <i class="bi bi-upload"></i> <span>Subida de archivo (CAJA CUSCO ▸ CASTIGADA)</span>
    </h5>
    <p class="text-secondary mb-2">
      Formato aceptado: <strong>CSV UTF-8</strong>, delimitado por <strong>coma</strong> o <strong>punto y coma</strong>.
      <a href="{{ route('integracion.pagos.template.cusco') }}">Descargar plantilla</a>.
    </p>

    <form method="POST" action="{{ route('integracion.pagos.import.cusco') }}" enctype="multipart/form-data" class="row g-2 align-items-end" id="formImportCCC">
      @csrf
      <div class="col-lg-7">
        <label class="form-label">Archivo CSV</label>
        <input type="file" name="archivo" id="csvFileCCC" class="form-control" accept=".csv,text/csv" required>
        <div class="form-text">
          Encabezados esperados:
          <span class="pill">ABOGADO</span>
          <span class="pill">REGION</span>
          <span class="pill">AGENCIA</span>
          <span class="pill">TITULAR</span>
          <span class="pill">DNI</span>
          <span class="pill">PAGARE</span>
          <span class="pill">MONEDA</span>
          <span class="pill">TIPO_DE_RECUPERACION</span>
          <span class="pill">CONDICION</span>
          <span class="pill">CARTERA</span>
          <span class="pill">DEMANDA</span>
          <span class="pill">FECHA_DE_PAGO</span>
          <span class="pill">PAGO_EN_SOLES</span>
          <span class="pill">CONCATENAR</span>
          <span class="pill">FECHA</span>
          <span class="pill">PAGADO_EN_SOLES</span>
          <span class="pill">GESTOR</span>
          <span class="pill">STATUS</span>
        </div>
      </div>
      <div class="col-lg-3">
        <button class="btn btn-primary w-100" id="btnImportCCC" disabled>
          <i class="bi bi-cloud-upload me-1"></i> Importar
        </button>
      </div>

      {{-- Pre-check --}}
      <div class="col-12 d-none" id="precheckBoxCCC">
        <hr class="my-2">
        <div class="d-flex flex-wrap align-items-center gap-3">
          <div><i class="bi bi-check-circle-fill ok me-1"></i><span class="mini" id="hdrMsgCCC">Validando encabezados…</span></div>
          <div><i class="bi bi-123 warn me-1"></i><span class="mini" id="typeMsgCCC">Tipos por muestra: —</span></div>
        </div>
        <div class="table-responsive mt-2 d-none" id="issuesWrapCCC">
          <table class="table table-sm align-middle mb-0">
            <thead><tr><th>Fila</th><th>Columna</th><th>Valor</th><th>Detalle</th></tr></thead>
            <tbody id="issuesBodyCCC"></tbody>
          </table>
        </div>
      </div>
    </form>
  </div>

  {{-- Guía rápida CUSCO ▸ CASTIGADA --}}
  <div class="card pad mb-3">
    <details>
      <summary class="fw-semibold d-flex align-items-center gap-2"><i class="bi bi-journal-text"></i> Guía rápida de columnas (tipos y ejemplos)</summary>
      <div class="table-responsive mt-2 spec">
        <table class="table align-middle">
          <thead><tr><th>Columna</th><th>Tipo</th><th>Obligatoria</th><th>Ejemplo</th><th>Notas</th></tr></thead>
          <tbody>
            <tr><td><code>ABOGADO</code></td><td>Texto</td><td>No</td><td>Gisella</td><td>Libre.</td></tr>
            <tr><td><code>REGION</code></td><td>Texto</td><td>No</td><td>CUSCO</td><td>Nombre corto.</td></tr>
            <tr><td><code>AGENCIA</code></td><td>Texto</td><td>No</td><td>Agencia Tito</td><td>Libre.</td></tr>
            <tr><td><code>TITULAR</code></td><td>Texto</td><td>No</td><td>Juan Pérez</td><td>Libre.</td></tr>
            <tr><td><code>DNI</code></td><td>Texto</td><td>No</td><td>"00123456"</td><td>Guardar como <strong>texto</strong> para no perder ceros.</td></tr>
            <tr><td><code>PAGARE</code></td><td>Texto</td><td>No</td><td>106172131010198521</td><td>Alfanumérico permitido.</td></tr>
            <tr><td><code>MONEDA</code></td><td>Texto</td><td>No</td><td>SOLES</td><td>Usar <code>SOLES</code>/<code>PEN</code> o <code>USD</code>; también <code>S/</code> o <code>$</code>.</td></tr>
            <tr><td><code>TIPO_DE_RECUPERACION</code></td><td>Texto</td><td>No</td><td>CASTIGADO</td><td>Libre.</td></tr>
            <tr><td><code>CONDICION</code></td><td>Texto</td><td>No</td><td>CASTIGADO</td><td>Libre.</td></tr>
            <tr><td><code>CARTERA</code></td><td>Texto</td><td>No</td><td>RECURSOS PROPIOS</td><td>Libre.</td></tr>
            <tr><td><code>DEMANDA</code></td><td>Texto</td><td>No</td><td>Con Demanda</td><td>Libre.</td></tr>
            <tr><td><code>FECHA_DE_PAGO</code></td><td>Fecha</td><td>No</td><td>2025-01-31</td><td>Formatos válidos: <code>YYYY-MM-DD</code> o <code>DD/MM/YYYY</code>.</td></tr>
            <tr><td><code>PAGO_EN_SOLES</code></td><td>Número</td><td>No</td><td>1234.56</td><td>Monto recibido en S/.</td></tr>
            <tr><td><code>CONCATENAR</code></td><td>Texto</td><td>No</td><td>ABC-001</td><td>Libre.</td></tr>
            <tr><td><code>FECHA</code></td><td>Fecha</td><td>No</td><td>2025-01-31</td><td>Igual a <code>FECHA_DE_PAGO</code> en la mayoría de casos.</td></tr>
            <tr><td><code>PAGADO_EN_SOLES</code></td><td>Número</td><td>No</td><td>1234.56</td><td>Convertido a PEN si aplica.</td></tr>
            <tr><td><code>GESTOR</code></td><td>Texto</td><td>No</td><td>Ana</td><td>Libre.</td></tr>
            <tr><td><code>STATUS</code></td><td>Texto</td><td>No</td><td>APLICADO</td><td>Libre.</td></tr>
          </tbody>
        </table>
      </div>
      <div class="mt-2 small text-secondary">
        <i class="bi bi-lightbulb me-1"></i>En Excel/Sheets, formatea <strong>DNI</strong> como Texto y las fechas como <code>YYYY-MM-DD</code>.
      </div>
    </details>
  </div>

  {{-- Último lote (resumen) --}}
  <div class="card pad">
    <div class="d-flex justify-content-between align-items-center mb-2">
      <h5 class="mb-0 d-flex align-items-center gap-2"><i class="bi bi-clock-history"></i> <span>Último lote importado (Caja Cusco ▸ Castigada)</span></h5>
      @if($ultimoLoteCusco)
        <span class="text-secondary small">
          Lote #{{ $ultimoLoteCusco->id }} · {{ $ultimoLoteCusco->created_at->format('Y-m-d H:i') }} · {{ $ultimoLoteCusco->total_registros }} registros
        </span>
      @endif
    </div>

    @if(!$ultimoLoteCusco)
      <div class="text-secondary">Aún no hay importaciones de Caja Cusco ▸ Castigada.</div>
    @else
      <div class="table-responsive">
        <table class="table align-middle">
          <thead>
            <tr>
              <th>DNI</th><th>Pagaré</th><th>Titular</th><th>Moneda</th><th>Tipo Recup.</th><th>Cartera</th>
              <th>F. Pago</th><th class="text-end">Pago en S/</th><th>Gestor</th><th>Status</th>
            </tr>
          </thead>
          <tbody>
            @forelse($pagosCusco as $p)
            <tr>
              <td class="text-nowrap">{{ $p->dni }}</td>
              <td class="text-nowrap">{{ $p->pagare }}</td>
              <td>{{ $p->titular }}</td>
              <td>{{ $p->moneda }}</td>
              <td>{{ $p->tipo_de_recuperacion }}</td>
              <td>{{ $p->cartera }}</td>
              <td class="text-nowrap">{{ optional($p->fecha_de_pago)->format('Y-m-d') }}</td>
              <td class="text-end">{{ number_format((float)$p->pago_en_soles, 2) }}</td>
              <td>{{ $p->gestor }}</td>
              <td>{{ $p->status }}</td>
            </tr>
            @empty
              <tr><td colspan="10" class="text-secondary">Sin datos para mostrar.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    @endif
  </div>
</div>

@push('scripts')
<script>
(function(){
  const HEADERS=["ABOGADO","REGION","AGENCIA","TITULAR","DNI","PAGARE","MONEDA","TIPO_DE_RECUPERACION","CONDICION","CARTERA","DEMANDA","FECHA_DE_PAGO","PAGO_EN_SOLES","CONCATENAR","FECHA","PAGADO_EN_SOLES","GESTOR","STATUS"];
  const $file=document.getElementById('csvFileCCC');
  const $btn=document.getElementById('btnImportCCC');
  const $box=document.getElementById('precheckBoxCCC');
  const $hdr=document.getElementById('hdrMsgCCC');
  const $typ=document.getElementById('typeMsgCCC');
  const $wrap=document.getElementById('issuesWrapCCC');
  const $body=document.getElementById('issuesBodyCCC');

  function splitCSV(line,sep){ const out=[]; let cur=''; let q=false;
    for(let i=0;i<line.length;i++){ const c=line[i];
      if(c==='"'){ q=!q } else if(c===sep && !q){ out.push(cur); cur='' } else { cur+=c } }
    out.push(cur); return out.map(s=>s.replace(/^"|"$/g,'').trim());
  }
  function parseCSV(text){
    text=(text||'').replace(/\r/g,'');
    const lines=text.split(/\n+/).filter(Boolean);
    if(!lines.length) return {rows:[],sep:','};
    const sep=(lines[0].split(';').length > lines[0].split(',').length) ? ';' : ',';
    return {rows:lines.map(l=>splitCSV(l,sep)),sep};
  }
  const isNumber=v=>{ if(v===''||v==null) return true; const x=(v+'').replace(/\s/g,'').replace(/,/g,'.'); return /^-?\d+(\.\d+)?$/.test(x) }
  const parseDate=v=>{ if(!v) return null; const s=v.trim(); let m=s.match(/^(\d{4})-(\d{2})-(\d{2})$/); if(m) return s; m=s.match(/^(\d{2})\/(\d{2})\/(\d{4})$/); if(m) return `${m[3]}-${m[2]}-${m[1]}`; return null }
  const isDate=v=> v==='' || parseDate(v)!=null;

  $file?.addEventListener('change', ev=>{
    const file=ev.target.files?.[0];
    if(!file){ $btn.disabled=true; return }
    const reader=new FileReader();
    reader.onload=e=>{
      const {rows}=parseCSV(e.target.result||''); if(!rows.length){ $btn.disabled=true; return }
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
          if(h==='FECHA_DE_PAGO' || h==='FECHA'){ ok=isDate(val); if(!ok) detail='Fecha inválida. Use YYYY-MM-DD o DD/MM/YYYY' }
          else if(h==='PAGO_EN_SOLES' || h==='PAGADO_EN_SOLES'){ ok=isNumber(val); if(!ok) detail='Número inválido' }
          else if(h==='MONEDA'){ ok=!val || ['SOLES','PEN','USD','S/','$'].includes(val.toUpperCase()); if(!ok) detail='Use SOLES/PEN, USD, S/ o $' }
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