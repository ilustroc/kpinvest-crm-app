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
    'label' => 'Solicitar',
    'title' => 'Solicitar CNA para las operaciones de esta cuenta de deuda',
])

<x-ui.button
    type="button"
    variant="bare"
    size="none"
    class="genCnaBtn inline-flex items-center gap-1.5 rounded-md px-2 py-1 text-[11px] font-semibold text-kp-primary-dark hover:bg-kp-primary-soft"
    title="{{ $title }}"
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
    <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
    </svg>
    {{ $label }}
</x-ui.button>
