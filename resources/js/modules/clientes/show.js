import { $, $$, ready } from '../../core/dom';
import { setupModals } from '../../core/modal';
import { setupPromesaForm } from '../promesas/form';
import { setupPromiseSchedule } from '../promesas/schedule';
import { setupCnaForm } from '../cna/form';

function setupToggleTargets(root) {
    root.addEventListener('click', (event) => {
        const button = event.target.closest('[data-toggle-target]');

        if (!button) return;

        const target = $(button.dataset.toggleTarget, root);

        if (target) {
            target.classList.toggle('hidden');
        }
    });
}

function setupDeletePayments(root) {
    const toggle = $('#toggleDeletePagos', root);

    if (!toggle) return;

    const table = $('#tblPagos', root);
    const deleteButton = $('#btnDeletePagos', root);
    const checkAll = $('#chkAllPagos', root);

    function updateButton() {
        if (deleteButton) {
            deleteButton.disabled = !table?.querySelector('.chkPago:checked');
        }
    }

    function showColumns(active) {
        $$('.col-del', root).forEach((element) => element.classList.toggle('hidden', !active));

        if (!active) {
            if (checkAll) checkAll.checked = false;
            $$('.chkPago', root).forEach((input) => {
                input.checked = false;
            });
            updateButton();
        }
    }

    toggle.addEventListener('change', () => showColumns(toggle.checked));

    checkAll?.addEventListener('change', () => {
        $$('.chkPago', root).forEach((input) => {
            input.checked = checkAll.checked;
        });
        updateButton();
    });

    table?.addEventListener('change', (event) => {
        if (event.target.classList.contains('chkPago')) {
            updateButton();
        }
    });

    showColumns(false);
}

function setupConfirmForms(root) {
    $$('form[data-confirm]', root).forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (!window.confirm(form.dataset.confirm || 'Confirmar accion?')) {
                event.preventDefault();
            }
        });
    });
}

function setupOnceForms(root) {
    $$('form[data-once]', root).forEach((form) => {
        let locked = false;

        form.addEventListener('submit', (event) => {
            if (event.defaultPrevented) return;

            if (locked) {
                event.preventDefault();
                return;
            }

            if (!form.checkValidity()) return;

            locked = true;
            form.setAttribute('aria-busy', 'true');

            form.querySelectorAll('button[type="submit"], input[type="submit"]').forEach((button) => {
                button.disabled = true;
                if (button.tagName === 'BUTTON') {
                    button.dataset.prev = button.textContent || '';
                    button.textContent = 'Enviando...';
                }
            });
        }, { capture: true });
    });
}

ready(() => {
    const root = $('[data-module="clientes-show"]');

    if (!root) return;

    setupToggleTargets(root);
    setupDeletePayments(root);
    setupConfirmForms(root);
    setupPromesaForm(root);
    setupPromiseSchedule(root);
    setupCnaForm(root);
    setupOnceForms(root);
    setupModals(root);
});
