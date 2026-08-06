import { reactive, ref, watch } from 'vue';
import {
    buildFuelRowSignature,
    calcDieselAmount,
    clampExpenseDate,
    emptyExpense,
    isActiveFuelRow,
    isDieselExpenseType,
    resolveExpenseRouteLegSequence,
} from '../api/trips';
import { toDateInputFromIso } from '../utils/datetime';

function mapExpenseRow(expense, form) {
    const row = {
        id: expense.id ?? null,
        expense_type: expense.expense_type ?? 'DIESEL',
        expense_date: clampExpenseDate(
            toDateInputFromIso(expense.expense_date) || form.start_at,
            form.start_at,
            form.end_at,
        ),
        amount: Number(expense.amount ?? 0),
        fuel_liters: expense.fuel_liters != null && expense.fuel_liters !== '' ? Number(expense.fuel_liters) : '',
        fuel_rate: expense.fuel_rate != null && expense.fuel_rate !== '' ? Number(expense.fuel_rate) : '',
        fuel_odometer_km:
            expense.fuel_odometer_km != null && expense.fuel_odometer_km !== ''
                ? Number(expense.fuel_odometer_km)
                : '',
        route_leg_sequence: resolveExpenseRouteLegSequence(expense, form.route_legs),
        notes: expense.notes ?? '',
        other_type: expense.other_type ?? '',
        _fuelDirty: false,
        _originalFuelSignature: null,
    };

    if (isDieselExpenseType(row.expense_type) && isActiveFuelRow(row)) {
        row._originalFuelSignature = buildFuelRowSignature(row);
    }

    return row;
}

export function useTripExpensesForm() {
    const form = reactive({
        start_at: '',
        end_at: '',
        start_meter_reading_km: '',
        end_meter_reading_km: '',
        route_legs: [],
        expenses: [],
        max_load_ton: '',
        planned_mileage_km_per_liter: '',
        planned_diesel_liters: '',
        diesel_rate_per_liter: '',
        customer_name: '',
        broker_name: '',
        total_freight_amount: 0,
        total_distance_km: '',
    });

    /** True when the trip loaded with no start meter — field stays editable until saved. */
    const startMeterOriginallyEmpty = ref(false);

    function resetForm() {
        form.start_at = '';
        form.end_at = '';
        form.start_meter_reading_km = '';
        form.end_meter_reading_km = '';
        form.route_legs = [];
        form.expenses = [];
        form.max_load_ton = '';
        form.planned_mileage_km_per_liter = '';
        form.planned_diesel_liters = '';
        form.diesel_rate_per_liter = '';
        form.customer_name = '';
        form.broker_name = '';
        form.total_freight_amount = 0;
        form.total_distance_km = '';
        startMeterOriginallyEmpty.value = false;
    }

    function populateFromTrip(trip) {
        if (!trip) {
            resetForm();

            return;
        }

        form.start_at = toDateInputFromIso(trip.start_at);
        form.end_at = toDateInputFromIso(trip.end_at);
        form.start_meter_reading_km =
            trip.start_meter_reading_km != null && trip.start_meter_reading_km !== ''
                ? Number(trip.start_meter_reading_km)
                : '';
        form.end_meter_reading_km =
            trip.end_meter_reading_km != null && trip.end_meter_reading_km !== ''
                ? Number(trip.end_meter_reading_km)
                : '';
        form.max_load_ton =
            trip.max_load_ton != null && trip.max_load_ton !== '' ? Number(trip.max_load_ton) : '';
        form.planned_mileage_km_per_liter =
            trip.planned_mileage_km_per_liter != null && trip.planned_mileage_km_per_liter !== ''
                ? Number(trip.planned_mileage_km_per_liter)
                : '';
        form.planned_diesel_liters =
            trip.planned_diesel_liters != null && trip.planned_diesel_liters !== ''
                ? Number(trip.planned_diesel_liters)
                : '';
        form.diesel_rate_per_liter =
            trip.diesel_rate_per_liter != null && trip.diesel_rate_per_liter !== ''
                ? Number(trip.diesel_rate_per_liter)
                : '';
        form.customer_name = trip.customer_name ?? '';
        form.broker_name = trip.broker_name ?? '';
        form.total_freight_amount = Number(trip.total_freight_amount ?? 0);
        form.total_distance_km =
            trip.total_distance_km != null && trip.total_distance_km !== ''
                ? Number(trip.total_distance_km)
                : '';

        startMeterOriginallyEmpty.value =
            form.start_meter_reading_km === ''
            || form.start_meter_reading_km == null
            || !(Number(form.start_meter_reading_km) > 0);

        form.route_legs = (trip.route_legs ?? []).map((leg) => ({
            id: leg.id ?? null,
            from_station_id: leg.from_station_id ?? '',
            to_station_id: leg.to_station_id ?? '',
            from_city: leg.from_city ?? '',
            to_city: leg.to_city ?? '',
            sequence_no: leg.sequence_no ?? 1,
            weight_ton:
                leg.weight_ton === '' || leg.weight_ton == null ? '' : Number(leg.weight_ton),
            customer_name: leg.customer_name ?? '',
            goods_name: leg.goods_name ?? '',
            freight_amount:
                leg.freight_amount === '' || leg.freight_amount == null
                    ? 0
                    : Number(leg.freight_amount),
            distance_km:
                leg.distance_km === '' || leg.distance_km == null ? 0 : Number(leg.distance_km),
        }));

        form.expenses = (trip.expenses ?? []).map((expense) => mapExpenseRow(expense, form));
        prefillDieselOdometersFromStartMeter();
    }

    function resolveStartMeterKm() {
        const startMeter = Number(form.start_meter_reading_km);

        if (!Number.isFinite(startMeter) || startMeter <= 0) {
            return null;
        }

        return Math.trunc(startMeter);
    }

    function prefillDieselOdometersFromStartMeter() {
        const startMeter = resolveStartMeterKm();

        if (startMeter == null) {
            return;
        }

        form.expenses.forEach((expense) => {
            if (!isDieselExpenseType(expense.expense_type)) {
                return;
            }

            const currentOdo = expense.fuel_odometer_km;

            if (currentOdo === '' || currentOdo == null) {
                expense.fuel_odometer_km = startMeter;
            }
        });
    }

    function canEditStartMeter() {
        return startMeterOriginallyEmpty.value;
    }

    function addExpense({ routeLegSequence = '', expenseType = 'DIESEL' } = {}) {
        const expense = emptyExpense({
            defaultDate: form.start_at,
            expenseType,
            dieselRatePerLiter: form.diesel_rate_per_liter,
            startMeterReadingKm: form.start_meter_reading_km,
        });
        expense.route_leg_sequence =
            routeLegSequence === '' || routeLegSequence == null ? '' : Number(routeLegSequence);
        expense._fuelDirty = false;
        expense._originalFuelSignature = null;
        form.expenses.push(expense);

        // Ensure km is set even if type was added empty and start meter is already present.
        if (isDieselExpenseType(expense.expense_type)) {
            const startMeter = resolveStartMeterKm();
            const currentOdo = expense.fuel_odometer_km;

            if (startMeter != null && (currentOdo === '' || currentOdo == null)) {
                expense.fuel_odometer_km = startMeter;
            }
        }
    }

    function removeExpense(index) {
        form.expenses.splice(index, 1);
    }

    function buildExpensesPayload({ includeStartMeter = false } = {}) {
        const payload = {
            expenses: form.expenses
                .filter((expense) => {
                    if (isDieselExpenseType(expense.expense_type)) {
                        return isActiveFuelRow(expense);
                    }

                    return Number(expense.amount) > 0;
                })
                .map((expense) => {
                    const amount = isDieselExpenseType(expense.expense_type)
                        ? calcDieselAmount(expense.fuel_liters, expense.fuel_rate)
                        : expense.amount;

                    return {
                        expense_type: expense.expense_type,
                        expense_date: expense.expense_date || form.start_at || null,
                        amount,
                        fuel_liters:
                            expense.fuel_liters === '' || expense.fuel_liters == null
                                ? null
                                : Number(expense.fuel_liters),
                        fuel_rate:
                            expense.fuel_rate === '' || expense.fuel_rate == null
                                ? null
                                : Number(expense.fuel_rate),
                        fuel_odometer_km:
                            expense.fuel_odometer_km === '' || expense.fuel_odometer_km == null
                                ? null
                                : Math.trunc(Number(expense.fuel_odometer_km)),
                        route_leg_sequence:
                            expense.route_leg_sequence === '' || expense.route_leg_sequence == null
                                ? null
                                : Number(expense.route_leg_sequence),
                        other_type: expense.expense_type === 'OTHER' ? expense.other_type || null : null,
                        notes: expense.notes || null,
                    };
                }),
        };

        if (includeStartMeter && startMeterOriginallyEmpty.value) {
            const startMeter = Number(form.start_meter_reading_km);

            if (Number.isFinite(startMeter) && startMeter > 0) {
                payload.start_meter_reading_km = Math.trunc(startMeter);
            }
        }

        return payload;
    }

    function markFuelFieldDirty(index) {
        const expense = form.expenses[index];

        if (expense) {
            expense._fuelDirty = true;
        }
    }

    watch(
        () => [form.start_at, form.end_at],
        () => {
            if (!form.start_at) {
                return;
            }

            form.expenses.forEach((expense) => {
                expense.expense_date = clampExpenseDate(
                    expense.expense_date || form.start_at,
                    form.start_at,
                    form.end_at,
                );
            });
        },
    );

    watch(
        () => form.start_meter_reading_km,
        () => {
            prefillDieselOdometersFromStartMeter();
        },
    );

    return {
        form,
        resetForm,
        populateFromTrip,
        canEditStartMeter,
        addExpense,
        removeExpense,
        buildExpensesPayload,
        markFuelFieldDirty,
    };
}
