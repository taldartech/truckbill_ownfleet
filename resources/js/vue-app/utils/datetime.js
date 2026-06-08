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
