import { initReportFilters } from './filters';

initReportFilters({
    rootSelector: '[data-module="reportes-cna"]',
    tableSelector: '#tablaCna',
    exportMeta: 'rpt-cna-export',
    facetsMeta: 'rpt-cna-facets',
    partialParam: false,
    facets: [
        { name: 'estado', response: 'estados' },
        { name: 'gestor', response: 'gestores' },
        { name: 'entidad', response: 'entidades' },
    ],
});
