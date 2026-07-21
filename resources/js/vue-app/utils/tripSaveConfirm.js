import {
    expenseTypeLabel,
    legLoadCategoryMeta,
    summarizeTripForm,
    tripTypeLabel,
} from '../api/trips';
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

function formatMeter(value) {
    if (value === '' || value == null || !Number.isFinite(Number(value))) {
        return '—';
    }

    return `${formatNum(value)} km`;
}

function legSignature(leg) {
    return [
        leg.from_station_id,
        leg.to_station_id,
        leg.customer_id,
        String(leg.goods_name ?? '').trim(),
        String(leg.pickup_date ?? '').trim(),
        Number(leg.no_of_units ?? 0),
        Number(leg.weight_ton ?? 0),
        Number(leg.expected_mileage_km_per_liter ?? 0),
        Number(leg.freight_rate ?? 0),
        Number(leg.freight_amount ?? 0),
        Number(leg.distance_km ?? 0),
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
    const maxLoadTon = Number(form.max_load_ton ?? 0);
    const tripType = String(form.trip_type ?? '').toUpperCase() || 'ONE_WAY';

    const detailFields = [
        {
            label: 'Trip Number',
            value: tripNumber || 'Auto-generated',
            accent: true,
            readonly: true,
        },
        {
            label: 'Trip Type',
            compareValue: tripType,
            comparePrevious: originalPayload?.trip_type
                ? String(originalPayload.trip_type).toUpperCase()
                : undefined,
            value: tripTypeLabel(tripType),
            previousValue: originalPayload?.trip_type
                ? tripTypeLabel(originalPayload.trip_type)
                : '—',
        },
        {
            label: 'Truck',
            value: vehicleLabel,
            accent: true,
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
            label: 'Reference by',
            compareValue: form.broker_id,
            comparePrevious: originalPayload?.broker_id,
            value: lookupName(brokers, form.broker_id, '—'),
            previousValue: lookupName(brokers, originalPayload?.broker_id, '—'),
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
            label: 'Start Meter',
            compareValue: form.start_meter_reading_km,
            comparePrevious: originalPayload?.start_meter_reading_km,
            value: formatMeter(form.start_meter_reading_km),
            previousValue: formatMeter(originalPayload?.start_meter_reading_km),
        },
        {
            label: 'End Meter',
            compareValue: form.end_meter_reading_km,
            comparePrevious: originalPayload?.end_meter_reading_km,
            value: formatMeter(form.end_meter_reading_km),
            previousValue: formatMeter(originalPayload?.end_meter_reading_km),
        },
        {
            label: 'Notes',
            compareValue: form.notes?.trim() ?? '',
            comparePrevious: originalPayload?.notes?.trim() ?? '',
            value: form.notes?.trim() || '—',
            previousValue: originalPayload?.notes?.trim() || '—',
            hidden: !form.notes?.trim() && !(originalPayload?.notes?.trim())
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
                accent: Boolean(field.accent),
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
            case 'pickupDate':
                return formatDate(leg.pickup_date) || '—';
            case 'units':
                return leg.no_of_units == null || leg.no_of_units === ''
                    ? '—'
                    : String(leg.no_of_units);
            case 'weight':
                return formatNum(leg.weight_ton);
            case 'load':
                return legLoadCategoryMeta(leg.weight_ton, maxLoadTon).label;
            case 'distance':
                return leg.distance_km == null || leg.distance_km === '' || Number(leg.distance_km) <= 0
                    ? '—'
                    : `${formatNum(leg.distance_km)} km`;
            case 'expectedMileage':
                return leg.expected_mileage_km_per_liter == null || leg.expected_mileage_km_per_liter === ''
                    ? '—'
                    : `${formatNum(leg.expected_mileage_km_per_liter)} KM/LTR`;
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
        const loadMeta = legLoadCategoryMeta(leg.weight_ton, maxLoadTon);

        const legFieldDefs = [
            { key: 'from', label: 'From' },
            { key: 'to', label: 'To' },
            { key: 'customer', label: 'Customer' },
            { key: 'pickupDate', label: 'Pickup Date' },
            { key: 'goods', label: 'Goods Type' },
            { key: 'units', label: 'No. of Units' },
            { key: 'weight', label: 'Weight (Ton)' },
            { key: 'rate', label: 'Freight Rate' },
            { key: 'freight', label: 'Freight' },
            { key: 'expectedMileage', label: 'Expected Mileage' },
            { key: 'distance', label: 'Distance' },
            { key: 'load', label: 'Load' },
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
            pickupDate: formatDate(leg.pickup_date) || '—',
            units:
                leg.no_of_units == null || leg.no_of_units === ''
                    ? '—'
                    : String(leg.no_of_units),
            weight: formatNum(leg.weight_ton),
            load: loadMeta.label,
            loadBadge: loadMeta.badge ?? 'bg-secondary',
            distance:
                leg.distance_km == null || leg.distance_km === '' || Number(leg.distance_km) <= 0
                    ? '—'
                    : `${formatNum(leg.distance_km)} km`,
            expectedMileage:
                leg.expected_mileage_km_per_liter == null || leg.expected_mileage_km_per_liter === ''
                    ? '—'
                    : `${formatNum(leg.expected_mileage_km_per_liter)} KM/LTR`,
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
        const notes = String(expense.notes ?? '').trim() || '—';
        const previousNotes = originalExpense
            ? String(originalExpense.notes ?? '').trim() || '—'
            : null;

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
            notes,
            previousNotes:
                changed && previousNotes && previousNotes !== notes ? previousNotes : null,
            amount: formatMoney(expense.amount),
            previousAmount: changed && previousAmount !== formatMoney(expense.amount) ? previousAmount : null,
            isNew,
            changed: isNew || changed,
        };
    });

    const removedExpenseCount =
        mode === 'edit' ? Math.max(0, originalExpenses.length - currentExpenses.length) : 0;

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

    return {
        mode,
        title: mode === 'create' ? 'Confirm New Trip' : 'Confirm Trip Update',
        iconTitle: mode === 'create' ? 'Confirm New Trip' : 'Confirm Trip Update',
        message:
            mode === 'create'
                ? 'Review every detail below before saving.'
                : hasChanges
                  ? 'Changed items are highlighted — review before updating.'
                  : 'Review the trip details below before saving.',
        detailFields,
        // Kept for backward compatibility with older modal markup.
        basicInfoFields: detailFields.filter((field) =>
            ['Trip Number', 'Trip Type', 'Start Date', 'End Date', 'Start Meter', 'End Meter', 'Notes'].includes(field.label),
        ),
        partyFields: detailFields.filter((field) =>
            ['Customer', 'Reference by'].includes(field.label),
        ),
        transportFields: detailFields.filter((field) =>
            ['Truck', 'Primary Driver', 'Substitute Driver'].includes(field.label),
        ),
        routeLegs,
        expenses,
        removedLegCount,
        removedExpenseCount,
        totals: {
            fullLoadStops: summary.fullLoadStops,
            halfLoadStops: summary.halfLoadStops,
            emptyStops: summary.emptyStops,
            expectedMileage:
                summary.expectedMileage == null ? '—' : `${formatNum(summary.expectedMileage)} KM/LTR`,
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
