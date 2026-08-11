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
import { formatCustomerAddress } from '../utils/customerAddress';
import { dateOnlyFromDatetimeLocal, toDateInputFromIso } from '../utils/datetime';
import {
    allocateTripDropsToPickups,
    consolidateNestedDropsToTripDrops,
    dropAllocatedFreight,
    emptyAllocation,
    syncDropWeightFromAllocations,
} from '../utils/multiPointDrops';

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
        pickups: [],
        drops: [],
        expenses: [],
    });

    function isMultiPoint() {
        return String(form.trip_type ?? '').toUpperCase() === 'MULTI_POINT';
    }

    function emptyPickupForm(sequence = 1) {
        return {
            _key: `pickup-${Date.now()}-${sequence}`,
            pickup_code: `P${String(sequence).padStart(3, '0')}`,
            station_id: '',
            consignor_id: '',
            from_address: '',
            goods_name: '',
            weight_ton: '',
            pickup_date: tripStartDateOnly() || '',
        };
    }

    function emptyDropForm(sequence = 1) {
        return {
            _key: `drop-${Date.now()}-${sequence}`,
            drop_code: `D${String(sequence).padStart(3, '0')}`,
            to_station_id: '',
            consignee_id: '',
            to_address: '',
            weight_ton: '',
            freight_amount: 0,
            lr_number: '',
            remarks: '',
            allocations: [emptyAllocation()],
        };
    }

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
        form.pickups = [];
        form.drops = [];
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
        if (isMultiPoint()) {
            return;
        }

        form.trip_type = inferTripTypeFromRoute(form.start_station_id, form.route_legs);
    }

    function switchToMultiPoint() {
        form.trip_type = 'MULTI_POINT';
        form.route_legs = [];

        if (!Array.isArray(form.pickups) || form.pickups.length === 0) {
            form.pickups = [emptyPickupForm(1)];
        }

        if (!Array.isArray(form.drops) || form.drops.length === 0) {
            form.drops = [emptyDropForm(1)];
        }
    }

    function switchToSequentialRoute() {
        form.trip_type = 'ONE_WAY';
        form.pickups = [];
        form.drops = [];
    }

    function addStop() {
        if (isMultiPoint()) {
            return;
        }

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
        if (isMultiPoint()) {
            return;
        }

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

    function resetRouteLegLocation(leg, { keepDestination = false } = {}) {
        leg.from_city = '';
        leg.to_city = '';
        leg.distance_km = '';

        if (!keepDestination) {
            leg.to_station_id = '';
        }
    }

    function onStartStationChange() {
        form.route_legs.forEach((leg) => {
            resetRouteLegLocation(leg);
        });
        rebuildLegChain();
        syncTripTypeFromRoute();
    }

    function onStopStationChange(index) {
        const changedLeg = form.route_legs[index];

        if (changedLeg) {
            resetRouteLegLocation(changedLeg, { keepDestination: true });
        }

        form.route_legs.slice(index + 1).forEach((leg) => {
            resetRouteLegLocation(leg);
        });

        rebuildLegChain();
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
        const tripIsMultiPoint = String(trip.trip_type ?? '').toUpperCase() === 'MULTI_POINT';

        if (tripIsMultiPoint) {
            form.pickups = (trip.pickups ?? []).map((pickup, pickupIndex) => ({
                _key: `pickup-${pickup.id ?? pickupIndex}`,
                id: pickup.id ?? null,
                pickup_code: pickup.pickup_code || `P${String(pickupIndex + 1).padStart(3, '0')}`,
                station_id: pickup.station_id ? String(pickup.station_id) : '',
                consignor_id: resolveLookupId(customers, pickup.consignor_id, pickup.consignor),
                from_address: pickup.from_address ?? '',
                goods_name: pickup.goods_name ?? '',
                weight_ton:
                    pickup.weight_ton == null || pickup.weight_ton === ''
                        ? ''
                        : Number(pickup.weight_ton),
                pickup_date: pickup.pickup_date
                    ? String(pickup.pickup_date).slice(0, 10)
                    : (dateOnlyFromDatetimeLocal(trip.start_at) || ''),
            }));

            const nestedForConsolidate = (trip.pickups ?? []).map((pickup, pickupIndex) => ({
                ...form.pickups[pickupIndex],
                drops: (pickup.drops ?? pickup.route_legs ?? []).map((drop, dropIndex) => ({
                    _key: `drop-${drop.id ?? `${pickupIndex}-${dropIndex}`}`,
                    id: drop.id ?? null,
                    drop_code: drop.drop_code || '',
                    to_station_id: drop.to_station_id ? String(drop.to_station_id) : '',
                    consignee_id: resolveLookupId(customers, drop.consignee_id, drop.consignee),
                    to_address: drop.to_address ?? '',
                    weight_ton:
                        drop.weight_ton == null || drop.weight_ton === ''
                            ? ''
                            : Number(drop.weight_ton),
                    freight_rate:
                        drop.freight_rate == null || drop.freight_rate === ''
                            ? ''
                            : Number(drop.freight_rate),
                    freight_amount: Number(drop.freight_amount ?? 0),
                    freight_auto_calculate: resolveLegFreightAutoCalculate(drop),
                    distance_km:
                        drop.distance_km == null || drop.distance_km === '' || Number(drop.distance_km) <= 0
                            ? ''
                            : Number(drop.distance_km),
                    ewaybill_no: drop.ewaybill_no ?? '',
                    ewaybill_expiry: drop.ewaybill_expiry ? String(drop.ewaybill_expiry).slice(0, 10) : '',
                    no_of_units:
                        drop.no_of_units == null || drop.no_of_units === ''
                            ? ''
                            : Number(drop.no_of_units),
                    goods_value:
                        drop.goods_value == null || drop.goods_value === ''
                            ? ''
                            : Number(drop.goods_value),
                    lr_number: drop.lr_number ?? '',
                    remarks: drop.remarks ?? '',
                })),
            }));

            form.drops = consolidateNestedDropsToTripDrops(nestedForConsolidate);
            if (form.drops.length === 0) {
                form.drops = [emptyDropForm(1)];
            }

            form.route_legs = [];
        } else {
            form.pickups = [];
            form.drops = [];
            form.route_legs = (trip.route_legs ?? []).map((leg) => {
                const customerId = leg.customer_id ?? '';
                const resolvedWeight = resolveLegWeightTon(leg);

                return {
                    id: leg.id ?? null,
                    from_station_id: leg.from_station_id ?? '',
                    to_station_id: leg.to_station_id ?? '',
                    customer_id: customerId ? String(customerId) : '',
                    use_same_customer:
                        tripCustomerId !== ''
                        && customerId !== ''
                        && String(customerId) === String(tripCustomerId),
                    from_city: leg.from_city ?? '',
                    to_city: leg.to_city ?? '',
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

        if (!tripIsMultiPoint) {
            rebuildLegChain();
        }
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

    function customerAddress(customers, customerId) {
        if (!customerId) {
            return null;
        }

        const match = customers.find((customer) => String(customer.id) === String(customerId));

        return formatCustomerAddress(match);
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

    function buildMultiPointPickupsPayload(stations = [], customers = []) {
        const preparedDrops = (form.drops ?? []).map((drop, dropIndex) => {
            syncDropWeightFromAllocations(drop);

            return {
                ...drop,
                drop_code: drop.drop_code || `D${String(dropIndex + 1).padStart(3, '0')}`,
                freight_amount: dropAllocatedFreight(drop),
            };
        });

        const { pickups: allocatedPickups, error } = allocateTripDropsToPickups(form.pickups ?? [], preparedDrops);

        if (error) {
            const allocationError = new Error(error);
            allocationError.fieldErrors = { drops: [error] };
            throw allocationError;
        }

        return allocatedPickups.map((pickup, pickupIndex) => ({
            pickup_code: pickup.pickup_code || `P${String(pickupIndex + 1).padStart(3, '0')}`,
            sequence_no: pickupIndex + 1,
            station_id: pickup.station_id || null,
            consignor: customerName(customers, pickup.consignor_id),
            from_address: customerAddress(customers, pickup.consignor_id) || null,
            goods_name: pickup.goods_name?.trim() || null,
            weight_ton: parseNumeric(pickup.weight_ton),
            pickup_date: pickup.pickup_date || null,
            drops: (pickup.drops ?? []).map((drop, dropIndex) => ({
                drop_code: drop.drop_code || null,
                to_station_id: drop.to_station_id || null,
                to_city: stationName(stations, drop.to_station_id),
                consignee: customerName(customers, drop.consignee_id),
                to_address: customerAddress(customers, drop.consignee_id) || null,
                weight_ton: parseNumeric(drop.weight_ton) > 0 ? parseNumeric(drop.weight_ton) : null,
                freight_rate:
                    drop.freight_rate === '' || drop.freight_rate == null
                        ? null
                        : Number(drop.freight_rate),
                freight_amount: parseNumeric(drop.freight_amount) > 0 ? parseNumeric(drop.freight_amount) : 0,
                distance_km:
                    drop.distance_km === '' || drop.distance_km == null || Number(drop.distance_km) <= 0
                        ? null
                        : Number(drop.distance_km),
                ewaybill_no: drop.ewaybill_no?.trim?.() || drop.ewaybill_no || null,
                ewaybill_expiry: drop.ewaybill_expiry || null,
                no_of_units:
                    drop.no_of_units === '' || drop.no_of_units == null
                        ? null
                        : Number(drop.no_of_units),
                goods_value:
                    drop.goods_value === '' || drop.goods_value == null
                        ? null
                        : Number(drop.goods_value),
                lr_number: drop.lr_number?.trim() || null,
                remarks: drop.remarks?.trim() || null,
                sequence_no: dropIndex + 1,
            })),
        }));
    }

    function buildPayload(stations = [], { mode = 'create', includeExpenses = true, customers = [] } = {}) {
        const multiPoint = isMultiPoint()
            || (
                Array.isArray(form.pickups)
                && form.pickups.length > 0
                && Array.isArray(form.drops)
                && form.drops.length > 0
            );

        if (multiPoint) {
            form.trip_type = 'MULTI_POINT';
            const firstPickupStation = (form.pickups ?? []).find((pickup) => pickup?.station_id)?.station_id;
            if (firstPickupStation) {
                form.start_station_id = firstPickupStation;
            }
        }

        if (!multiPoint) {
            rebuildLegChain();
            syncLegCustomersFromTrip();
        }

        const payload = {
            substitute_driver_id: form.substitute_driver_id || null,
            start_station_id: multiPoint
                ? ((form.pickups ?? []).find((pickup) => pickup?.station_id)?.station_id || form.start_station_id || null)
                : form.start_station_id,
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
            route_legs: multiPoint
                ? null
                : form.route_legs.map((leg, index) => {
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
                        from_address: customerAddress(customers, leg.consignor_id) || null,
                        to_address: customerAddress(customers, leg.consignee_id) || null,
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

        if (multiPoint) {
            payload.pickups = buildMultiPointPickupsPayload(stations, customers);
            delete payload.route_legs;

            if (mode === 'create') {
                payload.trip_type = 'MULTI_POINT';
            }
        }

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
            payload.trip_type = multiPoint ? 'MULTI_POINT' : form.trip_type;
            payload.start_meter_reading_km = startMeter;

            if (form.trip_sequence !== '' && form.trip_sequence != null) {
                payload.trip_sequence = Number(form.trip_sequence);
            }
        } else {
            payload.customer_id = form.customer_id || null;
            payload.broker_id = form.broker_id || null;
            payload.start_meter_reading_km = startMeter;
            delete payload.trip_type;
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

    watch(
        () => form.start_meter_reading_km,
        (startMeterReadingKm) => {
            const startMeter = Number(startMeterReadingKm);

            if (!Number.isFinite(startMeter) || startMeter <= 0) {
                return;
            }

            const odometer = Math.trunc(startMeter);

            form.expenses.forEach((expense) => {
                if (String(expense.expense_type ?? '').toUpperCase() !== 'DIESEL') {
                    return;
                }

                if (expense.fuel_odometer_km === '' || expense.fuel_odometer_km == null) {
                    expense.fuel_odometer_km = odometer;
                }
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
        switchToMultiPoint,
        switchToSequentialRoute,
        emptyPickupForm,
        emptyDropForm,
        addExpense,
        removeExpense,
        buildPayload,
        buildStatusChangePayload,
        stationName,
        applyLegacyPartyLookups,
        syncLegCustomersFromTrip,
        isMultiPoint,
    };
}
