export function toDateInputValue(date = new Date()) {
    const value = date instanceof Date ? date : new Date(date);

    if (Number.isNaN(value.getTime())) {
        return '';
    }

    const pad = (part) => String(part).padStart(2, '0');

    return `${value.getFullYear()}-${pad(value.getMonth() + 1)}-${pad(value.getDate())}`;
}

export function toDateInputFromIso(value) {
    if (!value) {
        return '';
    }

    return String(value).slice(0, 10);
}

export function dateOnlyFromDatetimeLocal(value) {
    return toDateInputFromIso(value);
}

function parseDisplayDate(value) {
    if (!value) {
        return null;
    }

    const dateOnly = String(value).slice(0, 10);

    if (/^\d{4}-\d{2}-\d{2}$/.test(dateOnly)) {
        const date = new Date(`${dateOnly}T00:00:00`);

        return Number.isNaN(date.getTime()) ? null : date;
    }

    const date = value instanceof Date ? value : new Date(value);

    return Number.isNaN(date.getTime()) ? null : date;
}

function monthShortName(date) {
    return date.toLocaleDateString('en-GB', { month: 'short' });
}

/** Display date as 20/May/2026 — matches project-wide formatDate() convention. */
export function formatDate(value, defaultValue = '—') {
    const date = parseDisplayDate(value);

    if (!date) {
        return defaultValue;
    }

    return `${date.getDate()}/${monthShortName(date)}/${date.getFullYear()}`;
}

/** Display datetime as 20/May/2026 03:45 pm — matches project-wide formatDateTime() convention. */
export function formatDateTime(value, defaultValue = '—') {
    const date = value instanceof Date ? value : new Date(value);

    if (!value || Number.isNaN(date.getTime())) {
        return defaultValue;
    }

    const timePart = date.toLocaleTimeString('en-IN', {
        hour: '2-digit',
        minute: '2-digit',
        hour12: true,
    });

    return `${date.getDate()}/${monthShortName(date)}/${date.getFullYear()} ${timePart}`;
}
