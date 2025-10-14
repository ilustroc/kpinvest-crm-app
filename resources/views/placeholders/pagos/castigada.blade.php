{{-- resources/views/placeholders/pagos/castigada.blade.php --}}
@push('head')
<style>
  /* ===== Mini UI + paleta ===== */
  .ui-compact .card.pad{ padding:14px 16px; border-radius:14px }
  .ui-compact .form-control,.ui-compact .form-select{ font-size:.9rem; padding:.38rem .55rem; height:auto; background:var(--surface); border-color:var(--border) }
  .ui-compact .form-control:focus,.ui-compact .form-select:focus{ border-color:var(--brand); box-shadow:0 0 0 .2rem color-mix(in oklab, var(--brand) 22%, transparent) }
  .ui-compact .btn{ --bs-btn-padding-y:.34rem; --bs-btn-padding-x:.7rem; --bs-btn-border-radius:.55rem; font-size:.9rem }
  .ui-compact .btn-primary{ background:var(--brand); border-color:var(--brand) }
  .ui-compact .btn-primary:hover{ background:color-mix(in oklab, var(--brand) 85%, black); border-color:color-mix(in oklab, var(--brand) 85%, black) }

  /* Links marca */
  .upload-card a{ color:var(--brand) }
  .upload-card a:hover{ color:color-mix(in oklab, var(--brand) 85%, black) }

  /* Dropzone */
  .dropzone{ display:flex; align-items:center; gap:.65rem; border:1.5px dashed color-mix(in oklab, var(--brand) 35%, var(--border));
             background:color-mix(in oklab, var(--brand) 6%, #fff); padding:.8rem .9rem; border-radius:12px; cursor:pointer; transition:.12s }
  .dropzone:hover{ background:color-mix(in oklab, var(--brand) 10%, #fff) }
  .dropzone .ico{ width:28px;height:28px;border-radius:8px; display:flex; align-items:center; justify-content:center;
                  background:color-mix(in oklab, var(--brand) 18%, #fff); color:var(--brand) }
  .dz-filename{ font-weight:600 }
  .dz-help{ font-size:.8rem; color:var(--muted) }

  /* Chips y microtexto */
  .pill{display:inline-flex;align-items:center;gap:.35rem;border:1px solid var(--border);background:var(--surface);
        border-radius:999px;padding:.15rem .55rem;font-size:.78rem}
  .mini{font-size:.85rem;color:var(--muted)}
  .ok{color:#0a7a3d} .err{color:#b42318} .warn{color:#8a6a00}

  /* Tablas compactas + encabezado sticky */
  .table> :not(caption)>*>*{ padding:.46rem .6rem }
  .table thead th{ font-size:.78rem; letter-spacing:.2px; text-transform:uppercase; color:var(--ink);
                   background:color-mix(in oklab, var(--brand) 9%, #fff); border-bottom:1px solid var(--border) }
  .sticky-head thead th{ position:sticky; top:0; z-index:2 }
  .spec .table tbody td{ color:var(--ink) }

  /* <code> con acento */
  .spec code{ background:color-mix(in oklab, var(--accent) 12%, transparent); color:color-mix(in oklab, var(--accent) 80%, black);
              padding:.05rem .35rem; border-radius:6px }

  /* Caja pre-check */
  #precheckBoxCCC{ background:color-mix(in oklab, var(--surface-2) 35%, transparent); border:1px dashed var(--border);
                   border-radius:12px; padding:.6rem .75rem }
  .tools .btn{ font-size:.8rem }
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

      {{-- Selector/Dropzone --}}
      <div class="col-xl-7">
        <input type="file" name="archivo" id="csvFileCCC" class="d-none" accept=".csv,text/csv" required>
        <label for="csvFileCCC" class="dropzone" id="dropzoneCCC">
          <div class="ico"><i class="bi bi-filetype-csv"></i></div>
          <div>
            <div class="dz-filename" id="dzNameCCC">Selecciona o arrastra tu CSV aquí</div>
            <div class="dz-help">CSV UTF-8 • Delimitado por coma o punto y coma</div>
          </div>
        </label>

        <div class="form-text mt-2">
          Encabezados esperados:
          <span class="pill">ABOGADO</span><span class="pill">REGION</span><span class="pill">AGENCIA</span>
          <span class="pill">TITULAR</span><span class="pill">DNI</span><span class="pill">PAGARE</span>
          <span class="pill">MONEDA</span><span class="pill">TIPO_DE_RECUPERACION</span><span class="pill">CONDICION</span>
          <span class="pill">CARTERA</span><span class="pill">DEMANDA</span><span class="pill">FECHA_DE_PAGO</span>
          <span class="pill">PAGO_EN_SOLES</span><span class="pill">CONCATENAR</span><span class="pill">FECHA</span>
          <span class="pill">PAGADO_EN_SOLES</span><span class="pill">GESTOR</span><span class="pill">STATUS</span>
        </div>
      </div>

      {{-- Acciones rápidas --}}
      <div class="col-xl-5 d-flex flex-wrap gap-2 tools">
        <button class="btn btn-primary flex-grow-1" id="btnImportCCC" disabled>
          <i class="bi bi-cloud-upload me-1"></i> Importar
        </button>
        <button class="btn btn-outline-secondary" type="button" id="btnClearCCC"><i class="bi bi-x-circle me-1"></i> Limpiar</button>
        <button class="btn btn-outline-primary" type="button" id="btnCopyHdrCCC"><i class="bi bi-clipboard-check me-1"></i> Copiar encabezados</button>
        <button class="btn btn-outline-danger d-none" type="button" id="btnExportIssuesCCC"><i class="bi bi-download me-1"></i> Errores CSV</button>
      </div>

      {{-- Pre-check --}}
      <div class="col-12 d-none" id="precheckBoxCCC">
        <hr class="my-2">
        <div class="d-flex flex-wrap align-items-center gap-3">
          <div><i class="bi bi-check-circle-fill ok me-1"></i><span class="mini" id="hdrMsgCCC">Validando encabezados…</span></div>
          <div><i class="bi bi-123 warn me-1"></i><span class="mini" id="typeMsgCCC">Tipos por muestra: —</span></div>
          <div class="mini">• <span id="countRowsCCC">0</span> fila(s) leídas</div>
          <div class="mini">• faltantes: <span class="err" id="missCountCCC">0</span> • extra: <span class="warn" id="extraCountCCC">0</span></div>
        </div>

        {{-- Vista previa (primeras filas) --}}
        <div class="table-responsive mt-2">
          <table class="table table-sm align-middle mb-0 sticky-head" id="previewWrapCCC" style="display:none">
            <thead id="previewHeadCCC"></thead>
            <tbody id="previewBodyCCC"></tbody>
          </table>
        </div>

        {{-- Detalle de issues --}}
        <div class="table-responsive mt-2 d-none" id="issuesWrapCCC">
          <table class="table table-sm align-middle mb-0 sticky-head">
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
        <i class="bi bi-lightbulb me-1"></i> En Excel/Sheets, formatea <strong>DNI</strong> como Texto y las fechas como <code>YYYY-MM-DD</code>.
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
      <div class="table-responsive sticky-head">
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
  const $file = document.getElementById('csvFileCCC');
  const $btn  = document.getElementById('btnImportCCC');
  const $box  = document.getElementById('precheckBoxCCC');
  const $hdr  = document.getElementById('hdrMsgCCC');
  const $typ  = document.getElementById('typeMsgCCC');
  const $wrap = document.getElementById('issuesWrapCCC');
  const $body = document.getElementById('issuesBodyCCC');
  const $countRows = document.getElementById('countRowsCCC');
  const $missCount = document.getElementById('missCountCCC');
  const $extraCount = document.getElementById('extraCountCCC');
  const $drop = document.getElementById('dropzoneCCC');
  const $dzName = document.getElementById('dzNameCCC');
  const $btnClear = document.getElementById('btnClearCCC');
  const $btnCopyHdr = document.getElementById('btnCopyHdrCCC');
  const $btnExportIssues = document.getElementById('btnExportIssuesCCC');

  const $prevWrap = document.getElementById('previewWrapCCC');
  const $prevHead = document.getElementById('previewHeadCCC');
  const $prevBody = document.getElementById('previewBodyCCC');

  let lastIssues = [];

  // CSV helpers
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

  function setName(n){ $dzName.textContent = n ? n : 'Selecciona o arrastra tu CSV aquí'; }

  function handleFile(file){
    if(!file){ $btn.disabled=true; setName(''); return }
    setName(file.name);
    const reader=new FileReader();
    reader.onload=e=>{
      const {rows}=parseCSV(e.target.result||'');
      $countRows.textContent = rows.length;
      if(!rows.length){ $btn.disabled=true; return }
      $box.classList.remove('d-none'); $body.innerHTML=''; $wrap.classList.add('d-none'); $btnExportIssues.classList.add('d-none');
      lastIssues = [];

      const header=rows[0];
      const missing=HEADERS.filter(h=>!header.includes(h));
      const extra=header.filter(h=>!HEADERS.includes(h));
      const headerOk=missing.length===0;

      $missCount.textContent = missing.length;
      $extraCount.textContent = extra.length;

      $hdr.innerHTML = headerOk
        ? `Encabezados: OK (<span class="ok">${header.length}</span>)`
        : `Encabezados: faltan <span class="err">${missing.join(', ')||'-'}</span>${extra.length?`, extra: <span class='warn'>${extra.join(', ')}</span>`:''}`;

      // Vista previa (hasta 5 filas)
      $prevHead.innerHTML = `<tr>${header.slice(0,20).map(h=>`<th>${h}</th>`).join('')}</tr>`;
      $prevBody.innerHTML = rows.slice(1,6).map(r=>`<tr>${header.slice(0,20).map((_,i)=>`<td>${(r[i]??'').toString().replace(/</g,'&lt;')}</td>`).join('')}</tr>`).join('');
      $prevWrap.style.display = 'table';

      // Validación por muestra (hasta 60)
      let issues=[], sampled=0;
      for(let r=1; r<Math.min(rows.length,61); r++){
        const row=rows[r]; if(!row||!row.length) continue; sampled++;
        HEADERS.forEach((h,idx)=>{
          const val=(row[idx]??'').trim(); let ok=true, detail='';
          if(h==='FECHA_DE_PAGO' || h==='FECHA'){ ok=isDate(val); if(!ok) detail='Fecha inválida. Use YYYY-MM-DD o DD/MM/YYYY' }
          else if(h==='PAGO_EN_SOLES' || h==='PAGADO_EN_SOLES'){ ok=isNumber(val); if(!ok) detail='Número inválido' }
          else if(h==='MONEDA'){ ok=!val || ['SOLES','PEN','USD','S/','$'].includes(val.toUpperCase()); if(!ok) detail='Use SOLES/PEN, USD, S/ o $' }
          if(!ok){ issues.push({r:r+1,col:h,val,detail}) }
        });
      }
      lastIssues = issues;
      $typ.textContent = `Tipos por muestra: ${sampled} fila(s) verificadas, ${issues.length} posible(s) problema(s)`;
      if(issues.length){
        $wrap.classList.remove('d-none');
        $btnExportIssues.classList.remove('d-none');
        $body.innerHTML = issues.slice(0,200).map(it=>`<tr><td>${it.r}</td><td>${it.col}</td><td>${(it.val||'').replace(/</g,'&lt;')}</td><td>${it.detail}</td></tr>`).join('');
      }
      $btn.disabled = !headerOk;
    };
    reader.readAsText(file,'UTF-8');
  }

  // Eventos: input + drop
  $file?.addEventListener('change', e => handleFile(e.target.files?.[0]));
  ['dragenter','dragover'].forEach(ev=> $drop.addEventListener(ev, e=>{ e.preventDefault(); e.stopPropagation(); $drop.classList.add('border-primary'); }));
  ;['dragleave','drop'].forEach(ev=> $drop.addEventListener(ev, e=>{ e.preventDefault(); e.stopPropagation(); $drop.classList.remove('border-primary'); }));
  $drop.addEventListener('drop', e => { const f = e.dataTransfer.files?.[0]; if(f){ $file.files = e.dataTransfer.files; handleFile(f); } });

  // Limpiar
  $btnClear?.addEventListener('click', ()=>{
    $file.value=''; setName(''); $btn.disabled=true; $box.classList.add('d-none'); $btnExportIssues.classList.add('d-none');
  });

  // Copiar encabezados
  $btnCopyHdr?.addEventListener('click', async ()=>{
    try{ await navigator.clipboard.writeText(HEADERS.join(',')); $btnCopyHdr.innerHTML='<i class="bi bi-clipboard-check-fill me-1"></i> Copiado'; setTimeout(()=>{$btnCopyHdr.innerHTML='<i class="bi bi-clipboard-check me-1"></i> Copiar encabezados'},1200); }catch(_){}
  });

  // Exportar issues
  $btnExportIssues?.addEventListener('click', ()=>{
    if(!lastIssues.length) return;
    const header = 'fila,columna,valor,detalle';
    const rows = lastIssues.map(i=>[i.r, i.col, (''+i.val).replaceAll('"','""'), i.detail.replaceAll('"','""')].map(v=>`"${v}"`).join(','));
    const blob = new Blob([header+'\n'+rows.join('\n')], {type:'text/csv;charset=utf-8;'});
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a'); a.href=url; a.download='errores_precheck.csv'; document.body.appendChild(a); a.click(); a.remove(); URL.revokeObjectURL(url);
  });

  // Spinner al importar
  document.getElementById('formImportCCC')?.addEventListener('submit', ()=>{
    $btn.disabled=true; $btn.innerHTML='<span class="spinner-border spinner-border-sm me-2"></span>Importando…';
  });
})();
</script>
@endpush