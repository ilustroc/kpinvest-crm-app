// resources/js/clientes/show/tooltips.js
export function initTooltips() {
  try {
    document
      .querySelectorAll('[data-bs-toggle="tooltip"]')
      .forEach((el) => new bootstrap.Tooltip(el));
  } catch (_) {}
}