<template>
    <div class="container mt-4 trip-detail-page pb-4">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
            <div>
                <RouterLink :to="tripListReturnTo" class="text-decoration-none small">
                    <i class="bi bi-arrow-left"></i> Back to Trips
                </RouterLink>
                <div class="d-flex flex-wrap align-items-center gap-2 mt-1">
                    <h3 class="mb-0">{{ trip?.trip_number ?? 'Trip Details' }}</h3>
                    <span v-if="trip" class="badge" :class="statusMeta.badge">{{ statusMeta.label }}</span>
                </div>
                <p v-if="trip" class="text-muted small mb-0">
                    <RouterLink
                        v-if="trip.vehicle?.id"
                        :to="{ name: 'trucks.show', params: { vehicleId: trip.vehicle.id } }"
                        class="text-decoration-none"
                    >
                        {{ trip.vehicle.registration_number }}
                    </RouterLink>
                    <span v-else>—</span>
                    <span v-if="trip.driver?.name"> · {{ trip.driver.name }}</span>
                    <span v-if="tripDuration"> · {{ tripDuration }}</span>
                </p>
            </div>
            <div v-if="trip" class="d-flex flex-wrap align-items-center gap-2">
                <TripInlineStatusActions
                    :trip="trip"
                    :can-manage="canManage && canEditTripStatus"
                    :compact="false"
                    @updated="onTripUpdated"
                />
                <AppButton
                    v-if="canManage && canManageTripExpensesFor"
                    variant="success"
                    outline
                    size="sm"
                    icon="bi bi-receipt"
                    label="Manage Expenses"
                    :to="{ name: 'trips.expenses', params: { id: trip.id } }"
                />
                <AppButton
                    v-if="canManage && canEditTripPage"
                    variant="primary"
                    outline
                    size="sm"
                    icon="bi bi-pencil"
                    label="Edit"
                    :to="{ name: 'trips.edit', params: { id: trip.id } }"
                />
            </div>
        </div>

        <div v-if="needsCompanySelection" class="alert alert-warning">
            Select a company and click <strong>Enter</strong> to view trip details.
        </div>

        <div v-if="loading" class="text-center py-5 text-muted">Loading trip details…</div>

        <template v-else-if="trip">
            <div v-if="terminalAlert" class="alert alert-secondary mb-3">
                <i class="bi bi-lock me-1"></i>
                {{ terminalAlert }}
            </div>

            <div class="row g-3">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header">
                            <strong><i class="bi bi-signpost-split me-2" aria-hidden="true"></i>Trip Information</strong>
                            <span v-if="headerMetaText" class="text-muted small ms-2">{{ headerMetaText }}</span>
                        </div>
                        <div class="card-body row g-3 small">
                            <div class="col-md-4">
                                <span class="text-muted">Trip Number</span>
                                <div class="fw-semibold">{{ trip.trip_number || '—' }}</div>
                            </div>
                            <div class="col-md-4">
                                <span class="text-muted">Status</span>
                                <div>
                                    <span class="badge" :class="statusMeta.badge">{{ statusMeta.label }}</span>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <span class="text-muted">Start Station</span>
                                <div class="fw-semibold">{{ startStationLabel }}</div>
                            </div>
                            <div class="col-md-4">
                                <span class="text-muted">Trip Type</span>
                                <div>{{ tripTypeLabel(trip.trip_type) }}</div>
                            </div>
                            <div class="col-md-4">
                                <span class="text-muted">Start Date</span>
                                <div>{{ formatDateTime(trip.start_at) }}</div>
                            </div>
                            <div class="col-md-4">
                                <span class="text-muted d-flex align-items-center gap-1 flex-wrap">
                                    End Date
                                    <span
                                        v-if="arrivalTiming === 'on-time'"
                                        class="badge bg-info text-dark"
                                        title="Arrived on planned end date"
                                    >On Time</span>
                                    <span
                                        v-else-if="arrivalTiming === 'early'"
                                        class="badge bg-success"
                                        title="Arrived before planned end date"
                                    >Early</span>
                                    <span
                                        v-else-if="arrivalTiming === 'late'"
                                        class="badge bg-danger"
                                        title="Arrived after planned end date"
                                    >Late</span>
                                </span>
                                <div>{{ formatDateTime(trip.end_at) }}</div>
                            </div>
                            <div class="col-md-4">
                                <span class="text-muted">Actual Arrival</span>
                                <div>{{ formatDateTime(trip.actual_arrival_at) }}</div>
                            </div>
                            <div class="col-md-4">
                                <span class="text-muted">Truck</span>
                                <div>
                                    <RouterLink
                                        v-if="trip.vehicle?.id"
                                        :to="{ name: 'trucks.show', params: { vehicleId: trip.vehicle.id } }"
                                        class="text-decoration-none fw-semibold"
                                    >
                                        {{ trip.vehicle.registration_number }}
                                    </RouterLink>
                                    <span v-else>—</span>
                                </div>
                                <div v-if="vehicleMeta" class="text-muted">{{ vehicleMeta }}</div>
                            </div>
                            <div class="col-md-4">
                                <span class="text-muted">Truck Type</span>
                                <div>{{ trip.vehicle?.vehicle_type_label ?? trip.vehicle?.vehicle_type ?? '—' }}</div>
                            </div>
                            <div class="col-md-4">
                                <span class="text-muted">Truck Running Meter Reading</span>
                                <div>{{ formatKm(trip.vehicle?.odometer_km) }}</div>
                            </div>
                            <div class="col-md-4">
                                <span class="text-muted">Expected Mileage (KM/Ltr)</span>
                                <div>{{ trip.planned_mileage_km_per_liter ? formatKm(trip.planned_mileage_km_per_liter) : '—' }}</div>
                            </div>
                            <div class="col-md-4">
                                <span class="text-muted">Max Load (Ton)</span>
                                <div>{{ trip.max_load_ton ? formatKm(trip.max_load_ton) : '—' }}</div>
                            </div>
                            <div class="col-md-4">
                                <span class="text-muted">Expected Diesel (Ltr)</span>
                                <div>{{ trip.planned_diesel_liters ? formatKm(trip.planned_diesel_liters) : '—' }}</div>
                            </div>
                            <div class="col-md-4">
                                <span class="text-muted">Diesel Rate (₹/Ltr)</span>
                                <div>{{ trip.diesel_rate_per_liter ? formatMoney(trip.diesel_rate_per_liter) : '—' }}</div>
                            </div>
                            <div class="col-md-4">
                                <span class="text-muted">Start Meter Reading</span>
                                <div>{{ formatOdometerReading(trip.start_meter_reading_km) }}</div>
                            </div>
                            <div class="col-md-4">
                                <span class="text-muted">End Meter Reading</span>
                                <div>{{ formatOdometerReading(trip.end_meter_reading_km) }}</div>
                            </div>
                            <div class="col-md-4">
                                <span class="text-muted">Primary Driver</span>
                                <div class="fw-semibold">{{ trip.driver?.name ?? '—' }}</div>
                                <div v-if="trip.driver?.phone" class="text-muted">{{ trip.driver.phone }}</div>
                            </div>
                            <div class="col-md-4">
                                <span class="text-muted">Substitute Driver</span>
                                <div>{{ trip.substitute_driver?.name ?? '—' }}</div>
                                <div v-if="trip.substitute_driver?.phone" class="text-muted">
                                    {{ trip.substitute_driver.phone }}
                                </div>
                            </div>
                            <div class="col-md-4">
                                <span class="text-muted">Customer</span>
                                <div>{{ trip.customer_name || '—' }}</div>
                            </div>
                            <div class="col-md-4">
                                <span class="text-muted">Reference</span>
                                <div>{{ trip.broker_name || '—' }}</div>
                            </div>
                            <div v-if="trip.notes?.trim()" class="col-12">
                                <span class="text-muted">Notes</span>
                                <div style="white-space: pre-wrap">{{ trip.notes }}</div>
                            </div>
                            <div v-if="trip.status === 'CANCELLED'" class="col-md-4">
                                <span class="text-muted">Cancelled On</span>
                                <div>{{ formatDateTime(trip.cancelled_at) }}</div>
                            </div>
                            <div v-if="trip.status === 'CANCELLED'" class="col-12">
                                <div class="alert alert-danger mb-0 py-2">
                                    <div class="small">
                                        <strong>Cancellation reason:</strong>
                                        {{ trip.cancellation_reason || '—' }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card mt-3">
                        <div class="card-header">
                            <strong><i class="bi bi-arrow-repeat me-2" aria-hidden="true"></i>{{ routeSectionTitle }}</strong>
                        </div>
                        <div class="card-body">
                            <div v-if="routePreview" class="alert alert-light border mb-3 py-2 small mb-3">
                                <strong>Route:</strong> {{ routePreview }}
                            </div>
                            <div v-if="routeLegs.length === 0" class="text-muted text-center py-4">
                                No route legs recorded.
                            </div>
                            <div v-else class="table-responsive">
                                <table class="table table-bordered align-middle mb-0 trip-route-table trip-route-table--readonly">
                                    <thead class="table-light">
                                        <tr>
                                            <th>#</th>
                                            <th>From</th>
                                            <th>To</th>
                                            <th>Customer</th>
                                            <th>Weight (Ton)</th>
                                            <th>Expected Mileage</th>
                                            <th>Freight Rate (₹/Ton)</th>
                                            <th>Freight</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="leg in routeLegs" :key="leg.id ?? leg.sequence_no">
                                            <td>{{ leg.sequence_no }}</td>
                                            <td>
                                                <div>{{ leg.from_city || leg.from_location || '—' }}</div>
                                                <div v-if="leg.from_address" class="text-muted">{{ leg.from_address }}</div>
                                            </td>
                                            <td>
                                                <div>{{ leg.to_city || leg.to_location || '—' }}</div>
                                                <div v-if="leg.to_address" class="text-muted">{{ leg.to_address }}</div>
                                            </td>
                                            <td>{{ leg.customer_name || '—' }}</td>
                                            <td>
                                                <div class="trip-route-weight-field">
                                                    <span
                                                        class="trip-route-load-tag"
                                                        :class="`trip-route-load-tag--${legLoadCategoryMeta(leg.weight_ton, trip.max_load_ton).category}`"
                                                    >
                                                        {{ legLoadCategoryMeta(leg.weight_ton, trip.max_load_ton).label }}
                                                    </span>
                                                    <span class="trip-route-weight-readonly">
                                                        {{ leg.weight_ton ? formatKm(leg.weight_ton) : '—' }}
                                                    </span>
                                                </div>
                                            </td>
                                            <td>
                                                {{
                                                    leg.expected_mileage_km_per_liter
                                                        ? `${formatKm(leg.expected_mileage_km_per_liter)} KM/Lt`
                                                        : '—'
                                                }}
                                            </td>
                                            <td>{{ leg.freight_rate ? formatMoney(leg.freight_rate) : '—' }}</td>
                                            <td>₹{{ formatMoney(leg.freight_amount) }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div v-if="routeLegs.length > 0" class="card-body p-0 border-top">
                            <div class="trip-detail-km-bar km-summary-bar">
                                <div>
                                    <div class="small text-muted">Full Load Stops</div>
                                    <strong class="text-success">{{ routeSummary.fullLoadStops }}</strong>
                                </div>
                                <div>
                                    <div class="small text-muted">Half Load Stops</div>
                                    <strong class="text-warning">{{ routeSummary.halfLoadStops }}</strong>
                                </div>
                                <div>
                                    <div class="small text-muted">Empty Stops</div>
                                    <strong class="text-secondary">{{ routeSummary.emptyStops }}</strong>
                                </div>
                                <div>
                                    <div class="small text-muted">Avg Expected Mileage</div>
                                    <strong>{{ routeSummary.expectedMileageDisplay }}</strong>
                                </div>
                                <div>
                                    <div class="small text-muted">Total KM</div>
                                    <strong>{{ formatKm(trip.total_distance_km) }}</strong>
                                </div>
                                <div>
                                    <div class="small text-muted">Total Freight</div>
                                    <strong>₹{{ formatMoney(trip.total_freight_amount) }}</strong>
                                </div>
                            </div>
                        </div>
                    </div>

                    <TripDieselPerformanceCard class="mt-3" :performance="dieselPerformance" />

                    <div class="card mt-3">
                        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                            <strong><i class="bi bi-receipt me-1"></i>Trip Expenses</strong>
                            <span class="badge bg-secondary">₹{{ formatMoney(trip.total_expense_amount) }}</span>
                        </div>
                        <div class="card-body">
                            <div v-if="expenses.length === 0" class="text-muted text-center py-4">
                                No expenses recorded.
                            </div>
                            <template v-else>
                                <div
                                    v-for="group in expenseGroups"
                                    :key="group.key"
                                    class="trip-detail-expense-group"
                                >
                                    <div class="trip-detail-expense-group__header">
                                        <span>{{ group.label }}</span>
                                        <strong>₹{{ formatMoney(group.total) }}</strong>
                                    </div>
                                    <div class="table-responsive">
                                        <table class="table table-sm table-hover align-middle mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Date</th>
                                                    <th>Type</th>
                                                    <th>Meter Reading(KM)</th>
                                                    <th>Liters</th>
                                                    <th>Rate</th>
                                                    <th>Amount</th>
                                                    <th>Notes</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr v-for="expense in group.items" :key="expense.id">
                                                    <td>{{ formatDate(expense.expense_date) }}</td>
                                                    <td>{{ expenseTypeLabel(expense.expense_type, expense.other_type) }}</td>
                                                    <td>{{ formatOdometerReading(expense.fuel_odometer_km) }}</td>
                                                    <td>{{ expense.fuel_liters ?? '—' }}</td>
                                                    <td>{{ expense.fuel_rate ?? '—' }}</td>
                                                    <td>₹{{ formatMoney(expense.amount) }}</td>
                                                    <td class="text-muted">{{ expense.notes || '—' }}</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>

                    <div v-if="statusHistories.length > 0" class="card mt-3">
                        <div class="card-header"><strong>Status History</strong></div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0 small">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Changed At</th>
                                            <th>From</th>
                                            <th>To</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="history in statusHistories" :key="history.id">
                                            <td>{{ formatDateTime(history.changed_at) }}</td>
                                            <td>
                                                <span class="badge" :class="tripStatusMeta(history.from_status).badge">
                                                    {{ tripStatusMeta(history.from_status).label }}
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge" :class="tripStatusMeta(history.to_status).badge">
                                                    {{ tripStatusMeta(history.to_status).label }}
                                                </span>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header"><strong>Financial Summary</strong></div>
                        <div class="card-body small">
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Freight</span>
                                <span>₹{{ formatMoney(trip.total_freight_amount) }}</span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Expenses</span>
                                <span>₹{{ formatMoney(trip.total_expense_amount) }}</span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Profit</span>
                                <strong :class="Number(trip.profit_amount) >= 0 ? 'text-success' : 'text-danger'">
                                    ₹{{ formatMoney(trip.profit_amount) }}
                                </strong>
                            </div>
                            <div v-if="profitMargin !== null" class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Profit Margin</span>
                                <span>{{ profitMargin }}%</span>
                            </div>
                            <hr class="my-2" />
                            <div class="d-flex justify-content-between">
                                <span class="text-muted">Total KM</span>
                                <strong>{{ formatKm(trip.total_distance_km) }}</strong>
                            </div>
                        </div>
                    </div>

                    <div class="card mt-3">
                        <div class="card-header"><strong>Record Info</strong></div>
                        <div class="card-body small">
                            <div class="mb-2">
                                <span class="text-muted">Created</span>
                                <div>{{ formatDateTime(trip.created_at) }}</div>
                            </div>
                            <div>
                                <span class="text-muted">Last Updated</span>
                                <div>{{ formatDateTime(trip.updated_at) }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </template>

        <div v-else-if="!needsCompanySelection" class="alert alert-danger">
            Trip not found or you do not have access.
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { RouterLink, useRoute } from 'vue-router';
import {
    canFullEditTrip,
    canManageTripExpenses,
    expenseTypeLabel,
    fetchTrip,
    isTerminalTripStatus,
    resolveTripEditAccess,
    summarizeTripForm,
    legLoadCategoryMeta,
    tripStatusMeta,
    tripTypeLabel,
} from '../../api/trips';
import TripInlineStatusActions from '../../components/trips/TripInlineStatusActions.vue';
import TripDieselPerformanceCard from '../../components/trips/TripDieselPerformanceCard.vue';
import AppButton from '../../components/ui/AppButton.vue';
import { usePermissions } from '../../composables/usePermissions';
import { useTripListReturn } from '../../composables/useTripListReturn';
import { useRequireCompanyContext } from '../../composables/useRequireCompanyContext';
import { useAppToast } from '../../composables/useAppToast';
import { dateOnlyFromDatetimeLocal, formatDate, formatDateTime } from '../../utils/datetime';

const route = useRoute();
const toast = useAppToast();
const { canInCompany, user } = usePermissions();
const { needsCompanySelection, guardCompanyContext, handleCompanyContextError } =
    useRequireCompanyContext();
const { tripListReturnTo } = useTripListReturn();

const canManage = computed(() => canInCompany('trips.manage'));

const trip = ref(null);
const loading = ref(true);

const routeLegs = computed(() => trip.value?.route_legs ?? []);
const routeSummary = computed(() => {
    const summary = summarizeTripForm({
        route_legs: routeLegs.value,
        planned_mileage_km_per_liter: trip.value?.planned_mileage_km_per_liter,
        max_load_ton: trip.value?.max_load_ton,
        start_meter_reading_km: trip.value?.start_meter_reading_km,
        end_meter_reading_km: trip.value?.end_meter_reading_km,
        expenses: [],
    });

    return {
        ...summary,
        expectedMileageDisplay:
            summary.expectedMileage == null
                ? '—'
                : `${Number(summary.expectedMileage).toLocaleString('en-IN', { maximumFractionDigits: 2 })} KM/Ltr`,
    };
});
const dieselPerformance = computed(() => trip.value?.diesel_performance ?? null);
const expenses = computed(() => trip.value?.expenses ?? []);
const statusHistories = computed(() => trip.value?.status_histories ?? []);
const isTerminal = computed(() => isTerminalTripStatus(trip.value?.status));
const editAccess = computed(() => resolveTripEditAccess(trip.value?.status, user.value));
const canEditTripPage = computed(() => canFullEditTrip(trip.value?.status, user.value));
const canManageTripExpensesFor = computed(() => canManageTripExpenses(trip.value?.status, user.value));
const canEditTripStatus = computed(() => editAccess.value.fullEdit);
const terminalAlert = computed(() => {
    if (!isTerminal.value) {
        return '';
    }

    if (editAccess.value.fullEdit) {
        return '';
    }

    if (editAccess.value.expensesOnly) {
        return 'This trip is completed. Trip details are read-only; use Manage Expenses to update expenses.';
    }

    const label = trip.value?.status === 'COMPLETED' ? 'completed' : 'cancelled';

    return `This trip is ${label} and is read-only.`;
});
const statusMeta = computed(() => tripStatusMeta(trip.value?.status));

const routeSectionTitle = computed(() =>
    trip.value?.trip_type === 'ONE_WAY' ? 'One Way Route' : 'Round Trip Route',
);

const startStationLabel = computed(() => {
    const station = trip.value?.start_station;

    if (station?.label) {
        return station.label;
    }

    if (station?.name) {
        return station.name;
    }

    const firstLeg = routeLegs.value[0];

    return firstLeg?.from_city || firstLeg?.from_location || '—';
});

const routePreview = computed(() => {
    if (!startStationLabel.value || startStationLabel.value === '—') {
        return '';
    }

    const points = [startStationLabel.value];

    routeLegs.value.forEach((leg) => {
        const label = leg.to_city || leg.to_location;

        if (label) {
            points.push(label);
        }
    });

    return points.length > 1 ? points.join(' → ') : '';
});

const headerMetaText = computed(() => {
    const parts = [];

    if (trip.value?.vehicle?.registration_number) {
        parts.push(trip.value.vehicle.registration_number);
    }

    if (trip.value?.driver?.name) {
        parts.push(trip.value.driver.name);
    }

    if (trip.value?.start_at) {
        parts.push(formatDateTime(trip.value.start_at));
    }

    return parts.join(' · ') || '—';
});

const vehicleMeta = computed(() => {
    const vehicle = trip.value?.vehicle;

    if (!vehicle) {
        return '';
    }

    const parts = [vehicle.make, vehicle.model].filter(Boolean);

    return parts.join(' / ');
});

const arrivalTiming = computed(() => {
    const endDate = dateOnlyFromDatetimeLocal(trip.value?.end_at);
    const arrivalDate = dateOnlyFromDatetimeLocal(trip.value?.actual_arrival_at);

    if (!endDate || !arrivalDate) {
        return null;
    }

    if (arrivalDate < endDate) {
        return 'early';
    }

    if (arrivalDate > endDate) {
        return 'late';
    }

    return 'on-time';
});

const tripDuration = computed(() => {
    const start = dateOnlyFromDatetimeLocal(trip.value?.start_at);
    const end = dateOnlyFromDatetimeLocal(trip.value?.end_at || trip.value?.actual_arrival_at);

    if (!start || !end) {
        return '';
    }

    const startMs = Date.parse(start);
    const endMs = Date.parse(end);

    if (!Number.isFinite(startMs) || !Number.isFinite(endMs) || endMs < startMs) {
        return '';
    }

    const days = Math.round((endMs - startMs) / (1000 * 60 * 60 * 24)) + 1;

    return `${days} day${days === 1 ? '' : 's'}`;
});

const profitMargin = computed(() => {
    const freight = Number(trip.value?.total_freight_amount ?? 0);

    if (freight <= 0) {
        return null;
    }

    const profit = Number(trip.value?.profit_amount ?? 0);

    return ((profit / freight) * 100).toFixed(1);
});

const expenseGroups = computed(() => {
    const groups = [];
    const generalItems = expenses.value.filter(
        (expense) => expense.route_leg_sequence === '' || expense.route_leg_sequence == null,
    );

    if (generalItems.length > 0) {
        groups.push({
            key: 'general',
            label: 'Whole trip (general)',
            items: generalItems,
            total: sumExpenses(generalItems),
        });
    }

    routeLegs.value.forEach((leg, index) => {
        const sequence = leg.sequence_no ?? index + 1;
        const items = expenses.value.filter(
            (expense) => Number(expense.route_leg_sequence) === Number(sequence),
        );

        if (items.length === 0) {
            return;
        }

        const from = leg.from_city || leg.from_location || '—';
        const to = leg.to_city || leg.to_location || '—';

        groups.push({
            key: `stop-${sequence}`,
            label: `Stop ${sequence}: ${from} → ${to}`,
            items,
            total: sumExpenses(items),
        });
    });

    const unmatched = expenses.value.filter((expense) => {
        const seq = expense.route_leg_sequence;

        if (seq === '' || seq == null) {
            return generalItems.length === 0;
        }

        return !routeLegs.value.some(
            (leg, index) => Number(leg.sequence_no ?? index + 1) === Number(seq),
        );
    });

    if (unmatched.length > 0 && groups.length === 0) {
        groups.push({
            key: 'all',
            label: 'All expenses',
            items: unmatched,
            total: sumExpenses(unmatched),
        });
    }

    return groups;
});

function sumExpenses(items) {
    return items.reduce((sum, expense) => sum + Number(expense.amount ?? 0), 0);
}

function formatMoney(value) {
    return Number(value ?? 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function formatKm(value) {
    return Number(value ?? 0).toLocaleString('en-IN', { maximumFractionDigits: 2 });
}

function formatOdometerReading(value) {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    const reading = Number(value);

    if (Number.isNaN(reading)) {
        return '—';
    }

    return Number.isInteger(reading) ? String(reading) : reading.toFixed(2).replace(/\.?0+$/, '');
}

function onTripUpdated(updated) {
    trip.value = updated;
}

async function loadTrip() {
    const tripId = route.params.id;

    if (needsCompanySelection.value || !tripId) {
        trip.value = null;
        loading.value = false;

        return;
    }

    loading.value = true;

    try {
        trip.value = await fetchTrip(tripId);
    } catch (error) {
        trip.value = null;

        if (!handleCompanyContextError(error, 'Could not load trip details.')) {
            toast.error('Could not load trip details.');
        }
    } finally {
        loading.value = false;
    }
}

onMounted(async () => {
    const ok = await guardCompanyContext();

    if (ok === true) {
        await loadTrip();
    } else {
        loading.value = false;
    }
});

watch(() => route.params.id, () => {
    loadTrip();
});
</script>

<style scoped>
.trip-detail-km-bar {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem 1.5rem;
    padding: 0.85rem 1rem;
    background: #f8fafc;
}

.trip-detail-expense-group + .trip-detail-expense-group {
    margin-top: 1rem;
    padding-top: 1rem;
    border-top: 1px solid #e5e7eb;
}

.trip-detail-expense-group__header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 0.5rem;
    font-size: 0.875rem;
    font-weight: 600;
}
</style>
