(function () {
  const $form = document.getElementById('filtros');
  const $btnLimpiar = document.getElementById('btnLimpiar');
  const $btnExport = document.getElementById('btnExport');
  const $summary = document.getElementById('summary');

  const baseUrl = $form.getAttribute('action');
  const exportUrl = document.querySelector('meta[name="rpt-cna-export"]').content;
  const facetsUrl = document.querySelector('meta[name="rpt-cna-facets"]').content;

  function buildQuery() {
    const fd = new FormData($form);
    const p = new URLSearchParams();
    for (const [k, v] of fd.entries()) {
      if (v !== null && v !== '') p.append(k, v);
    }
    return p.toString();
  }

  function updateExport() {
    $btnExport.href = exportUrl + '?' + buildQuery();
  }

  function updateSummary() {
    const m = document.querySelector('#pagMeta');
    $summary.textContent = m ? `Página ${m.dataset.page} · ${m.dataset.total} resultados` : '';
  }

  async function loadData(url = null) {
    const targetUrl = url ? url : (baseUrl + '?' + buildQuery());
    const tabla = document.getElementById('tablaCna');
    if (tabla) tabla.innerHTML = '<div class="skeleton">Cargando…</div>';

    const text = await fetch(targetUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest' } }).then(r => r.text());
    const doc = new DOMParser().parseFromString(text, 'text/html');
    const frag = doc.querySelector('#tablaCna');
    if (frag) document.getElementById('tablaCna').outerHTML = frag.outerHTML;

    hookPagination();
    updateExport();
    updateSummary();
    history.replaceState(null, '', baseUrl + '?' + buildQuery());
  }

  function hookPagination() {
    document.querySelectorAll('.pagination a').forEach(a => {
      a.addEventListener('click', ev => {
        ev.preventDefault();
        loadData(a.getAttribute('href'));
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

    apply?.addEventListener('click', () => {
      setLabel(msRoot);
      refreshFacetsAndMaybeLoad(true); // 👈 clave: facets dependientes
    });

    setLabel(msRoot);
  }

  document.querySelectorAll('[data-multiselect]').forEach(initMultiSelect);

  // ===== Facets dependientes =====
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

  async function refreshFacetsAndMaybeLoad(loadAfter = true) {
    const url = facetsUrl + '?' + buildQuery();
    const data = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } }).then(r => r.json());

    renderOptions(document.querySelector('[data-multiselect="estado"]'), 'estado', data.estados || []);
    renderOptions(document.querySelector('[data-multiselect="gestor"]'), 'gestor', data.gestores || []);
    renderOptions(document.querySelector('[data-multiselect="entidad"]'), 'entidad', data.entidades || []);

    document.querySelectorAll('[data-multiselect]').forEach(initMultiSelect);

    if (loadAfter) loadData();
  }

  // Eventos
  $btnLimpiar.addEventListener('click', () => {
    $form.from.value = $form.from.dataset.default || '';
    $form.to.value = $form.to.dataset.default || '';

    $form.querySelectorAll('input[type="checkbox"]').forEach(i => (i.checked = false));
    document.querySelectorAll('[data-multiselect]').forEach(ms => setLabel(ms));

    refreshFacetsAndMaybeLoad(true);
  });

  $form.from.addEventListener('change', () => refreshFacetsAndMaybeLoad(true));
  $form.to.addEventListener('change', () => refreshFacetsAndMaybeLoad(true));

  hookPagination();
  updateExport();
  updateSummary();
})();
