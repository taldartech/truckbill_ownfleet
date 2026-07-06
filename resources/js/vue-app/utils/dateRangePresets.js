import { addDaysToDateInput, formatDate, toDateInputValue } from './datetime';

export const TRIP_LIST_DATE_PRESET_KEYS = ['today', 'this_month', 'last_month'];

export const DASHBOARD_DATE_PRESET_KEYS = ['today', 'yesterday', 'this_month', 'last_month'];

export const MAX_LIST_DATE_RANGE_MONTHS = 3;

export const FUTURE_DATE_ERROR = 'Future date is not allowed.';

export const TO_DATE_BEFORE_FROM_ERROR = 'To Date cannot be earlier than From Date.';

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
    const today = toIsoDate(referenceDate);
    const [year, month] = today.split('-').map(Number);
    const lastDay = new Date(year, month, 0).getDate();
    const monthEnd = `${year}-${pad(month)}-${pad(lastDay)}`;

    return {
        from_date: `${year}-${pad(month)}-01`,
        to_date: monthEnd > today ? today : monthEnd,
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

export function maxDateRangeEnd(
    fromDate,
    maxMonths = MAX_LIST_DATE_RANGE_MONTHS,
    todayMax = toIsoDate(new Date()),
) {
    const rangeEnd = addMonthsToDateInput(fromDate, maxMonths);

    if (!todayMax) {
        return rangeEnd;
    }

    return rangeEnd > todayMax ? todayMax : rangeEnd;
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

export function validateDateRange(fromDate, toDate, options = {}) {
    const maxMonths = options.maxMonths ?? null;
    const todayMax = options.todayMax ?? toIsoDate(new Date());
    const blockFutureDates = options.blockFutureDates ?? true;

    if (!fromDate || !toDate) {
        return 'Select both From and To dates.';
    }

    if (blockFutureDates && fromDate > todayMax) {
        return FUTURE_DATE_ERROR;
    }

    if (blockFutureDates && toDate > todayMax) {
        return FUTURE_DATE_ERROR;
    }

    if (toDate < fromDate) {
        return TO_DATE_BEFORE_FROM_ERROR;
    }

    if (maxMonths && dateRangeExceedsMonths(fromDate, toDate, maxMonths)) {
        return dateRangeMonthsError(maxMonths);
    }

    return '';
}
