@props(['label', 'value', 'color' => 'slate'])

<div @class([
    'flex flex-col justify-center px-4 py-2 rounded-xl border min-w-[130px]',
    'bg-emerald-50/50 border-emerald-100' => $color === 'emerald',
    'bg-slate-50/50 border-slate-100' => $color === 'slate',
])>
    <span class="text-[9px] font-black uppercase tracking-widest text-slate-400 mb-0.5">{{ $label }}</span>
    <span @class([
        'text-[13px] font-black leading-none tracking-tight',
        'text-emerald-600' => $color === 'emerald',
        'text-slate-700' => $color === 'slate',
    ])>{{ $value }}</span>
</div>