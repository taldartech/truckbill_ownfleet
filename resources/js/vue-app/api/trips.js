import api from '../api';
import { LoadCategory, loadCategoryMeta } from '../constants/loadCategory';
import { calculatePerformanceSummary } from '../utils/tripFuelCalculator';
import { thisMonthDateRange } from '../utils/dateRangePresets';
import { dateOnlyFromDatetimeLocal } from '../utils/datetime';
import { userHasPermission } from '../utils/permissionAlias';

const tripsBase = '/app/api/trips';

function unwrapResource(payload) {
    return payload?.data ?? payload;
}

export async function listTrips({ page = 1, perPage = 15, ...filters } = {}) {
    const { data } = await api.get(tripsBase, {
        params: { page, per_page: perPage, ...filters },
    });

    return {
        data: data.data ?? [],
        meta: data.meta ?? {},
    };
}

export async function fetchNextTripNumber({ startAt = null, sequence = null } = {}) {
    const { data } = await api.get(`${tripsBase}/next-number`, {
        params: {
            ...(startAt ? { start_at: startAt } : {}),
            ...(sequence !== null && sequence !== '' ? { sequence } : {}),
        },
    });

    return {
        trip_number: data.trip_number ?? '',
        sequence: data.sequence ?? null,
    };
}

export async function fetchVehiclePrimaryDriver(vehicleId, { asOfDate = null } = {}) {
    const { data } = await api.get(`/app/api/vehicles/${vehicleId}/primary-driver`, {
        params: asOfDate ? { as_of_date: asOfDate } : {},
    });

    return data;
}

export async function fetchTrip(id) {
    const { data } = await api.get(`${tripsBase}/${id}`);

    return unwrapResource(data);
}

export function biltyPdfUrl(tripId, { showFreight = false } = {}) {
    const params = new URLSearchParams();

    if (showFreight) {
        params.set('show_freight', '1');
    }

    const query = params.toString();

    return `${tripsBase}/${tripId}/bilty.pdf${query ? `?${query}` : ''}`;
}

export async function createTrip(payload) {
    const { data } = await api.post(tripsBase, payload);

    return unwrapResource(data);
}

export async function updateTrip(id, payload) {
    const { data } = await api.put(`${tripsBase}/${id}`, payload);

    return unwrapResource(data);
}

export async function archiveTrip(id, { archive_reason } = {}) {
    await api.post(`${tripsBase}/${id}/archive`, {
        archive_reason,
    });
}

export async function restoreTrip(id) {
    await api.post(`${tripsBase}/${id}/restore`);
}

/** @deprecated Use archiveTrip */
export async function deleteTrip(id, { deletion_reason, archive_reason } = {}) {
    await archiveTrip(id, {
        archive_reason: archive_reason ?? deletion_reason,
    });
}

export async function fetchTripArchiveImpact(id) {
    const { data } = await api.get(`${tripsBase}/${id}/archive-impact`);

    return data;
}

/** @deprecated Use fetchTripArchiveImpact */
export async function fetchTripDeletionImpact(id) {
    return fetchTripArchiveImpact(id);
}

export function isTripArchivable(status) {
    const normalized = String(status ?? '').toUpperCase();

    return ['PENDING', 'IN_PROGRESS', 'COMPLETED', 'CANCELLED', 'INVOICED'].includes(normalized);
}

export function isTripArchived(trip) {
    return Boolean(trip?.is_archived);
}

/** @deprecated Use isTripArchivable */
export function isTripDeletable(status) {
    return isTripArchivable(status);
}

export async function checkTripWindowOverlap({
    vehicleId,
    driverId = null,
    startAt,
    endAt = null,
    ignoreTripId = null,
} = {}) {
    const { data } = await api.get(`${tripsBase}/check-window`, {
        params: {
            vehicle_id: vehicleId,
            start_at: startAt,
            ...(driverId ? { driver_id: driverId } : {}),
            ...(endAt ? { end_at: endAt } : {}),
            ...(ignoreTripId ? { ignore_trip_id: ignoreTripId } : {}),
        },
    });

    return data;
}

export function tripWindowConflictErrors(result) {
    if (result?.available !== false) {
        return {};
    }

    const messages = result.messages ?? {};

    return Object.fromEntries(
        Object.entries(messages).filter(([, value]) => Array.isArray(value) && value.length > 0),
    );
}

export async function validateTripWindowOverlap(params) {
    const result = await checkTripWindowOverlap(params);

    return tripWindowConflictErrors(result);
}

export const TRIP_TYPES = [
    { value: 'ROUND_TRIP', label: 'Round Trip' },
    { value: 'ONE_WAY', label: 'One Way Trip' },
    { value: 'MULTI_POINT', label: 'Multi-Point Trip' },
];

export function tripTypeLabel(type) {
    return TRIP_TYPES.find((t) => t.value === String(type ?? '').toUpperCase())?.label ?? 'Trip';
}

export function isMultiPointTripType(type) {
    return String(type ?? '').toUpperCase() === 'MULTI_POINT';
}

/**
 * Infer trip type from route shape: last stop back at start => round trip, else one way.
 */
export function inferTripTypeFromRoute(startStationId, routeLegs = []) {
    if (!startStationId || !Array.isArray(routeLegs) || routeLegs.length === 0) {
        return 'ONE_WAY';
    }

    const lastLeg = routeLegs[routeLegs.length - 1];
    const lastTo = lastLeg?.to_station_id;

    if (lastTo && String(lastTo) === String(startStationId)) {
        return 'ROUND_TRIP';
    }

    return 'ONE_WAY';
}

export function tripTypeMeta(type) {
    const normalized = String(type ?? 'ROUND_TRIP').toUpperCase();

    if (normalized === 'ONE_WAY') {
        return {
            label: 'One Way',
            icon: 'bi-arrow-right',
            badge: 'trip-type-badge--one-way',
        };
    }

    if (normalized === 'MULTI_POINT') {
        return {
            label: 'Multi-Point',
            icon: 'bi-diagram-3',
            badge: 'trip-type-badge--multi-point',
        };
    }

    return {
        label: 'Round Trip',
        icon: 'bi-arrow-repeat',
        badge: 'trip-type-badge--round',
    };
}

export const ROUTE_TYPES = [
    { value: 'LOADED', label: 'Full Load', badge: 'bg-success' },
    { value: 'HALF_LOADED', label: 'Half Load', badge: 'bg-warning text-dark' },
    { value: 'EMPTY', label: 'Empty', badge: 'bg-secondary' },
];

export const TRIP_STATUSES = [
    { value: 'PENDING', label: 'Pending' },
    { value: 'IN_PROGRESS', label: 'In Progress' },
    { value: 'COMPLETED', label: 'Completed' },
    { value: 'CANCELLED', label: 'Cancelled' },
    { value: 'INVOICED', label: 'Invoiced' },
];

export function emptyRouteStop(
    sequence = 1,
    fromStationId = '',
    toStationId = '',
    customerId = '',
    expectedMileage = '',
) {
    return {
        from_station_id: fromStationId,
        to_station_id: toStationId,
        customer_id: customerId,
        use_same_customer: false,
        sequence_no: sequence,
        freight_rate: '',
        freight_amount: 0,
        freight_auto_calculate: true,
        weight_ton: '',
        expected_mileage_km_per_liter: expectedMileage,
        distance_km: '',
        ewaybill_no: '',
        ewaybill_expiry: '',
        goods_value: '',
        goods_name: '',
        pickup_date: '',
        no_of_units: '',
        consignor_id: '',
        consignee_id: '',
        remarks: '',
    };
}

export function classifyLegLoad(weightTon, maxLoadTon) {
    const weight = Number(weightTon ?? 0);
    const maxLoad = Number(maxLoadTon ?? 0);

    if (weight === 0) {
        return { category: LoadCategory.EMPTY, percentage: 0 };
    }

    if (maxLoad <= 0) {
        return { category: LoadCategory.FULL, percentage: 100 };
    }

    const percentage = (weight / maxLoad) * 100;

    if (weight < maxLoad) {
        return { category: LoadCategory.HALF, percentage };
    }

    return { category: LoadCategory.FULL, percentage };
}

export function legLoadCategoryMeta(weightTon, maxLoadTon = 0) {
    const classification = classifyLegLoad(weightTon, maxLoadTon);
    const meta = loadCategoryMeta(classification.category);

    return {
        ...classification,
        ...meta,
    };
}

export function routeTypeMeta(type) {
    return ROUTE_TYPES.find((t) => t.value === type) ?? ROUTE_TYPES[0];
}

function parseNumeric(value) {
    const parsed = Number(String(value ?? '').trim());

    return Number.isFinite(parsed) ? parsed : 0;
}

export { parseNumeric };

export const MAX_FUEL_LITERS = 5000;

export const MAX_FUEL_RATE = 1000;

export const MAX_PLANNED_DIESEL_LITERS = 10000;

export const MAX_DIESEL_RATE_PER_LITER = MAX_FUEL_RATE;

export const FUEL_DECIMAL_PLACES = 2;

/** Max expected / planned mileage (KM per liter) on trip details and route legs. */
export const MAX_EXPECTED_MILEAGE_KM_PER_LITER = 50;

/** Max freight rate (₹ per ton) on route legs. */
export const MAX_FREIGHT_RATE_PER_TON = 100000;

export function validateMultiPointPickupsPayload(
    payload,
    { mode = 'create', startDate = null, endDate = null } = {},
) {
    const errors = {};
    const pickups = payload.pickups ?? [];

    if (!payload.customer_id) {
        errors.customer_id = ['Select the customer for this multi-point trip.'];
    }

    if (!Array.isArray(pickups) || pickups.length === 0) {
        errors.pickups = ['Add at least one pickup for this multi-point trip.'];

        return errors;
    }

    let hasFreight = false;

    pickups.forEach((pickup, pickupIndex) => {
        const pickupNo = pickupIndex + 1;
        const pickupWeight = parseNumeric(pickup?.weight_ton);
        const drops = pickup?.drops ?? [];

        if (!pickup?.station_id) {
            errors[`pickups.${pickupIndex}.station_id`] = [`Pickup ${pickupNo}: station is required.`];
        }

        if (pickupWeight <= 0) {
            errors[`pickups.${pickupIndex}.weight_ton`] = [`Pickup ${pickupNo}: weight must be greater than zero.`];
        }

        const pickupDate = String(pickup?.pickup_date ?? '').trim();

        if (pickupDate) {
            if (!/^\d{4}-\d{2}-\d{2}$/.test(pickupDate)) {
                errors[`pickups.${pickupIndex}.pickup_date`] = [`Pickup ${pickupNo}: enter a valid pickup date.`];
            } else if (startDate && pickupDate < startDate) {
                errors[`pickups.${pickupIndex}.pickup_date`] = [
                    `Pickup ${pickupNo}: pickup date cannot be before trip start date.`,
                ];
            } else if (endDate && pickupDate > endDate) {
                errors[`pickups.${pickupIndex}.pickup_date`] = [
                    `Pickup ${pickupNo}: pickup date cannot be after trip end date.`,
                ];
            }
        }

        if (!Array.isArray(drops) || drops.length === 0) {
            errors[`pickups.${pickupIndex}.drops`] = [
                `Pickup ${pickupNo}: no drop weight was allocated. Match total drop weight to total pickup weight.`,
            ];

            return;
        }

        let dropWeightTotal = 0;

        drops.forEach((drop, dropIndex) => {
            const dropNo = dropIndex + 1;
            const dropWeight = parseNumeric(drop?.weight_ton);
            const freightAmount = parseNumeric(drop?.freight_amount);

            if (!drop?.to_station_id) {
                errors[`pickups.${pickupIndex}.drops.${dropIndex}.to_station_id`] = [
                    `Pickup ${pickupNo} drop ${dropNo}: drop station is required.`,
                ];
            }

            if (dropWeight <= 0) {
                errors[`pickups.${pickupIndex}.drops.${dropIndex}.weight_ton`] = [
                    `Pickup ${pickupNo} drop ${dropNo}: weight must be greater than zero.`,
                ];
            }

            dropWeightTotal += dropWeight;

            if (freightAmount > 0) {
                hasFreight = true;
            }
        });

        if (dropWeightTotal - pickupWeight > 0.0005) {
            errors[`pickups.${pickupIndex}.weight_ton`] = [
                `Pickup ${pickupNo}: drop weight (${dropWeightTotal.toFixed(3)}) exceeds pickup weight (${pickupWeight.toFixed(3)}).`,
            ];
        }
    });

    if (mode === 'create' && !hasFreight) {
        errors.pickups = ['Total freight must be greater than zero before creating a trip.'];
    }

    return errors;
}

/**
 * Validate multi-point form before allocating trip-level drops onto pickups.
 */
export function validateMultiPointTripForm(
    form,
    { mode = 'create', startDate = null, endDate = null } = {},
) {
    const errors = {};
    const pickups = form?.pickups ?? [];
    const drops = form?.drops ?? [];

    if (!form?.customer_id) {
        errors.customer_id = ['Select the customer for this multi-point trip.'];
    }

    if (!Array.isArray(pickups) || pickups.length === 0) {
        errors.pickups = ['Add at least one pickup for this multi-point trip.'];
    }

    if (!Array.isArray(drops) || drops.length === 0) {
        errors.drops = ['Add at least one drop for this multi-point trip.'];
    }

    let hasFreight = false;
    const pickupKeys = new Set((pickups ?? []).map((pickup) => String(pickup?._key ?? '')).filter(Boolean));

    pickups.forEach((pickup, pickupIndex) => {
        const pickupNo = pickupIndex + 1;
        const pickupWeight = parseNumeric(pickup?.weight_ton);

        if (!pickup?.station_id) {
            errors[`pickups.${pickupIndex}.station_id`] = [`Pickup ${pickupNo}: station is required.`];
        }

        if (pickupWeight <= 0) {
            errors[`pickups.${pickupIndex}.weight_ton`] = [`Pickup ${pickupNo}: weight must be greater than zero.`];
        }

        const pickupDate = String(pickup?.pickup_date ?? '').trim();

        if (pickupDate) {
            if (!/^\d{4}-\d{2}-\d{2}$/.test(pickupDate)) {
                errors[`pickups.${pickupIndex}.pickup_date`] = [`Pickup ${pickupNo}: enter a valid pickup date.`];
            } else if (startDate && pickupDate < startDate) {
                errors[`pickups.${pickupIndex}.pickup_date`] = [
                    `Pickup ${pickupNo}: pickup date cannot be before trip start date.`,
                ];
            } else if (endDate && pickupDate > endDate) {
                errors[`pickups.${pickupIndex}.pickup_date`] = [
                    `Pickup ${pickupNo}: pickup date cannot be after trip end date.`,
                ];
            }
        }
    });

    drops.forEach((drop, dropIndex) => {
        const dropNo = dropIndex + 1;
        const allocations = drop?.allocations ?? [];
        const dropWeight = allocations.reduce(
            (sum, allocation) => sum + parseNumeric(allocation?.weight_ton),
            0,
        );

        if (!drop?.to_station_id) {
            errors[`drops.${dropIndex}.to_station_id`] = [`Drop ${dropNo}: station is required.`];
        }

        if (!Array.isArray(allocations) || allocations.length === 0) {
            errors[`drops.${dropIndex}.allocations`] = [
                `Drop ${dropNo}: select at least one pickup source.`,
            ];
        }

        const usedPickupKeys = new Set();

        allocations.forEach((allocation, allocationIndex) => {
            const pickupKey = String(allocation?.pickup_key ?? '');
            const allocWeight = parseNumeric(allocation?.weight_ton);
            const allocNo = allocationIndex + 1;

            let allocFreight = parseNumeric(allocation?.freight_amount);

            if (allocation?.freight_auto_calculate) {
                allocFreight = calculateLegFreight(allocWeight, allocation.freight_rate);
            }

            if (allocFreight > 0) {
                hasFreight = true;
            }

            if (!pickupKey) {
                errors[`drops.${dropIndex}.allocations.${allocationIndex}.pickup_key`] = [
                    `Drop ${dropNo} source ${allocNo}: select a pickup.`,
                ];
            } else if (!pickupKeys.has(pickupKey)) {
                errors[`drops.${dropIndex}.allocations.${allocationIndex}.pickup_key`] = [
                    `Drop ${dropNo} source ${allocNo}: pickup is invalid.`,
                ];
            } else if (usedPickupKeys.has(pickupKey)) {
                errors[`drops.${dropIndex}.allocations.${allocationIndex}.pickup_key`] = [
                    `Drop ${dropNo}: the same pickup is selected twice. Merge weights into one source row.`,
                ];
            } else {
                usedPickupKeys.add(pickupKey);
            }

            if (allocWeight <= 0) {
                errors[`drops.${dropIndex}.allocations.${allocationIndex}.weight_ton`] = [
                    `Drop ${dropNo} source ${allocNo}: weight must be greater than zero.`,
                ];
            }
        });

        if (dropWeight <= 0) {
            errors[`drops.${dropIndex}.weight_ton`] = [
                `Drop ${dropNo}: total allocated weight must be greater than zero.`,
            ];
        }
    });

    const pickupAllocated = new Map();

    pickups.forEach((pickup) => {
        const key = String(pickup?._key ?? '');
        if (key) {
            pickupAllocated.set(key, {
                code: pickup.pickup_code || '',
                weight: parseNumeric(pickup?.weight_ton),
                allocated: 0,
            });
        }
    });

    let dropTotal = 0;

    drops.forEach((drop) => {
        (drop?.allocations ?? []).forEach((allocation) => {
            const key = String(allocation?.pickup_key ?? '');
            const weight = parseNumeric(allocation?.weight_ton);
            dropTotal += weight;
            const row = pickupAllocated.get(key);
            if (row) {
                row.allocated += weight;
            }
        });
    });

    dropTotal = Math.round(dropTotal * 1000) / 1000;
    let pickupTotal = 0;

    for (const row of pickupAllocated.values()) {
        pickupTotal += row.weight;
        const allocated = Math.round(row.allocated * 1000) / 1000;
        const weight = Math.round(row.weight * 1000) / 1000;

        if (allocated - weight > 0.0005) {
            errors.drops = [
                `${row.code || 'Pickup'}: allocated ${allocated.toFixed(3)} ton exceeds pickup ${weight.toFixed(3)} ton.`,
            ];
            break;
        }

        if (weight - allocated > 0.0005 && !errors.drops) {
            errors.drops = [
                `${row.code || 'Pickup'}: allocate remaining ${(weight - allocated).toFixed(3)} ton to drops.`,
            ];
        }
    }

    pickupTotal = Math.round(pickupTotal * 1000) / 1000;

    if (!errors.drops && dropTotal - pickupTotal > 0.0005) {
        errors.drops = [
            `Total drop weight (${dropTotal.toFixed(3)}) exceeds total pickup weight (${pickupTotal.toFixed(3)}).`,
        ];
    }

    if (mode === 'create' && !hasFreight && !errors.drops) {
        errors.drops = ['Total freight must be greater than zero before creating a trip.'];
    }

    return errors;
}

export function validateTripPayload(
    payload,
    { mode = 'create', previousStatus = null, user = null, tripType = null, hasInvoices = false } = {},
) {
    const errors = {};
    const editOptions = { has_invoices: hasInvoices };

    if (mode === 'create') {
        if (!payload.trip_type) {
            errors.trip_type = ['Trip type is required.'];
        }
    }

    if (mode === 'create') {
        if (!payload.vehicle_id) {
            errors.vehicle_id = ['Please select a truck.'];
        } else if (!payload.driver_id) {
            errors.driver_id = ['Primary driver could not be resolved for the selected truck.'];
        }

        if (
            payload.substitute_driver_id
            && payload.driver_id
            && String(payload.substitute_driver_id) === String(payload.driver_id)
        ) {
            errors.substitute_driver_id = ['Substitute driver must be different from the primary driver.'];
        }
    }

    if (mode === 'edit' && previousStatus && !canFullEditTrip(previousStatus, user, editOptions)) {
        const access = resolveTripEditAccess(previousStatus, user, editOptions);

        if (access.invoiceLocked) {
            errors.trip = ['This trip has invoices and cannot be edited.'];
        } else if (isTerminalTripStatus(previousStatus)) {
            errors.trip = ['Completed or cancelled trips cannot be edited.'];
        }
    }

    const resolvedTripTypeForStart = String(tripType ?? payload.trip_type ?? 'ROUND_TRIP').toUpperCase();

    if (!payload.start_station_id && resolvedTripTypeForStart !== 'MULTI_POINT') {
        errors.start_station_id = ['Select the start station for this trip.'];
    }

    if (!payload.start_station_id && resolvedTripTypeForStart === 'MULTI_POINT') {
        errors.pickups = ['Add a pickup station — it is used as the trip start.'];
    }

    if (!payload.start_at) {
        errors.start_at = ['Trip start date is required.'];
    }

    const startDate = dateOnlyFromDatetimeLocal(payload.start_at);
    const endDate = dateOnlyFromDatetimeLocal(payload.end_at);
    const arrivalDate = dateOnlyFromDatetimeLocal(payload.actual_arrival_at);

    if (startDate && endDate && endDate < startDate) {
        errors.end_at = ['End date cannot be before start date.'];
    }

    if (mode === 'edit' && startDate && arrivalDate && arrivalDate < startDate) {
        errors.actual_arrival_at = ['Actual arrival cannot be before start date.'];
    }

    if (mode === 'edit' && endDate && arrivalDate && endDate < arrivalDate) {
        errors.end_at = ['End date cannot be before the actual arrival date.'];
    }

    const activeStatusForMeters = String(payload.status ?? previousStatus ?? '').toUpperCase();
    const startMeterOptional =
        mode === 'create' || (mode === 'edit' && (activeStatusForMeters === '' || activeStatusForMeters === 'PENDING'));

    if (startMeterOptional) {
        const startMeterRaw = payload.start_meter_reading_km;

        if (startMeterRaw !== '' && startMeterRaw != null) {
            const startMeterValue = parseNumeric(startMeterRaw);

            if (startMeterValue < 0) {
                errors.start_meter_reading_km = ['Start meter reading cannot be negative.'];
            }
        }
    } else if (mode === 'edit' && parseNumeric(payload.start_meter_reading_km) <= 0) {
        errors.start_meter_reading_km = ['Start meter reading is required and must be greater than zero.'];
    }

    if (mode === 'create' || mode === 'edit') {
        const startMeter = parseNumeric(payload.start_meter_reading_km);
        const endMeterRaw = payload.end_meter_reading_km;
        const endMeter =
            endMeterRaw === '' || endMeterRaw == null ? null : parseNumeric(endMeterRaw);

        const activeStatus = String(payload.status ?? previousStatus ?? '').toUpperCase();

        if (
            endMeter !== null
            && endMeter > 0
            && ['COMPLETED', 'CANCELLED'].includes(activeStatus)
        ) {
            if (startMeter > 0 && endMeter < startMeter) {
                errors.end_meter_reading_km = ['End Meter Reading cannot be less than Start Meter Reading.'];
            }
        }

        const plannedMileageRaw = payload.planned_mileage_km_per_liter;

        if (plannedMileageRaw !== '' && plannedMileageRaw != null) {
            const plannedMileage = parseNumeric(plannedMileageRaw);

            if (plannedMileage < 0) {
                errors.planned_mileage_km_per_liter = ['Expected mileage cannot be negative.'];
            } else if (plannedMileage > MAX_EXPECTED_MILEAGE_KM_PER_LITER) {
                errors.planned_mileage_km_per_liter = [
                    `Max ${MAX_EXPECTED_MILEAGE_KM_PER_LITER} KM/LTR.`,
                ];
            }
        }

        const plannedDieselRaw = payload.planned_diesel_liters;

        if (plannedDieselRaw !== '' && plannedDieselRaw != null) {
            const plannedDiesel = parseNumeric(plannedDieselRaw);

            if (plannedDiesel < 0) {
                errors.planned_diesel_liters = ['Expected diesel cannot be negative.'];
            } else if (plannedDiesel > MAX_PLANNED_DIESEL_LITERS) {
                errors.planned_diesel_liters = [
                    `Max ${MAX_PLANNED_DIESEL_LITERS.toLocaleString('en-IN')} LTR.`,
                ];
            }
        }

        const dieselRateRaw = payload.diesel_rate_per_liter;

        if (dieselRateRaw !== '' && dieselRateRaw != null) {
            const dieselRate = parseNumeric(dieselRateRaw);

            if (dieselRate < 0) {
                errors.diesel_rate_per_liter = ['Diesel rate cannot be negative.'];
            } else if (dieselRate > MAX_DIESEL_RATE_PER_LITER) {
                errors.diesel_rate_per_liter = [
                    `Max ₹${MAX_DIESEL_RATE_PER_LITER.toLocaleString('en-IN')}/LTR.`,
                ];
            }
        }
    }

    if (payload.status === 'COMPLETED' && mode === 'edit') {
        if (!payload.end_at) {
            errors.end_at = ['End date is required to complete the trip.'];
        }

        if (!payload.actual_arrival_at) {
            errors.actual_arrival_at = ['Actual arrival date is required to complete the trip.'];
        }

        if (parseNumeric(payload.end_meter_reading_km) <= 0) {
            errors.end_meter_reading_km = ['End meter reading is required to complete the trip.'];
        }

        const startMeter = parseNumeric(payload.start_meter_reading_km);
        const endMeter = parseNumeric(payload.end_meter_reading_km);

        if (endMeter > 0 && startMeter > 0 && endMeter < startMeter) {
            errors.end_meter_reading_km = ['End Meter Reading cannot be less than Start Meter Reading.'];
        }

        if (startDate && arrivalDate && arrivalDate < startDate) {
            errors.actual_arrival_at = ['Actual arrival cannot be before start date.'];
        }

        if (endDate && arrivalDate && endDate < arrivalDate) {
            errors.end_at = ['End date cannot be before the actual arrival date.'];
        }
    }

    if (payload.status === 'CANCELLED' && mode === 'edit') {
        if (!payload.cancelled_at) {
            errors.cancelled_at = ['Cancellation date is required.'];
        }

        if (!payload.cancellation_reason?.trim()) {
            errors.cancellation_reason = ['Please provide a reason for cancelling this trip.'];
        }

        if (parseNumeric(payload.end_meter_reading_km) <= 0) {
            errors.end_meter_reading_km = ['End meter reading is required to cancel the trip.'];
        }

        const startMeter = parseNumeric(payload.start_meter_reading_km);
        const endMeter = parseNumeric(payload.end_meter_reading_km);

        if (endMeter > 0 && startMeter > 0 && endMeter < startMeter) {
            errors.end_meter_reading_km = ['End Meter Reading cannot be less than Start Meter Reading.'];
        }
    }

    const resolvedTripTypeEarly = String(tripType ?? payload.trip_type ?? 'ROUND_TRIP').toUpperCase();

    if (resolvedTripTypeEarly === 'MULTI_POINT') {
        Object.assign(errors, validateMultiPointPickupsPayload(payload, { mode, startDate, endDate }));

        Object.assign(
            errors,
            validateExpenseDates(payload.expenses ?? [], {
                startAt: payload.start_at || null,
                endAt: payload.end_at || null,
            }),
            validateDieselExpenses(payload.expenses ?? [], {
                startMeterKm: payload.start_meter_reading_km ?? null,
                endMeterKm: payload.end_meter_reading_km ?? null,
            }),
        );

        if (mode === 'create') {
            return pickFirstTripValidationError(errors);
        }

        return errors;
    }

    const legs = payload.route_legs ?? [];

    if (legs.length === 0) {
        errors.route_legs = ['Add at least one stop on the route.'];
    }

    let hasLoadedLeg = false;

    legs.forEach((leg, index) => {
        if (!leg.from_station_id) {
            errors[`route_legs.${index}.from_station_id`] = [`Stop ${index + 1}: from station is required.`];
        }

        if (!leg.to_station_id) {
            errors[`route_legs.${index}.to_station_id`] = [`Stop ${index + 1}: to station is required.`];
        }

        if (
            leg.from_station_id &&
            leg.to_station_id &&
            String(leg.from_station_id) === String(leg.to_station_id)
        ) {
            errors[`route_legs.${index}.to_station_id`] = [`Stop ${index + 1}: from and to station cannot be same.`];
        }

        const weight = parseNumeric(leg.weight_ton);

        if (weight > 0 && !leg.customer_id) {
            errors[`route_legs.${index}.customer_id`] = [
                `Stop ${index + 1}: customer is required for loaded stops.`,
            ];
        }

        if (weight > 0 && !String(leg.goods_name ?? '').trim()) {
            errors[`route_legs.${index}.goods_name`] = [
                `Stop ${index + 1}: goods type is required for loaded stops.`,
            ];
        }

        const pickupDate = String(leg.pickup_date ?? '').trim();

        if (weight > 0 && !pickupDate) {
            errors[`route_legs.${index}.pickup_date`] = [
                `Stop ${index + 1}: pickup date is required for loaded stops.`,
            ];
        } else if (pickupDate) {
            if (!/^\d{4}-\d{2}-\d{2}$/.test(pickupDate)) {
                errors[`route_legs.${index}.pickup_date`] = [
                    `Stop ${index + 1}: enter a valid pickup date.`,
                ];
            } else if (startDate && pickupDate < startDate) {
                errors[`route_legs.${index}.pickup_date`] = [
                    `Stop ${index + 1}: pickup date cannot be before trip start date.`,
                ];
            } else if (endDate && pickupDate > endDate) {
                errors[`route_legs.${index}.pickup_date`] = [
                    `Stop ${index + 1}: pickup date cannot be after trip end date.`,
                ];
            } else {
                let previousPickupDate = null;
                let previousPickupStop = null;

                for (let previousIndex = index - 1; previousIndex >= 0; previousIndex -= 1) {
                    const previousDate = String(legs[previousIndex]?.pickup_date ?? '').trim();

                    if (previousDate && /^\d{4}-\d{2}-\d{2}$/.test(previousDate)) {
                        previousPickupDate = previousDate;
                        previousPickupStop = previousIndex + 1;
                        break;
                    }
                }

                if (previousPickupDate && pickupDate < previousPickupDate) {
                    errors[`route_legs.${index}.pickup_date`] = [
                        `Stop ${index + 1}: pickup date cannot be before stop ${previousPickupStop} pickup date.`,
                    ];
                }
            }
        }

        const unitsRaw = leg.no_of_units;

        if (unitsRaw !== '' && unitsRaw != null) {
            const units = Number(unitsRaw);

            if (!Number.isInteger(units) || units < 0) {
                errors[`route_legs.${index}.no_of_units`] = [
                    `Stop ${index + 1}: no. of units must be a whole number (0 or more).`,
                ];
            } else if (units > 100000) {
                errors[`route_legs.${index}.no_of_units`] = [
                    `Stop ${index + 1}: max 100,000 units allowed.`,
                ];
            }
        }

        const expectedMileageRaw = leg.expected_mileage_km_per_liter;

        if (expectedMileageRaw !== '' && expectedMileageRaw != null) {
            const expectedMileage = parseNumeric(expectedMileageRaw);

            if (expectedMileage < 0) {
                errors[`route_legs.${index}.expected_mileage_km_per_liter`] = [
                    `Stop ${index + 1}: mileage cannot be negative.`,
                ];
            } else if (expectedMileage > MAX_EXPECTED_MILEAGE_KM_PER_LITER) {
                errors[`route_legs.${index}.expected_mileage_km_per_liter`] = [
                    `Max ${MAX_EXPECTED_MILEAGE_KM_PER_LITER} KM/LTR.`,
                ];
            }
        }

        const freightRateRaw = leg.freight_rate;

        if (freightRateRaw !== '' && freightRateRaw != null) {
            const freightRate = parseNumeric(freightRateRaw);

            if (freightRate < 0) {
                errors[`route_legs.${index}.freight_rate`] = [
                    `Stop ${index + 1}: rate cannot be negative.`,
                ];
            } else if (freightRate > MAX_FREIGHT_RATE_PER_TON) {
                errors[`route_legs.${index}.freight_rate`] = [
                    `Max ₹${MAX_FREIGHT_RATE_PER_TON.toLocaleString('en-IN')}/Ton.`,
                ];
            }
        }

        const distanceRaw = leg.distance_km;

        if (distanceRaw !== '' && distanceRaw != null && parseNumeric(distanceRaw) < 0) {
            errors[`route_legs.${index}.distance_km`] = [
                `Stop ${index + 1}: distance cannot be negative.`,
            ];
        }

        if (legCountsAsLoaded(leg)) {
            hasLoadedLeg = true;
        }
    });

    const lastLeg = legs[legs.length - 1];

    const resolvedTripType = String(tripType ?? payload.trip_type ?? 'ROUND_TRIP').toUpperCase();

    if (
        resolvedTripType === 'ROUND_TRIP'
        && lastLeg
        && payload.start_station_id
        && String(lastLeg.to_station_id) !== String(payload.start_station_id)
    ) {
        errors.route_legs = ['Round trip must end at the start station. Use "Return to Start" button.'];
        errors[`route_legs.${legs.length - 1}.to_station_id`] = [
            'Round trip must end at the start station.',
        ];
    }

    if (legs.length > 0 && !hasLoadedLeg) {
        errors.route_legs = ['At least one stop with load weight or freight amount is required.'];
        errors['route_legs.0.weight_ton'] = [
            'Enter load weight or freight on at least one stop.',
        ];
        errors['route_legs.0.freight_amount'] = [
            'Enter load weight or freight on at least one stop.',
        ];
    }

    if (mode === 'create' && legs.length > 0) {
        const freightTotal = legs.reduce((sum, leg) => sum + parseNumeric(leg.freight_amount), 0);

        if (freightTotal <= 0) {
            errors.route_legs = ['Total freight must be greater than zero before creating a trip.'];
            errors['route_legs.0.freight_amount'] = [
                'Enter freight amount (or weight × rate) before saving.',
            ];
        }
    }

    Object.assign(
        errors,
        validateExpenseDates(payload.expenses ?? [], {
            startAt: payload.start_at || null,
            endAt: payload.end_at || null,
        }),
        validateDieselExpenses(payload.expenses ?? [], {
            startMeterKm: payload.start_meter_reading_km ?? null,
            endMeterKm: payload.end_meter_reading_km ?? null,
        }),
    );

    if (mode === 'create') {
        return pickFirstTripValidationError(errors);
    }

    return errors;
}

/**
 * Return only the highest-priority validation error so create shows one message at a time.
 *
 * @param {Record<string, string[]|string>} errors
 * @returns {Record<string, string[]|string>}
 */
export function pickFirstTripValidationError(errors = {}) {
    const priorityKeys = [
        'vehicle_id',
        'driver_id',
        'substitute_driver_id',
        'trip_type',
        'start_station_id',
        'start_at',
        'end_at',
        'start_meter_reading_km',
        'end_meter_reading_km',
        'planned_mileage_km_per_liter',
        'planned_diesel_liters',
        'diesel_rate_per_liter',
        'customer_id',
        'broker_id',
        'notes',
        'pickups',
        'route_legs',
        'expenses',
        'trip',
        'status',
    ];

    for (const key of priorityKeys) {
        if (errors[key]) {
            return { [key]: errors[key] };
        }
    }

    const pickupFieldKeys = Object.keys(errors)
        .filter((key) => /^pickups\.\d+/.test(key))
        .sort();

    if (pickupFieldKeys.length > 0) {
        const key = pickupFieldKeys[0];

        return { [key]: errors[key] };
    }

    const routeFieldKeys = Object.keys(errors)
        .filter((key) => /^route_legs\.\d+\./.test(key))
        .sort((left, right) => {
            const leftParts = left.split('.');
            const rightParts = right.split('.');
            const leftIndex = Number(leftParts[1] ?? 0);
            const rightIndex = Number(rightParts[1] ?? 0);

            if (leftIndex !== rightIndex) {
                return leftIndex - rightIndex;
            }

            return left.localeCompare(right);
        });

    if (routeFieldKeys.length > 0) {
        const key = routeFieldKeys[0];

        return { [key]: errors[key] };
    }

    const expenseFieldKeys = Object.keys(errors)
        .filter((key) => key.startsWith('expenses.'))
        .sort();

    if (expenseFieldKeys.length > 0) {
        const key = expenseFieldKeys[0];

        return { [key]: errors[key] };
    }

    const remaining = Object.keys(errors);

    if (remaining.length === 0) {
        return {};
    }

    return { [remaining[0]]: errors[remaining[0]] };
}

export function validateTripStatusChange(
    payload,
    {
        tripType = 'ROUND_TRIP',
        routeLegs = [],
        startStationId = null,
        previousStatus = null,
        user = null,
        startMeterReadingKm = null,
        hasInvoices = false,
    } = {},
) {
    const errors = {};
    const editOptions = { has_invoices: hasInvoices };

    const transitionError = tripStatusTransitionError(previousStatus, payload.status);

    if (transitionError) {
        errors.status = [transitionError];
    }

    if (previousStatus && !canFullEditTrip(previousStatus, user, editOptions)) {
        const access = resolveTripEditAccess(previousStatus, user, editOptions);

        if (access.invoiceLocked) {
            errors.trip = ['This trip has invoices and cannot be edited.'];
        } else if (isTerminalTripStatus(previousStatus)) {
            errors.trip = ['Completed, cancelled, or invoiced trips cannot be edited.'];
        }
    }

    const startDate = dateOnlyFromDatetimeLocal(payload.start_at);
    const endDate = dateOnlyFromDatetimeLocal(payload.end_at);
    const arrivalDate = dateOnlyFromDatetimeLocal(payload.actual_arrival_at);

    if (startDate && endDate && endDate < startDate) {
        errors.end_at = ['End date cannot be before start date.'];
    }

    if (startDate && arrivalDate && arrivalDate < startDate) {
        errors.actual_arrival_at = ['Actual arrival cannot be before start date.'];
    }

    if (endDate && arrivalDate && endDate < arrivalDate) {
        errors.end_at = ['End date cannot be before the actual arrival date.'];
    }

    const resolvedStartMeter =
        payload.start_meter_reading_km != null && payload.start_meter_reading_km !== ''
            ? parseNumeric(payload.start_meter_reading_km)
            : parseNumeric(startMeterReadingKm);

    if (payload.status === 'IN_PROGRESS') {
        if (!payload.start_at) {
            errors.start_at = ['Start date is required before marking a trip as in progress.'];
        }

        if (resolvedStartMeter <= 0) {
            errors.start_meter_reading_km = [
                'Start meter reading is required before marking a trip as in progress.',
            ];
        }
    }

    if (payload.status === 'COMPLETED') {
        if (!payload.start_at) {
            errors.start_at = ['Trip start date is required before completing. Edit the trip and set the start date first.'];
        }

        if (!payload.end_at) {
            errors.end_at = ['End date is required to complete the trip.'];
        }

        if (!payload.actual_arrival_at) {
            errors.actual_arrival_at = ['Actual arrival date is required to complete the trip.'];
        }

        if (parseNumeric(payload.end_meter_reading_km) <= 0) {
            errors.end_meter_reading_km = ['End meter reading is required to complete the trip.'];
        }

        const startMeter = resolvedStartMeter;
        const endMeter = parseNumeric(payload.end_meter_reading_km);

        if (startMeter <= 0) {
            errors.start_meter_reading_km = [
                'Start meter reading is required before completing. Edit the trip and set the start meter reading first.',
            ];
        }

        if (endMeter > 0 && startMeter > 0 && endMeter < startMeter) {
            errors.end_meter_reading_km = ['End Meter Reading cannot be less than Start Meter Reading.'];
        }

        const normalizedTripType = String(tripType ?? 'ROUND_TRIP').toUpperCase();
        const lastLeg = routeLegs[routeLegs.length - 1];

        if (
            normalizedTripType === 'ROUND_TRIP'
            && lastLeg
            && startStationId
            && String(lastLeg.to_station_id) !== String(startStationId)
        ) {
            errors.route_legs = ['Round trip must end at the start station. Edit the trip and use "Return to Start".'];
        }
    }

    if (payload.status === 'CANCELLED') {
        if (!payload.start_at) {
            errors.start_at = ['Trip start date is required before cancelling. Edit the trip and set the start date first.'];
        }

        if (!payload.cancelled_at) {
            errors.cancelled_at = ['Cancellation date is required.'];
        }

        if (!payload.cancellation_reason?.trim()) {
            errors.cancellation_reason = ['Please provide a reason for cancelling this trip.'];
        }

        if (parseNumeric(payload.end_meter_reading_km) <= 0) {
            errors.end_meter_reading_km = ['End meter reading is required to cancel the trip.'];
        }

        const startMeter = resolvedStartMeter;
        const endMeter = parseNumeric(payload.end_meter_reading_km);

        if (startMeter <= 0) {
            errors.start_meter_reading_km = [
                'Start meter reading is required before cancelling. Edit the trip and set the start meter reading first.',
            ];
        }

        if (endMeter > 0 && startMeter > 0 && endMeter < startMeter) {
            errors.end_meter_reading_km = ['End Meter Reading cannot be less than Start Meter Reading.'];
        }
    }

    return errors;
}

export function resolveExpenseRouteLegSequence(expense, routeLegs = []) {
    if (expense?.route_leg_sequence != null && expense.route_leg_sequence !== '') {
        return Number(expense.route_leg_sequence);
    }

    const legId = expense?.trip_route_leg_id;

    if (!legId) {
        return '';
    }

    const leg = routeLegs.find((item) => String(item.id) === String(legId));

    if (leg?.sequence_no != null && leg.sequence_no !== '') {
        return Number(leg.sequence_no);
    }

    return '';
}

export function emptyExpense({
    defaultDate = '',
    expenseType = 'DIESEL',
    dieselRatePerLiter = null,
    startMeterReadingKm = null,
} = {}) {
    const normalizedType = String(expenseType || 'DIESEL').toUpperCase();
    const dieselRate =
        normalizedType === 'DIESEL' && dieselRatePerLiter != null && dieselRatePerLiter !== ''
            ? Number(dieselRatePerLiter)
            : '';
    const startMeter =
        startMeterReadingKm != null && startMeterReadingKm !== ''
            ? Number(startMeterReadingKm)
            : NaN;
    const prefilledOdometer =
        normalizedType === 'DIESEL' && Number.isFinite(startMeter) && startMeter > 0
            ? Math.trunc(startMeter)
            : '';

    return {
        expense_type: normalizedType === 'DIESEL' ? 'DIESEL' : expenseType || 'DIESEL',
        expense_date: defaultDate || '',
        amount: 0,
        fuel_liters: '',
        fuel_rate: Number.isFinite(dieselRate) && dieselRate > 0 ? dieselRate : '',
        fuel_odometer_km: prefilledOdometer,
        route_leg_sequence: '',
        notes: '',
        other_type: '',
    };
}

export function clampExpenseDate(expenseDate, tripStartDate, tripEndDate = '') {
    if (!expenseDate || !tripStartDate) {
        return expenseDate || tripStartDate || '';
    }

    let clamped = expenseDate;

    if (clamped < tripStartDate) {
        clamped = tripStartDate;
    }

    if (tripEndDate && clamped > tripEndDate) {
        clamped = tripEndDate;
    }

    return clamped;
}

export function validateExpenseDates(expenses, { startAt = null, endAt = null } = {}) {
    const errors = {};

    if (!startAt) {
        return errors;
    }

    (expenses ?? []).forEach((expense, index) => {
        const isActiveDiesel = isDieselExpenseType(expense.expense_type) && isActiveFuelRow(expense);
        const isActiveNonDiesel = !isDieselExpenseType(expense.expense_type) && Number(expense.amount ?? 0) > 0;

        if (!isActiveDiesel && !isActiveNonDiesel) {
            return;
        }

        const expenseDate = expense.expense_date || '';

        if (!expenseDate) {
            errors[`expenses.${index}.expense_date`] = ['Expense date is required.'];

            return;
        }

        if (expenseDate < startAt) {
            errors[`expenses.${index}.expense_date`] = ['Expense date cannot be before the trip start date.'];
        }

        if (endAt && expenseDate > endAt) {
            errors[`expenses.${index}.expense_date`] = ['Expense date cannot be after the trip end date.'];
        }
    });

    return errors;
}

export function isActiveFuelRow(expense) {
    const odometer = expense?.fuel_odometer_km;
    const liters = expense?.fuel_liters;

    const hasOdometer = odometer !== '' && odometer != null;
    const hasLiters = liters !== '' && liters != null;
    const userEdited = expense?._fuelDirty === true;
    const isPersisted = expense?.id != null;

    return hasOdometer || hasLiters || userEdited || isPersisted;
}

export function buildFuelRowSignature(expense) {
    return fuelRowSignature(expense);
}

function fuelRowSignature(expense) {
    const odometer = expense.fuel_odometer_km;
    const liters = expense.fuel_liters;
    const rate = expense.fuel_rate;

    return [
        odometer !== '' && odometer != null ? String(Math.trunc(parseNumeric(odometer))) : '',
        liters !== '' && liters != null ? String(Math.round(parseNumeric(liters) * 100) / 100) : '',
        rate !== '' && rate != null ? String(Math.round(parseNumeric(rate) * 100) / 100) : '',
    ].join('|');
}

function hasValidDecimalPlaces(value, maxPlaces) {
    const str = String(value);

    if (!str.includes('.')) {
        return true;
    }

    const fraction = str.split('.')[1] ?? '';

    return fraction.replace(/0+$/, '').length <= maxPlaces;
}

function formatMeterKmLabel(meter) {
    const n = parseNumeric(meter);

    return Number.isInteger(n) ? String(n) : String(n).replace(/\.?0+$/, '');
}

function shouldSkipMeterRangeValidation(expense) {
    if (expense._fuelDirty || !expense._originalFuelSignature) {
        return false;
    }

    return fuelRowSignature(expense) === expense._originalFuelSignature;
}

export function getLegacyFuelWarning(expense, { startMeterKm = null, endMeterKm = null } = {}) {
    if (!isDieselExpenseType(expense.expense_type) || !isActiveFuelRow(expense) || expense._fuelDirty) {
        return '';
    }

    if (!shouldSkipMeterRangeValidation(expense)) {
        return '';
    }

    const startMeter = parseNumeric(startMeterKm);
    const endMeter = endMeterKm === '' || endMeterKm == null ? null : parseNumeric(endMeterKm);
    const rawOdometer = expense.fuel_odometer_km;

    if (rawOdometer === '' || rawOdometer == null || startMeter <= 0) {
        return '';
    }

    const meter = Math.trunc(parseNumeric(rawOdometer));

    if (meter < Math.trunc(startMeter)) {
        return `Meter reading (${meter} KM) is below the trip start reading (${formatMeterKmLabel(startMeter)} KM). Update this entry when convenient.`;
    }

    if (endMeter !== null && endMeter > 0 && meter > Math.trunc(endMeter)) {
        return `Meter reading (${meter} KM) exceeds the trip end reading (${formatMeterKmLabel(endMeter)} KM). Update this entry when convenient.`;
    }

    return '';
}

function meterRangeMessage(startMeter, endMeter) {
    if (startMeter > 0 && endMeter !== null && endMeter > 0) {
        return `Enter a meter reading between ${formatMeterKmLabel(startMeter)}–${formatMeterKmLabel(endMeter)} KM.`;
    }

    if (startMeter > 0) {
        return `Enter a meter reading of at least ${formatMeterKmLabel(startMeter)} KM.`;
    }

    if (endMeter !== null && endMeter > 0) {
        return `Enter a meter reading up to ${formatMeterKmLabel(endMeter)} KM.`;
    }

    return 'Meter reading is required.';
}

function isMissingMeterReading(rawOdometer) {
    return rawOdometer === '' || rawOdometer == null || parseNumeric(rawOdometer) <= 0;
}

export function validateDieselExpenses(
    expenses,
    { startMeterKm = null, endMeterKm = null, strict = true } = {},
) {
    const errors = {};
    const startMeter = parseNumeric(startMeterKm);
    const endMeterRaw = endMeterKm;
    const endMeter =
        endMeterRaw === '' || endMeterRaw == null ? null : parseNumeric(endMeterRaw);

    (expenses ?? []).forEach((expense, index) => {
        if (!isDieselExpenseType(expense.expense_type) || !isActiveFuelRow(expense)) {
            return;
        }

        const skipMeterRange = shouldSkipMeterRangeValidation(expense);
        const rawOdometer = expense.fuel_odometer_km;

        if (isMissingMeterReading(rawOdometer)) {
            if (strict) {
                errors[`expenses.${index}.fuel_odometer_km`] = [meterRangeMessage(startMeter, endMeter)];
            }
        } else if (!Number.isInteger(Number(rawOdometer)) || parseNumeric(rawOdometer) !== Math.trunc(parseNumeric(rawOdometer))) {
            errors[`expenses.${index}.fuel_odometer_km`] = ['Meter reading must be a whole number (KM).'];
        } else if (!skipMeterRange && startMeter > 0) {
            const meter = Math.trunc(parseNumeric(rawOdometer));

            if (meter < Math.trunc(startMeter)) {
                errors[`expenses.${index}.fuel_odometer_km`] = [
                    `Too low. Entered ${formatMeterKmLabel(meter)} KM — allowed from ${formatMeterKmLabel(startMeter)} KM.`,
                ];
            } else if (endMeter !== null && endMeter > 0 && meter > Math.trunc(endMeter)) {
                errors[`expenses.${index}.fuel_odometer_km`] = [
                    `Too high. Entered ${formatMeterKmLabel(meter)} KM — allowed up to ${formatMeterKmLabel(endMeter)} KM.`,
                ];
            }
        }

        const rawLiters = expense.fuel_liters;

        if (rawLiters === '' || rawLiters == null) {
            if (strict) {
                errors[`expenses.${index}.fuel_liters`] = [
                    `Enter fuel quantity in liters (max ${MAX_FUEL_LITERS.toLocaleString('en-IN')} L).`,
                ];
            }
        } else if (parseNumeric(rawLiters) <= 0) {
            errors[`expenses.${index}.fuel_liters`] = [
                `Fuel quantity must be greater than 0 L (max ${MAX_FUEL_LITERS.toLocaleString('en-IN')} L).`,
            ];
        } else if (!hasValidDecimalPlaces(rawLiters, FUEL_DECIMAL_PLACES)) {
            errors[`expenses.${index}.fuel_liters`] = [
                `Use at most ${FUEL_DECIMAL_PLACES} decimal places for fuel quantity.`,
            ];
        } else if (parseNumeric(rawLiters) > MAX_FUEL_LITERS) {
            const entered = parseNumeric(rawLiters).toLocaleString('en-IN', { maximumFractionDigits: 2 });

            errors[`expenses.${index}.fuel_liters`] = [
                `Max ${MAX_FUEL_LITERS.toLocaleString('en-IN')} L allowed — you entered ${entered} L.`,
            ];
        }

        const rawRate = expense.fuel_rate;

        if (rawRate === '' || rawRate == null) {
            if (strict) {
                errors[`expenses.${index}.fuel_rate`] = ['Enter the diesel rate per liter.'];
            }
        } else if (parseNumeric(rawRate) <= 0) {
            errors[`expenses.${index}.fuel_rate`] = ['Diesel rate must be greater than zero.'];
        } else if (!hasValidDecimalPlaces(rawRate, FUEL_DECIMAL_PLACES)) {
            errors[`expenses.${index}.fuel_rate`] = [
                `Use at most ${FUEL_DECIMAL_PLACES} decimal places for diesel rate.`,
            ];
        } else if (parseNumeric(rawRate) > MAX_FUEL_RATE) {
            const entered = parseNumeric(rawRate).toLocaleString('en-IN', { maximumFractionDigits: 2 });

            errors[`expenses.${index}.fuel_rate`] = [
                `Max ₹${MAX_FUEL_RATE.toLocaleString('en-IN')}/L allowed — you entered ₹${entered}/L.`,
            ];
        }
    });

    return errors;
}

/** @deprecated Use validateDieselExpenses */
export function validateExpenseMeterReadings(expenses, options = {}) {
    return validateDieselExpenses(expenses, options);
}

export function expenseTypeLabel(type, otherType = '') {
    if (type === 'OTHER') {
        return otherType || 'Other';
    }

    return String(type ?? '').replace(/_/g, ' ') || 'Expense';
}

export function isDieselExpenseType(type) {
    return String(type ?? '').toUpperCase() === 'DIESEL';
}

export function calcDieselAmount(liters, rate) {
    const l = Number(liters);
    const r = Number(rate);

    if (!Number.isFinite(l) || !Number.isFinite(r) || l <= 0 || r <= 0) {
        return 0;
    }

    return Math.round(l * r * 100) / 100;
}

export function tripStatusMeta(status) {
    const normalized = String(status ?? '').toUpperCase();

    const map = {
        PENDING: { label: 'Pending', badge: 'bg-warning text-dark' },
        IN_PROGRESS: { label: 'In Progress', badge: 'bg-primary' },
        COMPLETED: { label: 'Completed', badge: 'bg-success' },
        CANCELLED: { label: 'Cancelled', badge: 'bg-danger' },
        INVOICED: { label: 'Invoiced', badge: 'bg-info text-dark' },
    };

    return map[normalized] ?? { label: normalized || '—', badge: 'bg-secondary' };
}

export const TRIP_STATUS_TRANSITIONS = {
    PENDING: ['IN_PROGRESS'],
    IN_PROGRESS: ['COMPLETED', 'CANCELLED'],
    COMPLETED: [],
    CANCELLED: [],
    INVOICED: [],
};

export function tripStatusTransitionError(previousStatus, nextStatus) {
    const current = String(previousStatus ?? '').toUpperCase();
    const next = String(nextStatus ?? '').toUpperCase();

    if (current === next) {
        return null;
    }

    const allowed = TRIP_STATUS_TRANSITIONS[current] ?? [];

    if (allowed.includes(next)) {
        return null;
    }

    if (current === 'PENDING' && (next === 'COMPLETED' || next === 'CANCELLED')) {
        return 'Move this trip to In Progress first, then mark it Completed or Cancelled.';
    }

    if (next === 'INVOICED') {
        return 'Invoiced status is set automatically when invoices are generated for a completed trip.';
    }

    return `Cannot change trip status from ${current} to ${next}.`;
}

export function tripStatusActionMeta(status) {
    const normalized = String(status ?? '').toUpperCase();

    if (normalized === 'IN_PROGRESS') {
        return {
            value: 'IN_PROGRESS',
            label: 'In Progress',
            variant: 'info',
            icon: 'bi bi-hourglass-split',
        };
    }

    if (normalized === 'COMPLETED') {
        return {
            value: 'COMPLETED',
            label: 'Completed',
            variant: 'success',
            icon: 'bi bi-check-circle-fill',
        };
    }

    if (normalized === 'CANCELLED') {
        return {
            value: 'CANCELLED',
            label: 'Cancelled',
            variant: 'danger',
            icon: 'bi bi-x-circle-fill',
        };
    }

    return null;
}

export function tripAllowedNextStatuses(status) {
    const current = String(status ?? '').toUpperCase();

    if (current === 'PENDING') {
        return ['IN_PROGRESS']
            .map((value) => tripStatusActionMeta(value))
            .filter(Boolean);
    }

    if (current === 'IN_PROGRESS') {
        return ['COMPLETED', 'CANCELLED']
            .map((value) => tripStatusActionMeta(value))
            .filter(Boolean);
    }

    return [];
}

export function isTerminalTripStatus(status) {
    return ['COMPLETED', 'CANCELLED', 'INVOICED'].includes(String(status ?? '').toUpperCase());
}

function resolveUserRole(user) {
    return String(user?.role ?? '').toLowerCase();
}

function isSuperAdminUser(user) {
    return resolveUserRole(user) === 'super_admin' || Number(user?.user_role_id) === 2;
}

function userHasTripEditPermission(user) {
    return userHasPermission(user?.permissions ?? [], 'trips.edit');
}

function userHasTripExpensesPermission(user) {
    return userHasPermission(user?.permissions ?? [], 'trips.expenses');
}

export function resolveTripEditAccess(status, user, options = {}) {
    const normalized = String(status ?? '').toUpperCase();
    const hasInvoices = Boolean(options?.has_invoices ?? options?.hasInvoices);
    const canEditTrips = options.canEditTrips ?? userHasTripEditPermission(user);
    const canManageExpenses = options.canManageExpenses ?? userHasTripExpensesPermission(user);

    if (hasInvoices) {
        if (canManageExpenses && (normalized === 'COMPLETED' || normalized === 'INVOICED')) {
            return { canEdit: true, fullEdit: false, expensesOnly: true, invoiceLocked: true };
        }

        return { canEdit: false, fullEdit: false, expensesOnly: false, invoiceLocked: true };
    }

    if (!isTerminalTripStatus(normalized)) {
        if (canEditTrips) {
            return { canEdit: true, fullEdit: true, expensesOnly: false, invoiceLocked: false };
        }

        if (canManageExpenses) {
            return { canEdit: true, fullEdit: false, expensesOnly: true, invoiceLocked: false };
        }

        return { canEdit: false, fullEdit: false, expensesOnly: false, invoiceLocked: false };
    }

    if (canEditTrips && isSuperAdminUser(user)) {
        return { canEdit: true, fullEdit: true, expensesOnly: false, invoiceLocked: false };
    }

    if (canManageExpenses && (normalized === 'COMPLETED' || normalized === 'INVOICED')) {
        return { canEdit: true, fullEdit: false, expensesOnly: true, invoiceLocked: false };
    }

    return { canEdit: false, fullEdit: false, expensesOnly: false, invoiceLocked: false };
}

export function canFullEditTrip(status, user, options = {}) {
    return resolveTripEditAccess(status, user, options).fullEdit;
}

export function canManageTripExpenses(status, user, options = {}) {
    return resolveTripEditAccess(status, user, options).canEdit;
}

export function canArchiveTrip(status, user = null) {
    const normalized = String(status ?? '').toUpperCase();

    return ['PENDING', 'IN_PROGRESS', 'CANCELLED'].includes(normalized);
}

export function tripArchiveDisabledTitle(status, user = null) {
    if (! canArchiveTrip(status, user)) {
        const normalized = String(status ?? '').toUpperCase();

        if (normalized === 'COMPLETED' || normalized === 'INVOICED') {
            return 'Completed or invoiced trips cannot be archived.';
        }

        return 'This trip cannot be archived or deleted.';
    }

    return 'Archive trip';
}

export function expenseTotalsBySequence(expenses) {
    const totals = {};

    (expenses ?? []).forEach((expense) => {
        const amount = Number(expense.amount ?? 0);

        if (amount <= 0) {
            return;
        }

        const sequence = expense.route_leg_sequence;

        if (sequence === '' || sequence == null) {
            return;
        }

        const key = Number(sequence);
        totals[key] = (totals[key] ?? 0) + amount;
    });

    return totals;
}

export function stopHasExpenses(expenseTotalsBySeq, sequence) {
    return Number(expenseTotalsBySeq?.[Number(sequence)] ?? 0) > 0;
}

export function tripEditDisabledTitle(status, user, options = {}) {
    const access = resolveTripEditAccess(status, user, options);

    if (access.fullEdit) {
        return 'Edit trip';
    }

    if (access.invoiceLocked) {
        return 'This trip has invoices and cannot be edited. Use Expenses to update expenses.';
    }

    if (access.expensesOnly) {
        return 'Trip details are locked. Use Expenses to edit trip expenses.';
    }

    if (isTerminalTripStatus(status)) {
        return 'Completed or cancelled trips are read-only';
    }

    return 'Edit trip';
}

export { thisMonthDateRange };

export function defaultTripListFilters() {
    return {
        ...thisMonthDateRange(),
        search: '',
        vehicle_id: '',
        customer_id: '',
        broker_id: '',
        status: '',
        trip_type: '',
    };
}

export function averageLegExpectedMileage(routeLegs = []) {
    const values = (routeLegs ?? [])
        .map((leg) => Number(leg.expected_mileage_km_per_liter))
        .filter((value) => Number.isFinite(value) && value > 0);

    if (values.length === 0) {
        return null;
    }

    const sum = values.reduce((acc, value) => acc + value, 0);

    return Math.round((sum / values.length) * 100) / 100;
}

export function summarizeTripForm(form) {
    const maxLoadTon = Number(form.max_load_ton ?? 0);
    let fullLoadStops = 0;
    let halfLoadStops = 0;
    let emptyStops = 0;
    let freightTotal = 0;
    let totalWeightTon = 0;
    const isMultiPoint = String(form.trip_type ?? '').toUpperCase() === 'MULTI_POINT';

    if (isMultiPoint) {
        (form.pickups ?? []).forEach((pickup) => {
            totalWeightTon += Number(pickup.weight_ton ?? 0);
        });

        const tripDrops = form.drops ?? [];

        if (tripDrops.length > 0) {
            tripDrops.forEach((drop) => {
                const weight = Array.isArray(drop.allocations) && drop.allocations.length > 0
                    ? drop.allocations.reduce((sum, allocation) => sum + Number(allocation.weight_ton ?? 0), 0)
                    : Number(drop.weight_ton ?? 0);
                const classification = classifyLegLoad(weight, maxLoadTon);

                if (classification.category === LoadCategory.FULL) {
                    fullLoadStops += 1;
                } else if (classification.category === LoadCategory.HALF) {
                    halfLoadStops += 1;
                } else {
                    emptyStops += 1;
                }

                freightTotal += Array.isArray(drop.allocations) && drop.allocations.length > 0
                    ? drop.allocations.reduce((sum, allocation) => {
                        if (allocation?.freight_auto_calculate) {
                            return sum + calculateLegFreight(allocation.weight_ton, allocation.freight_rate);
                        }

                        return sum + Number(allocation.freight_amount ?? 0);
                    }, 0)
                    : Number(drop.freight_amount ?? 0);
            });
        } else {
            (form.pickups ?? []).forEach((pickup) => {
                (pickup.drops ?? []).forEach((drop) => {
                    const weight = Number(drop.weight_ton ?? 0);
                    const classification = classifyLegLoad(weight, maxLoadTon);

                    if (classification.category === LoadCategory.FULL) {
                        fullLoadStops += 1;
                    } else if (classification.category === LoadCategory.HALF) {
                        halfLoadStops += 1;
                    } else {
                        emptyStops += 1;
                    }

                    freightTotal += Number(drop.freight_amount ?? 0);
                });
            });
        }
    } else {
        (form.route_legs ?? []).forEach((leg) => {
            const weight = Number(leg.weight_ton ?? 0);
            const classification = classifyLegLoad(weight, maxLoadTon);

            if (classification.category === LoadCategory.FULL) {
                fullLoadStops += 1;
            } else if (classification.category === LoadCategory.HALF) {
                halfLoadStops += 1;
            } else {
                emptyStops += 1;
            }

            totalWeightTon += weight;
            freightTotal += Number(leg.freight_amount ?? 0);
        });
    }

    const expenseTotal = (form.expenses ?? [])
        .filter((e) => Number(e.amount) > 0)
        .reduce((sum, e) => sum + Number(e.amount), 0);

    const startMeter = parseNumeric(form.start_meter_reading_km);
    const endMeterRaw = form.end_meter_reading_km;
    const endMeter =
        endMeterRaw !== '' && endMeterRaw != null && Number.isFinite(Number(endMeterRaw))
            ? parseNumeric(endMeterRaw)
            : null;
    const meterKm = endMeter !== null && startMeter > 0 ? Math.max(0, endMeter - startMeter) : 0;
    const routeDistanceKm = isMultiPoint
        ? ((form.drops?.length ? form.drops : (form.pickups ?? []).flatMap((pickup) => pickup.drops ?? [])) ?? [])
            .reduce((sum, drop) => {
                const distance = Number(drop.distance_km ?? 0);

                return sum + (Number.isFinite(distance) && distance > 0 ? distance : 0);
            }, 0)
        : (form.route_legs ?? []).reduce((sum, leg) => {
            const distance = Number(leg.distance_km ?? 0);

            return sum + (Number.isFinite(distance) && distance > 0 ? distance : 0);
        }, 0);
    const totalKm = meterKm > 0 ? meterKm : routeDistanceKm;

    const legAverageMileage = averageLegExpectedMileage(
        isMultiPoint
            ? (form.drops?.length ? form.drops : (form.pickups ?? []).flatMap((pickup) => pickup.drops ?? []))
            : form.route_legs,
    );
    const plannedMileageRaw = form.planned_mileage_km_per_liter;
    const plannedMileage =
        plannedMileageRaw !== '' && plannedMileageRaw != null && Number.isFinite(Number(plannedMileageRaw))
            ? Number(plannedMileageRaw)
            : null;

    return {
        fullLoadStops,
        halfLoadStops,
        emptyStops,
        expectedMileage: legAverageMileage ?? plannedMileage,
        totalKm,
        meterKm,
        routeDistanceKm,
        totalWeightTon,
        freightTotal,
        expenseTotal,
        profit: freightTotal - expenseTotal,
    };
}

export function calculateLegFreight(weightTon, freightRate) {
    const weight = Number(weightTon ?? 0);
    const rate = Number(freightRate ?? 0);

    if (weight <= 0 || rate <= 0) {
        return 0;
    }

    return Math.round(weight * rate * 100) / 100;
}

export function resolveLegFreightAutoCalculate(leg) {
    const weight = resolveLegWeightTon(leg);
    const rate = parseNumeric(leg?.freight_rate);
    const freight = parseNumeric(leg?.freight_amount);

    if (weight <= 0) {
        return true;
    }

    if (rate <= 0) {
        return freight <= 0;
    }

    if (freight <= 0) {
        return true;
    }

    const calculated = calculateLegFreight(weight, rate);

    return Math.abs(freight - calculated) < 0.01;
}

export function resolveLegWeightTon(leg) {
    const weight = parseNumeric(leg?.weight_ton);

    if (weight > 0) {
        return weight;
    }

    const rate = parseNumeric(leg?.freight_rate);
    const freight = parseNumeric(leg?.freight_amount);

    if (rate > 0 && freight > 0) {
        return Math.round((freight / rate) * 1000) / 1000;
    }

    return 0;
}

export function legCountsAsLoaded(leg) {
    return resolveLegWeightTon(leg) > 0 || parseNumeric(leg?.freight_amount) > 0;
}

/**
 * @param {{
 *   plannedMileageKmPerLiter?: number | string | null,
 *   plannedDieselLiters?: number | string | null,
 *   dieselRatePerLiter?: number | string | null,
 *   actualTotalKm?: number | string | null,
 * }} params
 */
export function computeDieselPerformance({
    plannedMileageKmPerLiter = null,
    plannedDieselLiters = null,
    dieselRatePerLiter = null,
    actualTotalKm = null,
} = {}) {
    return calculatePerformanceSummary({
        plannedMileageKmPerLiter,
        plannedDieselLiters,
        dieselRatePerLiter,
        actualTotalKm,
    });
}
