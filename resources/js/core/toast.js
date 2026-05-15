export function notify(message, variant = 'info') {
    window.dispatchEvent(new CustomEvent('kp:notify', {
        detail: { message, variant },
    }));
}
