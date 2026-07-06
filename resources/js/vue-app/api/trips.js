import api from '../api';
import { LoadCategory, loadCategoryMeta } from '../constants/loadCategory';
import { calculatePerformanceSummary } from '../utils/tripFuelCalculator';
import { thisMonthDateRange } from '../utils/dateRangePresets';
import { dateOnlyFromDatetimeLocal, toDateInputValue, isDateAfter } from '../utils/datetime';

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

export function tripTypeMeta(type) {
    const normalized = String(type ?? 'ROUND_TRIP').toUpperCase();

    if (normalized === 'ONE_WAY') {
        return {
            label: 'One Way',
            icon: 'bi-arrow-right',
            badge: 'trip-type-badge--one-way',
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

export const EXPENSE_FORM_TYPES = [
    { value: 'DIESEL', label: 'Fuel (L × Rate)' },
    { value: 'TOLL', label: 'Highway toll' },
    { value: 'LOADING_CHARGES', label: 'Load charges' },
    { value: 'UNLOADING_CHARGES', label: 'Unload charges' },
    { value: 'FOOD_EXPENSE', label: 'Driver food' },
    { value: 'DRIVER_EXPENSE', label: 'Driver cash / misc' },
    { value: 'REPAIR', label: 'Breakdown' },
    { value: 'PARKING', label: 'Parking fee' },
    { value: 'RTO_EXPENSE', label: 'RTO' },
    { value: 'PERMIT_BORDER_TAX', label: 'Permit / border tax' },
    { value: 'OTHER', label: 'Other' },
];

export const QUICK_EXPENSE_PILLS = [
    { value: 'DIESEL', label: 'Diesel', icon: 'bi-fuel-pump', className: 'pill-diesel' },
    { value: 'TOLL', label: 'Toll', icon: 'bi-sign-turn-right', className: 'pill-toll' },
    { value: 'LOADING_CHARGES', label: 'Loading', icon: 'bi-box-arrow-in-down', className: 'pill-loading' },
    { value: 'UNLOADING_CHARGES', label: 'Unloading', icon: 'bi-box-arrow-up', className: 'pill-unloading' },
    { value: 'FOOD_EXPENSE', label: 'Food', icon: 'bi-cup-hot', className: 'pill-food' },
    { value: 'RTO_EXPENSE', label: 'RTO', icon: 'bi-shield-check', className: 'pill-rto' },
    {
        value: 'PERMIT_BORDER_TAX',
        label: 'Permit',
        icon: 'bi-passport',
        className: 'pill-permit',
    },
    { value: 'OTHER', label: 'Other', icon: 'bi-plus-lg', className: 'pill-other' },
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

export function validateTripPayload(payload, { mode = 'create', previousStatus = null, user = null } = {}) {
    const errors = {};

    if (mode === 'create') {
        if (!payload.trip_type) {
            errors.trip_type = ['Trip type is required.'];
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
        && !canFullEditTrip(previousStatus, user)
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

    if (mode === 'create' || mode === 'edit') {
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
        validateDieselExpenses(payload.expenses ?? [], {
            startMeterKm: payload.start_meter_reading_km ?? null,
            endMeterKm: payload.end_meter_reading_km ?? null,
        }),
    );

    return errors;
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
    } = {},
) {
    const errors = {};

    if (
        previousStatus
        && ['COMPLETED', 'CANCELLED'].includes(String(previousStatus).toUpperCase())
        && !canFullEditTrip(previousStatus, user)
    ) {
        errors.trip = ['Completed or cancelled trips cannot be edited.'];
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

    if (payload.status === 'COMPLETED') {
        if (!payload.start_at) {
            errors.start_at = ['Trip start date is required before completing. Edit the trip and set the start date first.'];
        }

        const today = toDateInputValue();

        if (startDate && isDateAfter(startDate, today)) {
            errors.status = ['This trip cannot be completed before its start date.'];
        }

        if (arrivalDate && isDateAfter(arrivalDate, today)) {
            errors.actual_arrival_at = ['Actual arrival date cannot be in the future.'];
        }

        if (endDate && isDateAfter(endDate, today)) {
            errors.end_at = ['End date cannot be in the future.'];
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

export function emptyExpense({ defaultDate = '', expenseType = 'DIESEL', dieselRatePerLiter = null } = {}) {
    const normalizedType = expenseType || 'DIESEL';
    const dieselRate =
        normalizedType === 'DIESEL' && dieselRatePerLiter != null && dieselRatePerLiter !== ''
            ? Number(dieselRatePerLiter)
            : '';

    return {
        expense_type: normalizedType,
        expense_date: defaultDate || '',
        amount: 0,
        fuel_liters: '',
        fuel_rate: Number.isFinite(dieselRate) && dieselRate > 0 ? dieselRate : '',
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

export const MAX_FUEL_LITERS = 5000;

export const MAX_FUEL_RATE = 1000;

export const FUEL_DECIMAL_PLACES = 2;

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

export function canFullEditTrip(status, user) {
    return resolveTripEditAccess(status, user).fullEdit;
}

export function canManageTripExpenses(status, user) {
    return resolveTripEditAccess(status, user).canEdit;
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

export function tripEditDisabledTitle(status, user) {
    const access = resolveTripEditAccess(status, user);

    if (access.fullEdit) {
        return 'Edit trip';
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

    const legAverageMileage = averageLegExpectedMileage(form.route_legs);
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
