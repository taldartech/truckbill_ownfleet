import {
    expenseTypeLabel,
    isDieselExpenseType,
    MAX_FUEL_LITERS,
    MAX_FUEL_RATE,
    FUEL_DECIMAL_PLACES,
    validateDieselExpenses,
    validateExpenseDates,
} from '../api/trips';
import { fieldErrorMessage } from './formErrors';
import { formatMeterKm } from './formatMeter';

const FIELD_LABELS = {
    expense_date: 'Expense Date',
    fuel_odometer_km: 'Meter Reading',
    fuel_liters: 'Fuel Quantity',
    fuel_rate: 'Diesel Rate',
    amount: 'Amount',
    other_type: 'Expense Type Name',
};

function parseNum(value) {
    const n = Number(value);

    return Number.isFinite(n) ? n : 0;
}

function formatLiters(value) {
    return Number(value ?? 0).toLocaleString('en-IN', { maximumFractionDigits: 2 });
}

function formatRate(value) {
    return Number(value ?? 0).toLocaleString('en-IN', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
}

function formatAmount(value) {
    return Number(value ?? 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

export function expenseFieldFocusId(index, field) {
    return `expense-${index}-${field}`;
}

export function parseExpenseFieldKey(key) {
    const match = String(key ?? '').match(/^expenses\.(\d+)\.(.+)$/);

    if (!match) {
        return null;
    }

    return { index: Number(match[1]), field: match[2] };
}

export function validateNonDieselExpenses(expenses, { strict = false } = {}) {
    const errors = {};

    (expenses ?? []).forEach((expense, index) => {
        if (isDieselExpenseType(expense.expense_type)) {
            return;
        }

        const amount = parseNum(expense.amount);
        const hasAmount = amount > 0;

        if (!hasAmount) {
            if (strict && expense.expense_type) {
                errors[`expenses.${index}.amount`] = ['Enter an amount greater than zero.'];
            }

            return;
        }

        if (amount < 0) {
            errors[`expenses.${index}.amount`] = ['Amount cannot be negative.'];
        }

        const amountStr = String(expense.amount ?? '');

        if (amountStr.includes('.')) {
            const fraction = amountStr.split('.')[1] ?? '';

            if (fraction.replace(/0+$/, '').length > 2) {
                errors[`expenses.${index}.amount`] = ['Amount must have at most 2 decimal places.'];
            }
        }

        if (expense.expense_type === 'OTHER' && !String(expense.other_type ?? '').trim()) {
            errors[`expenses.${index}.other_type`] = ['Please specify the expense type name.'];
        }
    });

    return errors;
}

export function validateAllTripExpenses(form, { strict = false } = {}) {
    return {
        ...validateExpenseDates(form.expenses, {
            startAt: form.start_at || null,
            endAt: form.end_at || null,
        }),
        ...validateDieselExpenses(form.expenses, {
            startMeterKm: form.start_meter_reading_km,
            endMeterKm: form.end_meter_reading_km,
            strict,
        }),
        ...validateNonDieselExpenses(form.expenses, { strict }),
    };
}

function buildEnteredAllowed(expense, field, form) {
    const startMeter = parseNum(form.start_meter_reading_km);
    const endMeter =
        form.end_meter_reading_km === '' || form.end_meter_reading_km == null
            ? null
            : parseNum(form.end_meter_reading_km);

    if (field === 'fuel_odometer_km') {
        const entered = expense.fuel_odometer_km;

        if (entered === '' || entered == null) {
            return { entered: '—', allowed: buildMeterAllowedLabel(startMeter, endMeter) };
        }

        return {
            entered: `${formatMeterKm(entered)}`,
            allowed: buildMeterAllowedLabel(startMeter, endMeter),
        };
    }

    if (field === 'fuel_liters') {
        return {
            entered: `${formatLiters(expense.fuel_liters)} L`,
            allowed: `≤ ${formatLiters(MAX_FUEL_LITERS)} L`,
        };
    }

    if (field === 'fuel_rate') {
        return {
            entered: `₹${formatRate(expense.fuel_rate)}/L`,
            allowed: `≤ ₹${formatRate(MAX_FUEL_RATE)}/L`,
        };
    }

    if (field === 'amount') {
        return {
            entered: `₹${formatAmount(expense.amount)}`,
            allowed: '> ₹0',
        };
    }

    if (field === 'expense_date') {
        return {
            entered: expense.expense_date || '—',
            allowed: form.end_at ? `${form.start_at} – ${form.end_at}` : `from ${form.start_at}`,
        };
    }

    if (field === 'other_type') {
        return {
            entered: expense.other_type || '—',
            allowed: 'Required for Other expenses',
        };
    }

    return { entered: '—', allowed: '—' };
}

function buildMeterAllowedLabel(startMeter, endMeter) {
    if (startMeter > 0 && endMeter != null && endMeter > 0) {
        return `${formatMeterKm(startMeter)} – ${formatMeterKm(endMeter)}`;
    }

    if (startMeter > 0) {
        return `≥ ${formatMeterKm(startMeter)}`;
    }

    if (endMeter != null && endMeter > 0) {
        return `≤ ${formatMeterKm(endMeter)}`;
    }

    return '—';
}

export function buildMeterLimitHints({ startMeterKm, endMeterKm }) {
    const startMeter = parseNum(startMeterKm);
    const endMeter = endMeterKm === '' || endMeterKm == null ? null : parseNum(endMeterKm);

    return {
        tripStart: startMeter > 0 ? formatMeterKm(startMeter) : null,
        tripEnd: endMeter != null && endMeter > 0 ? formatMeterKm(endMeter) : null,
        allowed: buildMeterAllowedLabel(startMeter, endMeter),
        maxLiters: `${formatLiters(MAX_FUEL_LITERS)} L`,
        maxRate: `₹${formatRate(MAX_FUEL_RATE)}/L`,
    };
}

function expenseRowLabel(expense, index, routeLegs = []) {
    const typeLabel = expenseTypeLabel(expense.expense_type, expense.other_type);
    const seq = expense.route_leg_sequence;

    if (seq === '' || seq == null) {
        return `${typeLabel} (whole trip, row ${index + 1})`;
    }

    const leg = routeLegs[Number(seq) - 1];
    const stopLabel = leg
        ? `Stop ${seq}: ${leg.from_city || '—'} → ${leg.to_city || '—'}`
        : `Stop ${seq}`;

    return `${typeLabel} (${stopLabel})`;
}

export function buildExpenseValidationSummaryItems(fieldErrors, expenses, form = {}) {
    const items = [];
    const routeLegs = form.route_legs ?? [];

    Object.keys(fieldErrors ?? {}).forEach((key) => {
        const parsed = parseExpenseFieldKey(key);

        if (!parsed) {
            return;
        }

        const message = fieldErrorMessage(fieldErrors, key);
        const expense = expenses[parsed.index];

        if (!message || !expense) {
            return;
        }

        const { entered, allowed } = buildEnteredAllowed(expense, parsed.field, form);

        items.push({
            key,
            field: parsed.field,
            expenseIndex: parsed.index,
            focusId: expenseFieldFocusId(parsed.index, parsed.field),
            label: FIELD_LABELS[parsed.field] ?? parsed.field,
            rowLabel: expenseRowLabel(expense, parsed.index, routeLegs),
            message,
            entered,
            allowed,
        });
    });

    return items;
}

export function buildExpenseValidationBanner(summaryItems) {
    const count = summaryItems.length;

    if (count === 0) {
        return '';
    }

    const bullets = summaryItems.map((item) => `• ${item.message}`).join('\n');

    return `Cannot save this expense.\n\n${count} validation error${count === 1 ? '' : 's'} found:\n\n${bullets}\n\nScroll to highlighted fields to fix them.`;
}

export function countValidationErrors(fieldErrors) {
    return Object.keys(fieldErrors ?? {}).length;
}

export { FIELD_LABELS, FUEL_DECIMAL_PLACES, MAX_FUEL_LITERS, MAX_FUEL_RATE };
