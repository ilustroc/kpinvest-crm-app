// public/js/reportes/pagos.js
(function () {
  const $form = document.getElementById('filtros');
  const $btnBuscar = document.getElementById('btnBuscar');
  const $btnLimpiar = document.getElementById('btnLimpiar');
  const $btnExport = document.getElementById('btnExport');
  const $summary = document.getElementById('summary');

  if (!$form) return;

  const baseUrl = $form.getAttribute('action') || location.pathname;
  const exportUrl = document.querySelector('meta[name="rpt-pagos-export"]')?.content || '';
  const facetsUrl = document.querySelector('meta[name="rpt-pagos-facets"]')?.content || '';

  function escapeHtml(str) {
    return String(str).replace(/[&<>"']/g, s => ({
      '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'
    }[s]));
  }

  // Query builders
  function buildQuery() {
    const fd = new FormData($form);
    const p = new URLSearchParams();
    for (const [k, v] of fd.entries()) {
      if (v !== null && v !== '') p.append(k, v);
    }
    return p.toString();
  }

  function buildFacetParams() {
    const p = new URLSearchParams();

    // fechas
    p.set('from', $form.from?.value || '');
    p.set('to', $form.to?.value || '');

    // q
    const q = ($form.q?.value || '').trim();
    if (q) p.set('q', q);

    // arrays seleccionados (gestor[], entidad[], cosecha[])
    ['gestor', 'entidad', 'cosecha'].forEach(name => {
      $form.querySelectorAll(`input[name="${name}[]"]:checked`).forEach(i => {
        p.append(`${name}[]`, i.value);
      });
    });

    return p.toString();
  }

  function updateExport() {
    if (!$btnExport || !exportUrl) return;
    $btnExport.href = exportUrl + '?' + buildQuery();
  }

  function updateSummary() {
    if (!$summary) return;
    const m = document.querySelector('#pagMeta');
    $summary.textContent = m ? `Página ${m.dataset.page} · ${m.dataset.total} resultados` : '';
  }

  // Ajax table load
  async function loadData(url = null) {
    const host = document.getElementById('tablaPagos');
    if (!host) {
      console.error('No existe #tablaPagos en el HTML');
      return;
    }

    const targetUrl = url ? url : (baseUrl + '?' + buildQuery());
    host.innerHTML = '<div class="skeleton">Cargando…</div>';

    const text = await fetch(targetUrl, {
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    }).then(r => r.text());

    const doc = new DOMParser().parseFromString(text, 'text/html');
    const frag = doc.querySelector('#tablaPagos');

    if (!frag) {
      host.innerHTML = '<div class="text-danger p-3">No se pudo renderizar #tablaPagos.</div>';
      return;
    }

    host.outerHTML = frag.outerHTML;

    hookPagination();
    updateExport();
    updateSummary();

    history.replaceState(null, '', baseUrl + '?' + buildQuery());
  }

  function hookPagination() {
    document.querySelectorAll('#tablaPagos .pagination a').forEach(a => {
      a.addEventListener('click', ev => {
        ev.preventDefault();
        loadData(a.getAttribute('href'));
      });
    });
  }

  // Multiselect (Excel-like)
  function getSelected(name) {
    return [...$form.querySelectorAll(`input[name="${name}[]"]:checked`)].map(i => i.value);
  }

  function setLabel(msRoot) {
    const title = msRoot.dataset.title || 'Filtro';
    const empty = msRoot.dataset.empty || 'Todos';
    const btn = msRoot.querySelector('[data-ms-button]');
    if (!btn) return;

    const checked = msRoot.querySelectorAll('input[type="checkbox"]:checked').length;
    btn.textContent = checked ? `${title} (${checked})` : `${title}: ${empty}`;
  }

  function closeDropdown(msRoot) {
    const btn = msRoot.querySelector('[data-ms-button]');
    try {
      if (window.bootstrap && btn) {
        window.bootstrap.Dropdown.getOrCreateInstance(btn).hide();
      }
    } catch (e) {}
  }

  function initMultiSelect(msRoot) {
    if (!msRoot) return;
    if (msRoot.dataset.inited === '1') { setLabel(msRoot); return; }
    msRoot.dataset.inited = '1';

    const search = msRoot.querySelector('[data-ms-search]');
    const list = msRoot.querySelector('[data-ms-list]');
    const clear = msRoot.querySelector('[data-ms-clear]');
    const apply = msRoot.querySelector('[data-ms-apply]');

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

    // IMPORTANTE: aplicar = recargar tabla + refrescar facets cascada
    apply?.addEventListener('click', async () => {
      setLabel(msRoot);
      closeDropdown(msRoot);
      await loadData();
      await refreshFacets(false);
    });

    setLabel(msRoot);
  }

  document.querySelectorAll('[data-multiselect]').forEach(initMultiSelect);

  // Facets cascada (por fecha + filtros actuales)
  function renderOptions(msRoot, name, options) {
    if (!msRoot) return;

    const selected = new Set(getSelected(name));
    const list = msRoot.querySelector('[data-ms-list]');
    if (!list) return;

    if (!options || !options.length) {
      list.innerHTML = `<div class="text-muted small px-1">Sin opciones en este rango.</div>`;
      setLabel(msRoot);
      return;
    }

    // Si una selección ya no existe, se “cae” sola porque no aparecerá checkbox.
    list.innerHTML = options.map(v => {
      const vv = String(v);
      const checked = selected.has(vv) ? 'checked' : '';
      return `
        <label class="ms-item">
          <input class="form-check-input" type="checkbox" name="${name}[]" value="${escapeHtml(vv)}" ${checked}>
          <span class="ms-text">${escapeHtml(vv)}</span>
        </label>
      `;
    }).join('');

    setLabel(msRoot);
  }

  async function refreshFacets(loadAfter = false) {
    if (!facetsUrl) {
      if (loadAfter) loadData();
      return;
    }

    const url = facetsUrl + '?' + buildFacetParams();
    const data = await fetch(url, {
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    }).then(r => r.json());

    renderOptions(document.querySelector('[data-multiselect="gestor"]'), 'gestor', data.gestores || []);
    renderOptions(document.querySelector('[data-multiselect="entidad"]'), 'entidad', data.entidades || []);
    renderOptions(document.querySelector('[data-multiselect="cosecha"]'), 'cosecha', data.cosechas || []);

    // re-etiquetar (no reiniciar handlers)
    document.querySelectorAll('[data-multiselect]').forEach(setLabel);

    updateExport();

    if (loadAfter) await loadData();
  }

  // Eventos generales
  $btnBuscar?.addEventListener('click', e => {
    e.preventDefault();
    loadData();
  });

  $btnLimpiar?.addEventListener('click', async () => {
    // reset fechas al default
    if ($form.from) $form.from.value = $form.from.dataset.default || '';
    if ($form.to) $form.to.value = $form.to.dataset.default || '';
    if ($form.q) $form.q.value = '';

    // limpia checks
    $form.querySelectorAll('input[type="checkbox"]').forEach(i => (i.checked = false));

    await refreshFacets(true); // refresca listas y carga tabla
  });

  $form.from?.addEventListener('change', () => refreshFacets(true));
  $form.to?.addEventListener('change', () => refreshFacets(true));

  $form.q?.addEventListener('keydown', e => {
    if (e.key === 'Enter') {
      e.preventDefault();
      loadData();
    }
  });

  // Inicial
  hookPagination();
  updateExport();
  updateSummary();
})();
