@props([
    'colspan' => 1,
    'message' => 'Sin resultados.',
])

<tr>
    <td colspan="{{ $colspan }}" {{ $attributes->merge(['class' => 'border-t border-kp-border px-4 py-8 text-center text-sm text-kp-muted']) }}>
        @if(trim((string) $slot) === '')
            {{ $message }}
        @else
            {{ $slot }}
        @endif
    </td>
</tr>
