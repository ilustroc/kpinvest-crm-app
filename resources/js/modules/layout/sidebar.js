function toggleRail() {
    document.getElementById('rail')?.classList.toggle('-translate-x-full');
    document.getElementById('backdrop')?.classList.toggle('hidden');
}

document.addEventListener('click', (event) => {
    const focusLink = event.target.closest('[data-rail-focus]');

    if (focusLink) {
        const target = document.querySelector(focusLink.dataset.railFocus);

        if (target) {
            window.setTimeout(() => target.focus(), 0);
        }

        if (window.matchMedia('(max-width: 767px)').matches) {
            toggleRail();
        }

        return;
    }

    const toggle = event.target.closest('[data-toggle-rail]');
    if (!toggle) return;

    event.preventDefault();
    toggleRail();
});
