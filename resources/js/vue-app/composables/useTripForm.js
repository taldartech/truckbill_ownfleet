import { reactive } from 'vue';
import { emptyExpense, emptyRouteStop } from '../api/trips';
import { toDateInputFromIso } from '../utils/datetime';

export function useTripForm() {
    const form = reactive({
        trip_number: '',
        vehicle_id: '',
        driver_id: '',
        customer_id: '',
        broker_id: '',
        start_station_id: '',
        status: 'CREATED',
        start_at: '',
        end_at: '',
        cancelled_at: '',
        cancellation_reason: '',
        actual_arrival_at: '',
        route_legs: [],
        expenses: [],
    });

    function resetForm() {
        form.trip_number = '';
        form.vehicle_id = '';
        form.driver_id = '';
        form.customer_id = '';
        form.broker_id = '';
        form.start_station_id = '';
        form.status = 'CREATED';
        form.start_at = '';
        form.end_at = '';
        form.cancelled_at = '';
        form.cancellation_reason = '';
        form.actual_arrival_at = '';
        form.route_legs = [];
        form.expenses = [];
    }

    function rebuildLegChain() {
        form.route_legs.forEach((leg, index) => {
            leg.sequence_no = index + 1;
            leg.from_station_id =
                index === 0 ? form.start_station_id : form.route_legs[index - 1]?.to_station_id ?? '';
        });
    }

    function addStop(defaultType = 'LOADED') {
        if (!form.start_station_id) {
            return;
        }

        const fromStationId =
            form.route_legs.length === 0
                ? form.start_station_id
                : form.route_legs[form.route_legs.length - 1]?.to_station_id ?? '';

        form.route_legs.push(emptyRouteStop(form.route_legs.length + 1, fromStationId, defaultType));
    }

    function addReturnToStart() {
        if (!form.start_station_id || form.route_legs.length === 0) {
            return;
        }

        const lastLeg = form.route_legs[form.route_legs.length - 1];

        if (String(lastLeg.to_station_id) === String(form.start_station_id)) {
            return;
        }

        form.route_legs.push(
            emptyRouteStop(form.route_legs.length + 1, lastLeg.to_station_id, 'EMPTY', form.start_station_id),
        );
    }

    function removeRouteLeg(index) {
        form.route_legs.splice(index, 1);
        rebuildLegChain();
    }

    function onStartStationChange() {
        rebuildLegChain();
    }

    function onStopStationChange(index) {
        rebuildLegChain();

        if (index < form.route_legs.length - 1) {
            form.route_legs[index + 1].from_station_id = form.route_legs[index].to_station_id;
        }
    }

    function populateFromTrip(trip) {
        if (!trip) {
            resetForm();

            return;
        }

        form.trip_number = trip.trip_number ?? '';
        form.vehicle_id = trip.vehicle_id ?? '';
        form.driver_id = trip.driver_id ?? '';
        form.start_station_id = trip.start_station_id ?? '';
        form.customer_id = trip.customer_id ?? '';
        form.broker_id = trip.broker_id ?? '';
        form.status = trip.status ?? 'CREATED';
        form.start_at = toDateInputFromIso(trip.start_at);
        form.end_at = toDateInputFromIso(trip.end_at);
        form.cancelled_at = toDateInputFromIso(trip.cancelled_at);
        form.cancellation_reason = trip.cancellation_reason ?? '';
        form.actual_arrival_at = toDateInputFromIso(trip.actual_arrival_at);
        form.route_legs = (trip.route_legs ?? []).map((leg) => ({
            from_station_id: leg.from_station_id ?? '',
            to_station_id: leg.to_station_id ?? '',
            from_city: leg.from_city ?? leg.from_location ?? '',
            to_city: leg.to_city ?? leg.to_location ?? '',
            route_type: leg.route_type ?? 'LOADED',
            sequence_no: leg.sequence_no ?? 1,
            distance_km: Number(leg.distance_km ?? 0),
            freight_amount: Number(leg.freight_amount ?? 0),
            remarks: leg.remarks ?? '',
        }));

        form.expenses = (trip.expenses ?? []).map((expense) => ({
            expense_type: expense.expense_type ?? 'DIESEL',
            amount: Number(expense.amount ?? 0),
            fuel_liters: expense.fuel_liters != null && expense.fuel_liters !== '' ? Number(expense.fuel_liters) : '',
            fuel_rate: expense.fuel_rate != null && expense.fuel_rate !== '' ? Number(expense.fuel_rate) : '',
            odometer_start:
                expense.odometer_start != null && expense.odometer_start !== ''
                    ? Number(expense.odometer_start)
                    : '',
            odometer_end:
                expense.odometer_end != null && expense.odometer_end !== '' ? Number(expense.odometer_end) : '',
            route_leg_sequence: expense.route_leg_sequence ?? '',
            notes: expense.notes ?? '',
        }));

        rebuildLegChain();
    }

    function addExpense({ routeLegSequence = '', expenseType = 'DIESEL' } = {}) {
        const expense = emptyExpense();
        expense.expense_type = expenseType;
        expense.route_leg_sequence =
            routeLegSequence === '' || routeLegSequence == null ? '' : Number(routeLegSequence);
        form.expenses.push(expense);
    }

    function removeExpense(index) {
        form.expenses.splice(index, 1);
    }

    function stationName(stations, stationId) {
        if (!stationId) {
            return '—';
        }

        return stations.find((s) => String(s.id) === String(stationId))?.name ?? `#${stationId}`;
    }

    function resolveLookupId(items, id, name) {
        if (id) {
            return id;
        }

        if (!name) {
            return '';
        }

        const match = items.find((item) => String(item.name).toLowerCase() === String(name).toLowerCase());

        return match?.id ?? '';
    }

    function applyLegacyPartyLookups(trip, customers = [], brokers = []) {
        if (!form.customer_id && trip.customer_name) {
            form.customer_id = resolveLookupId(customers, '', trip.customer_name);
        }

        if (!form.broker_id && trip.broker_name) {
            form.broker_id = resolveLookupId(brokers, '', trip.broker_name);
        }
    }

    function buildPayload(stations = [], { mode = 'create' } = {}) {
        rebuildLegChain();

        const payload = {
            customer_id: form.customer_id || null,
            broker_id: form.broker_id || null,
            start_station_id: form.start_station_id,
            start_at: form.start_at || null,
            end_at: form.end_at || null,
            cancelled_at: form.cancelled_at || null,
            cancellation_reason: form.cancellation_reason || null,
            actual_arrival_at: form.actual_arrival_at || null,
            route_legs: form.route_legs.map((leg, index) => ({
                from_station_id: leg.from_station_id,
                to_station_id: leg.to_station_id,
                from_city: stationName(stations, leg.from_station_id),
                to_city: stationName(stations, leg.to_station_id),
                route_type: leg.route_type,
                sequence_no: leg.sequence_no || index + 1,
                distance_km: Number(leg.distance_km ?? 0) || 0,
                freight_amount: leg.route_type === 'EMPTY' ? 0 : leg.freight_amount,
                remarks: leg.remarks || null,
            })),
            expenses: form.expenses
                .filter((e) => Number(e.amount) > 0)
                .map((e) => ({
                    expense_type: e.expense_type,
                    amount: e.amount,
                    fuel_liters: e.fuel_liters === '' || e.fuel_liters == null ? null : Number(e.fuel_liters),
                    fuel_rate: e.fuel_rate === '' || e.fuel_rate == null ? null : Number(e.fuel_rate),
                    odometer_start:
                        e.odometer_start === '' || e.odometer_start == null ? null : Number(e.odometer_start),
                    odometer_end: e.odometer_end === '' || e.odometer_end == null ? null : Number(e.odometer_end),
                    route_leg_sequence: e.route_leg_sequence || null,
                    notes: e.notes || null,
                })),
        };

        if (mode === 'create') {
            payload.vehicle_id = form.vehicle_id;
            payload.driver_id = form.driver_id;
        } else {
            payload.status = form.status;
        }

        return payload;
    }

    return {
        form,
        resetForm,
        populateFromTrip,
        addStop,
        addReturnToStart,
        removeRouteLeg,
        rebuildLegChain,
        onStartStationChange,
        onStopStationChange,
        addExpense,
        removeExpense,
        buildPayload,
        stationName,
        applyLegacyPartyLookups,
    };
}
