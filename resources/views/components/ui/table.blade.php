<div {{ $attributes->merge(['class' => 'overflow-hidden rounded-lg border border-kp-border bg-white']) }}>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-kp-border text-sm">
            {{ $slot }}
        </table>
    </div>
</div>
