import { addDaysToDateInput, formatDate, toDateInputValue } from './datetime';

export const TRIP_LIST_DATE_PRESET_KEYS = ['today', 'this_month', 'last_month'];

export const MAX_LIST_DATE_RANGE_MONTHS = 3;

export const DATE_RANGE_PRESETS = [
    { key: 'today', label: 'Today' },
    { key: 'yesterday', label: 'Yesterday' },
    // { key: 'last_7_days', label: 'Last 7 Days' },
    // { key: 'last_30_days', label: 'Last 30 Days' },
    { key: 'this_month', label: 'This Month' },
    { key: 'last_month', label: 'Last Month' },
    { key: 'custom', label: 'Custom Range' },
];

function pad(value) {
    return String(value).padStart(2, '0');
}

export function toIsoDate(date) {
    return toDateInputValue(date);
}

export function todayDateRange(referenceDate = new Date()) {
    const iso = toIsoDate(referenceDate);

    return { from_date: iso, to_date: iso };
}

export function yesterdayDateRange(referenceDate = new Date()) {
    const iso = addDaysToDateInput(toIsoDate(referenceDate), -1);

    return { from_date: iso, to_date: iso };
}

export function lastDaysDateRange(days, referenceDate = new Date()) {
    const toDate = toIsoDate(referenceDate);

    return {
        from_date: addDaysToDateInput(toDate, -(days - 1)),
        to_date: toDate,
    };
}

export function lastSevenDaysDateRange(referenceDate = new Date()) {
    return lastDaysDateRange(7, referenceDate);
}

export function lastThirtyDaysDateRange(referenceDate = new Date()) {
    return lastDaysDateRange(30, referenceDate);
}

export function thisMonthDateRange(referenceDate = new Date()) {
    const [year, month] = toIsoDate(referenceDate).split('-').map(Number);
    const lastDay = new Date(year, month, 0).getDate();

    return {
        from_date: `${year}-${pad(month)}-01`,
        to_date: `${year}-${pad(month)}-${pad(lastDay)}`,
    };
}

export function lastMonthDateRange(referenceDate = new Date()) {
    let [year, month] = toIsoDate(referenceDate).split('-').map(Number);
    month -= 1;

    if (month < 1) {
        month = 12;
        year -= 1;
    }

    const lastDay = new Date(year, month, 0).getDate();

    return {
        from_date: `${year}-${pad(month)}-01`,
        to_date: `${year}-${pad(month)}-${pad(lastDay)}`,
    };
}

export function resolveDateRangePreset(presetKey, referenceDate = new Date()) {
    switch (presetKey) {
        case 'yesterday':
            return yesterdayDateRange(referenceDate);
        case 'last_7_days':
            return lastSevenDaysDateRange(referenceDate);
        case 'last_30_days':
            return lastThirtyDaysDateRange(referenceDate);
        case 'this_month':
            return thisMonthDateRange(referenceDate);
        case 'last_month':
            return lastMonthDateRange(referenceDate);
        case 'custom':
            return todayDateRange(referenceDate);
        case 'today':
        default:
            return todayDateRange(referenceDate);
    }
}

export function detectDateRangePreset(fromDate, toDate, referenceDate = new Date()) {
    for (const preset of DATE_RANGE_PRESETS) {
        if (preset.key === 'custom') {
            continue;
        }

        const range = resolveDateRangePreset(preset.key, referenceDate);

        if (range.from_date === fromDate && range.to_date === toDate) {
            return preset.key;
        }
    }

    return 'custom';
}

export function defaultDashboardTripRange(referenceDate = new Date()) {
    return thisMonthDateRange(referenceDate);
}

export function formatDateRangeLabel(fromDate, toDate) {
    if (!fromDate || !toDate) {
        return 'Select date range';
    }

    if (fromDate === toDate) {
        return formatDate(fromDate);
    }

    return `${formatDate(fromDate)} - ${formatDate(toDate)}`;
}

export function addMonthsToDateInput(dateInput, monthOffset) {
    if (!dateInput) {
        return '';
    }

    const [year, month, day] = dateInput.split('-').map(Number);
    const date = new Date(year, month - 1, day);
    date.setMonth(date.getMonth() + monthOffset);

    return toIsoDate(date);
}

export function maxDateRangeEnd(fromDate, maxMonths = MAX_LIST_DATE_RANGE_MONTHS) {
    return addMonthsToDateInput(fromDate, maxMonths);
}

export function dateRangeExceedsMonths(
    fromDate,
    toDate,
    maxMonths = MAX_LIST_DATE_RANGE_MONTHS,
) {
    if (!fromDate || !toDate) {
        return false;
    }

    const start = new Date(`${fromDate}T00:00:00`);
    const end = new Date(`${toDate}T00:00:00`);

    if (Number.isNaN(start.getTime()) || Number.isNaN(end.getTime())) {
        return false;
    }

    const earliestAllowedStart = new Date(end);
    earliestAllowedStart.setMonth(earliestAllowedStart.getMonth() - maxMonths);

    return start < earliestAllowedStart;
}

export function dateRangeMonthsError(maxMonths = MAX_LIST_DATE_RANGE_MONTHS) {
    return `Maximum allowed date range is ${maxMonths} months.`;
}
