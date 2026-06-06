<template>
    <div class="trip-form">
        <div v-if="formError" class="alert alert-danger">
            <strong>{{ formError }}</strong>
            <ul v-if="validationMessages.length > 0" class="mb-0 mt-2 small">
                <li v-for="(message, idx) in validationMessages" :key="idx">{{ message }}</li>
            </ul>
        </div>
        <div v-if="driverWarning" class="alert alert-warning">{{ driverWarning }}</div>

        <div class="card mb-4">
            <div class="card-header bg-success text-white">
                <h3 class="card-title mb-0"><i class="bi bi-signpost-split me-1"></i> Trip Details</h3>
            </div>
            <div class="card-body row g-3">
                <div class="col-md-3">
                    <label class="form-label">Trip Number</label>
                    <input :value="tripNumberDisplay" type="text" class="form-control bg-light" readonly />
                </div>
                <div class="col-md-3">
                    <label class="form-label">Vehicle *</label>
                    <select
                        v-model="form.vehicle_id"
                        class="form-select"
                        :class="{ 'is-invalid': fieldErrors.vehicle_id }"
                    >
                        <option value="">Select vehicle</option>
                        <option v-for="v in vehicles" :key="v.id" :value="v.id">{{ v.registration_number }}</option>
                    </select>
                    <div v-if="fieldErrors.vehicle_id" class="invalid-feedback d-block">
                        {{ fieldErrors.vehicle_id[0] }}
                    </div>
                </div>
                <div class="col-md-3">
                    <label class="form-label">
                        Driver *
                        <span v-if="loadingDriver" class="text-muted small">(loading…)</span>
                    </label>
                    <select
                        v-model="form.driver_id"
                        class="form-select"
                        :class="{ 'is-invalid': fieldErrors.driver_id }"
                    >
                        <option value="">Select driver</option>
                        <option v-for="d in drivers" :key="d.id" :value="d.id">{{ d.name }}</option>
                    </select>
                    <div v-if="fieldErrors.driver_id" class="invalid-feedback d-block">
                        {{ fieldErrors.driver_id[0] }}
                    </div>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select
                        :value="form.status"
                        class="form-select"
                        :disabled="statusLocked"
                        @change="onStatusChange($event.target.value)"
                    >
                        <option v-for="s in tripStatuses" :key="s.value" :value="s.value">{{ s.label }}</option>
                    </select>
                    <div class="mt-1">
                        <span class="badge" :class="currentStatusBadge.badge">{{ currentStatusBadge.label }}</span>
                    </div>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Start Date *</label>
                    <input
                        v-model="form.start_at"
                        type="datetime-local"
                        class="form-control"
                        :class="{ 'is-invalid': fieldErrors.start_at }"
                    />
                </div>
                <div class="col-md-3">
                    <label class="form-label">End Date</label>
                    <input
                        v-model="form.end_at"
                        type="datetime-local"
                        class="form-control"
                        :class="{ 'is-invalid': fieldErrors.end_at }"
                    />
                    <div v-if="fieldErrors.end_at" class="invalid-feedback d-block">
                        {{ fieldErrors.end_at[0] }}
                    </div>
                </div>
                <div v-if="form.status === 'CANCELLED'" class="col-md-6">
                    <div class="alert alert-danger mb-0 py-2">
                        <div class="small">
                            <strong>Cancelled:</strong> {{ formatDisplayDate(form.cancelled_at) }}
                        </div>
                        <div v-if="form.cancellation_reason" class="small mt-1">
                            <strong>Reason:</strong> {{ form.cancellation_reason }}
                        </div>
                    </div>
                </div>
                <div v-if="form.status === 'COMPLETED' && form.actual_arrival_at" class="col-md-3">
                    <label class="form-label">Actual Arrival</label>
                    <input
                        :value="formatDisplayDate(form.actual_arrival_at)"
                        type="text"
                        class="form-control bg-light"
                        readonly
                    />
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header bg-success text-white d-flex flex-wrap justify-content-between align-items-center gap-2">
                <h3 class="card-title mb-0"><i class="bi bi-arrow-repeat me-1"></i> Round Trip Route</h3>
                <div class="d-flex flex-wrap gap-2">
                    <button
                        type="button"
                        class="btn btn-sm btn-light"
                        :disabled="!form.start_station_id"
                        @click="$emit('add-stop')"
                    >
                        <i class="bi bi-plus"></i> Add Stop
                    </button>
                    <button
                        type="button"
                        class="btn btn-sm btn-outline-light"
                        :disabled="!canReturnToStart"
                        @click="$emit('return-to-start')"
                    >
                        <i class="bi bi-house"></i> Return to Start
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Start Station (Home Base) *</label>
                        <SearchablePicker
                            v-model="form.start_station_id"
                            :items="stations"
                            placeholder="Select start station"
                            :invalid="Boolean(fieldErrors.start_station_id)"
                            :error-message="fieldErrorMessage('start_station_id')"
                            :can-quick-add="canQuickAddStation"
                            @update:model-value="$emit('start-station-change')"
                            @quick-add="showStationQuickAdd = true"
                        />
                    </div>
                    <div class="col-md-6">
                        <div class="alert alert-light border mb-0 h-100">
                            <strong>Round trip flow:</strong>
                            Start → Stop B → Stop C → back to Start.
                            <div v-if="routePreview" class="small mt-1 text-muted">{{ routePreview }}</div>
                        </div>
                    </div>
                </div>

                <div v-if="routeLegsError" class="alert alert-danger py-2">{{ routeLegsError }}</div>

                <div v-if="form.route_legs.length === 0" class="text-muted">
                    Select start station, then click <strong>Add Stop</strong> to build the route.
                </div>

                <div class="table-responsive" v-else>
                    <table class="table table-bordered align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>From</th>
                                <th>To Station *</th>
                                <th>Load Type</th>
                                <th>KM *</th>
                                <th>Freight ₹</th>
                                <th style="width: 50px"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(leg, index) in form.route_legs" :key="index">
                                <td>{{ index + 1 }}</td>
                                <td>{{ stationLabel(leg.from_station_id) }}</td>
                                <td>
                                    <SearchablePicker
                                        v-model="leg.to_station_id"
                                        :items="stations"
                                        placeholder="Select station"
                                        attach-dropdown-to-body
                                        :invalid="Boolean(legFieldError(index, 'to_station_id'))"
                                        :error-message="legFieldError(index, 'to_station_id')"
                                        :can-quick-add="canQuickAddStation"
                                        @update:model-value="$emit('stop-change', index)"
                                        @quick-add="openQuickAddForLeg(index)"
                                    />
                                </td>
                                <td>
                                    <select v-model="leg.route_type" class="form-select form-select-sm">
                                        <option v-for="t in routeTypes" :key="t.value" :value="t.value">
                                            {{ t.label }}
                                        </option>
                                    </select>
                                </td>
                                <td>
                                    <input
                                        v-model.number="leg.distance_km"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        class="form-control form-control-sm"
                                        :class="{ 'is-invalid': legFieldError(index, 'distance_km') }"
                                    />
                                    <div v-if="legFieldError(index, 'distance_km')" class="invalid-feedback d-block">
                                        {{ legFieldError(index, 'distance_km') }}
                                    </div>
                                </td>
                                <td>
                                    <input
                                        v-model.number="leg.freight_amount"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        class="form-control form-control-sm"
                                        :disabled="leg.route_type === 'EMPTY'"
                                    />
                                </td>
                                <td>
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-outline-danger"
                                        @click="$emit('remove-route-leg', index)"
                                    >
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card mb-4 border-primary">
            <div class="card-body py-3">
                <div class="row g-3 text-center">
                    <div class="col-md-2 col-6">
                        <div class="small text-muted">Full Load KM</div>
                        <strong class="text-success">{{ formatNum(summary.loadedKm) }}</strong>
                    </div>
                    <div class="col-md-2 col-6">
                        <div class="small text-muted">Half Load KM</div>
                        <strong class="text-warning">{{ formatNum(summary.halfLoadedKm) }}</strong>
                    </div>
                    <div class="col-md-2 col-6">
                        <div class="small text-muted">Empty KM</div>
                        <strong class="text-secondary">{{ formatNum(summary.emptyKm) }}</strong>
                    </div>
                    <div class="col-md-2 col-6">
                        <div class="small text-muted">Total KM</div>
                        <strong>{{ formatNum(summary.totalKm) }}</strong>
                    </div>
                    <div class="col-md-2 col-6">
                        <div class="small text-muted">Freight</div>
                        <strong>₹{{ formatMoney(summary.freightTotal) }}</strong>
                    </div>
                    <div class="col-md-2 col-6">
                        <div class="small text-muted">Expenses</div>
                        <strong>₹{{ formatMoney(summary.expenseTotal) }}</strong>
                    </div>
                </div>
                <div class="text-center mt-2">
                    <span class="small text-muted me-2">Profit:</span>
                    <strong :class="summary.profit >= 0 ? 'text-success' : 'text-danger'">
                        ₹{{ formatMoney(summary.profit) }}
                    </strong>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header bg-success text-white d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div>
                    <h3 class="card-title mb-0"><i class="bi bi-cash-stack me-1"></i> Trip Expenses</h3>
                    <div v-if="form.expenses.length > 0" class="small opacity-75">
                        Total: ₹{{ formatMoney(summary.expenseTotal) }}
                    </div>
                </div>
                <button type="button" class="btn btn-sm btn-light" @click="$emit('add-expense')">
                    <i class="bi bi-plus"></i> Add Expense
                </button>
            </div>
            <div class="card-body">
                <p class="small text-muted mb-3">
                    <strong>Linked Stop</strong> ties an expense to a route stop (e.g. diesel at Stop 1, loading at Stop 2).
                    Use <strong>Whole trip</strong> for costs that apply to the entire trip.
                </p>

                <div v-if="form.expenses.length === 0" class="text-muted border rounded p-3 text-center bg-light">
                    No expenses yet. Click <strong>Add Expense</strong> for diesel, toll, loading, etc.
                </div>

                <div v-else class="table-responsive">
                    <table class="table table-bordered align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="min-width: 160px">Type</th>
                                <th style="width: 130px">Amount ₹</th>
                                <th style="min-width: 260px">
                                    Linked Stop
                                    <i
                                        class="bi bi-info-circle text-muted ms-1"
                                        title="Which route stop this expense belongs to"
                                    ></i>
                                </th>
                                <th>Notes</th>
                                <th style="width: 52px"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(expense, index) in form.expenses" :key="index">
                                <td>
                                    <select v-model="expense.expense_type" class="form-select form-select-sm">
                                        <option v-for="t in expenseTypes" :key="t.value" :value="t.value">
                                            {{ t.label }}
                                        </option>
                                    </select>
                                </td>
                                <td>
                                    <input
                                        v-model.number="expense.amount"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        class="form-control form-control-sm text-end"
                                        placeholder="0.00"
                                    />
                                </td>
                                <td>
                                    <select
                                        class="form-select form-select-sm"
                                        :value="expenseStopValue(expense)"
                                        :disabled="expenseStopOptions.length <= 1"
                                        @change="onExpenseStopChange(expense, $event.target.value)"
                                    >
                                        <option v-for="opt in expenseStopOptions" :key="opt.value" :value="opt.value">
                                            {{ opt.label }}
                                        </option>
                                    </select>
                                </td>
                                <td>
                                    <input
                                        v-model="expense.notes"
                                        type="text"
                                        class="form-control form-control-sm"
                                        placeholder="Optional note"
                                    />
                                </td>
                                <td class="text-center">
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-outline-danger"
                                        title="Remove expense"
                                        @click="$emit('remove-expense', index)"
                                    >
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <StationQuickAddModal
            :show="showStationQuickAdd"
            @close="showStationQuickAdd = false"
            @created="onStationCreated"
        />

        <TripStatusChangeModal
            :show="showStatusModal"
            :status="pendingStatus"
            :start-at="form.start_at"
            :end-at-value="form.end_at"
            :actual-arrival-at-value="form.actual_arrival_at"
            :cancelled-at-value="form.cancelled_at"
            :cancellation-reason-value="form.cancellation_reason"
            @cancel="onStatusModalCancel"
            @confirm="onStatusModalConfirm"
        />
    </div>
</template>

<script setup>
import { computed, ref } from 'vue';
import {
    EXPENSE_TYPES,
    ROUTE_TYPES,
    TRIP_STATUSES,
    routeTypeMeta,
    summarizeTripForm,
    tripStatusMeta,
} from '../../api/trips';
import SearchablePicker from '../ui/SearchablePicker.vue';
import StationQuickAddModal from './StationQuickAddModal.vue';
import TripStatusChangeModal from './TripStatusChangeModal.vue';

const props = defineProps({
    form: { type: Object, required: true },
    vehicles: { type: Array, default: () => [] },
    drivers: { type: Array, default: () => [] },
    stations: { type: Array, default: () => [] },
    formError: { type: String, default: '' },
    fieldErrors: { type: Object, default: () => ({}) },
    tripNumberDisplay: { type: String, default: '' },
    driverWarning: { type: String, default: '' },
    loadingDriver: { type: Boolean, default: false },
    statusLocked: { type: Boolean, default: false },
    canQuickAddStation: { type: Boolean, default: false },
});

const emit = defineEmits([
    'add-stop',
    'return-to-start',
    'remove-route-leg',
    'add-expense',
    'remove-expense',
    'start-station-change',
    'stop-change',
    'station-created',
]);

const showStationQuickAdd = ref(false);
const quickAddLegIndex = ref(null);
const showStatusModal = ref(false);
const pendingStatus = ref('');

const summary = computed(() => summarizeTripForm(props.form));

const currentStatusBadge = computed(() => tripStatusMeta(props.form.status));

const validationMessages = computed(() => {
    const messages = [];

    Object.values(props.fieldErrors).forEach((value) => {
        if (Array.isArray(value)) {
            value.forEach((message) => messages.push(message));
        } else if (typeof value === 'string' && value) {
            messages.push(value);
        }
    });

    return [...new Set(messages)].slice(0, 8);
});

const routeLegsError = computed(() => {
    const err = props.fieldErrors.route_legs;

    return Array.isArray(err) ? err[0] : err;
});

const canReturnToStart = computed(() => {
    if (!props.form.start_station_id || props.form.route_legs.length === 0) {
        return false;
    }

    const last = props.form.route_legs[props.form.route_legs.length - 1];

    return String(last.to_station_id) !== String(props.form.start_station_id);
});

const routePreview = computed(() => {
    if (!props.form.start_station_id) {
        return '';
    }

    const parts = [stationLabel(props.form.start_station_id)];

    props.form.route_legs.forEach((leg) => {
        if (leg.to_station_id) {
            parts.push(stationLabel(leg.to_station_id));
        }
    });

    return parts.join(' → ');
});

const expenseStopOptions = computed(() => {
    const options = [{ value: '', label: 'Whole trip (general expense)' }];

    props.form.route_legs.forEach((leg, index) => {
        if (!leg.to_station_id) {
            return;
        }

        const from = stationLabel(leg.from_station_id);
        const to = stationLabel(leg.to_station_id);
        const loadType = routeTypeMeta(leg.route_type).label;

        options.push({
            value: String(index + 1),
            label: `Stop ${index + 1}: ${from} → ${to} (${loadType})`,
        });
    });

    return options;
});

function expenseStopValue(expense) {
    if (expense.route_leg_sequence === '' || expense.route_leg_sequence == null) {
        return '';
    }

    return String(expense.route_leg_sequence);
}

function onExpenseStopChange(expense, value) {
    expense.route_leg_sequence = value === '' ? '' : Number(value);
}

function stationLabel(stationId) {
    if (!stationId) {
        return '—';
    }

    const match = props.stations.find((s) => String(s.id) === String(stationId));

    return match?.name ?? `#${stationId}`;
}

function fieldErrorMessage(field) {
    const err = props.fieldErrors[field];

    return Array.isArray(err) ? err[0] : err;
}

function legFieldError(index, field) {
    const key = `route_legs.${index}.${field}`;
    const errors = props.fieldErrors[key];

    return Array.isArray(errors) ? errors[0] : errors;
}

function formatNum(value) {
    return Number(value ?? 0).toLocaleString('en-IN', { maximumFractionDigits: 2 });
}

function formatMoney(value) {
    return Number(value ?? 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function formatDisplayDate(value) {
    if (!value) {
        return '—';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return date.toLocaleString('en-IN', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function onStatusChange(newStatus) {
    if (newStatus === props.form.status || props.statusLocked) {
        return;
    }

    if (newStatus === 'CANCELLED' || newStatus === 'COMPLETED') {
        pendingStatus.value = newStatus;
        showStatusModal.value = true;

        return;
    }

    props.form.status = newStatus;
}

function onStatusModalCancel() {
    showStatusModal.value = false;
    pendingStatus.value = '';
}

function onStatusModalConfirm(data) {
    props.form.status = pendingStatus.value;

    if (pendingStatus.value === 'CANCELLED') {
        props.form.cancelled_at = data.cancelled_at;
        props.form.cancellation_reason = data.cancellation_reason;
    } else if (pendingStatus.value === 'COMPLETED') {
        props.form.end_at = data.end_at;
        props.form.actual_arrival_at = data.actual_arrival_at;
    }

    showStatusModal.value = false;
    pendingStatus.value = '';
}

function openQuickAddForLeg(index) {
    quickAddLegIndex.value = index;
    showStationQuickAdd.value = true;
}

function onStationCreated(station) {
    emit('station-created', { station, legIndex: quickAddLegIndex.value });
    quickAddLegIndex.value = null;
}

const routeTypes = ROUTE_TYPES;
const tripStatuses = TRIP_STATUSES;
const expenseTypes = EXPENSE_TYPES;
</script>
