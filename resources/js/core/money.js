const CURRENCY_SYMBOLS = {
    PEN: 'S/',
    SOL: 'S/',
    SOLES: 'S/',
    USD: 'US$',
    US$: 'US$',
    US: 'US$',
    EUR: '€',
};

function toAmount(value) {
    if (typeof value === 'string') {
        const normalized = value.replace(/[^\d,.-]/g, '').replace(/,/g, '').trim();
        const parsed = Number(normalized);

        return Number.isFinite(parsed) ? parsed : 0;
    }

    const parsed = Number(value ?? 0);

    return Number.isFinite(parsed) ? parsed : 0;
}

export function currencySymbol(currency = 'PEN') {
    if (currency === null) return null;

    const code = String(currency || '').trim().toUpperCase();

    if (code === '') return 'S/';
    if (code.includes(',') || code.includes('/')) return null;

    return CURRENCY_SYMBOLS[code] || code;
}

export function formatMoney(value, currency = 'PEN', options = {}) {
    const formatted = new Intl.NumberFormat('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(toAmount(value));

    if (options.symbol === false) return formatted;

    const symbol = currencySymbol(currency);

    return symbol === null ? formatted : `${symbol} ${formatted}`;
}

export function formatMoneyCompact(value, currency = 'PEN') {
    const amount = toAmount(value);
    const absolute = Math.abs(amount);

    if (absolute >= 1000000) {
        return `${currencySymbol(currency) || ''} ${(amount / 1000000).toFixed(2)}M`.trim();
    }

    if (absolute >= 1000) {
        return `${currencySymbol(currency) || ''} ${(amount / 1000).toFixed(2)}k`.trim();
    }

    return formatMoney(amount, currency);
}
