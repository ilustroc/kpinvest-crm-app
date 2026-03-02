// resources/js/clientes/index.js
import { bindModalTriggers } from './ui/modal';

import { initTooltips } from './show/tooltips';
import { initModalNota } from './show/modal-nota';
import { initCronogramaModal } from './show/cronograma-modal';
import { initSelection } from './show/selection';
import { initPropuestaModal } from './show/propuesta-modal';
import { initCnaModal } from './show/cna-modal';
import { initOnceForms } from './show/once-forms';
import { initPagosDeleteToggle } from './show/pagos-delete-toggle';

document.addEventListener('DOMContentLoaded', () => {
  bindModalTriggers();

  initTooltips();
  initModalNota();
  initCronogramaModal();

  const selection = initSelection();

  initPropuestaModal({ selection });
  initCnaModal();

  initOnceForms();
  initPagosDeleteToggle();
});