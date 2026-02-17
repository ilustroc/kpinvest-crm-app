(function () {
  function toggleRail() {
    document.getElementById('rail')?.classList.toggle('show');
    document.getElementById('backdrop')?.classList.toggle('show');
  }

  window.toggleRail = toggleRail;

  document.querySelectorAll('[data-toggle-rail]').forEach(el => {
    el.addEventListener('click', (e) => {
      e.preventDefault();
      toggleRail();
    });
  });
})();
