import './core/toast';
import { ready } from './core/dom';
import { initSubmitOnce } from './core/forms';
import './modules/layout/sidebar';
import './modules/layout/client-search';
import './modules/layout/notifications';
import './modules/layout/user-menu';

ready(() => {
    initSubmitOnce(document);

    if (document.querySelector('[data-dashboard-page]')) {
        import('./modules/dashboard/stats');
    }

    if (document.querySelector('[data-module="panel-resumen"]')) {
        import('./modules/panel/resumen');
    }

    if (document.querySelector('[data-module="clientes-show"]')) {
        import('./modules/clientes/show');
    }

    if (document.querySelector('[data-module="autorizacion-index"]')) {
        import('./modules/autorizacion/index');
    }

    if (document.querySelector('[data-admin-users-page]')) {
        import('./modules/admin/users');
    }

    if (document.querySelector('[data-module="integracion-imports"]')) {
        import('./modules/integracion/imports');
    }

    if (document.querySelector('[data-module="reportes-pagos"]')) {
        import('./modules/reportes/pagos');
    }

    if (document.querySelector('[data-module="reportes-promesas"]')) {
        import('./modules/reportes/promesas');
    }

    if (document.querySelector('[data-module="reportes-cna"]')) {
        import('./modules/reportes/cna');
    }

    if (document.querySelector('[data-module="auth-login"]')) {
        import('./modules/auth/login');
    }
});
