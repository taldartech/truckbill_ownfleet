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
            <div class="card-body trip-form-fields">
                <div class="form-grid-2 mb-3">
                    <div>
                        <label class="form-label">Trip Number</label>
                        <input :value="tripNumberDisplay" type="text" class="form-control bg-light" readonly />
                    </div>
                    <div>
                        <label class="form-label">Status</label>
                        <template v-if="mode === 'create'">
                            <input type="text" class="form-control bg-light" value="Created" readonly />
                            <div class="mt-1">
                                <span class="badge bg-info text-dark">Created</span>
                            </div>
                        </template>
                        <template v-else>
                            <div class="d-flex flex-wrap align-items-center gap-2 status-actions">
                                <span class="badge" :class="currentStatusBadge.badge">{{ currentStatusBadge.label }}</span>
                                <template v-if="!readonly && !statusLocked && allowedNextStatuses.length">
                                    <button
                                        v-for="next in allowedNextStatuses"
                                        :key="next.value"
                                        type="button"
                                        class="btn btn-sm btn-outline-primary"
                                        @click="onStatusChange(next.value)"
                                    >
                                        Mark {{ next.label }}
                                    </button>
                                </template>
                            </div>
                        </template>
                    </div>
                </div>
                <div class="form-grid-2 mb-3">
                    <div>
                        <label class="form-label">Truck *</label>
                        <template v-if="mode === 'create'">
                            <AsyncVehicleTripPicker
                                v-model="form.vehicle_id"
                                :as-of-date="tripStartDate"
                                :trip-end-date="tripEndDate"
                                :driver-label="primaryDriverName"
                                :invalid="Boolean(fieldErrors.vehicle_id)"
                                :error-message="fieldErrorMessage('vehicle_id')"
                                @vehicle-selected="$emit('vehicle-selected', $event)"
                            />
                        </template>
                        <template v-else>
                            <input
                                :value="snapshotVehicleLabel"
                                type="text"
                                class="form-control bg-light"
                                readonly
                            />
                        </template>
                    </div>
                    <div>
                        <label class="form-label">
                            Primary Driver
                            <span v-if="loadingDriver" class="text-muted small">(loading…)</span>
                        </label>
                        <input
                            :value="driverDisplayName"
                            type="text"
                            class="form-control bg-light"
                            :class="{ 'is-invalid': fieldErrors.driver_id && mode !== 'edit' }"
                            readonly
                        />
                        <div v-if="fieldErrors.driver_id && mode !== 'edit'" class="invalid-feedback d-block">
                            {{ fieldErrorMessage('driver_id') }}
                        </div>
                        <div v-else-if="mode === 'edit'" class="form-text">
                            Locked on this trip — saved when the trip was created.
                        </div>
                    </div>
                </div>
                <div class="form-grid-2 mb-3">
                    <div>
                        <label class="form-label">Customer</label>
                        <SearchablePicker
                            v-if="!readonly"
                            v-model="form.customer_id"
                            :items="customers"
                            placeholder="Select customer"
                            search-placeholder="Search customer…"
                            empty-message="No customer found."
                            view-title="Customer details"
                            quick-add-title="Quick add customer"
                            :invalid="Boolean(fieldErrors.customer_id)"
                            :error-message="fieldErrorMessage('customer_id')"
                            :can-quick-add="canQuickAddCustomer"
                            @quick-add="showCustomerQuickAdd = true"
                        />
                        <input
                            v-else
                            :value="partyLabel(customers, form.customer_id, snapshotCustomerName)"
                            type="text"
                            class="form-control form-input bg-light"
                            readonly
                        />
                    </div>
                    <div>
                        <label class="form-label">Broker</label>
                        <SearchablePicker
                            v-if="!readonly"
                            v-model="form.broker_id"
                            :items="brokers"
                            placeholder="Select broker"
                            search-placeholder="Search broker…"
                            empty-message="No broker found."
                            view-title="Broker details"
                            quick-add-title="Quick add broker"
                            :invalid="Boolean(fieldErrors.broker_id)"
                            :error-message="fieldErrorMessage('broker_id')"
                            :can-quick-add="canQuickAddBroker"
                            @quick-add="showBrokerQuickAdd = true"
                        />
                        <input
                            v-else
                            :value="partyLabel(brokers, form.broker_id, snapshotBrokerName)"
                            type="text"
                            class="form-control form-input bg-light"
                            readonly
                        />
                    </div>
                </div>
                <div class="form-grid-2 mb-3">
                    <div>
                        <label class="form-label">Start Date *</label>
                        <input
                            v-model="form.start_at"
                            type="date"
                            class="form-control"
                            :class="{ 'is-invalid': fieldErrors.start_at }"
                            :readonly="readonly"
                        />
                        <div v-if="fieldErrors.start_at" class="invalid-feedback d-block">
                            {{ fieldErrors.start_at[0] }}
                        </div>
                    </div>
                    <div>
                        <label class="form-label">End Date</label>
                        <input
                            v-model="form.end_at"
                            type="date"
                            class="form-control"
                            :class="{ 'is-invalid': fieldErrors.end_at }"
                            :readonly="readonly"
                        />
                        <div v-if="fieldErrors.end_at" class="invalid-feedback d-block">
                            {{ fieldErrors.end_at[0] }}
                        </div>
                    </div>
                </div>
                <div v-if="form.status === 'CANCELLED'" class="mb-3">
                    <div class="alert alert-danger mb-0 py-2">
                        <div class="small">
                            <strong>Cancelled:</strong> {{ formatDisplayDate(form.cancelled_at) }}
                        </div>
                        <div v-if="form.cancellation_reason" class="small mt-1">
                            <strong>Reason:</strong> {{ form.cancellation_reason }}
                        </div>
                    </div>
                </div>
                <div v-if="form.status === 'COMPLETED' && form.actual_arrival_at" class="mb-3">
                    <label class="form-label">Actual Arrival Date</label>
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
                <div v-if="!readonly" class="d-flex flex-wrap gap-2">
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
                            v-if="!readonly"
                            v-model="form.start_station_id"
                            :items="stations"
                            placeholder="Select start station"
                            :invalid="Boolean(fieldErrors.start_station_id)"
                            :error-message="fieldErrorMessage('start_station_id')"
                            :can-quick-add="canQuickAddStation"
                            @update:model-value="$emit('start-station-change')"
                            @quick-add="showStationQuickAdd = true"
                        />
                        <input
                            v-else
                            :value="stationLabel(form.start_station_id)"
                            type="text"
                            class="form-control bg-light"
                            readonly
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

                <div class="table-wrap" v-else>
                    <table class="table table-bordered align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>From</th>
                                <th>To Station *</th>
                                <th>Load Type</th>
                                <th>KM *</th>
                                <th>Freight ₹</th>
                                <th v-if="!readonly" style="width: 50px"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(leg, index) in form.route_legs" :key="index">
                                <td>{{ index + 1 }}</td>
                                <td>{{ stationLabel(leg.from_station_id) }}</td>
                                <td>
                                    <SearchablePicker
                                        v-if="!readonly"
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
                                    <span v-else>{{ stationLabel(leg.to_station_id) }}</span>
                                </td>
                                <td>
                                    <select
                                        v-if="!readonly"
                                        v-model="leg.route_type"
                                        class="form-select form-select-sm"
                                    >
                                        <option v-for="t in routeTypes" :key="t.value" :value="t.value">
                                            {{ t.label }}
                                        </option>
                                    </select>
                                    <span v-else>{{ routeTypeMeta(leg.route_type).label }}</span>
                                </td>
                                <td>
                                    <input
                                        v-if="!readonly"
                                        v-model.number="leg.distance_km"
                                        v-no-scroll-change
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        class="form-control form-control-sm"
                                        :class="{ 'is-invalid': legFieldError(index, 'distance_km') }"
                                        @input="onLegDistanceInput(index)"
                                    />
                                    <span v-else>{{ formatNum(leg.distance_km) }}</span>
                                    <div v-if="legFieldError(index, 'distance_km')" class="invalid-feedback d-block">
                                        {{ legFieldError(index, 'distance_km') }}
                                    </div>
                                </td>
                                <td>
                                    <input
                                        v-if="!readonly"
                                        v-model.number="leg.freight_amount"
                                        v-no-scroll-change
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        class="form-control form-control-sm"
                                        :disabled="leg.route_type === 'EMPTY'"
                                    />
                                    <span v-else>₹{{ formatMoney(leg.freight_amount) }}</span>
                                </td>
                                <td v-if="!readonly">
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
            <div class="card-body p-0">
                <div class="km-summary-bar">
                    <div>
                        <div class="small text-muted">Full Load KM</div>
                        <strong class="text-success">{{ formatNum(summary.loadedKm) }}</strong>
                    </div>
                    <div>
                        <div class="small text-muted">Half Load KM</div>
                        <strong class="text-warning">{{ formatNum(summary.halfLoadedKm) }}</strong>
                    </div>
                    <div>
                        <div class="small text-muted">Empty KM</div>
                        <strong class="text-secondary">{{ formatNum(summary.emptyKm) }}</strong>
                    </div>
                    <div>
                        <div class="small text-muted">Total KM</div>
                        <strong>{{ formatNum(summary.totalKm) }}</strong>
                    </div>
                    <div>
                        <div class="small text-muted">Freight</div>
                        <strong>₹{{ formatMoney(summary.freightTotal) }}</strong>
                    </div>
                    <div>
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

        <div class="card mb-4 trip-expenses-card">
            <div class="card-header trip-expenses-card__header text-white">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <div>
                        <h3 class="card-title mb-0">
                            <i class="bi bi-receipt me-2"></i>Trip Expenses
                        </h3>
                        
                    </div>
                    <div class="trip-expenses-card__live-total">
                        Live total: ₹{{ formatMoney(summary.expenseTotal) }}
                    </div>
                </div>
            </div>
            <div class="card-body trip-expenses-card__body">
                <TripExpensesPanel
                    :expenses="form.expenses"
                    :route-legs="form.route_legs"
                    :stations="stations"
                    :readonly="readonly"
                    @add-expense="$emit('add-expense', $event)"
                    @remove-expense="$emit('remove-expense', $event)"
                />
            </div>
        </div>

        <StationQuickAddModal
            :show="showStationQuickAdd"
            @close="showStationQuickAdd = false"
            @created="onStationCreated"
        />

        <BrokerQuickAddModal
            :show="showBrokerQuickAdd"
            @close="showBrokerQuickAdd = false"
            @created="onBrokerCreated"
        />

        <CustomerQuickAddModal
            :show="showCustomerQuickAdd"
            @close="showCustomerQuickAdd = false"
            @created="onCustomerCreated"
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
    ROUTE_TYPES,
    routeTypeMeta,
    summarizeTripForm,
    tripStatusMeta,
} from '../../api/trips';
import { dateOnlyFromDatetimeLocal } from '../../utils/datetime';
import SearchablePicker from '../ui/SearchablePicker.vue';
import AsyncVehicleTripPicker from './AsyncVehicleTripPicker.vue';
import TripExpensesPanel from './TripExpensesPanel.vue';
import BrokerQuickAddModal from './BrokerQuickAddModal.vue';
import CustomerQuickAddModal from './CustomerQuickAddModal.vue';
import StationQuickAddModal from './StationQuickAddModal.vue';
import TripStatusChangeModal from './TripStatusChangeModal.vue';

const props = defineProps({
    form: { type: Object, required: true },
    mode: { type: String, default: 'create' },
    vehicles: { type: Array, default: () => [] },
    drivers: { type: Array, default: () => [] },
    snapshotVehicleLabel: { type: String, default: '' },
    snapshotDriverName: { type: String, default: '' },
    stations: { type: Array, default: () => [] },
    brokers: { type: Array, default: () => [] },
    customers: { type: Array, default: () => [] },
    snapshotCustomerName: { type: String, default: '' },
    snapshotBrokerName: { type: String, default: '' },
    formError: { type: String, default: '' },
    fieldErrors: { type: Object, default: () => ({}) },
    tripNumberDisplay: { type: String, default: '' },
    driverWarning: { type: String, default: '' },
    loadingDriver: { type: Boolean, default: false },
    primaryDriverName: { type: String, default: '' },
    statusLocked: { type: Boolean, default: false },
    readonly: { type: Boolean, default: false },
    canQuickAddStation: { type: Boolean, default: false },
    canQuickAddBroker: { type: Boolean, default: false },
    canQuickAddCustomer: { type: Boolean, default: false },
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
    'broker-created',
    'customer-created',
    'vehicle-selected',
    'status-updated',
    'clear-field-error',
]);

const showStationQuickAdd = ref(false);
const showBrokerQuickAdd = ref(false);
const showCustomerQuickAdd = ref(false);
const quickAddLegIndex = ref(null);
const showStatusModal = ref(false);
const pendingStatus = ref('');

const summary = computed(() => summarizeTripForm(props.form));

const tripStartDate = computed(() => dateOnlyFromDatetimeLocal(props.form.start_at));
const tripEndDate = computed(() => dateOnlyFromDatetimeLocal(props.form.end_at));

const currentStatusBadge = computed(() => tripStatusMeta(props.form.status));

const driverDisplayName = computed(() => {
    if (props.mode === 'edit') {
        return props.snapshotDriverName || '—';
    }

    return props.primaryDriverName || (props.loadingDriver ? 'Loading…' : '—');
});

const allowedNextStatuses = computed(() => {
    const current = String(props.form.status ?? '').toUpperCase();

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
});

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

function stationLabel(stationId) {
    if (!stationId) {
        return '—';
    }

    const match = props.stations.find((s) => String(s.id) === String(stationId));

    return match?.name ?? `#${stationId}`;
}

function partyLabel(items, entityId, fallbackName = '') {
    if (!entityId) {
        return fallbackName || '—';
    }

    const match = items.find((item) => String(item.id) === String(entityId));

    return match?.name ?? fallbackName ?? `#${entityId}`;
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

function onLegDistanceInput(index) {
    emit('clear-field-error', `route_legs.${index}.distance_km`);
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

    const dateOnly = String(value).slice(0, 10);
    const parts = dateOnly.split('-');

    if (parts.length === 3) {
        return `${parts[2]}/${parts[1]}/${parts[0]}`;
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return date.toLocaleDateString('en-IN');
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
    emit('status-updated', { status: newStatus });
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

    emit('status-updated', {
        status: props.form.status,
        ...data,
    });
}

function openQuickAddForLeg(index) {
    quickAddLegIndex.value = index;
    showStationQuickAdd.value = true;
}

function onStationCreated(station) {
    emit('station-created', { station, legIndex: quickAddLegIndex.value });
    quickAddLegIndex.value = null;
}

function onBrokerCreated(broker) {
    emit('broker-created', broker);
}

function onCustomerCreated(customer) {
    emit('customer-created', customer);
}

const routeTypes = ROUTE_TYPES;
</script>

<style scoped>
.trip-expenses-card__header {
    background: var(--brand-primary) !important;
    padding: 14px 18px;
}
.trip-expenses-card__subtitle {
    font-size: 12px;
    opacity: 0.8;
    margin-top: 4px;
}
.trip-expenses-card__live-total {
    font-size: 14px;
    font-weight: 600;
    background: rgba(0, 0, 0, 0.18);
    border-radius: 99px;
    padding: 8px 14px;
    white-space: nowrap;
}
.trip-expenses-card__body {
    padding: 16px;
    background: #f9fafb;
}
</style>
