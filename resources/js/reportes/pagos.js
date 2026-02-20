// resources/js/reportes/pagos.js
(() => {
  const $form = document.getElementById('filtros');
  if (!$form) return;

  const $btnLimpiar = document.getElementById('btnLimpiar');
  const $btnExport  = document.getElementById('btnExport');
  const $summary    = document.getElementById('summary');
  const $btnBuscar  = document.getElementById('btnBuscar');

  const baseUrl   = $form.getAttribute('action');
  const exportUrl = document.querySelector('meta[name="rpt-pagos-export"]')?.content || '';
  const facetsUrl = document.querySelector('meta[name="rpt-pagos-facets"]')?.content || '';

  function buildQuery() {
    const fd = new FormData($form);
    const p = new URLSearchParams();
    for (const [k, v] of fd.entries()) {
      if (v !== null && v !== '') p.append(k, v);
    }
    return p.toString();
  }

  function updateExport() {
    if (!$btnExport || !exportUrl) return;
    const q = buildQuery();
    $btnExport.href = q ? (exportUrl + '?' + q) : exportUrl;
  }

  function updateSummary() {
    const m = document.querySelector('#pagMeta');
    if (!$summary) return;
    $summary.textContent = (m && m.dataset.page && m.dataset.total)
      ? `Página ${m.dataset.page} · ${m.dataset.total} resultados`
      : '';
  }

  async function loadData(url = null) {
    const targetUrl = url ? url : (baseUrl + (buildQuery() ? `?${buildQuery()}` : ''));
    const tabla = document.getElementById('tablaPagos');
    if (tabla) tabla.innerHTML = `<div class="pagos-skeleton">Cargando…</div>`;

    const text = await fetch(targetUrl, {
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    }).then(r => r.text());

    const doc = new DOMParser().parseFromString(text, 'text/html');
    const frag = doc.querySelector('#tablaPagos');
    if (frag) document.getElementById('tablaPagos').outerHTML = frag.outerHTML;

    hookPagination();
    updateExport();
    updateSummary();

    const q = buildQuery();
    history.replaceState(null, '', baseUrl + (q ? `?${q}` : ''));
  }

  function hookPagination() {
    document.querySelectorAll('#tablaPagos nav a[href]').forEach(a => {
      a.addEventListener('click', (ev) => {
        ev.preventDefault();
        loadData(a.getAttribute('href'));
      });
    });
  }

  // -------------------------
  // MultiSelect (igual CNA)
  // -------------------------
  function getSelected(name) {
    return [...$form.querySelectorAll(`input[name="${name}[]"]:checked`)].map(i => i.value);
  }

  function setLabel(msRoot) {
    const title = msRoot.dataset.title || 'Filtro';
    const empty = msRoot.dataset.empty || 'Todos';
    const btn = msRoot.querySelector('[data-ms-button]');
    const checked = msRoot.querySelectorAll('input[type="checkbox"]:checked').length;
    if (!btn) return;

    const label = checked ? `${title} (${checked})` : `${title}: ${empty}`;
    const span = btn.querySelector('span');
    if (span) span.textContent = label;
    else btn.textContent = label;
  }

  function closeAllMenus(except = null) {
    document.querySelectorAll('[data-multiselect] [data-ms-menu]').forEach(menu => {
      if (except && menu === except) return;
      menu.classList.add('hidden');
    });
  }

  function initMultiSelect(msRoot) {
    if (msRoot.dataset.inited === '1') { setLabel(msRoot); return; }
    msRoot.dataset.inited = '1';

    const btn    = msRoot.querySelector('[data-ms-button]');
    const menu   = msRoot.querySelector('[data-ms-menu]');
    const search = msRoot.querySelector('[data-ms-search]');
    const list   = msRoot.querySelector('[data-ms-list]');
    const clear  = msRoot.querySelector('[data-ms-clear]');
    const apply  = msRoot.querySelector('[data-ms-apply]');

    btn?.addEventListener('click', (e) => {
      e.preventDefault();
      const willOpen = menu.classList.contains('hidden');
      closeAllMenus(menu);
      if (willOpen) {
        menu.classList.remove('hidden');
        setTimeout(() => search?.focus(), 0);
      } else {
        menu.classList.add('hidden');
      }
    });

    search?.addEventListener('input', () => {
      const q = (search.value || '').toLowerCase();
      list?.querySelectorAll('.ms-item').forEach(item => {
        item.style.display = item.innerText.toLowerCase().includes(q) ? '' : 'none';
      });
    });

    clear?.addEventListener('click', () => {
      msRoot.querySelectorAll('input[type="checkbox"]').forEach(i => (i.checked = false));
      setLabel(msRoot);
    });

    apply?.addEventListener('click', async () => {
      setLabel(msRoot);
      closeAllMenus();
      await refreshFacetsAndMaybeLoad(true);
    });

    setLabel(msRoot);
  }

  document.querySelectorAll('[data-multiselect]').forEach(initMultiSelect);

  document.addEventListener('click', (e) => {
    const inside = e.target.closest('[data-multiselect]');
    if (!inside) closeAllMenus();
  });

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') closeAllMenus();
  });

  // -------------------------
  // Facets dependientes
  // -------------------------
  function escapeHtml(str) {
    return String(str).replace(/[&<>"']/g, s => ({
      '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
    }[s]));
  }

  function renderOptions(msRoot, name, options) {
    if (!msRoot) return;

    const selected = new Set(getSelected(name));
    const list = msRoot.querySelector('[data-ms-list]');
    if (!list) return;

    if (!options.length) {
      list.innerHTML = `<div class="px-2 py-2 text-xs text-slate-500">Sin opciones en este rango.</div>`;
      setLabel(msRoot);
      return;
    }

    list.innerHTML = options.map(v => {
      const checked = selected.has(v) ? 'checked' : '';
      return `
        <label class="ms-item">
          <input type="checkbox" name="${name}[]" value="${escapeHtml(v)}" ${checked}>
          <span class="ms-text">${escapeHtml(v)}</span>
        </label>
      `;
    }).join('');

    setLabel(msRoot);
  }

  async function refreshFacetsAndMaybeLoad(loadAfter = true) {
    if (!facetsUrl) {
      if (loadAfter) await loadData();
      return;
    }

    const q = buildQuery();
    const url = facetsUrl + (q ? `?${q}` : '');
    const data = await fetch(url, {
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    }).then(r => r.json());

    renderOptions(document.querySelector('[data-multiselect="gestor"]'),  'gestor',  data.gestores  || []);
    renderOptions(document.querySelector('[data-multiselect="entidad"]'), 'entidad', data.entidades || []);
    renderOptions(document.querySelector('[data-multiselect="cosecha"]'), 'cosecha', data.cosechas || []);

    document.querySelectorAll('[data-multiselect]').forEach(initMultiSelect);

    if (loadAfter) await loadData();
  }

  // -------------------------
  // Eventos
  // -------------------------
  $btnLimpiar?.addEventListener('click', async () => {
    if ($form.from) $form.from.value = $form.from.dataset.default || '';
    if ($form.to)   $form.to.value   = $form.to.dataset.default || '';

    // limpiar q
    if ($form.q) $form.q.value = '';

    // limpiar checks
    $form.querySelectorAll('input[type="checkbox"]').forEach(i => (i.checked = false));
    document.querySelectorAll('[data-multiselect]').forEach(ms => setLabel(ms));

    await refreshFacetsAndMaybeLoad(true);
  });

  $form.from?.addEventListener('change', () => refreshFacetsAndMaybeLoad(true));
  $form.to?.addEventListener('change',   () => refreshFacetsAndMaybeLoad(true));

  // botón buscar => dispara carga (sin submit)
  $btnBuscar?.addEventListener('click', () => refreshFacetsAndMaybeLoad(true));

  // Enter en q => no recargar página
  $form.q?.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') {
      e.preventDefault();
      refreshFacetsAndMaybeLoad(true);
    }
  });

  // Init
  hookPagination();
  updateExport();
  updateSummary();
})();