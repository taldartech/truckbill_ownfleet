import { reactive, watch } from 'vue';
import { clampExpenseDate, emptyExpense } from '../api/trips';
import { toDateInputFromIso } from '../utils/datetime';

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
        diesel_rate_per_liter: '',
    });

    function resetForm() {
        form.start_at = '';
        form.end_at = '';
        form.start_meter_reading_km = '';
        form.end_meter_reading_km = '';
        form.route_legs = [];
        form.expenses = [];
        form.max_load_ton = '';
        form.planned_mileage_km_per_liter = '';
        form.diesel_rate_per_liter = '';
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
        form.diesel_rate_per_liter =
            trip.diesel_rate_per_liter != null && trip.diesel_rate_per_liter !== ''
                ? Number(trip.diesel_rate_per_liter)
                : '';

        form.route_legs = (trip.route_legs ?? []).map((leg) => ({
            from_station_id: leg.from_station_id ?? '',
            to_station_id: leg.to_station_id ?? '',
            from_city: leg.from_city ?? leg.from_location ?? '',
            to_city: leg.to_city ?? leg.to_location ?? '',
            sequence_no: leg.sequence_no ?? 1,
            weight_ton:
                leg.weight_ton === '' || leg.weight_ton == null ? '' : Number(leg.weight_ton),
        }));

        form.expenses = (trip.expenses ?? []).map((expense) => ({
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
            route_leg_sequence: expense.route_leg_sequence ?? '',
            notes: expense.notes ?? '',
            other_type: expense.other_type ?? '',
        }));
    }

    function addExpense({ routeLegSequence = '', expenseType = 'DIESEL' } = {}) {
        const expense = emptyExpense({
            defaultDate: form.start_at,
            expenseType,
            dieselRatePerLiter: form.diesel_rate_per_liter,
        });
        expense.route_leg_sequence =
            routeLegSequence === '' || routeLegSequence == null ? '' : Number(routeLegSequence);
        form.expenses.push(expense);
    }

    function removeExpense(index) {
        form.expenses.splice(index, 1);
    }

    function buildExpensesPayload() {
        return {
            expenses: form.expenses
                .filter((e) => Number(e.amount) > 0)
                .map((e) => ({
                    expense_type: e.expense_type,
                    expense_date: e.expense_date || form.start_at || null,
                    amount: e.amount,
                    fuel_liters: e.fuel_liters === '' || e.fuel_liters == null ? null : Number(e.fuel_liters),
                    fuel_rate: e.fuel_rate === '' || e.fuel_rate == null ? null : Number(e.fuel_rate),
                    fuel_odometer_km:
                        e.fuel_odometer_km === '' || e.fuel_odometer_km == null ? null : Number(e.fuel_odometer_km),
                    route_leg_sequence: e.route_leg_sequence || null,
                    other_type: e.expense_type === 'OTHER' ? e.other_type || null : null,
                    notes: e.notes || null,
                })),
        };
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

    return {
        form,
        resetForm,
        populateFromTrip,
        addExpense,
        removeExpense,
        buildExpensesPayload,
    };
}
