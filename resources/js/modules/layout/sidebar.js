function toggleRail() {
    document.getElementById('rail')?.classList.toggle('-translate-x-full');
    document.getElementById('backdrop')?.classList.toggle('hidden');
}

document.addEventListener('click', (event) => {
    const toggle = event.target.closest('[data-toggle-rail]');
    if (!toggle) return;

    event.preventDefault();
    toggleRail();
});
