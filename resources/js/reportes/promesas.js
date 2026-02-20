(function () {
  const $form = document.getElementById('filtros');
  const $btnLimpiar = document.getElementById('btnLimpiar');
  const $btnExport = document.getElementById('btnExport');
  const $summary = document.getElementById('summary');
  const $btnBuscar = document.getElementById('btnBuscar');
  const $qInput = $form.querySelector('input[name="q"]'); // Buscar DNI/Cliente

  const baseUrl = $form.getAttribute('action');
  const exportUrl = document.querySelector('meta[name="rpt-pdp-export"]').content;
  const facetsUrl = document.querySelector('meta[name="rpt-pdp-facets"]').content;

  function buildQuery(extra = {}) {
    const fd = new FormData($form);
    const p = new URLSearchParams();
    for (const [k, v] of fd.entries()) if (v !== null && v !== '') p.append(k, v);
    for (const k in extra) if (Object.prototype.hasOwnProperty.call(extra, k)) p.set(k, extra[k]);
    return p.toString();
  }

  function updateExport() {
    $btnExport.href = exportUrl + '?' + buildQuery();
  }

  function updateSummary() {
    const m = document.querySelector('#pagMeta');
    const page = m?.dataset.page || '';
    const total = m?.dataset.total || '';
    $summary.textContent = total ? `Página ${page} · ${total} resultados` : (page ? `Página ${page}` : '');
  }

  async function loadData(url = null) {
    const targetUrl = url ? url : (baseUrl + '?' + buildQuery({ partial: 1 }));

    const tabla = document.getElementById('tablaPdp');
    if (tabla) tabla.outerHTML = `<div id="tablaPdp"><div class="skeleton">Cargando…</div></div>`;

    const text = await fetch(targetUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest' } }).then(r => r.text());
    const doc = new DOMParser().parseFromString(text, 'text/html');
    const frag = doc.querySelector('#tablaPdp');

    if (frag) document.getElementById('tablaPdp').outerHTML = frag.outerHTML;

    hookPagination();
    updateExport();
    updateSummary();
    history.replaceState(null, '', baseUrl + '?' + buildQuery());
  }

  function hookPagination() {
    document.querySelectorAll('#tablaPdp .pagination a').forEach(a => {
      a.addEventListener('click', ev => {
        ev.preventDefault();
        const u = new URL(a.getAttribute('href'), location.origin);
        u.searchParams.set('partial', '1');
        loadData(u.pathname + '?' + u.searchParams.toString());
      });
    });
  }

  // ===== Multiselect =====
  function getSelected(name) {
    return [...$form.querySelectorAll(`input[name="${name}[]"]:checked`)].map(i => i.value);
  }

  function setLabel(msRoot) {
    const title = msRoot.dataset.title || 'Filtro';
    const empty = msRoot.dataset.empty || 'Todos';
    const btn = msRoot.querySelector('[data-ms-button]');
    const checked = msRoot.querySelectorAll('input[type="checkbox"]:checked').length;
    btn.textContent = checked ? `${title} (${checked})` : `${title}: ${empty}`;
  }

  function initMultiSelect(msRoot) {
    if (msRoot.dataset.inited === '1') { setLabel(msRoot); return; }
    msRoot.dataset.inited = '1';

    const search = msRoot.querySelector('[data-ms-search]');
    const list = msRoot.querySelector('[data-ms-list]');
    const clear = msRoot.querySelector('[data-ms-clear]');
    const apply = msRoot.querySelector('[data-ms-apply]');

    search?.addEventListener('input', () => {
      const q = (search.value || '').toLowerCase();
      list.querySelectorAll('.ms-item').forEach(item => {
        item.style.display = item.innerText.toLowerCase().includes(q) ? '' : 'none';
      });
    });

    clear?.addEventListener('click', () => {
      msRoot.querySelectorAll('input[type="checkbox"]').forEach(i => (i.checked = false));
      setLabel(msRoot);
    });

    apply?.addEventListener('click', async () => {
      setLabel(msRoot);
      await loadData();
      await refreshFacets(false); // cascada (opcional)
    });

    setLabel(msRoot);
  }

  document.querySelectorAll('[data-multiselect]').forEach(initMultiSelect);

  // ===== Facets =====
  function escapeHtml(str) {
    return String(str).replace(/[&<>"']/g, s => ({
      '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
    }[s]));
  }

  function renderOptions(msRoot, name, options) {
    const selected = new Set(getSelected(name));
    const list = msRoot.querySelector('[data-ms-list]');
    list.innerHTML = '';

    if (!options.length) {
      list.innerHTML = `<div class="text-muted small px-1">Sin opciones en este rango.</div>`;
      setLabel(msRoot);
      return;
    }

    list.innerHTML = options.map(v => {
      const checked = selected.has(v) ? 'checked' : '';
      return `
        <label class="ms-item">
          <input class="form-check-input" type="checkbox" name="${name}[]" value="${escapeHtml(v)}" ${checked}>
          <span class="ms-text">${escapeHtml(v)}</span>
        </label>
      `;
    }).join('');

    setLabel(msRoot);
  }

  async function refreshFacets(loadAfter = true) {
    const qs = buildQuery(); // incluye selecciones actuales
    const url = facetsUrl + '?' + qs;

    const data = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } }).then(r => r.json());

    renderOptions(document.querySelector('[data-multiselect="estado"]'), 'estado', data.estados || []);
    renderOptions(document.querySelector('[data-multiselect="tipo"]'), 'tipo', data.tipos || []);
    renderOptions(document.querySelector('[data-multiselect="entidad"]'), 'entidad', data.entidades || []);

    document.querySelectorAll('[data-multiselect]').forEach(initMultiSelect);

    if (loadAfter) await loadData();
  }

  // ===== Eventos =====
  $btnLimpiar.addEventListener('click', async () => {
    $form.from.value = $form.from.dataset.default || '';
    $form.to.value = $form.to.dataset.default || '';
    $form.querySelectorAll('input[type="checkbox"]').forEach(i => (i.checked = false));
    await refreshFacets(true);
  });

  // Buscar (DNI / Cliente)
  $btnBuscar?.addEventListener('click', async (e) => {
    e.preventDefault();

    // OJO: si quieres que también se actualicen las listas (facets) según lo buscado:
    try {
      await refreshFacets(true);
    } catch (err) {
      console.error(err);
      await loadData();
    }
  });

  // Enter en el input Buscar
  $qInput?.addEventListener('keydown', async (e) => {
    if (e.key === 'Enter') {
      e.preventDefault();
      try {
        await refreshFacets(true);
      } catch (err) {
        console.error(err);
        await loadData();
      }
    }
  });

  $form.from.addEventListener('change', () => refreshFacets(true));
  $form.to.addEventListener('change', () => refreshFacets(true));

  // Init
  hookPagination();
  updateExport();
  updateSummary();
})();
