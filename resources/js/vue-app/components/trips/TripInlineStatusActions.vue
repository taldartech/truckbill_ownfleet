<template>
    <div class="trip-inline-status" :class="{ 'trip-inline-status--compact': compact }">
        <div
            v-if="compact && canManage && nextStatuses.length > 0"
            class="dropdown trip-inline-status__dropdown"
        >
            <button
                type="button"
                class="badge dropdown-toggle trip-inline-status__badge-btn"
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

        <span v-else-if="compact" class="badge" :class="statusMeta.badge">{{ statusMeta.label }}</span>

        <template v-else>
            <span class="badge" :class="statusMeta.badge">{{ statusMeta.label }}</span>
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
            :start-at="form.start_at"
            :end-at-value="form.end_at"
            :actual-arrival-at-value="form.actual_arrival_at"
            :cancelled-at-value="form.cancelled_at"
            :cancellation-reason-value="form.cancellation_reason"
            :start-meter-reading-km="form.start_meter_reading_km"
            :truck-running-meter-km="truckRunningMeterKm"
            :end-meter-reading-km-value="form.end_meter_reading_km"
            @cancel="onStatusModalCancel"
            @confirm="onStatusModalConfirm"
        />
    </div>
</template>

<script setup>
import { computed, ref } from 'vue';
import { listAllStations } from '../../api/stations';
import {
    fetchTrip,
    tripAllowedNextStatuses,
    tripStatusMeta,
    updateTrip,
    validateTripPayload,
} from '../../api/trips';
import { useApiFormErrors } from '../../composables/useApiFormErrors';
import { useAppToast } from '../../composables/useAppToast';
import { useTripForm } from '../../composables/useTripForm';
import TripStatusChangeModal from './TripStatusChangeModal.vue';

const props = defineProps({
    trip: { type: Object, required: true },
    canManage: { type: Boolean, default: false },
    compact: { type: Boolean, default: true },
});

const emit = defineEmits(['updated']);

const toast = useAppToast();
const { applyApiError } = useApiFormErrors();
const { form, populateFromTrip, buildPayload } = useTripForm();

const saving = ref(false);
const showStatusModal = ref(false);
const pendingStatus = ref('');
const originalStatus = ref('');
const stations = ref([]);
const truckRunningMeterKm = ref('');

const statusMeta = computed(() => tripStatusMeta(props.trip.status));
const nextStatuses = computed(() => tripAllowedNextStatuses(props.trip.status));

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
    if (stations.value.length === 0) {
        stations.value = await listAllStations();
    }

    const fullTrip = await fetchTrip(props.trip.id);
    populateFromTrip(fullTrip);
    originalStatus.value = fullTrip.status ?? 'CREATED';
    truckRunningMeterKm.value = fullTrip.vehicle?.odometer_km ?? '';

    return fullTrip;
}

async function saveStatusChange(successLabel) {
    const payload = buildPayload(stations.value, { mode: 'edit' });
    const clientErrors = validateTripPayload(
        {
            ...payload,
            start_meter_reading_km: form.start_meter_reading_km,
            truck_running_meter_km: truckRunningMeterKm.value,
        },
        {
            mode: 'edit',
            previousStatus: originalStatus.value,
        },
    );

    if (Object.keys(clientErrors).length > 0) {
        const firstError = Object.values(clientErrors).flat()[0] ?? 'Could not update trip status.';
        toast.error(firstError);

        return null;
    }

    saving.value = true;

    try {
        const updated = await updateTrip(props.trip.id, payload);
        toast.success(successLabel);

        return updated;
    } catch (error) {
        if (!applyApiError(error, 'Failed to update trip status.')) {
            toast.error(error?.response?.data?.message ?? 'Failed to update trip status.');
        }

        return null;
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
        if (!applyApiError(error, 'Could not load trip for status change.')) {
            toast.error('Could not load trip for status change.');
        }

        return;
    }

    if (newStatus === 'COMPLETED' || newStatus === 'CANCELLED') {
        pendingStatus.value = newStatus;
        showStatusModal.value = true;

        return;
    }

    form.status = newStatus;

    const updated = await saveStatusChange('Trip marked as in progress.');

    if (updated) {
        emit('updated', updated);
    }
}

function onStatusModalCancel() {
    showStatusModal.value = false;
    pendingStatus.value = '';
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
    }

    showStatusModal.value = false;

    const label =
        pendingStatus.value === 'COMPLETED'
            ? 'Trip marked as completed.'
            : 'Trip marked as cancelled.';
    pendingStatus.value = '';

    const updated = await saveStatusChange(label);

    if (updated) {
        emit('updated', updated);
    }
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

.trip-inline-status__badge-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.2rem;
    border: none;
    cursor: pointer;
    padding: 0.4em 0.65em;
    font-size: 0.8125rem;
    font-weight: 600;
    line-height: 1.2;
    white-space: nowrap;
}

.trip-inline-status__badge-btn:disabled {
    opacity: 0.75;
    cursor: wait;
}

.trip-inline-status__badge-btn.dropdown-toggle::after {
    margin-left: 0.15em;
    vertical-align: 0.15em;
    opacity: 0.85;
}

.trip-inline-status__actions {
    display: inline-flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.35rem;
}
</style>
