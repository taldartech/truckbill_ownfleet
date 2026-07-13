import { expenseTypeLabel, summarizeTripForm } from '../api/trips';
import { formatDate } from './datetime';

function formatNum(value) {
    return Number(value ?? 0).toLocaleString('en-IN', { maximumFractionDigits: 2 });
}

function formatMoney(value) {
    return Number(value ?? 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function lookupName(items, id, fallback = '—') {
    if (!id) {
        return fallback;
    }

    return items.find((item) => String(item.id) === String(id))?.name ?? fallback;
}

function legSignature(leg) {
    return [
        leg.from_station_id,
        leg.to_station_id,
        leg.customer_id,
        String(leg.goods_name ?? '').trim(),
        Number(leg.weight_ton ?? 0),
        Number(leg.expected_mileage_km_per_liter ?? 0),
        Number(leg.freight_rate ?? 0),
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
            other_type: expense.other_type ?? '',
        }));
}

function expenseSignature(expense) {
    return [
        expense.expense_type,
        expense.expense_date,
        expense.amount,
        expense.route_leg_sequence,
        expense.notes,
        expense.other_type,
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
            label: 'Reference by (optional)',
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
            value: formatDate(form.start_at),
            previousValue: formatDate(originalPayload?.start_at),
        },
        {
            label: 'End Date',
            compareValue: form.end_at,
            comparePrevious: originalPayload?.end_at,
            value: formatDate(form.end_at),
            previousValue: formatDate(originalPayload?.end_at),
        },
        {
            label: 'Notes',
            compareValue: form.notes?.trim() ?? '',
            comparePrevious: originalPayload?.notes?.trim() ?? '',
            value: form.notes?.trim() || '—',
            previousValue: originalPayload?.notes?.trim() || '—',
            hidden: !form.notes?.trim() && !(originalPayload?.notes?.trim()),
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

    function formatLegField(leg, field, stationsList, customersList) {
        switch (field) {
            case 'from':
                return lookupName(stationsList, leg.from_station_id);
            case 'to':
                return lookupName(stationsList, leg.to_station_id);
            case 'customer':
                return lookupName(customersList, leg.customer_id, '—');
            case 'goods':
                return String(leg.goods_name ?? '').trim() || '—';
            case 'weight':
                return formatNum(leg.weight_ton);
            case 'expectedMileage':
                return leg.expected_mileage_km_per_liter == null || leg.expected_mileage_km_per_liter === ''
                    ? '—'
                    : `${formatNum(leg.expected_mileage_km_per_liter)} KM/Lt`;
            case 'rate':
                return `₹${formatMoney(leg.freight_rate)}`;
            case 'freight':
                return `₹${formatMoney(leg.freight_amount)}`;
            default:
                return '—';
        }
    }

    const routeLegs = (form.route_legs ?? []).map((leg, index) => {
        const originalLeg = originalLegs[index];
        const signature = legSignature(leg);
        const isNew = mode === 'edit' && index >= originalLegs.length;
        const changed = mode === 'edit' && originalLeg && legSignature(originalLeg) !== signature;

        const legFieldDefs = [
            { key: 'from', label: 'From' },
            { key: 'to', label: 'To' },
            { key: 'customer', label: 'Customer' },
            { key: 'goods', label: 'Goods Type' },
            { key: 'weight', label: 'Weight (Ton)' },
            { key: 'expectedMileage', label: 'Expected Mileage' },
            { key: 'rate', label: 'Freight Rate' },
            { key: 'freight', label: 'Freight' },
        ];

        const fieldChanges =
            changed && originalLeg
                ? legFieldDefs
                      .map(({ key, label }) => {
                          const previous = formatLegField(originalLeg, key, stations, customers);
                          const current = formatLegField(leg, key, stations, customers);

                          if (previous === current) {
                              return null;
                          }

                          return { label, previous, current };
                      })
                      .filter(Boolean)
                : [];

        return {
            sequence: index + 1,
            from: lookupName(stations, leg.from_station_id),
            to: lookupName(stations, leg.to_station_id),
            customer: lookupName(customers, leg.customer_id, '—'),
            goods: String(leg.goods_name ?? '').trim() || '—',
            weight: formatNum(leg.weight_ton),
            expectedMileage:
                leg.expected_mileage_km_per_liter == null || leg.expected_mileage_km_per_liter === ''
                    ? '—'
                    : `${formatNum(leg.expected_mileage_km_per_liter)} KM/Lt`,
            rate: formatMoney(leg.freight_rate),
            freight: formatMoney(leg.freight_amount),
            isNew,
            changed: isNew || changed,
            fieldChanges,
        };
    });

    const removedLegCount =
        mode === 'edit' ? Math.max(0, originalLegs.length - (form.route_legs ?? []).length) : 0;

    const expenses = currentExpenses.map((expense, index) => {
        const originalExpense = originalExpenses[index];
        const isNew = mode === 'edit' && index >= originalExpenses.length;
        const changed =
            mode === 'edit' && originalExpense && expenseSignature(originalExpense) !== expenseSignature(expense);

        const previousAmount = originalExpense ? formatMoney(originalExpense.amount) : null;

        return {
            type: expenseTypeLabel(expense.expense_type, expense.other_type),
            previousType: originalExpense
                ? expenseTypeLabel(originalExpense.expense_type, originalExpense.other_type)
                : null,
            date: formatDate(expense.expense_date),
            previousDate: originalExpense ? formatDate(originalExpense.expense_date) : null,
            leg: expense.route_leg_sequence ? `Stop ${expense.route_leg_sequence}` : 'General',
            previousLeg: originalExpense
                ? originalExpense.route_leg_sequence
                    ? `Stop ${originalExpense.route_leg_sequence}`
                    : 'General'
                : null,
            amount: formatMoney(expense.amount),
            previousAmount: changed && previousAmount !== formatMoney(expense.amount) ? previousAmount : null,
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
        partyFields: pickFields('Customer', 'Reference by (optional)', 'Start Station'),
        transportFields: pickFields('Truck', 'Primary Driver', 'Substitute Driver'),
        routePreview: routePreviewParts.filter(Boolean).join(' → ') || '—',
        detailFields,
        routeLegs,
        expenses,
        removedLegCount,
        removedExpenseCount,
        totals: {
            fullLoadStops: summary.fullLoadStops,
            halfLoadStops: summary.halfLoadStops,
            emptyStops: summary.emptyStops,
            expectedMileage:
                summary.expectedMileage == null ? '—' : `${formatNum(summary.expectedMileage)} KM/Ltr`,
            totalKm: formatNum(summary.totalKm),
            totalWeight: summary.totalWeightTon > 0 ? formatNum(summary.totalWeightTon) : '—',
            freight: formatMoney(summary.freightTotal),
            expenses: formatMoney(summary.expenseTotal),
            profit: formatMoney(summary.profit),
            profitPositive: summary.profit >= 0,
        },
        hasChanges,
        showExpensesAction: (mode === 'create' || mode === 'edit') && options.showExpensesAction !== false,
        confirmLabel: mode === 'create' ? 'Yes, Save Trip' : 'Yes, Update Trip',
        expensesActionLabel: mode === 'create' ? 'Save & Add Expenses' : 'Update & Add Expenses',
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
