import { EXPENSE_TYPES, routeTypeMeta, summarizeTripForm } from '../api/trips';

function formatNum(value) {
    return Number(value ?? 0).toLocaleString('en-IN', { maximumFractionDigits: 2 });
}

function formatMoney(value) {
    return Number(value ?? 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function formatDisplayDate(value) {
    if (!value) {
        return '—';
    }

    const dateOnly = String(value).slice(0, 10);
    const parts = dateOnly.split('-');

    if (parts.length === 3) {
        return `${parts[2]}/${parts[1]}/${parts[0]}`;
    }

    return value;
}

function lookupName(items, id, fallback = '—') {
    if (!id) {
        return fallback;
    }

    return items.find((item) => String(item.id) === String(id))?.name ?? fallback;
}

function expenseTypeLabel(type) {
    return EXPENSE_TYPES.find((item) => item.value === type)?.label ?? type ?? 'Expense';
}

function legSignature(leg) {
    return [
        leg.from_station_id,
        leg.to_station_id,
        leg.customer_id,
        leg.route_type,
        Number(leg.distance_km ?? 0),
        Number(leg.freight_amount ?? 0),
    ].join('|');
}

function normalizePayloadExpenses(expenses = []) {
    return expenses
        .filter((expense) => Number(expense.amount) > 0)
        .map((expense) => ({
            expense_type: expense.expense_type ?? '',
            expense_date: expense.expense_date ?? '',
            amount: Number(expense.amount ?? 0),
            route_leg_sequence: expense.route_leg_sequence ?? null,
            notes: expense.notes ?? '',
        }));
}

function expenseSignature(expense) {
    return [
        expense.expense_type,
        expense.expense_date,
        expense.amount,
        expense.route_leg_sequence,
        expense.notes,
    ].join('|');
}

function fieldChanged(current, previous) {
    if (previous === undefined) {
        return false;
    }

    const left = current == null || current === '' ? '' : String(current);
    const right = previous == null || previous === '' ? '' : String(previous);

    return left !== right;
}

/**
 * @param {object} form
 * @param {object} options
 * @returns {object}
 */
export function buildTripSaveConfirmViewModel(form, options = {}) {
    const {
        mode = 'create',
        stations = [],
        customers = [],
        brokers = [],
        drivers = [],
        originalPayload = null,
        vehicleLabel = '—',
        driverLabel = '—',
        substituteDriverLabel = '—',
        tripNumber = '',
    } = options;

    const summary = summarizeTripForm(form);
    const originalLegs = originalPayload?.route_legs ?? [];
    const originalExpenses = normalizePayloadExpenses(originalPayload?.expenses ?? []);

    const currentExpenses = normalizePayloadExpenses(form.expenses ?? []);

    const detailFields = [
        {
            label: 'Trip Number',
            value: tripNumber || 'Auto-generated',
            readonly: true,
        },
        {
            label: 'Truck',
            value: vehicleLabel,
            readonly: mode === 'edit',
        },
        {
            label: 'Primary Driver',
            compareValue: form.driver_id,
            comparePrevious: originalPayload?.driver_id,
            value: driverLabel,
            previousValue: lookupName(drivers, originalPayload?.driver_id, '—'),
            readonly: mode === 'edit',
        },
        {
            label: 'Substitute Driver',
            compareValue: form.substitute_driver_id,
            comparePrevious: originalPayload?.substitute_driver_id,
            value: substituteDriverLabel,
            previousValue: lookupName(drivers, originalPayload?.substitute_driver_id, '—'),
            hidden: !form.substitute_driver_id && substituteDriverLabel === '—',
        },
        {
            label: 'Customer',
            compareValue: form.customer_id,
            comparePrevious: originalPayload?.customer_id,
            value: lookupName(customers, form.customer_id, '—'),
            previousValue: lookupName(customers, originalPayload?.customer_id, '—'),
        },
        {
            label: 'Broker',
            compareValue: form.broker_id,
            comparePrevious: originalPayload?.broker_id,
            value: lookupName(brokers, form.broker_id, '—'),
            previousValue: lookupName(brokers, originalPayload?.broker_id, '—'),
        },
        {
            label: 'Start Station',
            compareValue: form.start_station_id,
            comparePrevious: originalPayload?.start_station_id,
            value: lookupName(stations, form.start_station_id),
            previousValue: lookupName(stations, originalPayload?.start_station_id),
        },
        {
            label: 'Start Date',
            compareValue: form.start_at,
            comparePrevious: originalPayload?.start_at,
            value: formatDisplayDate(form.start_at),
            previousValue: formatDisplayDate(originalPayload?.start_at),
        },
        {
            label: 'End Date',
            compareValue: form.end_at,
            comparePrevious: originalPayload?.end_at,
            value: formatDisplayDate(form.end_at),
            previousValue: formatDisplayDate(originalPayload?.end_at),
        },
        {
            label: 'Status',
            compareValue: form.status,
            comparePrevious: originalPayload?.status,
            value: form.status ?? 'CREATED',
            previousValue: originalPayload?.status ?? '',
            hidden: mode === 'create',
        },
    ]
        .filter((field) => !field.hidden)
        .map((field) => {
            const changed =
                mode === 'edit' &&
                !field.readonly &&
                field.compareValue !== undefined &&
                fieldChanged(field.compareValue, field.comparePrevious);

            return {
                label: field.label,
                value: field.value,
                changed,
                previousValue: changed ? field.previousValue : null,
            };
        });

    const routeLegs = (form.route_legs ?? []).map((leg, index) => {
        const originalLeg = originalLegs[index];
        const signature = legSignature(leg);
        const isNew = mode === 'edit' && index >= originalLegs.length;
        const changed = mode === 'edit' && originalLeg && legSignature(originalLeg) !== signature;

        return {
            sequence: index + 1,
            from: lookupName(stations, leg.from_station_id),
            to: lookupName(stations, leg.to_station_id),
            customer: lookupName(customers, leg.customer_id, '—'),
            loadType: routeTypeMeta(leg.route_type).label,
            km: formatNum(leg.distance_km),
            freight: formatMoney(leg.freight_amount),
            isNew,
            changed: isNew || changed,
        };
    });

    const removedLegCount =
        mode === 'edit' ? Math.max(0, originalLegs.length - (form.route_legs ?? []).length) : 0;

    const expenses = currentExpenses.map((expense, index) => {
        const originalExpense = originalExpenses[index];
        const isNew = mode === 'edit' && index >= originalExpenses.length;
        const changed =
            mode === 'edit' && originalExpense && expenseSignature(originalExpense) !== expenseSignature(expense);

        return {
            type: expenseTypeLabel(expense.expense_type),
            date: formatDisplayDate(expense.expense_date),
            leg: expense.route_leg_sequence ? `Stop ${expense.route_leg_sequence}` : 'General',
            amount: formatMoney(expense.amount),
            isNew,
            changed: isNew || changed,
        };
    });

    const removedExpenseCount =
        mode === 'edit' ? Math.max(0, originalExpenses.length - currentExpenses.length) : 0;

    const routePreviewParts = [lookupName(stations, form.start_station_id)];

    (form.route_legs ?? []).forEach((leg) => {
        const toName = lookupName(stations, leg.to_station_id, '');

        if (toName && toName !== '—') {
            routePreviewParts.push(toName);
        }
    });

    const changedFieldCount = detailFields.filter((field) => field.changed).length;
    const changedLegCount = routeLegs.filter((leg) => leg.changed).length;
    const changedExpenseCount = expenses.filter((expense) => expense.changed).length;
    const hasChanges =
        mode === 'edit' &&
        (changedFieldCount > 0 ||
            changedLegCount > 0 ||
            changedExpenseCount > 0 ||
            removedLegCount > 0 ||
            removedExpenseCount > 0);

    const pickFields = (...labels) =>
        detailFields.filter((field) => labels.includes(field.label));

    return {
        mode,
        title: mode === 'create' ? 'Confirm New Trip Details' : 'Confirm Trip Update',
        iconTitle: mode === 'create' ? 'Confirm New Trip Details' : 'Confirm Trip Update',
        message:
            mode === 'create'
                ? 'Please review the trip details below. Amounts are highlighted — save only if everything is correct.'
                : hasChanges
                  ? 'Please review what you are updating. Changed items are marked — save only if everything is correct.'
                  : 'Please review the trip details below before saving.',
        basicInfoFields: pickFields('Trip Number', 'Start Date', 'End Date', 'Status'),
        partyFields: pickFields('Customer', 'Broker', 'Start Station'),
        transportFields: pickFields('Truck', 'Primary Driver', 'Substitute Driver'),
        routePreview: routePreviewParts.filter(Boolean).join(' → ') || '—',
        detailFields,
        routeLegs,
        expenses,
        removedLegCount,
        removedExpenseCount,
        totals: {
            loadedKm: formatNum(summary.loadedKm),
            halfLoadedKm: formatNum(summary.halfLoadedKm),
            emptyKm: formatNum(summary.emptyKm),
            totalKm: formatNum(summary.totalKm),
            freight: formatMoney(summary.freightTotal),
            expenses: formatMoney(summary.expenseTotal),
            profit: formatMoney(summary.profit),
            profitPositive: summary.profit >= 0,
        },
        hasChanges,
        changeSummary:
            mode === 'edit' && hasChanges
                ? [
                      changedFieldCount > 0 ? `${changedFieldCount} trip field(s)` : null,
                      changedLegCount > 0 ? `${changedLegCount} route stop(s)` : null,
                      removedLegCount > 0 ? `${removedLegCount} route stop(s) removed` : null,
                      changedExpenseCount > 0 ? `${changedExpenseCount} expense(s)` : null,
                      removedExpenseCount > 0 ? `${removedExpenseCount} expense(s) removed` : null,
                  ]
                      .filter(Boolean)
                      .join(', ')
                : '',
    };
}
