/**
 * User-facing date/time display — change format HERE only; whole app follows.
 *
 * Date: 15/Jun/2026
 * Time: 04:23 pm
 */

export const USER_DATE_TIME_DISPLAY = {
    monthLocale: 'en-GB',
    timeLocale: 'en-IN',
    hour12: true,
};

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

function parseDisplayDateTime(value) {
    if (!value) {
        return null;
    }

    if (value instanceof Date) {
        return Number.isNaN(value.getTime()) ? null : value;
    }

    const raw = String(value).trim();

    if (/^\d{4}-\d{2}-\d{2}$/.test(raw)) {
        return parseDisplayDate(raw);
    }

    if (/^\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}/.test(raw)) {
        const normalized = raw.replace(' ', 'T');
        const date = new Date(normalized);

        return Number.isNaN(date.getTime()) ? null : date;
    }

    const date = new Date(raw);

    return Number.isNaN(date.getTime()) ? null : date;
}

function monthShortName(date) {
    return date.toLocaleDateString(USER_DATE_TIME_DISPLAY.monthLocale, { month: 'short' });
}

function buildUserDate(date) {
    return `${date.getDate()}/${monthShortName(date)}/${date.getFullYear()}`;
}

function buildUserTime(date) {
    return date.toLocaleTimeString(USER_DATE_TIME_DISPLAY.timeLocale, {
        hour: '2-digit',
        minute: '2-digit',
        hour12: USER_DATE_TIME_DISPLAY.hour12,
    });
}

function hasMeaningfulTime(date) {
    return date.getHours() !== 0 || date.getMinutes() !== 0 || date.getSeconds() !== 0;
}

function valueHasTimeHint(value) {
    return String(value ?? '').includes(':');
}

/**
 * Single display helper for the whole app.
 *
 * @param {string|Date|null|undefined} value
 * @param {'auto'|'date'|'time'|'datetime'} mode
 * @param {string} defaultValue
 */
export function formatDisplay(value, mode = 'auto', defaultValue = '—') {
    const date = parseDisplayDateTime(value);

    if (!date) {
        return defaultValue;
    }

    if (mode === 'date') {
        return buildUserDate(date);
    }

    if (mode === 'time') {
        return buildUserTime(date);
    }

    if (mode === 'datetime') {
        return `${buildUserDate(date)} ${buildUserTime(date)}`;
    }

    if (!hasMeaningfulTime(date) && !valueHasTimeHint(value)) {
        return buildUserDate(date);
    }

    return `${buildUserDate(date)} ${buildUserTime(date)}`;
}

/**
 * @returns {{ date: string, time: string }}
 */
export function formatDisplayParts(value, defaultValue = '—') {
    const date = parseDisplayDateTime(value);

    if (!date) {
        return { date: defaultValue, time: defaultValue };
    }

    const showTime = hasMeaningfulTime(date) || valueHasTimeHint(value);

    return {
        date: buildUserDate(date),
        time: showTime ? buildUserTime(date) : defaultValue,
    };
}

/** Short aliases — all use formatDisplay() internally */
export function formatDate(value, defaultValue = '—') {
    return formatDisplay(value, 'date', defaultValue);
}

export function formatTime(value, defaultValue = '—') {
    return formatDisplay(value, 'time', defaultValue);
}

export function formatDateTime(value, defaultValue = '—') {
    return formatDisplay(value, 'datetime', defaultValue);
}

function rawFromEvent(event) {
    if (!event) {
        return null;
    }

    if (event.sort_at) {
        return event.sort_at;
    }

    if (event.date && event.time) {
        return `${event.date}T${event.time}`;
    }

    if (event.date) {
        return event.date;
    }

    return null;
}

/** History / timeline rows */
export function formatEventDateTime(event, defaultValue = '—') {
    return formatDisplay(rawFromEvent(event), 'auto', defaultValue);
}

/**
 * @returns {{ date: string, time: string }}
 */
export function formatEventParts(event, defaultValue = '—') {
    const raw = rawFromEvent(event);

    if (!raw) {
        return { date: defaultValue, time: defaultValue };
    }

    if (event?.date && !event?.time && !valueHasTimeHint(event?.sort_at)) {
        return {
            date: formatDisplay(raw, 'date', defaultValue),
            time: defaultValue,
        };
    }

    return formatDisplayParts(raw, defaultValue);
}
