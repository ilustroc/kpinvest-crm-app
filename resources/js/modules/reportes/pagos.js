import { initReportFilters } from './filters';

initReportFilters({
    rootSelector: '[data-module="reportes-pagos"]',
    tableSelector: '#tablaPagos',
    exportMeta: 'rpt-pagos-export',
    facetsMeta: 'rpt-pagos-facets',
    partialParam: false,
    facets: [
        { name: 'gestor', response: 'gestores' },
        { name: 'entidad', response: 'entidades' },
        { name: 'cosecha', response: 'cosechas' },
    ],
});
