import { initReportFilters } from './filters';

initReportFilters({
    rootSelector: '[data-module="reportes-promesas"]',
    tableSelector: '#tablaPdp',
    exportMeta: 'rpt-pdp-export',
    facetsMeta: 'rpt-pdp-facets',
    partialParam: true,
    facets: [
        { name: 'estado', response: 'estados' },
        { name: 'tipo', response: 'tipos' },
        { name: 'entidad', response: 'entidades' },
    ],
});
