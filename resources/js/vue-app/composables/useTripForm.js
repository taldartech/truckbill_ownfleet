import { reactive, watch } from 'vue';
import { clampExpenseDate, emptyExpense, emptyRouteStop, parseNumeric } from '../api/trips';
import { toDateInputFromIso } from '../utils/datetime';

export function useTripForm() {
    const form = reactive({
        trip_number: '',
        vehicle_id: '',
        driver_id: '',
        substitute_driver_id: '',
        customer_id: '',
        broker_id: '',
        start_station_id: '',
        trip_type: 'ROUND_TRIP',
        status: 'CREATED',
        start_at: '',
        end_at: '',
        cancelled_at: '',
        cancellation_reason: '',
        notes: '',
        actual_arrival_at: '',
        start_meter_reading_km: '',
        end_meter_reading_km: '',
        planned_mileage_km_per_liter: '',
        max_load_ton: '',
        planned_diesel_liters: '',
        planned_diesel_manually_set: false,
        diesel_rate_per_liter: '',
        route_legs: [],
        expenses: [],
    });

    function resetForm() {
        form.trip_number = '';
        form.vehicle_id = '';
        form.driver_id = '';
        form.substitute_driver_id = '';
        form.customer_id = '';
        form.broker_id = '';
        form.start_station_id = '';
        form.trip_type = 'ROUND_TRIP';
        form.status = 'CREATED';
        form.start_at = '';
        form.end_at = '';
        form.cancelled_at = '';
        form.cancellation_reason = '';
        form.notes = '';
        form.actual_arrival_at = '';
        form.start_meter_reading_km = '';
        form.end_meter_reading_km = '';
        form.planned_mileage_km_per_liter = '';
        form.max_load_ton = '';
        form.planned_diesel_liters = '';
        form.planned_diesel_manually_set = false;
        form.diesel_rate_per_liter = '';
        form.route_legs = [];
        form.expenses = [];
    }

    function applySameCustomerDefault(leg) {
        const customerId = form.customer_id ?? '';

        if (customerId === '') {
            return;
        }

        leg.use_same_customer = true;
        leg.customer_id = customerId;
    }

    function syncLegCustomersFromTrip() {
        const customerId = form.customer_id ?? '';

        form.route_legs.forEach((leg) => {
            if (leg.use_same_customer) {
                leg.customer_id = customerId;

                return;
            }

            if (customerId !== '' && !leg.customer_id) {
                applySameCustomerDefault(leg);
            }
        });
    }

    function rebuildLegChain() {
        form.route_legs.forEach((leg, index) => {
            leg.sequence_no = index + 1;
            leg.from_station_id =
                index === 0 ? form.start_station_id : form.route_legs[index - 1]?.to_station_id ?? '';
        });
    }

    function addStop() {
        if (!form.start_station_id) {
            return;
        }

        const fromStationId =
            form.route_legs.length === 0
                ? form.start_station_id
                : form.route_legs[form.route_legs.length - 1]?.to_station_id ?? '';

        const defaultMileage =
            form.planned_mileage_km_per_liter !== '' && form.planned_mileage_km_per_liter != null
                ? form.planned_mileage_km_per_liter
                : '';

        const leg = emptyRouteStop(form.route_legs.length + 1, fromStationId, '', '', defaultMileage);
        applySameCustomerDefault(leg);
        form.route_legs.push(leg);
    }

    function addReturnToStart() {
        if (!form.start_station_id || form.route_legs.length === 0) {
            return;
        }

        const lastLeg = form.route_legs[form.route_legs.length - 1];

        if (String(lastLeg.to_station_id) === String(form.start_station_id)) {
            return;
        }

        const defaultMileage =
            form.planned_mileage_km_per_liter !== '' && form.planned_mileage_km_per_liter != null
                ? form.planned_mileage_km_per_liter
                : '';

        const returnLeg = emptyRouteStop(
            form.route_legs.length + 1,
            lastLeg.to_station_id,
            form.start_station_id,
            '',
            defaultMileage,
        );
        returnLeg.weight_ton = 0;
        returnLeg.freight_amount = 0;
        returnLeg.freight_rate = '';
        returnLeg.freight_auto_calculate = true;
        applySameCustomerDefault(returnLeg);

        form.route_legs.push(returnLeg);
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
        form.substitute_driver_id = trip.substitute_driver_id ?? '';
        form.start_station_id = trip.start_station_id ?? '';
        form.trip_type = trip.trip_type ?? 'ROUND_TRIP';
        form.customer_id = trip.customer_id ?? '';
        form.broker_id = trip.broker_id ?? '';
        form.status = trip.status ?? 'CREATED';
        form.start_at = toDateInputFromIso(trip.start_at);
        form.end_at = toDateInputFromIso(trip.end_at);
        form.cancelled_at = toDateInputFromIso(trip.cancelled_at);
        form.cancellation_reason = trip.cancellation_reason ?? '';
        form.notes = trip.notes ?? '';
        form.actual_arrival_at = toDateInputFromIso(trip.actual_arrival_at);
        form.start_meter_reading_km =
            trip.start_meter_reading_km != null && trip.start_meter_reading_km !== ''
                ? Number(trip.start_meter_reading_km)
                : '';
        form.end_meter_reading_km =
            trip.end_meter_reading_km != null && trip.end_meter_reading_km !== ''
                ? Number(trip.end_meter_reading_km)
                : '';
        form.planned_mileage_km_per_liter =
            trip.planned_mileage_km_per_liter != null && trip.planned_mileage_km_per_liter !== ''
                ? Number(trip.planned_mileage_km_per_liter)
                : '';
        form.max_load_ton =
            trip.max_load_ton != null && trip.max_load_ton !== '' ? Number(trip.max_load_ton) : '';
        form.planned_diesel_liters =
            trip.planned_diesel_liters != null && trip.planned_diesel_liters !== ''
                ? Number(trip.planned_diesel_liters)
                : '';
        form.planned_diesel_manually_set = false;
        form.diesel_rate_per_liter =
            trip.diesel_rate_per_liter != null && trip.diesel_rate_per_liter !== ''
                ? Number(trip.diesel_rate_per_liter)
                : '';
        const tripCustomerId = trip.customer_id ?? '';

        form.route_legs = (trip.route_legs ?? []).map((leg) => {
            const customerId = leg.customer_id ?? '';

            return {
                from_station_id: leg.from_station_id ?? '',
                to_station_id: leg.to_station_id ?? '',
                customer_id: customerId,
                use_same_customer:
                    tripCustomerId !== '' && String(customerId) === String(tripCustomerId),
                from_city: leg.from_city ?? leg.from_location ?? '',
                to_city: leg.to_city ?? leg.to_location ?? '',
                sequence_no: leg.sequence_no ?? 1,
                freight_rate:
                    leg.freight_rate == null || leg.freight_rate === '' ? '' : Number(leg.freight_rate),
                freight_amount: Number(leg.freight_amount ?? 0),
                freight_auto_calculate: true,
                weight_ton:
                    leg.weight_ton === '' || leg.weight_ton == null ? '' : Number(leg.weight_ton),
                expected_mileage_km_per_liter:
                    leg.expected_mileage_km_per_liter == null || leg.expected_mileage_km_per_liter === ''
                        ? ''
                        : Number(leg.expected_mileage_km_per_liter),
                remarks: leg.remarks ?? '',
            };
        });

        const hasLegWeight = form.route_legs.some((leg) => Number(leg.weight_ton) > 0);

        if (!hasLegWeight && trip.load_weight_ton != null && trip.load_weight_ton !== '') {
            const firstLeg = form.route_legs[0];

            if (firstLeg) {
                firstLeg.weight_ton = Number(trip.load_weight_ton);
            }
        }

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

        rebuildLegChain();
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

    function buildPayload(stations = [], { mode = 'create', includeExpenses = true } = {}) {
        rebuildLegChain();

        const payload = {
            substitute_driver_id: form.substitute_driver_id || null,
            customer_id: form.customer_id || null,
            broker_id: form.broker_id || null,
            start_station_id: form.start_station_id,
            start_at: form.start_at || null,
            end_at: form.end_at || null,
            end_meter_reading_km:
                form.end_meter_reading_km === '' || form.end_meter_reading_km == null
                    ? null
                    : Number(form.end_meter_reading_km),
            planned_mileage_km_per_liter:
                form.planned_mileage_km_per_liter === '' || form.planned_mileage_km_per_liter == null
                    ? 0
                    : Number(form.planned_mileage_km_per_liter),
            max_load_ton:
                form.max_load_ton === '' || form.max_load_ton == null ? 0 : Number(form.max_load_ton),
            planned_diesel_liters:
                form.planned_diesel_liters === '' || form.planned_diesel_liters == null
                    ? null
                    : Number(form.planned_diesel_liters),
            diesel_rate_per_liter:
                form.diesel_rate_per_liter === '' || form.diesel_rate_per_liter == null
                    ? null
                    : Number(form.diesel_rate_per_liter),
            cancelled_at: form.cancelled_at || null,
            cancellation_reason: form.cancellation_reason || null,
            notes: form.notes?.trim() || null,
            actual_arrival_at: form.actual_arrival_at || null,
            route_legs: form.route_legs.map((leg, index) => {
                const weight =
                    leg.weight_ton === '' || leg.weight_ton == null ? 0 : Number(leg.weight_ton);

                return {
                    from_station_id: leg.from_station_id,
                    to_station_id: leg.to_station_id,
                    customer_id: leg.customer_id || null,
                    from_city: stationName(stations, leg.from_station_id),
                    to_city: stationName(stations, leg.to_station_id),
                    sequence_no: leg.sequence_no || index + 1,
                    freight_rate:
                        leg.freight_rate === '' || leg.freight_rate == null ? null : Number(leg.freight_rate),
                    weight_ton: weight > 0 ? weight : null,
                    expected_mileage_km_per_liter:
                        leg.expected_mileage_km_per_liter === '' || leg.expected_mileage_km_per_liter == null
                            ? null
                            : Number(leg.expected_mileage_km_per_liter),
                    freight_amount: weight > 0 ? Number(leg.freight_amount ?? 0) : 0,
                    remarks: leg.remarks || null,
                };
            }),
            expenses: includeExpenses
                ? form.expenses
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
                      }))
                : [],
        };

        if (mode === 'create') {
            payload.vehicle_id = form.vehicle_id;
            payload.driver_id = form.driver_id;
            payload.trip_type = form.trip_type;
            payload.start_meter_reading_km = parseNumeric(form.start_meter_reading_km);
        } else {
            payload.status = form.status;
            payload.start_meter_reading_km = parseNumeric(form.start_meter_reading_km);
        }

        return payload;
    }

    function buildStatusChangePayload() {
        const payload = {
            status: form.status,
            start_at: form.start_at || null,
            end_at: form.end_at || null,
            actual_arrival_at: form.actual_arrival_at || null,
            cancelled_at: form.cancelled_at || null,
            cancellation_reason: form.cancellation_reason || null,
        };

        const startMeter = parseNumeric(form.start_meter_reading_km);

        if (startMeter > 0) {
            payload.start_meter_reading_km = startMeter;
        }

        const endMeter =
            form.end_meter_reading_km === '' || form.end_meter_reading_km == null
                ? null
                : Number(form.end_meter_reading_km);

        if (endMeter != null && endMeter > 0) {
            payload.end_meter_reading_km = endMeter;
        }

        return payload;
    }

    watch(
        () => form.customer_id,
        () => {
            syncLegCustomersFromTrip();
        },
    );

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
        addStop,
        addReturnToStart,
        removeRouteLeg,
        rebuildLegChain,
        onStartStationChange,
        onStopStationChange,
        addExpense,
        removeExpense,
        buildPayload,
        buildStatusChangePayload,
        stationName,
        applyLegacyPartyLookups,
        syncLegCustomersFromTrip,
    };
}
