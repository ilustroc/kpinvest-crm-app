@props([
    'dni',
    'cuenta' => '',
    'operacion' => '',
    'cosecha' => '',
    'entidad' => '',
    'producto' => '',
    'pagosCount' => 0,
    'pagosSum' => 0,
    'moneda' => 'PEN',
])

<x-ui.button
    type="button"
    variant="bare"
    size="none"
    class="genCnaBtn inline-flex size-7 items-center justify-center rounded-md text-kp-secondary transition-colors hover:bg-kp-secondary-soft hover:text-kp-secondary-dark kp-focus"
    title="Solicitar CNA"
    aria-label="Solicitar CNA"
    data-modal-open="#modalCna"
    data-dni="{{ $dni }}"
    data-cuenta="{{ $cuenta }}"
    data-oper="{{ $operacion }}"
    data-cosecha="{{ $cosecha }}"
    data-entidad="{{ $entidad }}"
    data-producto="{{ $producto }}"
    data-pagos-count="{{ $pagosCount }}"
    data-pagos-sum="{{ $pagosSum }}"
    data-moneda="{{ $moneda }}"
>
    <x-ui.document-gavel-icon class="size-5" />
</x-ui.button>
