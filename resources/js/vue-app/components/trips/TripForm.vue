<template>
    <div class="trip-form">
        <div v-if="formError" class="alert alert-danger">
            <strong>{{ formError }}</strong>
            <ul v-if="validationMessages.length > 0" class="mb-0 mt-2 small">
                <li v-for="(message, idx) in validationMessages" :key="idx">{{ message }}</li>
            </ul>
        </div>
        <div v-if="driverWarning" class="alert alert-warning">{{ driverWarning }}</div>

        <div class="card mb-4 trip-details-card">
            <div class="card-header trip-details-card__header bg-success text-white">
                <div class="trip-details-card__row trip-details-card__row--main">
                    <h3 class="card-title mb-0 trip-details-card__title">
                        <span>{{ tripDetailsHeaderTitle }}</span>
                        <template v-if="mode === 'edit'">
                            <span v-if="tripNumberDisplay" class="trip-details-card__sep">{{ tripNumberDisplay }}</span>
                            <span v-if="snapshotVehicleLabel" class="trip-details-card__sep">{{ snapshotVehicleLabel }}</span>
                            <span class="trip-details-card__sep">
                                <span class="badge" :class="currentStatusBadge.badge">{{ currentStatusBadge.label }}</span>
                            </span>
                        </template>
                    </h3>
                    <div
                        v-if="showHeaderActions"
                        class="trip-details-card__actions"
                    >
                        <template v-if="mode === 'edit' && !readonly && !statusLocked">
                            <button
                                v-for="next in allowedNextStatuses"
                                :key="next.value"
                                type="button"
                                class="btn btn-sm trip-details-action-btn"
                                :class="statusActionButtonClass(next.value)"
                                @click="onStatusChange(next.value)"
                            >
                                {{ next.label }}
                            </button>
                        </template>
                        <slot name="trip-details-actions" />
                    </div>
                </div>
            </div>
            <div class="card-body trip-form-fields">
                <div class="trip-details-grid mb-3">
                    <div>
                        <label class="form-label trip-number-label">
                            <span>Trip Number</span>
                            <span class="badge" :class="mode === 'create' ? 'bg-info text-dark' : currentStatusBadge.badge">
                                {{ mode === 'create' ? 'Created' : currentStatusBadge.label }}
                            </span>
                        </label>
                        <input :value="tripNumberDisplay" type="text" class="form-control bg-light" readonly />
                    </div>
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
                        <div class="driver-field-header">
                            <label class="form-label mb-0">
                                Primary Driver
                                <span v-if="loadingDriver" class="text-muted small">(loading…)</span>
                            </label>
                            <div
                                v-if="!readonly && hasPrimaryDriver"
                                class="form-check form-switch substitute-toggle mb-0"
                            >
                                <input
                                    id="use-substitute-driver"
                                    v-model="showSubstituteDriver"
                                    class="form-check-input"
                                    type="checkbox"
                                    role="switch"
                                />
                                <label class="form-check-label" for="use-substitute-driver">
                                    Substitute
                                </label>
                            </div>
                        </div>
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
                    </div>
                    <div v-if="showSubstituteDriverColumn" class="trip-details-grid__substitute">
                        <label class="form-label">Substitute Driver</label>
                        <SearchablePicker
                            v-if="!readonly"
                            v-model="form.substitute_driver_id"
                            :items="substituteDriverOptions"
                            placeholder="Select substitute driver"
                            search-placeholder="Search driver…"
                            empty-message="No driver found."
                            view-title="Driver details"
                            :invalid="Boolean(fieldErrors.substitute_driver_id)"
                            :error-message="fieldErrorMessage('substitute_driver_id')"
                            :can-quick-add="false"
                        />
                        <input
                            v-else
                            :value="substituteDriverDisplay"
                            type="text"
                            class="form-control bg-light"
                            readonly
                        />
                    </div>
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
                            @quick-add="openQuickAddForTripCustomer"
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
                        <label class="form-label d-flex align-items-center gap-2">
                            <span>Actual Arrival Date</span>
                            <span
                                v-if="arrivalTiming === 'early'"
                                class="arrival-timing arrival-timing--early"
                                title="Arrived before planned end date"
                            >
                                <i class="bi bi-arrow-down-circle-fill" aria-hidden="true"></i>
                                <span>Early</span>
                            </span>
                            <span
                                v-else-if="arrivalTiming === 'late'"
                                class="arrival-timing arrival-timing--late"
                                title="Arrived after planned end date"
                            >
                                <i class="bi bi-exclamation-circle-fill" aria-hidden="true"></i>
                                <span>Late</span>
                            </span>
                        </label>
                        <input
                            :value="form.actual_arrival_at ? formatDate(form.actual_arrival_at) : '—'"
                            type="text"
                            class="form-control bg-light"
                            readonly
                        />
                    </div>
                    <div>
                        <label class="form-label">Reference by (not recommended)</label>
                        <SearchablePicker
                            v-if="!readonly"
                            v-model="form.broker_id"
                            :items="brokers"
                            placeholder="Select reference"
                            search-placeholder="Search reference…"
                            empty-message="No reference found."
                            view-title="Reference details"
                            quick-add-title="Quick add reference"
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
                <div v-if="form.status === 'CANCELLED'" class="mb-3">
                    <div class="alert alert-danger mb-0 py-2">
                        <div class="small">
                            <strong>Cancelled:</strong> {{ formatDate(form.cancelled_at) }}
                        </div>
                        <div v-if="form.cancellation_reason" class="small mt-1">
                            <strong>Reason:</strong> {{ form.cancellation_reason }}
                        </div>
                    </div>
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
                                <th style="min-width: 200px">Customer</th>
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
                                    <div v-if="!readonly" class="leg-customer-cell">
                                        <div class="form-check mb-1">
                                            <input
                                                :id="`same-customer-${index}`"
                                                v-model="leg.use_same_customer"
                                                class="form-check-input"
                                                type="checkbox"
                                                @change="onLegSameCustomerChange(index)"
                                            />
                                            <label
                                                class="form-check-label small text-muted"
                                                :for="`same-customer-${index}`"
                                            >
                                                Same customer
                                            </label>
                                        </div>
                                        <SearchablePicker
                                            v-model="leg.customer_id"
                                            :items="customers"
                                            :placeholder="leg.use_same_customer ? 'Uses trip customer' : 'Select customer'"
                                            attach-dropdown-to-body
                                            view-title="Customer details"
                                            quick-add-title="Quick add customer"
                                            :disabled="leg.use_same_customer"
                                            :invalid="Boolean(legFieldError(index, 'customer_id'))"
                                            :error-message="legFieldError(index, 'customer_id')"
                                            :can-quick-add="canQuickAddCustomer && !leg.use_same_customer"
                                            @quick-add="openQuickAddForLegCustomer(index)"
                                        />
                                    </div>
                                    <span v-else>{{ partyLabel(customers, leg.customer_id) }}</span>
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
                    :trip-start-date="form.start_at"
                    :trip-end-date="form.end_at"
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
import { computed, ref, useSlots, watch } from 'vue';
import {
    ROUTE_TYPES,
    routeTypeMeta,
    summarizeTripForm,
    tripStatusMeta,
} from '../../api/trips';
import { dateOnlyFromDatetimeLocal, formatDate } from '../../utils/datetime';
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
    snapshotSubstituteDriverName: { type: String, default: '' },
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

const slots = useSlots();

const showStationQuickAdd = ref(false);
const showBrokerQuickAdd = ref(false);
const showCustomerQuickAdd = ref(false);
const quickAddLegIndex = ref(null);
const quickAddCustomerLegIndex = ref(null);
const showStatusModal = ref(false);
const pendingStatus = ref('');

const summary = computed(() => summarizeTripForm(props.form));

const tripStartDate = computed(() => dateOnlyFromDatetimeLocal(props.form.start_at));
const tripEndDate = computed(() => dateOnlyFromDatetimeLocal(props.form.end_at));

const arrivalTiming = computed(() => {
    const endDate = tripEndDate.value;
    const arrivalDate = dateOnlyFromDatetimeLocal(props.form.actual_arrival_at);

    if (!endDate || !arrivalDate) {
        return null;
    }

    if (endDate === arrivalDate) {
        return null;
    }

    if (endDate > arrivalDate) {
        return 'early';
    }

    return 'late';
});

const currentStatusBadge = computed(() => tripStatusMeta(props.form.status));

const tripDetailsHeaderTitle = computed(() => {
    if (props.mode === 'create') {
        return 'Create - Trip';
    }

    if (props.readonly || props.statusLocked) {
        return 'View - Trip';
    }

    return 'Edit - Trip';
});

const driverDisplayName = computed(() => {
    if (props.mode === 'edit') {
        return props.snapshotDriverName || '—';
    }

    return props.primaryDriverName || (props.loadingDriver ? 'Loading…' : '—');
});

const primaryDriverId = computed(() => props.form.driver_id ?? '');

const hasPrimaryDriver = computed(() => Boolean(String(primaryDriverId.value).trim()));

const showSubstituteDriver = ref(false);

const showSubstituteDriverColumn = computed(
    () => (props.readonly && Boolean(props.form.substitute_driver_id))
        || (!props.readonly && hasPrimaryDriver.value && showSubstituteDriver.value),
);

const substituteDriverOptions = computed(() =>
    props.drivers.filter((driver) => String(driver.id) !== String(primaryDriverId.value)),
);

const substituteDriverDisplay = computed(() => {
    if (!props.form.substitute_driver_id) {
        return '—';
    }

    const match = props.drivers.find(
        (driver) => String(driver.id) === String(props.form.substitute_driver_id),
    );

    return match?.name ?? props.snapshotSubstituteDriverName ?? '—';
});

watch(
    () => props.form.substitute_driver_id,
    (substituteDriverId) => {
        if (substituteDriverId) {
            showSubstituteDriver.value = true;
        }
    },
    { immediate: true },
);

watch(showSubstituteDriver, (enabled) => {
    if (!enabled && !props.readonly) {
        props.form.substitute_driver_id = '';
        emit('clear-field-error', 'substitute_driver_id');
    }
});

watch(
    () => props.form.driver_id,
    (driverId) => {
        if (!driverId) {
            showSubstituteDriver.value = false;

            return;
        }

        if (
            props.form.substitute_driver_id
            && String(props.form.substitute_driver_id) === String(driverId)
        ) {
            props.form.substitute_driver_id = '';
        }
    },
);

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

const showHeaderActions = computed(
    () => Boolean(slots['trip-details-actions']) || (props.mode === 'edit' && !props.readonly && !props.statusLocked && allowedNextStatuses.value.length > 0),
);

function statusActionButtonClass(status) {
    const normalized = String(status ?? '').toUpperCase();

    if (normalized === 'COMPLETED') {
        return 'trip-details-action-btn--completed';
    }

    if (normalized === 'CANCELLED') {
        return 'trip-details-action-btn--cancelled';
    }

    if (normalized === 'IN_PROGRESS') {
        return 'trip-details-action-btn--progress';
    }

    return 'trip-details-action-btn--neutral';
}

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

function onLegSameCustomerChange(index) {
    const leg = props.form.route_legs[index];

    if (leg?.use_same_customer) {
        leg.customer_id = props.form.customer_id ?? '';
    }
}

function openQuickAddForLeg(index) {
    quickAddLegIndex.value = index;
    showStationQuickAdd.value = true;
}

function openQuickAddForTripCustomer() {
    quickAddCustomerLegIndex.value = null;
    showCustomerQuickAdd.value = true;
}

function openQuickAddForLegCustomer(index) {
    quickAddCustomerLegIndex.value = index;
    showCustomerQuickAdd.value = true;
}

function onStationCreated(station) {
    emit('station-created', { station, legIndex: quickAddLegIndex.value });
    quickAddLegIndex.value = null;
}

function onBrokerCreated(broker) {
    emit('broker-created', broker);
}

function onCustomerCreated(customer) {
    emit('customer-created', { customer, legIndex: quickAddCustomerLegIndex.value });
    quickAddCustomerLegIndex.value = null;
}

const routeTypes = ROUTE_TYPES;

defineExpose({
    changeStatus: onStatusChange,
});
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
.trip-details-card__header {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    padding: 14px 18px;
}
.trip-details-card__row {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.5rem 0.75rem;
}
.trip-details-card__row--main {
    justify-content: space-between;
    gap: 0.75rem;
}
@media (max-width: 768px) {
    .trip-details-card__actions {
        flex-wrap: wrap;
        justify-content: flex-end;
        width: 100%;
    }
}
.trip-details-card__actions {
    display: flex;
    flex-wrap: nowrap;
    align-items: center;
    gap: 0.35rem;
    margin-left: auto;
}
.trip-details-card__actions :deep(.trip-details-action-btn),
.trip-details-action-btn {
    white-space: nowrap;
    font-weight: 600;
    padding: 0.3rem 0.7rem;
    border-radius: 0.375rem;
    line-height: 1.2;
}
.trip-details-action-btn--completed {
    background: #fff;
    color: #157347;
    border: 1px solid #157347;
}
.trip-details-action-btn--completed:hover {
    background: #d1e7dd;
    color: #146c43;
}
.trip-details-action-btn--cancelled {
    background: #dc3545;
    color: #fff;
    border: 1px solid #bb2d3b;
}
.trip-details-action-btn--cancelled:hover {
    background: #bb2d3b;
    color: #fff;
}
.trip-details-action-btn--progress {
    background: #0d6efd;
    color: #fff;
    border: 1px solid #0a58ca;
}
.trip-details-action-btn--progress:hover {
    background: #0b5ed7;
    color: #fff;
}
.trip-details-action-btn--back {
    background: rgba(255, 255, 255, 0.14);
    color: #fff;
    border: 1px solid rgba(255, 255, 255, 0.55);
}
.trip-details-action-btn--back:hover {
    background: rgba(255, 255, 255, 0.24);
    color: #fff;
}
.trip-details-action-btn--update {
    background: #fff;
    color: #146c43;
    border: 1px solid #fff;
}
.trip-details-action-btn--update:hover {
    background: #f8f9fa;
    color: #146c43;
}
.trip-details-action-btn--neutral {
    background: #fff;
    color: #495057;
    border: 1px solid #dee2e6;
}
.trip-details-card__title {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.35rem 0.5rem;
    min-width: 0;
}
.trip-details-card__sep::before {
    content: '–';
    margin-right: 0.5rem;
    opacity: 0.75;
    font-weight: 400;
}
.trip-number-label {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}
.trip-details-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 16px;
}
.arrival-timing {
    display: inline-flex;
    align-items: center;
    gap: 0.2rem;
    font-size: 0.7rem;
    font-weight: 600;
    line-height: 1;
    padding: 0.2rem 0.45rem;
    border-radius: 999px;
}
.arrival-timing--early {
    color: #0f5132;
    background: #d1e7dd;
}
.arrival-timing--late {
    color: #842029;
    background: #f8d7da;
}
.driver-field-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    min-height: 1.5rem;
    margin-bottom: 0.5rem;
}
.trip-details-grid__substitute {
    animation: substitute-field-in 0.2s ease-out;
}
.substitute-toggle {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding-left: 0;
    min-height: auto;
}
.substitute-toggle .form-check-input {
    width: 2em;
    height: 1.1em;
    margin: 0;
    cursor: pointer;
}
.substitute-toggle .form-check-label {
    font-size: 0.8125rem;
    font-weight: 500;
    color: #6c757d;
    cursor: pointer;
    white-space: nowrap;
    padding-top: 0;
}
.substitute-toggle .form-check-input:checked + .form-check-label {
    color: #146c43;
}
@media (max-width: 768px) {
    .trip-details-grid {
        grid-template-columns: 1fr;
    }
}
@keyframes substitute-field-in {
    from {
        opacity: 0;
        transform: translateY(-4px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>
