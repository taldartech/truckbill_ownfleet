import api from '../api';

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

export function emptyRouteStop(sequence = 1, fromStationId = '', routeType = 'LOADED', toStationId = '', customerId = '') {
    return {
        from_station_id: fromStationId,
        to_station_id: toStationId,
        customer_id: customerId,
        use_same_customer: false,
        route_type: routeType,
        sequence_no: sequence,
        distance_km: 0,
        freight_amount: 0,
        remarks: '',
    };
}

export function routeTypeMeta(type) {
    return ROUTE_TYPES.find((t) => t.value === type) ?? ROUTE_TYPES[0];
}

function parseNumeric(value) {
    const parsed = Number(String(value ?? '').trim());

    return Number.isFinite(parsed) ? parsed : 0;
}

export function validateTripPayload(payload, { mode = 'create', previousStatus = null } = {}) {
    const errors = {};

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
        errors.start_station_id = ['Select the start station for this round trip.'];
    }

    if (!payload.start_at) {
        errors.start_at = ['Trip start date is required.'];
    }

    if (payload.start_at && payload.end_at && payload.end_at < payload.start_at) {
        errors.end_at = ['End date cannot be before start date.'];
    }

    if (payload.status === 'COMPLETED' && mode === 'edit') {
        if (!payload.end_at) {
            errors.end_at = ['End date is required to complete the trip.'];
        }

        if (!payload.actual_arrival_at) {
            errors.actual_arrival_at = ['Actual arrival date is required to complete the trip.'];
        }

        if (payload.start_at && payload.actual_arrival_at && payload.actual_arrival_at < payload.start_at) {
            errors.actual_arrival_at = ['Actual arrival cannot be before start date.'];
        }
    }

    if (payload.status === 'CANCELLED' && mode === 'edit') {
        if (!payload.cancelled_at) {
            errors.cancelled_at = ['Cancellation date is required.'];
        }

        if (!payload.cancellation_reason?.trim()) {
            errors.cancellation_reason = ['Please provide a reason for cancelling this trip.'];
        }
    }

    const legs = payload.route_legs ?? [];

    if (legs.length === 0) {
        errors.route_legs = ['Add at least one stop on the route.'];
    }

    let hasRevenueLeg = false;

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

        if (parseNumeric(leg.distance_km) <= 0) {
            errors[`route_legs.${index}.distance_km`] = [`Stop ${index + 1}: distance must be greater than zero.`];
        }

        if (leg.route_type === 'LOADED' || leg.route_type === 'HALF_LOADED') {
            hasRevenueLeg = true;
        }
    });

    const lastLeg = legs[legs.length - 1];

    if (lastLeg && payload.start_station_id && String(lastLeg.to_station_id) !== String(payload.start_station_id)) {
        errors.route_legs = ['Round trip must end at the start station. Use "Return to Start" button.'];
    }

    if (legs.length > 0 && !hasRevenueLeg) {
        errors.route_legs = ['At least one full load or half load stop is required.'];
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
        distance_km: '',
        odometer_start: '',
        odometer_end: '',
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

export function thisMonthDateRange(referenceDate = new Date()) {
    const year = referenceDate.getFullYear();
    const month = referenceDate.getMonth();
    const pad = (value) => String(value).padStart(2, '0');
    const lastDay = new Date(year, month + 1, 0).getDate();

    return {
        from_date: `${year}-${pad(month + 1)}-01`,
        to_date: `${year}-${pad(month + 1)}-${pad(lastDay)}`,
    };
}

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
    let loadedKm = 0;
    let halfLoadedKm = 0;
    let emptyKm = 0;
    let freightTotal = 0;

    (form.route_legs ?? []).forEach((leg) => {
        const km = Number(leg.distance_km ?? 0);

        if (leg.route_type === 'EMPTY') {
            emptyKm += km;

            return;
        }

        if (leg.route_type === 'HALF_LOADED') {
            halfLoadedKm += km;
            freightTotal += Number(leg.freight_amount ?? 0);

            return;
        }

        loadedKm += km;
        freightTotal += Number(leg.freight_amount ?? 0);
    });

    const expenseTotal = (form.expenses ?? [])
        .filter((e) => Number(e.amount) > 0)
        .reduce((sum, e) => sum + Number(e.amount), 0);

    return {
        loadedKm,
        halfLoadedKm,
        emptyKm,
        totalKm: loadedKm + halfLoadedKm + emptyKm,
        freightTotal,
        expenseTotal,
        profit: freightTotal - expenseTotal,
    };
}
