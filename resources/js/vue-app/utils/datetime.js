/**
 * User-facing date/time display — change format HERE only; whole app follows.
 *
 * All calendar dates and clock times use APP_TIMEZONE (Asia/Kolkata), not the browser locale.
 *
 * Display (tables, labels): 15/Jun/2026
 * Text box / date inputs:     dd/mm/yyyy
 * Time:                       04:23 pm
 */

export const APP_TIMEZONE = 'Asia/Kolkata';

export const USER_DATE_TIME_DISPLAY = {
    monthLocale: 'en-GB',
    timeLocale: 'en-IN',
    hour12: true,
};

/** Text box / date-picker display — change HERE only; all date inputs follow. */
export const USER_DATE_INPUT = {
    placeholder: 'dd/mm/yyyy',
    separator: '/',
};

const formatterCache = new Map();

function getFormatter(options) {
    const key = JSON.stringify(options);

    if (!formatterCache.has(key)) {
        formatterCache.set(
            key,
            new Intl.DateTimeFormat('en-US', {
                timeZone: APP_TIMEZONE,
                ...options,
            }),
        );
    }

    return formatterCache.get(key);
}

function getAppTimezoneParts(date) {
    const formatter = getFormatter({
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: false,
    });

    const parts = Object.fromEntries(
        formatter
            .formatToParts(date)
            .filter((part) => part.type !== 'literal')
            .map((part) => [part.type, part.value]),
    );

    return {
        year: parts.year,
        month: parts.month,
        day: parts.day,
        hour: Number(parts.hour),
        minute: Number(parts.minute),
        second: Number(parts.second),
    };
}

function toDateInputFromParts(parts) {
    return `${parts.year}-${parts.month}-${parts.day}`;
}

function parseInstant(value) {
    if (value instanceof Date) {
        return Number.isNaN(value.getTime()) ? null : value;
    }

    const date = new Date(String(value).trim());

    return Number.isNaN(date.getTime()) ? null : date;
}

function zonedDateTimeToUtc(year, month, day, hour = 0, minute = 0, second = 0) {
    const utcGuess = Date.UTC(year, month - 1, day, hour, minute, second);
    const guess = new Date(utcGuess);
    const parts = getAppTimezoneParts(guess);
    const formattedAsUtc = Date.UTC(
        Number(parts.year),
        Number(parts.month) - 1,
        Number(parts.day),
        parts.hour,
        parts.minute,
        parts.second,
    );
    const offset = formattedAsUtc - utcGuess;

    return new Date(utcGuess - offset);
}

function parseDateOnlyInAppTimezone(value) {
    const raw = String(value).trim();
    const match = raw.match(/^(\d{4})-(\d{2})-(\d{2})$/);

    if (!match) {
        return null;
    }

    return zonedDateTimeToUtc(Number(match[1]), Number(match[2]), Number(match[3]), 0, 0, 0);
}

function parseNaiveDateTimeInAppTimezone(value) {
    const match = String(value)
        .trim()
        .match(/^(\d{4})-(\d{2})-(\d{2})\s+(\d{2}):(\d{2})(?::(\d{2}))?/);

    if (!match) {
        return null;
    }

    return zonedDateTimeToUtc(
        Number(match[1]),
        Number(match[2]),
        Number(match[3]),
        Number(match[4]),
        Number(match[5]),
        Number(match[6] ?? 0),
    );
}

/**
 * ISO date (YYYY-MM-DD) → user text box format (dd/mm/yyyy).
 */
export function toDateInputDisplay(iso) {
    const raw = String(iso ?? '').trim();
    const match = raw.match(/^(\d{4})-(\d{2})-(\d{2})$/);

    if (!match) {
        return '';
    }

    return `${match[3]}/${match[2]}/${match[1]}`;
}

/**
 * User text box format (dd/mm/yyyy) → ISO date (YYYY-MM-DD), or '' if invalid.
 */
export function fromDateInputDisplay(display) {
    const raw = String(display ?? '').trim();

    if (!raw) {
        return '';
    }

    const match = raw.match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/);

    if (!match) {
        return '';
    }

    const day = match[1].padStart(2, '0');
    const month = match[2].padStart(2, '0');
    const year = match[3];
    const iso = `${year}-${month}-${day}`;

    if (!/^\d{4}-\d{2}-\d{2}$/.test(iso)) {
        return '';
    }

    const parsed = parseDateOnlyInAppTimezone(iso);

    if (!parsed) {
        return '';
    }

    const parts = getAppTimezoneParts(parsed);

    if (parts.year !== year || parts.month !== month || parts.day !== day) {
        return '';
    }

    return iso;
}

function digitsOnly(value) {
    return String(value ?? '').replace(/\D/g, '');
}

/**
 * Auto-format partial typing into dd/mm/yyyy as the user enters digits.
 */
export function formatDateInputTyping(value) {
    const digits = digitsOnly(value).slice(0, 8);

    if (digits.length <= 2) {
        return digits;
    }

    if (digits.length <= 4) {
        return `${digits.slice(0, 2)}/${digits.slice(2)}`;
    }

    return `${digits.slice(0, 2)}/${digits.slice(2, 4)}/${digits.slice(4)}`;
}

export function toDateInputValue(date = new Date()) {
    const value = parseInstant(date);

    if (!value) {
        return '';
    }

    return toDateInputFromParts(getAppTimezoneParts(value));
}

function normalizeDateOnly(value) {
    if (!value) {
        return '';
    }

    return toDateInputFromIso(value) || String(value).trim().slice(0, 10);
}

/** @returns {-1|0|1} */
export function compareDateOnly(left, right) {
    const a = normalizeDateOnly(left);
    const b = normalizeDateOnly(right);

    if (!a || !b) {
        return 0;
    }

    return a.localeCompare(b);
}

export function isDateAfter(left, right) {
    return compareDateOnly(left, right) > 0;
}

export function isDateBefore(left, right) {
    return compareDateOnly(left, right) < 0;
}

export function toAppTimezoneIso(date = new Date()) {
    const value = parseInstant(date);

    if (!value) {
        return '';
    }

    const parts = getAppTimezoneParts(value);
    const pad = (part) => String(part).padStart(2, '0');

    return `${parts.year}-${parts.month}-${parts.day}T${pad(parts.hour)}:${pad(parts.minute)}:${pad(parts.second)}+05:30`;
}

export function addDaysToDateInput(dateInput, offsetDays) {
    const match = String(dateInput ?? '')
        .trim()
        .match(/^(\d{4})-(\d{2})-(\d{2})$/);

    if (!match) {
        return '';
    }

    const base = zonedDateTimeToUtc(Number(match[1]), Number(match[2]), Number(match[3]), 12, 0, 0);
    const shifted = new Date(base.getTime() + offsetDays * 86_400_000);

    return toDateInputFromParts(getAppTimezoneParts(shifted));
}

export function currentMonthValue(date = new Date()) {
    return toDateInputValue(date).slice(0, 7);
}

export function toDateInputFromIso(value) {
    if (!value) {
        return '';
    }

    const raw = String(value).trim();

    if (/^\d{4}-\d{2}-\d{2}$/.test(raw)) {
        return raw;
    }

    const dateOnlyMatch = raw.match(/^(\d{4}-\d{2}-\d{2})[T\s]/);

    if (dateOnlyMatch && !raw.includes('Z') && !/[+-]\d{2}:\d{2}$/.test(raw)) {
        return dateOnlyMatch[1];
    }

    const date = parseInstant(raw);

    if (!date) {
        return raw.slice(0, 10);
    }

    return toDateInputFromParts(getAppTimezoneParts(date));
}

export function dateOnlyFromDatetimeLocal(value) {
    return toDateInputFromIso(value);
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
        return parseDateOnlyInAppTimezone(raw);
    }

    const naiveDateTime = parseNaiveDateTimeInAppTimezone(raw);

    if (naiveDateTime) {
        return naiveDateTime;
    }

    return parseInstant(raw);
}

function monthShortName(date) {
    return date.toLocaleDateString(USER_DATE_TIME_DISPLAY.monthLocale, {
        month: 'short',
        timeZone: APP_TIMEZONE,
    });
}

function buildUserDate(date) {
    const parts = getAppTimezoneParts(date);

    return `${Number(parts.day)}/${monthShortName(date)}/${parts.year}`;
}

function buildUserTime(date) {
    return date.toLocaleTimeString(USER_DATE_TIME_DISPLAY.timeLocale, {
        hour: '2-digit',
        minute: '2-digit',
        hour12: USER_DATE_TIME_DISPLAY.hour12,
        timeZone: APP_TIMEZONE,
    });
}

function hasMeaningfulTime(date) {
    const parts = getAppTimezoneParts(date);

    return parts.hour !== 0 || parts.minute !== 0 || parts.second !== 0;
}

function valueHasTimeHint(value) {
    return String(value ?? '').includes(':');
}

function looksLikeIsoDate(value) {
    return typeof value === 'string' && /^\d{4}-\d{2}-\d{2}/.test(value.trim());
}

const DATE_COLUMN_PATTERN =
    /(date|_at|installed|removed|replaced|occurred|scrapped|issue|expiry|valid_until|rotation|retread|challan|arrival|cancelled|assigned|purchase)/i;

const DATETIME_COLUMN_PATTERN = /(_at|sort_at)$|^start_at$|^end_at$|^created_at$|^updated_at$/;

/**
 * Detect report/table columns that should be formatted as user dates.
 */
export function isDateColumnKey(columnKey) {
    if (!columnKey || typeof columnKey !== 'string') {
        return false;
    }

    if (columnKey === 's_no' || columnKey.endsWith('_id') || columnKey.endsWith('_km')) {
        return false;
    }

    return DATE_COLUMN_PATTERN.test(columnKey);
}

export function isDateTimeColumnKey(columnKey) {
    if (!columnKey || typeof columnKey !== 'string') {
        return false;
    }

    return DATETIME_COLUMN_PATTERN.test(columnKey);
}

/**
 * Format any table cell — auto-detects date/time columns and ISO values.
 */
export function formatTableCell(columnKey, value, defaultValue = '—') {
    if (value === null || value === undefined || value === '') {
        return defaultValue;
    }

    if (typeof value === 'number' && !isDateColumnKey(columnKey) && !looksLikeIsoDate(String(value))) {
        return value.toLocaleString('en-IN', { maximumFractionDigits: 2 });
    }

    const shouldFormatAsDate =
        isDateColumnKey(columnKey) || (typeof value === 'string' && looksLikeIsoDate(value));

    if (!shouldFormatAsDate) {
        return value;
    }

    const mode =
        isDateTimeColumnKey(columnKey) ||
        (typeof value === 'string' && (value.includes('T') || /^\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}/.test(value)))
            ? 'datetime'
            : 'date';

    return formatDisplay(value, mode, defaultValue);
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

/**
 * Month picker labels — e.g. "2026-06" → "Jun/2026"
 */
export function formatMonthYear(value, defaultValue = '—') {
    if (!value) {
        return defaultValue;
    }

    const raw = String(value).trim();
    const match = raw.match(/^(\d{4})-(\d{2})/);

    if (!match) {
        return formatDate(value, defaultValue);
    }

    const date = zonedDateTimeToUtc(Number(match[1]), Number(match[2]), 1, 12, 0, 0);

    if (Number.isNaN(date.getTime())) {
        return defaultValue;
    }

    return `${monthShortName(date)}/${match[1]}`;
}

/**
 * Day headings with weekday — e.g. "Monday, 15/Jun/2026"
 */
export function formatDateHeading(value, defaultValue = '—') {
    const date = parseDisplayDateTime(value);

    if (!date) {
        return defaultValue;
    }

    const weekday = date.toLocaleDateString(USER_DATE_TIME_DISPLAY.monthLocale, {
        weekday: 'long',
        timeZone: APP_TIMEZONE,
    });

    return `${weekday}, ${buildUserDate(date)}`;
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
