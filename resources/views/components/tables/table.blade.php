<div {{ $attributes->merge(['class' => 'overflow-hidden rounded-sm border border-kp-border bg-white']) }}>
    <div class="kp-scrollbar overflow-x-auto">
        <table class="min-w-full divide-y divide-kp-border text-[11px] [&_tbody_tr]:transition-colors [&_tbody_tr:hover]:bg-kp-blue-soft/45">
            {{ $slot }}
        </table>
    </div>
</div>
