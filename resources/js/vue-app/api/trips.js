import api from '../api';
import { thisMonthDateRange } from '../utils/dateRangePresets';
import { dateOnlyFromDatetimeLocal } from '../utils/datetime';

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

export async function fetchNextTripNumber() {
    const { data } = await api.get(`${tripsBase}/next-number`);

    return data.trip_number ?? '';
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

export async function createTrip(payload) {
    const { data } = await api.post(tripsBase, payload);

    return unwrapResource(data);
}

export async function updateTrip(id, payload) {
    const { data } = await api.put(`${tripsBase}/${id}`, payload);

    return unwrapResource(data);
}

export async function deleteTrip(id) {
    await api.delete(`${tripsBase}/${id}`);
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
];

export function tripTypeLabel(type) {
    return TRIP_TYPES.find((t) => t.value === String(type ?? '').toUpperCase())?.label ?? 'Trip';
}

export const ROUTE_TYPES = [
    { value: 'LOADED', label: 'Full Load', badge: 'bg-success' },
    { value: 'HALF_LOADED', label: 'Half Load', badge: 'bg-warning text-dark' },
    { value: 'EMPTY', label: 'Empty', badge: 'bg-secondary' },
];

export const TRIP_STATUSES = [
    { value: 'CREATED', label: 'Created' },
    { value: 'IN_PROGRESS', label: 'In Progress' },
    { value: 'COMPLETED', label: 'Completed' },
    { value: 'CANCELLED', label: 'Cancelled' },
];

export const EXPENSE_TYPES = [
    { value: 'DIESEL', label: 'Diesel' },
    { value: 'TOLL', label: 'Toll' },
    { value: 'DRIVER_EXPENSE', label: 'Driver Expense' },
    { value: 'LOADING_CHARGES', label: 'Loading Charges' },
    { value: 'UNLOADING_CHARGES', label: 'Unloading Charges' },
    { value: 'REPAIR', label: 'Repair' },
    { value: 'FOOD_EXPENSE', label: 'Food Expense' },
    { value: 'PARKING', label: 'Parking' },
    { value: 'RTO_EXPENSE', label: 'RTO' },
    { value: 'PERMIT_BORDER_TAX', label: 'Permit / border tax' },
    { value: 'OTHER', label: 'Other' },
];

export function emptyRouteStop(sequence = 1, fromStationId = '', toStationId = '', customerId = '', { expectedMileage = '' } = {}) {
    return {
        from_station_id: fromStationId,
        to_station_id: toStationId,
        customer_id: customerId,
        use_same_customer: false,
        sequence_no: sequence,
        expected_mileage_km_per_liter: expectedMileage,
        freight_rate: '',
        freight_amount: 0,
        freight_auto_calculate: true,
        weight_ton: '',
        remarks: '',
    };
}

export function legLoadCategory(weightTon) {
    return Number(weightTon ?? 0) > 0 ? 'LOADED' : 'EMPTY';
}

export function legLoadCategoryMeta(weightTon) {
    const category = legLoadCategory(weightTon);

    if (category === 'LOADED') {
        return { value: 'LOADED', label: 'Loaded', badge: 'bg-success' };
    }

    return { value: 'EMPTY', label: 'Empty', badge: 'bg-secondary' };
}

export function routeTypeMeta(type) {
    return ROUTE_TYPES.find((t) => t.value === type) ?? ROUTE_TYPES[0];
}

function parseNumeric(value) {
    const parsed = Number(String(value ?? '').trim());

    return Number.isFinite(parsed) ? parsed : 0;
}

export { parseNumeric };

export function validateTripPayload(payload, { mode = 'create', previousStatus = null } = {}) {
    const errors = {};

    if (mode === 'create') {
        if (!payload.trip_type) {
            errors.trip_type = ['Trip type is required.'];
        }

        if (parseNumeric(payload.start_meter_reading_km) <= 0) {
            errors.start_meter_reading_km = ['Start meter reading is required and must be greater than zero.'];
        }
    }

    if (mode === 'create') {
        if (!payload.vehicle_id) {
            errors.vehicle_id = ['Please select a truck.'];
        }

        if (!payload.driver_id) {
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

    if (
        mode === 'edit'
        && previousStatus
        && ['COMPLETED', 'CANCELLED'].includes(String(previousStatus).toUpperCase())
    ) {
        errors.trip = ['Completed or cancelled trips cannot be edited.'];
    }

    if (!payload.start_station_id) {
        errors.start_station_id = ['Select the start station for this trip.'];
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

    if (mode === 'edit') {
        if (parseNumeric(payload.start_meter_reading_km) <= 0) {
            errors.start_meter_reading_km = ['Start meter reading is required and must be greater than zero.'];
        }

        const startMeter = parseNumeric(payload.start_meter_reading_km);
        const endMeterRaw = payload.end_meter_reading_km;
        const endMeter =
            endMeterRaw === '' || endMeterRaw == null ? null : parseNumeric(endMeterRaw);

        if (endMeter !== null && endMeter > 0) {
            if (startMeter > 0 && endMeter < startMeter) {
                errors.end_meter_reading_km = ['End Meter Reading cannot be less than Start Meter Reading.'];
            }

            if (
                payload.truck_running_meter_km != null
                && endMeter < parseNumeric(payload.truck_running_meter_km)
            ) {
                errors.end_meter_reading_km = [
                    'End Meter Reading cannot be less than the current Truck Running Meter Reading. Please enter a valid meter reading.',
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

        if (payload.truck_running_meter_km != null && endMeter > 0 && endMeter < parseNumeric(payload.truck_running_meter_km)) {
            errors.end_meter_reading_km = [
                'End Meter Reading cannot be less than the current Truck Running Meter Reading. Please enter a valid meter reading.',
            ];
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

        if (
            endMeter > 0
            && payload.truck_running_meter_km != null
            && endMeter < parseNumeric(payload.truck_running_meter_km)
        ) {
            errors.end_meter_reading_km = [
                'End Meter Reading cannot be less than the current Truck Running Meter Reading. Please enter a valid meter reading.',
            ];
        }
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

        if (weight > 0) {
            hasLoadedLeg = true;
        }

        if (weight <= 0 && parseNumeric(leg.freight_amount) > 0) {
            errors[`route_legs.${index}.freight_amount`] = [`Stop ${index + 1}: freight must be zero when weight is zero.`];
        }
    });

    const lastLeg = legs[legs.length - 1];

    const tripType = String(payload.trip_type ?? 'ROUND_TRIP').toUpperCase();

    if (tripType === 'ROUND_TRIP' && lastLeg && payload.start_station_id && String(lastLeg.to_station_id) !== String(payload.start_station_id)) {
        errors.route_legs = ['Round trip must end at the start station. Use "Return to Start" button.'];
    }

    if (legs.length > 0 && !hasLoadedLeg) {
        errors.route_legs = ['At least one loaded stop with weight greater than zero is required.'];
    }

    if (mode === 'create' && legs.length > 0) {
        const freightTotal = legs.reduce((sum, leg) => sum + parseNumeric(leg.freight_amount), 0);

        if (freightTotal <= 0) {
            errors.route_legs = ['Total freight must be greater than zero before creating a trip.'];
        }
    }

    Object.assign(
        errors,
        validateExpenseDates(payload.expenses ?? [], {
            startAt: payload.start_at || null,
            endAt: payload.end_at || null,
        }),
    );

    return errors;
}

export function emptyExpense({ defaultDate = '' } = {}) {
    return {
        expense_type: 'DIESEL',
        expense_date: defaultDate || '',
        amount: 0,
        fuel_liters: '',
        fuel_rate: '',
        fuel_odometer_km: '',
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
        if (Number(expense.amount ?? 0) <= 0) {
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

export function expenseTypeLabel(type, otherType = '') {
    if (type === 'OTHER') {
        return otherType || 'Other';
    }

    return EXPENSE_TYPES.find((item) => item.value === type)?.label ?? (String(type ?? '').replace(/_/g, ' ') || 'Expense');
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
        CREATED: { label: 'Created', badge: 'bg-info text-dark' },
        IN_PROGRESS: { label: 'In Progress', badge: 'bg-primary' },
        COMPLETED: { label: 'Completed', badge: 'bg-success' },
        CANCELLED: { label: 'Cancelled', badge: 'bg-danger' },
    };

    return map[normalized] ?? { label: normalized || '—', badge: 'bg-secondary' };
}

export function tripAllowedNextStatuses(status) {
    const current = String(status ?? '').toUpperCase();

    if (current === 'CREATED') {
        return [{ value: 'IN_PROGRESS', label: 'In Progress' }];
    }

    if (current === 'IN_PROGRESS') {
        return [
            { value: 'COMPLETED', label: 'Completed' },
            { value: 'CANCELLED', label: 'Cancelled' },
        ];
    }

    return [];
}

export function isTerminalTripStatus(status) {
    return ['COMPLETED', 'CANCELLED'].includes(String(status ?? '').toUpperCase());
}

function resolveUserRole(user) {
    return String(user?.role ?? '').toLowerCase();
}

function isSuperAdminUser(user) {
    return resolveUserRole(user) === 'super_admin' || Number(user?.user_role_id) === 2;
}

function isAdminUser(user) {
    return resolveUserRole(user) === 'admin' || Number(user?.user_role_id) === 3;
}

export function resolveTripEditAccess(status, user) {
    const normalized = String(status ?? '').toUpperCase();

    if (!isTerminalTripStatus(normalized)) {
        return { canEdit: true, fullEdit: true, expensesOnly: false };
    }

    if (isSuperAdminUser(user)) {
        return { canEdit: true, fullEdit: true, expensesOnly: false };
    }

    if (isAdminUser(user) && normalized === 'COMPLETED') {
        return { canEdit: true, fullEdit: false, expensesOnly: true };
    }

    return { canEdit: false, fullEdit: false, expensesOnly: false };
}

export function tripEditDisabledTitle(status, user) {
    const access = resolveTripEditAccess(status, user);

    if (access.canEdit) {
        if (access.expensesOnly) {
            return 'Edit trip expenses';
        }

        return 'Edit trip';
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
    };
}

export function summarizeTripForm(form) {
    let loadedStops = 0;
    let emptyStops = 0;
    let mileageSum = 0;
    let mileageCount = 0;
    let freightTotal = 0;
    let totalWeightTon = 0;

    (form.route_legs ?? []).forEach((leg) => {
        const weight = Number(leg.weight_ton ?? 0);

        if (weight > 0) {
            loadedStops += 1;
        } else {
            emptyStops += 1;
        }

        totalWeightTon += weight;
        freightTotal += Number(leg.freight_amount ?? 0);

        const mileage = leg.expected_mileage_km_per_liter;

        if (mileage !== '' && mileage != null && Number.isFinite(Number(mileage))) {
            mileageSum += Number(mileage);
            mileageCount += 1;
        }
    });

    const expenseTotal = (form.expenses ?? [])
        .filter((e) => Number(e.amount) > 0)
        .reduce((sum, e) => sum + Number(e.amount), 0);

    const startMeter = parseNumeric(form.start_meter_reading_km);
    const endMeterRaw = form.end_meter_reading_km;
    const endMeter =
        endMeterRaw !== '' && endMeterRaw != null && Number.isFinite(Number(endMeterRaw))
            ? parseNumeric(endMeterRaw)
            : null;
    const totalKm = endMeter !== null && startMeter > 0 ? Math.max(0, endMeter - startMeter) : 0;

    return {
        loadedStops,
        emptyStops,
        expectedAvgMileage: mileageCount > 0 ? mileageSum / mileageCount : null,
        totalKm,
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

/**
 * @param {{
 *   routeLegs?: Array<{ expected_mileage_km_per_liter?: number | string | null }>,
 *   expectedDieselLiters?: number | string | null,
 *   dieselRatePerLiter?: number | string | null,
 *   actualTotalKm?: number | string | null,
 * }} params
 * @returns {null | {
 *   expected_avg_mileage_km_per_liter: number,
 *   expected_diesel_liters: number,
 *   diesel_rate_per_liter: number | null,
 *   expected_total_km: number,
 *   actual_total_km: number | null,
 *   total_trip_cost: number | null,
 *   rate_per_km: number | null,
 *   unused_km: number | null,
 *   extra_km: number | null,
 *   remaining_amount: number | null,
 *   performance_status: 'pending' | 'within' | 'under' | 'over',
 *   extra_diesel_liters: number | null,
 *   extra_diesel_cost: number | null,
 * }}
 */
export function computeDieselPerformance({
    routeLegs = [],
    expectedDieselLiters = null,
    dieselRatePerLiter = null,
    actualTotalKm = null,
} = {}) {
    let mileageSum = 0;
    let mileageCount = 0;

    (routeLegs ?? []).forEach((leg) => {
        const mileage = leg.expected_mileage_km_per_liter;

        if (mileage !== '' && mileage != null && Number.isFinite(Number(mileage)) && Number(mileage) > 0) {
            mileageSum += Number(mileage);
            mileageCount += 1;
        }
    });

    if (mileageCount === 0) {
        return null;
    }

    const expectedAvgMileage = roundCurrency(mileageSum / mileageCount);
    const expectedDiesel = Number(expectedDieselLiters ?? 0);
    const dieselRate = Number(dieselRatePerLiter ?? 0);

    if (!Number.isFinite(expectedDiesel) || expectedDiesel <= 0) {
        return null;
    }

    const expectedTotalKm = roundCurrency(expectedDiesel * expectedAvgMileage);
    const actualKm = Number(actualTotalKm ?? 0);
    const hasActual = Number.isFinite(actualKm) && actualKm > 0;

    let extraKm = 0;
    let unusedKm = 0;
    let performanceStatus = hasActual ? 'within' : 'pending';

    if (hasActual) {
        const diffKm = roundCurrency(actualKm - expectedTotalKm);

        if (diffKm > 0) {
            extraKm = diffKm;
            performanceStatus = 'over';
        } else if (diffKm < 0) {
            unusedKm = Math.abs(diffKm);
            performanceStatus = 'under';
        }
    }

    const extraDiesel =
        extraKm > 0 && expectedAvgMileage > 0 ? roundCurrency(extraKm / expectedAvgMileage) : 0;
    const extraDieselCost =
        extraDiesel > 0 && dieselRate > 0 ? roundCurrency(extraDiesel * dieselRate) : 0;
    const totalTripCost = dieselRate > 0 ? roundCurrency(expectedDiesel * dieselRate) : null;
    const ratePerKm =
        totalTripCost !== null && expectedTotalKm > 0 ? roundCurrency(totalTripCost / expectedTotalKm) : null;
    const remainingAmount =
        unusedKm > 0 && ratePerKm !== null ? roundCurrency(unusedKm * ratePerKm) : 0;

    return {
        expected_avg_mileage_km_per_liter: roundCurrency(expectedAvgMileage),
        expected_diesel_liters: roundCurrency(expectedDiesel),
        diesel_rate_per_liter: dieselRate > 0 ? roundCurrency(dieselRate) : null,
        total_trip_cost: totalTripCost,
        expected_total_km: expectedTotalKm,
        actual_total_km: hasActual ? roundCurrency(actualKm) : null,
        rate_per_km: ratePerKm,
        unused_km: hasActual ? roundCurrency(unusedKm) : null,
        extra_km: hasActual ? extraKm : null,
        remaining_amount: hasActual ? remainingAmount : null,
        performance_status: performanceStatus,
        extra_diesel_liters: hasActual ? extraDiesel : null,
        extra_diesel_cost: hasActual ? extraDieselCost : null,
    };
}

function roundCurrency(value) {
    return Math.round(Number(value) * 100) / 100;
}
