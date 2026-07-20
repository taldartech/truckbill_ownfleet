import { reactive, watch } from 'vue';
import {
    calculateLegFreight,
    clampExpenseDate,
    emptyExpense,
    emptyRouteStop,
    inferTripTypeFromRoute,
    parseNumeric,
    resolveExpenseRouteLegSequence,
    resolveLegFreightAutoCalculate,
    resolveLegWeightTon,
} from '../api/trips';
import { dateOnlyFromDatetimeLocal, toDateInputFromIso } from '../utils/datetime';

export function useTripForm() {
    const form = reactive({
        trip_number: '',
        trip_sequence: '',
        vehicle_id: '',
        driver_id: '',
        substitute_driver_id: '',
        customer_id: '',
        broker_id: '',
        start_station_id: '',
        trip_type: 'ONE_WAY',
        status: 'PENDING',
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
        form.trip_sequence = '';
        form.vehicle_id = '';
        form.driver_id = '';
        form.substitute_driver_id = '';
        form.customer_id = '';
        form.broker_id = '';
        form.start_station_id = '';
        form.trip_type = 'ONE_WAY';
        form.status = 'PENDING';
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

    function tripStartDateOnly() {
        return dateOnlyFromDatetimeLocal(form.start_at);
    }

    function tripEndDateOnly() {
        return dateOnlyFromDatetimeLocal(form.end_at);
    }

    function applyDefaultPickupDate(leg) {
        const startDate = tripStartDateOnly();

        if (startDate) {
            leg.pickup_date = startDate;
        }
    }

    function syncLegPickupDates(previousStartDate = null) {
        const startDate = tripStartDateOnly();
        const endDate = tripEndDateOnly();

        if (!startDate) {
            return;
        }

        form.route_legs.forEach((leg) => {
            const pickup = String(leg.pickup_date ?? '').trim();

            if (!pickup || (previousStartDate && pickup === previousStartDate)) {
                leg.pickup_date = startDate;

                return;
            }

            leg.pickup_date = clampExpenseDate(leg.pickup_date, startDate, endDate);
        });
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
        });
    }

    function rebuildLegChain() {
        form.route_legs.forEach((leg, index) => {
            leg.sequence_no = index + 1;
            leg.from_station_id =
                index === 0 ? form.start_station_id : form.route_legs[index - 1]?.to_station_id ?? '';
        });
    }

    function syncTripTypeFromRoute() {
        form.trip_type = inferTripTypeFromRoute(form.start_station_id, form.route_legs);
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
        applyDefaultPickupDate(leg);
        form.route_legs.push(leg);
        syncTripTypeFromRoute();
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
        applyDefaultPickupDate(returnLeg);

        form.route_legs.push(returnLeg);
        syncTripTypeFromRoute();
    }

    function removeRouteLeg(index) {
        form.route_legs.splice(index, 1);
        rebuildLegChain();
        syncTripTypeFromRoute();
    }

    function onStartStationChange() {
        rebuildLegChain();
        syncTripTypeFromRoute();
    }

    function onStopStationChange(index) {
        rebuildLegChain();

        if (index < form.route_legs.length - 1) {
            form.route_legs[index + 1].from_station_id = form.route_legs[index].to_station_id;
        }

        syncTripTypeFromRoute();
    }

    function populateFromTrip(trip, customers = []) {
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
        form.status = trip.status ?? 'PENDING';
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
            const resolvedWeight = resolveLegWeightTon(leg);
            const freightAmount = Number(leg.freight_amount ?? 0);

            return {
                id: leg.id ?? null,
                from_station_id: leg.from_station_id ?? '',
                to_station_id: leg.to_station_id ?? '',
                customer_id: customerId ? String(customerId) : '',
                use_same_customer:
                    tripCustomerId !== ''
                    && customerId !== ''
                    && String(customerId) === String(tripCustomerId),
                from_city: leg.from_city ?? leg.from_location ?? '',
                to_city: leg.to_city ?? leg.to_location ?? '',
                sequence_no: leg.sequence_no ?? 1,
                freight_rate:
                    leg.freight_rate == null || leg.freight_rate === '' ? '' : Number(leg.freight_rate),
                freight_amount: Number(leg.freight_amount ?? 0),
                freight_auto_calculate: resolveLegFreightAutoCalculate(leg),
                weight_ton: resolvedWeight > 0 ? resolvedWeight : '',
                expected_mileage_km_per_liter:
                    leg.expected_mileage_km_per_liter == null || leg.expected_mileage_km_per_liter === ''
                        ? ''
                        : Number(leg.expected_mileage_km_per_liter),
                distance_km:
                    leg.distance_km == null || leg.distance_km === '' || Number(leg.distance_km) <= 0
                        ? ''
                        : Number(leg.distance_km),
                ewaybill_no: leg.ewaybill_no ?? '',
                ewaybill_expiry: leg.ewaybill_expiry ? String(leg.ewaybill_expiry).slice(0, 10) : '',
                goods_value:
                    leg.goods_value == null || leg.goods_value === ''
                        ? ''
                        : Number(leg.goods_value),
                goods_name: leg.goods_name ?? '',
                pickup_date: leg.pickup_date
                    ? String(leg.pickup_date).slice(0, 10)
                    : (dateOnlyFromDatetimeLocal(trip.start_at) || ''),
                consignor_id: resolveLookupId(customers, leg.consignor_id, leg.consignor),
                consignee_id: resolveLookupId(customers, leg.consignee_id, leg.consignee),
                no_of_units:
                    leg.no_of_units == null || leg.no_of_units === ''
                        ? ''
                        : Number(leg.no_of_units),
                remarks: leg.remarks ?? '',
            };
        });

        form.route_legs.forEach((leg) => {
            normalizeRouteLegForSave(leg);

            if (leg.freight_auto_calculate) {
                leg.freight_amount = calculateLegFreight(leg.weight_ton, leg.freight_rate);
            }
        });

        syncLegPickupDates();

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
            route_leg_sequence: resolveExpenseRouteLegSequence(expense, form.route_legs),
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

    function customerName(customers, customerId) {
        if (!customerId) {
            return null;
        }

        const match = customers.find((customer) => String(customer.id) === String(customerId));

        return match?.name?.trim() || null;
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

    function normalizeRouteLegForSave(leg) {
        let weight = resolveLegWeightTon(leg);
        const freight = parseNumeric(leg.freight_amount);
        const rate = parseNumeric(leg.freight_rate);

        if (weight <= 0 && freight > 0 && rate > 0) {
            weight = Math.round((freight / rate) * 1000) / 1000;
            leg.weight_ton = weight;
        }

        return {
            weight,
            freight,
        };
    }

    function applyLegacyPartyLookups() {
        // Trip customer and broker are loaded from persisted ids only.
    }

    function buildPayload(stations = [], { mode = 'create', includeExpenses = true, customers = [] } = {}) {
        rebuildLegChain();
        syncLegCustomersFromTrip();

        const payload = {
            substitute_driver_id: form.substitute_driver_id || null,
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
                const { weight, freight } = normalizeRouteLegForSave(leg);

                return {
                    from_station_id: leg.from_station_id,
                    to_station_id: leg.to_station_id,
                    customer_id: leg.use_same_customer
                        ? (form.customer_id || null)
                        : (leg.customer_id || null),
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
                    distance_km:
                        leg.distance_km === '' || leg.distance_km == null || Number(leg.distance_km) <= 0
                            ? null
                            : Number(leg.distance_km),
                    ewaybill_no: leg.ewaybill_no?.trim() || null,
                    ewaybill_expiry: leg.ewaybill_expiry || null,
                    goods_value:
                        leg.goods_value === '' || leg.goods_value == null
                            ? null
                            : Number(leg.goods_value),
                    goods_name: leg.goods_name?.trim() || null,
                    consignor: customerName(customers, leg.consignor_id) || null,
                    consignee: customerName(customers, leg.consignee_id) || null,
                    pickup_date: leg.pickup_date || null,
                    no_of_units:
                        leg.no_of_units === '' || leg.no_of_units == null
                            ? null
                            : Number(leg.no_of_units),
                    freight_amount: freight > 0 ? freight : 0,
                    remarks: leg.remarks || null,
                };
            }),
        };

        if (includeExpenses) {
            payload.expenses = form.expenses
                .filter((e) => Number(e.amount) > 0)
                .map((e) => ({
                    expense_type: e.expense_type,
                    expense_date: e.expense_date || form.start_at || null,
                    amount: e.amount,
                    fuel_liters: e.fuel_liters === '' || e.fuel_liters == null ? null : Number(e.fuel_liters),
                    fuel_rate: e.fuel_rate === '' || e.fuel_rate == null ? null : Number(e.fuel_rate),
                    fuel_odometer_km:
                        e.fuel_odometer_km === '' || e.fuel_odometer_km == null ? null : Number(e.fuel_odometer_km),
                    route_leg_sequence:
                        e.route_leg_sequence === '' || e.route_leg_sequence == null
                            ? null
                            : Number(e.route_leg_sequence),
                    other_type: e.expense_type === 'OTHER' ? e.other_type || null : null,
                    notes: e.notes || null,
                }));
        }

        const startMeterRaw = form.start_meter_reading_km;
        const startMeter =
            startMeterRaw === '' || startMeterRaw == null || Number(startMeterRaw) <= 0
                ? null
                : Number(startMeterRaw);

        if (mode === 'create') {
            payload.customer_id = form.customer_id || null;
            payload.broker_id = form.broker_id || null;
            payload.vehicle_id = form.vehicle_id;
            payload.driver_id = form.driver_id;
            payload.trip_type = form.trip_type;
            payload.start_meter_reading_km = startMeter;

            if (form.trip_sequence !== '' && form.trip_sequence != null) {
                payload.trip_sequence = Number(form.trip_sequence);
            }
        } else {
            payload.customer_id = form.customer_id || null;
            payload.broker_id = form.broker_id || null;
            payload.start_meter_reading_km = startMeter;
        }

        return payload;
    }

    function buildStatusChangePayload() {
        const status = String(form.status ?? '').toUpperCase();
        const payload = { status };

        if (
            (status === 'IN_PROGRESS' || status === 'COMPLETED' || status === 'CANCELLED')
            && form.start_at
        ) {
            payload.start_at = form.start_at;
        }

        if (status === 'COMPLETED') {
            payload.end_at = form.end_at || null;
            payload.actual_arrival_at = form.actual_arrival_at || null;
        }

        if (status === 'CANCELLED') {
            payload.cancelled_at = form.cancelled_at || null;
            payload.cancellation_reason = form.cancellation_reason || null;
        }

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
        ([startAt], [previousStartAt]) => {
            if (!startAt) {
                return;
            }

            syncLegPickupDates(dateOnlyFromDatetimeLocal(previousStartAt));

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
        syncTripTypeFromRoute,
        addExpense,
        removeExpense,
        buildPayload,
        buildStatusChangePayload,
        stationName,
        applyLegacyPartyLookups,
        syncLegCustomersFromTrip,
    };
}
