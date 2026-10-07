<div {{ $attributes->merge(['class' => 'overflow-hidden rounded-sm border border-kp-border bg-white']) }}>
    <div class="kp-scrollbar overflow-x-auto">
        <table class="min-w-full divide-y divide-kp-border text-[11px]">
            {{ $slot }}
        </table>
    </div>
</div>
