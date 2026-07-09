<template>
    <AppModal
        :show="show"
        :loading="saving"
        :form-error="saveError || formError"
        :header-gradient="status !== 'CANCELLED'"
        :content-class="[
            'trip-status-modal__content',
            status === 'CANCELLED' ? 'trip-status-modal--cancelled' : '',
            status === 'IN_PROGRESS' ? 'trip-status-modal--in-progress' : '',
        ]"
        body-class="trip-status-modal__body"
        dismissible
        as-form
        @close="$emit('cancel')"
        @submit="submit"
    >
        <template #header>
            <div class="trip-status-modal__header-main">
                <h5 class="modal-title mb-0 text-white d-flex align-items-center gap-2">
                    <i class="bi" :class="headerIconClass"></i>
                    {{ modalTitle }}
                </h5>
                <div class="trip-status-modal__header-meta">
                    <span class="trip-status-modal__header-meta-item">
                        <span class="trip-status-modal__header-label">Trip Number</span>
                        <span class="trip-status-modal__header-value">{{ tripNumber || '—' }}</span>
                    </span>
                    <span class="trip-status-modal__header-meta-item">
                        <span class="trip-status-modal__header-label">Truck Number</span>
                        <span class="trip-status-modal__header-value">{{ truckNumber || '—' }}</span>
                    </span>
                </div>
            </div>
        </template>

        <div v-if="awaitingFinalConfirm" class="status-final-confirm text-center py-2">
                            <div
                                class="status-final-confirm__icon mb-3"
                                :class="finalConfirmIconToneClass"
                            >
                                <i class="bi" :class="finalConfirmIconClass"></i>
                            </div>
                            <h6 class="mb-2">{{ finalConfirmTitle }}</h6>
                            <p class="text-muted small mb-0">{{ finalConfirmMessage }}</p>
                            <TripStatusChangeUpdatesPanel
                                class="mt-3 text-start"
                                :items="statusUpdateItems"
                            />
                        </div>

                        <template v-else-if="status === 'CANCELLED'">
                            <div v-if="!hasStartDate" class="alert alert-warning mb-0">
                                <i class="bi bi-exclamation-triangle me-1"></i>
                                This trip has no <strong>start date</strong>. Open the trip, set the start date, then try cancelling again.
                            </div>
                            <div v-else-if="!hasStartMeterReading" class="alert alert-warning mb-0">
                                <i class="bi bi-exclamation-triangle me-1"></i>
                                This trip has no <strong>start meter reading</strong>. Open the trip, set the start meter reading, then try cancelling again.
                            </div>
                            <template v-else>
                            <div class="status-readonly-strip mb-3">
                                <div class="status-readonly-strip__item">
                                    <span class="status-readonly-strip__label">Start Meter Reading</span>
                                    <span class="status-readonly-strip__value">{{ formatMeter(startMeterReadingKm) }}</span>
                                </div>
                                <div class="status-readonly-strip__item">
                                    <span class="status-readonly-strip__label">Truck Running Meter</span>
                                    <span class="status-readonly-strip__value">{{ formatMeter(truckRunningMeterKm) }}</span>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">End Meter Reading *</label>
                                <input
                                    v-model.number="endMeterReadingKm"
                                    v-no-scroll-change
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    class="form-control"
                                    :class="{ 'is-invalid': fieldErrors.end_meter_reading_km }"
                                    required
                                />
                                <div v-if="fieldErrors.end_meter_reading_km" class="invalid-feedback d-block">
                                    {{ fieldErrors.end_meter_reading_km }}
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Cancellation Date *</label>
                                <AppDateInput
                                    v-model="cancelledAt"
                                    :invalid="!!fieldErrors.cancelled_at"
                                    required
                                />
                                <div v-if="fieldErrors.cancelled_at" class="invalid-feedback d-block">
                                    {{ fieldErrors.cancelled_at }}
                                </div>
                            </div>
                            <div class="mb-0">
                                <label class="form-label">Reason / Comment *</label>
                                <textarea
                                    v-model="cancellationReason"
                                    class="form-control"
                                    rows="3"
                                    placeholder="Why is this trip being cancelled?"
                                    :class="{ 'is-invalid': fieldErrors.cancellation_reason }"
                                    required
                                ></textarea>
                                <div v-if="fieldErrors.cancellation_reason" class="invalid-feedback d-block">
                                    {{ fieldErrors.cancellation_reason }}
                                </div>
                            </div>
                            <TripStatusChangeUpdatesPanel
                                class="mt-3"
                                :items="statusUpdateItems"
                            />
                            </template>
                        </template>

                        <template v-else-if="!awaitingFinalConfirm && status === 'COMPLETED'">
                            <div v-if="!hasStartDate" class="alert alert-warning mb-0">
                                <i class="bi bi-exclamation-triangle me-1"></i>
                                This trip has no <strong>start date</strong>. Open the trip, set the start date, then try completing again.
                            </div>
                            <div v-else-if="!hasStartMeterReading" class="alert alert-warning mb-0">
                                <i class="bi bi-exclamation-triangle me-1"></i>
                                This trip has no <strong>start meter reading</strong>. Open the trip, set the start meter reading, then try completing again.
                            </div>
                            <template v-else>
                            <div class="status-readonly-strip mb-3">
                                <div class="status-readonly-strip__item">
                                    <span class="status-readonly-strip__label">Start Date</span>
                                    <span class="status-readonly-strip__value">{{ formatDate(startAt) }}</span>
                                </div>
                                <div class="status-readonly-strip__item">
                                    <span class="status-readonly-strip__label">Truck Running Meter</span>
                                    <span class="status-readonly-strip__value">{{ formatMeter(truckRunningMeterKm) }}</span>
                                </div>
                                <div class="status-readonly-strip__item">
                                    <span class="status-readonly-strip__label">Start Meter Reading</span>
                                    <span class="status-readonly-strip__value">{{ formatMeter(startMeterReadingKm) }}</span>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">End Meter Reading *</label>
                                <input
                                    v-model.number="endMeterReadingKm"
                                    v-no-scroll-change
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    class="form-control"
                                    :class="{ 'is-invalid': fieldErrors.end_meter_reading_km }"
                                    required
                                />
                                <div v-if="fieldErrors.end_meter_reading_km" class="invalid-feedback d-block">
                                    {{ fieldErrors.end_meter_reading_km }}
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Actual Arrival Date *</label>
                                <AppDateInput
                                    v-model="actualArrivalAt"
                                    :invalid="!!fieldErrors.actual_arrival_at"
                                    :min="actualArrivalMin || undefined"
                                    required
                                />
                                <div class="form-text">When did the truck actually arrive back?</div>
                                <div v-if="fieldErrors.actual_arrival_at" class="invalid-feedback d-block">
                                    {{ fieldErrors.actual_arrival_at }}
                                </div>
                            </div>
                            <div class="mb-0">
                                <label class="form-label d-flex align-items-center gap-2 flex-wrap">
                                    <span>End Date *</span>
                                    <span
                                        v-if="completionArrivalTiming === 'on-time'"
                                        class="badge bg-info text-dark"
                                    >On Time</span>
                                    <span
                                        v-else-if="completionArrivalTiming === 'early'"
                                        class="badge bg-success"
                                    >Early</span>
                                    <span
                                        v-else-if="completionArrivalTiming === 'late'"
                                        class="badge bg-danger"
                                    >Late</span>
                                </label>
                                <AppDateInput
                                    v-model="endAt"
                                    :invalid="!!fieldErrors.end_at"
                                    :min="endDateMin || undefined"
                                    required
                                />
                                <div class="form-text">Must be on or after the actual arrival date.</div>
                                <div v-if="fieldErrors.end_at" class="invalid-feedback d-block">
                                    {{ fieldErrors.end_at }}
                                </div>
                            </div>
                            <TripStatusChangeUpdatesPanel
                                class="mt-3"
                                :items="statusUpdateItems"
                            />
                            </template>
                        </template>

        <template #footer>
            <template v-if="awaitingFinalConfirm">
                <button type="button" class="btn btn-secondary" :disabled="saving" @click="backToForm">
                    No, Go Back
                </button>
                <AppButton
                    type="button"
                    label="OK"
                    :variant="confirmButtonVariant"
                    :disabled="saving"
                    :loading="saving"
                    @click="finalConfirm"
                />
            </template>
            <template v-else>
                <button type="button" class="btn btn-secondary" :disabled="saving" @click="$emit('cancel')">Back</button>
                <AppButton
                    type="submit"
                    :label="status === 'CANCELLED' ? 'Confirm Cancellation' : 'Confirm Completion'"
                    :variant="status === 'CANCELLED' ? 'danger' : 'success'"
                    :disabled="saving || !canSubmit"
                />
            </template>
        </template>
    </AppModal>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { toDateInputFromIso, toDateInputValue, formatDate } from '../../utils/datetime';
import { formatMeterKm } from '../../utils/formatMeter';
import { buildTripStatusChangeUpdateItems } from '../../utils/trip-status-change-info';
import AppButton from '../ui/AppButton.vue';
import AppModal from '../ui/AppModal.vue';
import TripStatusChangeUpdatesPanel from './TripStatusChangeUpdatesPanel.vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    status: { type: String, default: '' },
    fromStatus: { type: String, default: '' },
    tripNumber: { type: String, default: '' },
    truckNumber: { type: String, default: '' },
    startAt: { type: String, default: '' },
    endAtValue: { type: String, default: '' },
    actualArrivalAtValue: { type: String, default: '' },
    cancelledAtValue: { type: String, default: '' },
    cancellationReasonValue: { type: String, default: '' },
    startMeterReadingKm: { type: [Number, String], default: '' },
    truckRunningMeterKm: { type: [Number, String], default: '' },
    endMeterReadingKmValue: { type: [Number, String], default: '' },
    saving: { type: Boolean, default: false },
    saveError: { type: String, default: '' },
});

const emit = defineEmits(['cancel', 'confirm']);

const cancelledAt = ref('');
const cancellationReason = ref('');
const endAt = ref('');
const actualArrivalAt = ref('');
const endMeterReadingKm = ref('');
const formError = ref('');
const fieldErrors = reactive({});
const awaitingFinalConfirm = ref(false);
const pendingConfirmPayload = ref(null);

const modalTitle = computed(() => {
    if (props.status === 'CANCELLED') {
        return 'Cancel Trip';
    }

    if (props.status === 'IN_PROGRESS') {
        return 'Mark In Progress';
    }

    return 'Complete Trip';
});

const headerIconClass = computed(() => {
    if (props.status === 'CANCELLED') {
        return 'bi-x-circle';
    }

    if (props.status === 'IN_PROGRESS') {
        return 'bi-hourglass-split';
    }

    return 'bi-check-circle';
});

const finalConfirmTitle = computed(() => {
    if (props.status === 'CANCELLED') {
        return 'Cancel this trip?';
    }

    if (props.status === 'IN_PROGRESS') {
        return 'Mark trip as in progress?';
    }

    return 'Mark trip as completed?';
});

const finalConfirmMessage = computed(() => {
    if (props.status === 'CANCELLED') {
        return 'This will save the trip as cancelled. Click OK to confirm.';
    }

    if (props.status === 'IN_PROGRESS') {
        return 'This will save the trip as in progress. Click OK to confirm.';
    }

    return 'This will save the trip as completed. Click OK to confirm.';
});

const finalConfirmIconToneClass = computed(() => {
    if (props.status === 'CANCELLED') {
        return 'text-danger';
    }

    if (props.status === 'IN_PROGRESS') {
        return 'text-warning';
    }

    return 'text-success';
});

const finalConfirmIconClass = computed(() => {
    if (props.status === 'CANCELLED') {
        return 'bi-exclamation-triangle-fill';
    }

    return 'bi-question-circle-fill';
});

const confirmButtonVariant = computed(() => {
    if (props.status === 'CANCELLED') {
        return 'danger';
    }

    if (props.status === 'IN_PROGRESS') {
        return 'warning';
    }

    return 'success';
});

const tripStartDateMin = computed(() => toDateInputFromIso(props.startAt));

const hasStartDate = computed(() => Boolean(tripStartDateMin.value));

const hasStartMeterReading = computed(() => {
    const value = Number(props.startMeterReadingKm ?? 0);

    return Number.isFinite(value) && value > 0;
});

const canSubmit = computed(() => {
    if (!hasStartDate.value) {
        return false;
    }

    if ((props.status === 'COMPLETED' || props.status === 'CANCELLED') && !hasStartMeterReading.value) {
        return false;
    }

    return true;
});

const actualArrivalMin = computed(() => tripStartDateMin.value || '');

const endDateMin = computed(() => actualArrivalAt.value || tripStartDateMin.value || '');

const completionArrivalTiming = computed(() => {
    if (!endAt.value || !actualArrivalAt.value) {
        return null;
    }

    if (endAt.value === actualArrivalAt.value) {
        return 'on-time';
    }

    if (endAt.value > actualArrivalAt.value) {
        return 'early';
    }

    return 'late';
});

const statusUpdateItems = computed(() =>
    buildTripStatusChangeUpdateItems({
        fromStatus: props.fromStatus,
        toStatus: props.status,
        confirmPayload: awaitingFinalConfirm.value
            ? pendingConfirmPayload.value
            : {
                  end_at: endAt.value,
                  actual_arrival_at: actualArrivalAt.value,
                  end_meter_reading_km: endMeterReadingKm.value,
                  cancelled_at: cancelledAt.value,
                  cancellation_reason: cancellationReason.value,
              },
    }),
);

function formatMeter(value) {
    return formatMeterKm(value);
}

function initCompletionDates() {
    const startMin = tripStartDateMin.value;
    const existingEnd = toDateInputFromIso(props.endAtValue);
    const existingArrival = toDateInputFromIso(props.actualArrivalAtValue);
    const today = toDateInputValue();

    if (!startMin) {
        actualArrivalAt.value = existingArrival || '';
        endAt.value = existingEnd || '';

        return;
    }

    actualArrivalAt.value = existingArrival || startMin || today;

    if (actualArrivalAt.value < startMin) {
        actualArrivalAt.value = startMin;
    }

    endAt.value = existingEnd || actualArrivalAt.value || today;

    if (endAt.value < startMin) {
        endAt.value = startMin;
    }

    if (actualArrivalAt.value && endAt.value < actualArrivalAt.value) {
        endAt.value = actualArrivalAt.value;
    }
}

watch(
    () => props.show,
    (visible) => {
        if (!visible) {
            awaitingFinalConfirm.value = false;
            pendingConfirmPayload.value = null;

            return;
        }

        formError.value = '';
        awaitingFinalConfirm.value = false;
        pendingConfirmPayload.value = null;
        Object.keys(fieldErrors).forEach((key) => delete fieldErrors[key]);

        if (props.status === 'CANCELLED') {
            cancelledAt.value = toDateInputFromIso(props.cancelledAtValue) || toDateInputValue();
            cancellationReason.value = props.cancellationReasonValue || '';
            endMeterReadingKm.value =
                props.endMeterReadingKmValue !== '' && props.endMeterReadingKmValue != null
                    ? Number(props.endMeterReadingKmValue)
                    : '';
        } else if (props.status === 'COMPLETED') {
            initCompletionDates();
            endMeterReadingKm.value =
                props.endMeterReadingKmValue !== '' && props.endMeterReadingKmValue != null
                    ? Number(props.endMeterReadingKmValue)
                    : '';
        } else if (props.status === 'IN_PROGRESS') {
            awaitingFinalConfirm.value = true;
            pendingConfirmPayload.value = {};
        }
    },
);

function validateEndMeterReading() {
    const endMeter = Number(endMeterReadingKm.value);
    const startMeter = Number(props.startMeterReadingKm ?? 0);

    if (!Number.isFinite(endMeter) || endMeter <= 0) {
        fieldErrors.end_meter_reading_km = 'End meter reading is required.';

        return;
    }

    if (Number.isFinite(startMeter) && startMeter > 0 && endMeter < startMeter) {
        fieldErrors.end_meter_reading_km = 'End Meter Reading cannot be less than Start Meter Reading.';
    }
}

function validate() {
    formError.value = '';
    Object.keys(fieldErrors).forEach((key) => delete fieldErrors[key]);

    if (props.status === 'CANCELLED') {
        if (!hasStartDate.value) {
            fieldErrors.start_at = 'Trip start date is required before cancelling this trip.';
        }

        if (!cancelledAt.value) {
            fieldErrors.cancelled_at = 'Cancellation date is required.';
        }

        if (!cancellationReason.value.trim()) {
            fieldErrors.cancellation_reason = 'Please provide a reason for cancelling this trip.';
        }

        validateEndMeterReading();
    }

    if (props.status === 'COMPLETED') {
        if (!hasStartDate.value) {
            fieldErrors.start_at = 'Trip start date is required before completing this trip.';
        }

        if (!endAt.value) {
            fieldErrors.end_at = 'End date is required to complete the trip.';
        }

        if (!actualArrivalAt.value) {
            fieldErrors.actual_arrival_at = 'Actual arrival date is required.';
        }

        validateEndMeterReading();

        const startMin = tripStartDateMin.value;

        if (startMin && actualArrivalAt.value && actualArrivalAt.value < startMin) {
            fieldErrors.actual_arrival_at = 'Actual arrival cannot be before the trip start date.';
        }

        if (startMin && endAt.value && endAt.value < startMin) {
            fieldErrors.end_at = 'End date cannot be before the trip start date.';
        }

        if (actualArrivalAt.value && endAt.value && endAt.value < actualArrivalAt.value) {
            fieldErrors.end_at = 'End date cannot be before the actual arrival date.';
        }
    }

    return Object.keys(fieldErrors).length === 0;
}

function buildConfirmPayload() {
    if (props.status === 'CANCELLED') {
        return {
            cancelled_at: cancelledAt.value,
            cancellation_reason: cancellationReason.value.trim(),
            end_meter_reading_km: Number(endMeterReadingKm.value),
        };
    }

    return {
        end_at: endAt.value,
        actual_arrival_at: actualArrivalAt.value,
        end_meter_reading_km: Number(endMeterReadingKm.value),
    };
}

function submit() {
    if (!validate()) {
        formError.value = 'Please fix the errors below.';

        return;
    }

    pendingConfirmPayload.value = buildConfirmPayload();
    awaitingFinalConfirm.value = true;
    formError.value = '';
}

function backToForm() {
    if (props.status === 'IN_PROGRESS') {
        emit('cancel');

        return;
    }

    awaitingFinalConfirm.value = false;
    pendingConfirmPayload.value = null;
}

function finalConfirm() {
    if (!pendingConfirmPayload.value) {
        return;
    }

    emit('confirm', pendingConfirmPayload.value);
    awaitingFinalConfirm.value = false;
    pendingConfirmPayload.value = null;
}
</script>

<style scoped>
:deep(.trip-status-modal__content) {
    max-height: none;
}

:deep(.trip-status-modal__body) {
    overflow: visible !important;
}

:deep(.trip-status-modal--cancelled .app-modal__header) {
    background-color: var(--bs-danger);
    color: #fff;
}

:deep(.trip-status-modal--in-progress .app-modal__header) {
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    color: #fff;
}

.trip-status-modal__header-main {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
    min-width: 0;
}

.trip-status-modal__header-meta {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.35rem 1rem;
    font-size: 0.75rem;
    line-height: 1.3;
    color: rgba(255, 255, 255, 0.82);
}

.trip-status-modal__header-meta-item {
    display: inline-flex;
    align-items: baseline;
    gap: 0.3rem;
    min-width: 0;
}

.trip-status-modal__header-label {
    opacity: 0.9;
}

.trip-status-modal__header-label::after {
    content: ':';
}

.trip-status-modal__header-value {
    font-weight: 700;
    color: #fff;
}

.status-final-confirm__icon {
    font-size: 2.5rem;
    line-height: 1;
}

.status-readonly-strip {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.5rem 1.25rem;
    padding: 0.65rem 0.85rem;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 0.5rem;
    font-size: 0.8125rem;
}

.status-readonly-strip__item {
    display: inline-flex;
    align-items: baseline;
    gap: 0.35rem;
    min-width: 0;
}

.status-readonly-strip__label {
    color: #64748b;
    white-space: nowrap;
}

.status-readonly-strip__label::after {
    content: ':';
}

.status-readonly-strip__value {
    font-weight: 600;
    color: #1e293b;
}
</style>
