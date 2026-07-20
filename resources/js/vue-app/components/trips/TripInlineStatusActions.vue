<template>
    <div class="trip-inline-status" :class="{ 'trip-inline-status--compact': compact }">
        <div
            v-if="compact && canManage && nextStatuses.length > 0"
            class="dropdown trip-inline-status__dropdown"
        >
            <button
                type="button"
                class="badge dropdown-toggle trip-inline-status__badge trip-inline-status__badge-btn"
                :class="statusMeta.badge"
                :disabled="saving"
                data-bs-toggle="dropdown"
                data-bs-boundary="viewport"
                data-bs-popper-config='{"strategy":"fixed"}'
                aria-expanded="false"
                :title="`Change status — ${statusMeta.label}`"
            >
                <span
                    v-if="saving"
                    class="spinner-border spinner-border-sm"
                    role="status"
                    aria-hidden="true"
                ></span>
                <template v-else>{{ statusMeta.label }}</template>
            </button>
            <ul class="dropdown-menu dropdown-menu-sm">
                <li v-for="next in nextStatuses" :key="next.value">
                    <button
                        type="button"
                        class="dropdown-item"
                        :disabled="saving"
                        @click="requestStatusChange(next.value)"
                    >
                        {{ next.label }}
                    </button>
                </li>
            </ul>
        </div>

        <span v-else-if="compact" class="badge trip-inline-status__badge" :class="statusMeta.badge">{{ statusMeta.label }}</span>

        <template v-else>
            <div v-if="canManage && nextStatuses.length > 0" class="trip-inline-status__actions">
                <button
                    v-for="next in nextStatuses"
                    :key="next.value"
                    type="button"
                    class="btn btn-sm trip-header-btn"
                    :class="statusActionButtonClass(next.value)"
                    :disabled="saving"
                    @click="requestStatusChange(next.value)"
                >
                    <span
                        v-if="saving"
                        class="spinner-border spinner-border-sm me-1"
                        role="status"
                        aria-hidden="true"
                    ></span>
                    <i v-else :class="[statusActionIcon(next.value), 'me-1']" aria-hidden="true"></i>
                    {{ next.label }}
                </button>
            </div>
        </template>

        <TripStatusChangeModal
            :show="showStatusModal"
            :status="pendingStatus"
            :from-status="originalStatus || trip.status"
            :trip-number="statusContext.tripNumber"
            :truck-number="statusContext.truckNumber"
            :start-at="form.start_at"
            :end-at-value="form.end_at"
            :actual-arrival-at-value="form.actual_arrival_at"
            :cancelled-at-value="form.cancelled_at"
            :cancellation-reason-value="form.cancellation_reason"
            :start-meter-reading-km="form.start_meter_reading_km"
            :truck-running-meter-km="truckRunningMeterKm"
            :end-meter-reading-km-value="form.end_meter_reading_km"
            :saving="saving"
            :save-error="saveError"
            @cancel="onStatusModalCancel"
            @confirm="onStatusModalConfirm"
        />
    </div>
</template>

<script setup>
import { computed, ref } from 'vue';
import {
    fetchTrip,
    tripAllowedNextStatuses,
    tripStatusMeta,
    updateTrip,
    validateTripStatusChange,
} from '../../api/trips';
import { useAppToast } from '../../composables/useAppToast';
import { useTripForm } from '../../composables/useTripForm';
import { extractValidationErrors, friendlyApiErrorMessage } from '../../utils/formErrors';
import { resolveTripStatusChangeContext } from '../../utils/trip-status-change-info';
import TripStatusChangeModal from './TripStatusChangeModal.vue';

const props = defineProps({
    trip: { type: Object, required: true },
    canManage: { type: Boolean, default: false },
    compact: { type: Boolean, default: true },
});

const emit = defineEmits(['updated']);

const toast = useAppToast();
const { form, populateFromTrip, buildStatusChangePayload } = useTripForm();

const saving = ref(false);
const showStatusModal = ref(false);
const pendingStatus = ref('');
const originalStatus = ref('');
const hasInvoices = ref(false);
const truckRunningMeterKm = ref('');
const vehicleRegistration = ref('');
const saveError = ref('');

const statusMeta = computed(() => tripStatusMeta(props.trip.status));
const nextStatuses = computed(() => tripAllowedNextStatuses(props.trip.status));
const statusContext = computed(() =>
    resolveTripStatusChangeContext({
        trip: props.trip,
        form,
        vehicleRegistration: vehicleRegistration.value,
    }),
);

function resolveSaveError(error, fallback) {
    const { message } = extractValidationErrors(error);

    return message ?? friendlyApiErrorMessage(error, fallback);
}

function reportStatusError(message) {
    saveError.value = message;

    if (!showStatusModal.value) {
        toast.error(message);
    }
}

function statusActionButtonClass(status) {
    const normalized = String(status ?? '').toUpperCase();

    if (normalized === 'COMPLETED') {
        return 'trip-header-btn--completed';
    }

    if (normalized === 'CANCELLED') {
        return 'trip-header-btn--cancelled';
    }

    if (normalized === 'IN_PROGRESS') {
        return 'trip-header-btn--progress';
    }

    return 'trip-header-btn--back';
}

function statusActionIcon(status) {
    const normalized = String(status ?? '').toUpperCase();

    if (normalized === 'COMPLETED') {
        return 'bi bi-check-circle-fill';
    }

    if (normalized === 'CANCELLED') {
        return 'bi bi-x-circle-fill';
    }

    if (normalized === 'IN_PROGRESS') {
        return 'bi bi-hourglass-split';
    }

    return 'bi bi-circle';
}

async function ensureTripLoaded() {
    const fullTrip = await fetchTrip(props.trip.id);
    populateFromTrip(fullTrip);
    originalStatus.value = fullTrip.status ?? 'PENDING';
    truckRunningMeterKm.value = fullTrip.vehicle?.odometer_km ?? '';
    vehicleRegistration.value = fullTrip.vehicle?.registration_number ?? '';
    hasInvoices.value = Boolean(fullTrip.has_invoices);

    return fullTrip;
}

async function saveStatusChange(successLabel) {
    const payload = buildStatusChangePayload();
    const clientErrors = validateTripStatusChange(payload, {
        tripType: form.trip_type,
        routeLegs: form.route_legs,
        startStationId: form.start_station_id,
        previousStatus: originalStatus.value,
        startMeterReadingKm: form.start_meter_reading_km,
        hasInvoices: hasInvoices.value,
    });

    if (Object.keys(clientErrors).length > 0) {
        const firstError = Object.values(clientErrors).flat()[0] ?? 'Could not update trip status.';
        reportStatusError(firstError);

        return { updated: null, error: firstError };
    }

    saving.value = true;
    saveError.value = '';

    try {
        const updated = await updateTrip(props.trip.id, payload);
        toast.success(successLabel);

        return { updated, error: null };
    } catch (error) {
        const errorMessage = resolveSaveError(error, 'Failed to update trip status.');
        reportStatusError(errorMessage);

        return { updated: null, error: errorMessage };
    } finally {
        saving.value = false;
    }
}

async function requestStatusChange(newStatus) {
    if (!props.canManage || saving.value) {
        return;
    }

    try {
        await ensureTripLoaded();
    } catch (error) {
        toast.error(resolveSaveError(error, 'Could not load trip for status change.'));

        return;
    }

    saveError.value = '';
    pendingStatus.value = newStatus;

    if ((newStatus === 'COMPLETED' || newStatus === 'CANCELLED') && !form.start_at) {
        toast.error('Trip start date is required. Edit the trip, set the start date, then change the status.');
    }

    showStatusModal.value = true;
}

function onStatusModalCancel() {
    if (saving.value) {
        return;
    }

    showStatusModal.value = false;
    pendingStatus.value = '';
    saveError.value = '';
}

async function onStatusModalConfirm(data) {
    form.status = pendingStatus.value;

    if (pendingStatus.value === 'CANCELLED') {
        form.cancelled_at = data.cancelled_at;
        form.cancellation_reason = data.cancellation_reason;
        form.end_meter_reading_km = data.end_meter_reading_km;
    } else if (pendingStatus.value === 'COMPLETED') {
        form.end_at = data.end_at;
        form.actual_arrival_at = data.actual_arrival_at;
        form.end_meter_reading_km = data.end_meter_reading_km;
    } else if (pendingStatus.value === 'IN_PROGRESS') {
        if (data.start_at) {
            form.start_at = data.start_at;
        }

        if (data.start_meter_reading_km != null && data.start_meter_reading_km !== '') {
            form.start_meter_reading_km = data.start_meter_reading_km;
        }
    }

    const statusBeingSaved = pendingStatus.value;
    const label =
        statusBeingSaved === 'COMPLETED'
            ? 'Trip marked as completed.'
            : statusBeingSaved === 'CANCELLED'
              ? 'Trip marked as cancelled.'
              : 'Trip marked as in progress.';
    const result = await saveStatusChange(label);

    if (result.updated) {
        showStatusModal.value = false;
        pendingStatus.value = '';
        saveError.value = '';
        emit('updated', result.updated);

        return;
    }

    saveError.value = result.error ?? 'Failed to update trip status.';
}
</script>

<style scoped>
.trip-inline-status {
    display: inline-flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.35rem;
}

.trip-inline-status--compact {
    min-width: 0;
}

.trip-inline-status__dropdown {
    display: inline-block;
}

.trip-inline-status__badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 8.25rem;
    height: 1.75rem;
    padding: 0 0.55rem;
    font-size: 0.75rem;
    font-weight: 600;
    line-height: 1;
    white-space: nowrap;
    box-sizing: border-box;
    border-radius: 0.375rem;
}

.trip-inline-status__badge-btn {
    gap: 0.2rem;
    border: none;
    cursor: pointer;
}

.trip-inline-status__badge-btn:disabled {
    opacity: 0.75;
    cursor: wait;
}

.trip-inline-status__badge-btn.dropdown-toggle::after {
    margin-left: 0.2em;
    vertical-align: 0;
    opacity: 0.85;
    flex-shrink: 0;
}

.trip-inline-status__actions {
    display: inline-flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.35rem;
}
</style>
