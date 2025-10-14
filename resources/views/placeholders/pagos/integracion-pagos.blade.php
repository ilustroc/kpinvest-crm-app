@push('head')
<style>
  /* ===== Tabs compactas + scroll ===== */
  .tabs-wrap{ position:sticky; top:64px; z-index:3; background:var(--app-bg,transparent); margin-bottom:10px }
  @media (max-width: 992px){ .tabs-wrap{ top:56px } }

  .tabs{
    display:flex; gap:.5rem; padding:.25rem; border:1px solid var(--border);
    background:var(--surface); border-radius:999px; overflow:auto; scrollbar-width:none
  }
  .tabs::-webkit-scrollbar{ display:none }

  .tab-btn{
    position:relative; white-space:nowrap; cursor:pointer;
    padding:.42rem .85rem; border:1px solid transparent;
    background:transparent; border-radius:999px; font-weight:700; font-size:.92rem;
    color:var(--ink); transition:border-color .15s, background .15s, color .15s
  }
  .tab-btn:hover{
    border-color:color-mix(in oklab,var(--brand) 30%,transparent);
    background:color-mix(in oklab,var(--brand) 10%,transparent);
  }
  .tab-btn.active{
    color:var(--brand-ink);
    background:color-mix(in oklab,var(--brand) 18%,transparent);
    border-color:color-mix(in oklab,var(--brand) 40%,transparent);
  }
  .tab-btn:focus-visible{
    outline:3px solid color-mix(in oklab,var(--brand) 45%,transparent);
    outline-offset:2px;
  }

  .tab-panel{ display:none }
  .tab-panel.show{ display:block }

  /* Separación visual entre paneles */
  .tab-panel > .card,
  .tab-panel > .card.pad{ margin-top:.25rem }
</style>
@endpush

<div class="tabs-wrap">
  <div class="tabs" role="tablist" aria-label="Tipos de integración">
    <button class="tab-btn active" id="tab-propia" data-tab="propia" role="tab" aria-selected="true" aria-controls="panel-propia" tabindex="0">
      <i class="bi bi-file-spreadsheet me-1"></i> PROPIA
    </button>
    <button class="tab-btn" id="tab-cusco" data-tab="cusco" role="tab" aria-selected="false" aria-controls="panel-cusco" tabindex="-1">
      <i class="bi bi-bank me-1"></i> Caja Cusco ▸ Castigada
    </button>
    <button class="tab-btn" id="tab-extrajudicial" data-tab="extrajudicial" role="tab" aria-selected="false" aria-controls="panel-extrajudicial" tabindex="-1">
      <i class="bi bi-briefcase me-1"></i> Extrajudicial
    </button>
  </div>
</div>

{{-- Asegúrate de que existan estos contenedores en tu vista --}}
{{-- <div id="panel-propia" class="tab-panel show" role="tabpanel" aria-labelledby="tab-propia"> ... </div> --}}
{{-- <div id="panel-cusco" class="tab-panel" role="tabpanel" aria-labelledby="tab-cusco"> ... </div> --}}
{{-- <div id="panel-extrajudicial" class="tab-panel" role="tabpanel" aria-labelledby="tab-extrajudicial"> ... </div> --}}

@push('scripts')
<script>
(function(){
  const TKEY = 'pay.tab';
  const PARAM = 'tab';

  const tabs  = [...document.querySelectorAll('.tab-btn')];
  const panels = {
    propia: document.getElementById('panel-propia'),
    cusco: document.getElementById('panel-cusco'),
    extrajudicial: document.getElementById('panel-extrajudicial')
  };

  function setRovingTabindex(activeKey){
    tabs.forEach(b => b.tabIndex = (b.dataset.tab === activeKey ? 0 : -1));
  }

  function showPanel(key){
    Object.entries(panels).forEach(([k, el])=>{
      if(!el) return;
      el.classList.toggle('show', k === key);
      el.setAttribute('aria-hidden', k === key ? 'false' : 'true');
    });
  }

  function setActiveBtn(key){
    tabs.forEach(b=>{
      const on = (b.dataset.tab === key);
      b.classList.toggle('active', on);
      b.setAttribute('aria-selected', on ? 'true' : 'false');
    });
    setRovingTabindex(key);
  }

  function updateURL(key, replace){
    try{
      const url = new URL(window.location.href);
      url.searchParams.set(PARAM, key);
      if(replace) history.replaceState({}, '', url);
      else history.pushState({}, '', url);
    }catch(_){}
  }

  function activate(key, persist = false, push = false){
    if(!panels[key]) key = 'propia';
    setActiveBtn(key);
    showPanel(key);
    if(persist) localStorage.setItem(TKEY, key);
    if(push)    updateURL(key, false);
  }

  function initialKey(){
    const url = new URL(window.location.href);
    const q = (url.searchParams.get(PARAM) || '').trim();
    if(q && panels[q]) return q;
    const hash = (location.hash || '').replace(/^#tab=/,'');
    if(hash && panels[hash]) return hash;
    const mem = localStorage.getItem(TKEY);
    if(mem && panels[mem]) return mem;
    return 'propia';
  }

  // Click / teclado
  tabs.forEach((btn, idx)=>{
    btn.addEventListener('click', ()=> activate(btn.dataset.tab, true, true));
    btn.addEventListener('keydown', (e)=>{
      const key = e.key;
      if(key === 'Enter' || key === ' '){
        e.preventDefault();
        activate(btn.dataset.tab, true, true);
        return;
      }
      // Navegación con flechas
      let targetIdx = null;
      if(key === 'ArrowRight') targetIdx = (idx + 1) % tabs.length;
      if(key === 'ArrowLeft')  targetIdx = (idx - 1 + tabs.length) % tabs.length;
      if(key === 'Home')       targetIdx = 0;
      if(key === 'End')        targetIdx = tabs.length - 1;
      if(targetIdx !== null){
        e.preventDefault();
        tabs[targetIdx].focus();
      }
    });
  });

  // Back/forward del navegador
  window.addEventListener('popstate', ()=>{
    const url = new URL(window.location.href);
    const q = url.searchParams.get(PARAM);
    if(q && panels[q]) activate(q, false, false);
  });

  // Si hay errores visibles dentro de un panel, muéstralo al cargar
  function panelWithErrors(){
    for(const [k, el] of Object.entries(panels)){
      if(!el) continue;
      if(el.querySelector('.is-invalid, .alert-danger, [data-error]')) return k;
    }
    return null;
  }

  // Init
  const start = panelWithErrors() || initialKey();
  activate(start, false, false);

  // Asegura roving tabindex correcto desde el inicio
  setRovingTabindex(start);
})();
</script>
@endpush
