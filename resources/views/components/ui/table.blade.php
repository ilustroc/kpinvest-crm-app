<div {{ $attributes->merge(['class' => 'overflow-hidden rounded-md border border-kp-border bg-white']) }}>
    <div class="kp-scrollbar overflow-x-auto">
        <table class="min-w-full divide-y divide-kp-border text-xs">
            {{ $slot }}
        </table>
    </div>
</div>
