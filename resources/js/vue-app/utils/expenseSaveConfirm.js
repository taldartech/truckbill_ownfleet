import {
    calcDieselAmount,
    expenseTypeLabel,
    isActiveFuelRow,
    isDieselExpenseType,
} from '../api/trips';
import { formatDate } from './datetime';

function formatMoney(value) {
    return Number(value ?? 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function formatNum(value) {
    return Number(value ?? 0).toLocaleString('en-IN', { maximumFractionDigits: 2 });
}

function normalizeExpense(expense) {
    const isDiesel = isDieselExpenseType(expense.expense_type);
    const active = isDiesel ? isActiveFuelRow(expense) : Number(expense.amount ?? 0) > 0;

    if (!active) {
        return null;
    }

    const amount = isDiesel
        ? calcDieselAmount(expense.fuel_liters, expense.fuel_rate)
        : Number(expense.amount ?? 0);

    return {
        id: expense.id ?? null,
        expense_type: expense.expense_type ?? '',
        other_type: expense.other_type ?? '',
        expense_date: expense.expense_date ?? '',
        amount,
        fuel_liters:
            expense.fuel_liters === '' || expense.fuel_liters == null ? null : Number(expense.fuel_liters),
        fuel_rate: expense.fuel_rate === '' || expense.fuel_rate == null ? null : Number(expense.fuel_rate),
        fuel_odometer_km:
            expense.fuel_odometer_km === '' || expense.fuel_odometer_km == null
                ? null
                : Math.trunc(Number(expense.fuel_odometer_km)),
        route_leg_sequence: expense.route_leg_sequence || null,
        notes: expense.notes ?? '',
    };
}

function expenseSignature(expense) {
    return [
        expense.expense_type,
        expense.other_type,
        expense.expense_date,
        expense.amount,
        expense.fuel_liters,
        expense.fuel_rate,
        expense.fuel_odometer_km,
        expense.route_leg_sequence,
        expense.notes,
    ].join('|');
}

function legLabel(sequence) {
    return sequence ? `Stop ${sequence}` : 'General';
}

function buildExpenseRow(expense) {
    const isDiesel = isDieselExpenseType(expense.expense_type);

    return {
        type: expenseTypeLabel(expense.expense_type, expense.other_type),
        date: formatDate(expense.expense_date),
        leg: legLabel(expense.route_leg_sequence),
        amount: formatMoney(expense.amount),
        meter: isDiesel && expense.fuel_odometer_km != null ? `${formatNum(expense.fuel_odometer_km)} KM` : null,
        liters: isDiesel && expense.fuel_liters != null ? `${formatNum(expense.fuel_liters)} L` : null,
        rate: isDiesel && expense.fuel_rate != null ? `₹${formatMoney(expense.fuel_rate)}/L` : null,
        notes: expense.notes?.trim() || null,
    };
}

function buildFieldChanges(previous, current) {
    const changes = [];
    const fields = [
        { key: 'type', label: 'Type' },
        { key: 'date', label: 'Date' },
        { key: 'leg', label: 'Stop' },
        { key: 'amount', label: 'Amount', prefix: '₹', previousPrefix: '₹' },
        { key: 'meter', label: 'Meter' },
        { key: 'liters', label: 'Liters' },
        { key: 'rate', label: 'Rate' },
        { key: 'notes', label: 'Notes' },
    ];

    fields.forEach(({ key, label, prefix = '', previousPrefix = prefix }) => {
        const prev = previous[key];
        const next = current[key];

        if (!prev && !next) {
            return;
        }

        if (prev !== next) {
            changes.push({
                label,
                previous: prev ? `${previousPrefix}${prev}` : '—',
                current: next ? `${prefix}${next}` : '—',
            });
        }
    });

    return changes;
}

/**
 * @param {object} form
 * @param {object} options
 * @returns {object}
 */
export function buildExpenseSaveConfirmViewModel(form, options = {}) {
    const { originalExpenses = [], tripNumber = '', vehicleLabel = '—' } = options;

    const currentList = (form.expenses ?? []).map(normalizeExpense).filter(Boolean);
    const originalList = originalExpenses.map(normalizeExpense).filter(Boolean);

    const originalById = new Map(
        originalList.filter((expense) => expense.id).map((expense) => [String(expense.id), expense]),
    );
    const matchedOriginalIds = new Set();

    const rows = currentList.map((expense) => {
        const original = expense.id ? originalById.get(String(expense.id)) : null;

        if (original) {
            matchedOriginalIds.add(String(expense.id));
        }

        const currentRow = buildExpenseRow(expense);
        const isNew = !original;
        const changed = original ? expenseSignature(original) !== expenseSignature(expense) : true;
        const fieldChanges = original && changed ? buildFieldChanges(buildExpenseRow(original), currentRow) : [];

        return {
            ...currentRow,
            isNew,
            changed: isNew || changed,
            fieldChanges,
        };
    });

    const removedRows = originalList
        .filter((expense) => expense.id && !matchedOriginalIds.has(String(expense.id)))
        .map((expense) => ({
            ...buildExpenseRow(expense),
            isRemoved: true,
        }));

    const newCount = rows.filter((row) => row.isNew).length;
    const updatedCount = rows.filter((row) => row.changed && !row.isNew).length;
    const removedCount = removedRows.length;
    const hasChanges = newCount > 0 || updatedCount > 0 || removedCount > 0;

    const currentTotal = currentList.reduce((sum, expense) => sum + Number(expense.amount ?? 0), 0);
    const previousTotal = originalList.reduce((sum, expense) => sum + Number(expense.amount ?? 0), 0);

    return {
        title: hasChanges ? 'Confirm Expense Changes' : 'Confirm Expense Save',
        message: hasChanges
            ? 'Please review what you changed below. Old and new values are shown — save only if everything is correct.'
            : 'Please review the expense entries below before saving.',
        tripNumber,
        vehicleLabel,
        rows,
        removedRows,
        newCount,
        updatedCount,
        removedCount,
        hasChanges,
        changeSummary: hasChanges
            ? [
                  newCount > 0 ? `${newCount} new expense(s)` : null,
                  updatedCount > 0 ? `${updatedCount} updated expense(s)` : null,
                  removedCount > 0 ? `${removedCount} expense(s) removed` : null,
              ]
                  .filter(Boolean)
                  .join(', ')
            : '',
        totals: {
            previous: formatMoney(previousTotal),
            current: formatMoney(currentTotal),
            changed: previousTotal !== currentTotal,
        },
        confirmLabel: 'Yes, Save Expenses',
    };
}
