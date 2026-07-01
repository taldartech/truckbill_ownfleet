<template>
    <div
        v-if="show"
        class="modal fade show d-block"
        tabindex="-1"
        style="background: rgba(0, 0, 0, 0.45)"
        @click.self="$emit('cancel')"
    >
        <div class="modal-dialog">
            <div class="modal-content">
                <div
                    class="modal-header text-white"
                    :class="status === 'CANCELLED' ? 'bg-danger' : 'bg-success'"
                >
                    <h5 class="modal-title text-white">
                        <i class="bi" :class="status === 'CANCELLED' ? 'bi-x-circle' : 'bi-check-circle'"></i>
                        {{ modalTitle }}
                    </h5>
                    <button type="button" class="btn-close btn-close-white" @click="$emit('cancel')"></button>
                </div>
                <form @submit.prevent="submit">
                    <div class="modal-body">
                        <div v-if="formError" class="alert alert-danger py-2">{{ formError }}</div>

                        <div v-if="awaitingFinalConfirm" class="status-final-confirm text-center py-2">
                            <div
                                class="status-final-confirm__icon mb-3"
                                :class="status === 'CANCELLED' ? 'text-danger' : 'text-success'"
                            >
                                <i class="bi" :class="status === 'CANCELLED' ? 'bi-exclamation-triangle-fill' : 'bi-question-circle-fill'"></i>
                            </div>
                            <h6 class="mb-2">{{ finalConfirmTitle }}</h6>
                            <p class="text-muted small mb-0">{{ finalConfirmMessage }}</p>
                        </div>

                        <template v-else-if="status === 'CANCELLED'">
                            <p class="text-muted small mb-3">
                                Please enter when this trip was cancelled, the end meter reading, and why.
                            </p>
                            <div class="mb-3">
                                <label class="form-label">Start Meter Reading</label>
                                <input :value="formatMeter(startMeterReadingKm)" type="text" class="form-control bg-light" readonly />
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Truck Running Meter Reading</label>
                                <input :value="formatMeter(truckRunningMeterKm)" type="text" class="form-control bg-light" readonly />
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
                                <input
                                    v-model="cancelledAt"
                                    type="date"
                                    class="form-control"
                                    :class="{ 'is-invalid': fieldErrors.cancelled_at }"
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
                        </template>

                        <template v-else-if="!awaitingFinalConfirm && status === 'COMPLETED'">
                            <p class="text-muted small mb-3">
                                Confirm trip completion dates and meter reading before saving.
                            </p>
                            <div class="mb-3">
                                <label class="form-label">Start Date</label>
                                <input :value="formatDate(startAt)" type="text" class="form-control bg-light" readonly />
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Truck Running Meter Reading</label>
                                <input :value="formatMeter(truckRunningMeterKm)" type="text" class="form-control bg-light" readonly />
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Start Meter Reading</label>
                                <input :value="formatMeter(startMeterReadingKm)" type="text" class="form-control bg-light" readonly />
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
                                <input
                                    v-model="actualArrivalAt"
                                    type="date"
                                    class="form-control"
                                    :class="{ 'is-invalid': fieldErrors.actual_arrival_at }"
                                    :min="actualArrivalMin"
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
                                <input
                                    v-model="endAt"
                                    type="date"
                                    class="form-control"
                                    :class="{ 'is-invalid': fieldErrors.end_at }"
                                    :min="endDateMin"
                                    required
                                />
                                <div class="form-text">Must be on or after the actual arrival date.</div>
                                <div v-if="fieldErrors.end_at" class="invalid-feedback d-block">
                                    {{ fieldErrors.end_at }}
                                </div>
                            </div>
                        </template>
                    </div>
                    <div class="modal-footer">
                        <template v-if="awaitingFinalConfirm">
                            <button type="button" class="btn btn-secondary" @click="backToForm">
                                No, Go Back
                            </button>
                            <AppButton
                                type="button"
                                label="OK"
                                :variant="status === 'CANCELLED' ? 'danger' : 'success'"
                                @click="finalConfirm"
                            />
                        </template>
                        <template v-else>
                            <button type="button" class="btn btn-secondary" @click="$emit('cancel')">Back</button>
                            <AppButton
                                type="submit"
                                :label="status === 'CANCELLED' ? 'Confirm Cancellation' : 'Confirm Completion'"
                                :variant="status === 'CANCELLED' ? 'danger' : 'success'"
                            />
                        </template>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { toDateInputFromIso, toDateInputValue, formatDate } from '../../utils/datetime';
import { formatMeterKm } from '../../utils/formatMeter';
import AppButton from '../ui/AppButton.vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    status: { type: String, default: '' },
    startAt: { type: String, default: '' },
    endAtValue: { type: String, default: '' },
    actualArrivalAtValue: { type: String, default: '' },
    cancelledAtValue: { type: String, default: '' },
    cancellationReasonValue: { type: String, default: '' },
    startMeterReadingKm: { type: [Number, String], default: '' },
    truckRunningMeterKm: { type: [Number, String], default: '' },
    endMeterReadingKmValue: { type: [Number, String], default: '' },
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

const modalTitle = computed(() => (props.status === 'CANCELLED' ? 'Cancel Trip' : 'Complete Trip'));

const finalConfirmTitle = computed(() =>
    props.status === 'CANCELLED' ? 'Cancel this trip?' : 'Mark trip as completed?',
);

const finalConfirmMessage = computed(() =>
    props.status === 'CANCELLED'
        ? 'This will save the trip as cancelled. Click OK to confirm.'
        : 'This will save the trip as completed. Click OK to confirm.',
);

const tripStartDateMin = computed(() => toDateInputFromIso(props.startAt));

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

function formatMeter(value) {
    return formatMeterKm(value);
}

function initCompletionDates() {
    const startMin = tripStartDateMin.value;
    const existingEnd = toDateInputFromIso(props.endAtValue);
    const existingArrival = toDateInputFromIso(props.actualArrivalAtValue);
    const today = toDateInputValue();

    actualArrivalAt.value = existingArrival || startMin || today;

    if (startMin && actualArrivalAt.value < startMin) {
        actualArrivalAt.value = startMin;
    }

    endAt.value = existingEnd || startMin || today;

    if (startMin && endAt.value < startMin) {
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
        }
    },
);

function validateEndMeterReading() {
    const endMeter = Number(endMeterReadingKm.value);
    const startMeter = Number(props.startMeterReadingKm ?? 0);
    const truckMeter = Number(props.truckRunningMeterKm ?? 0);

    if (!Number.isFinite(endMeter) || endMeter <= 0) {
        fieldErrors.end_meter_reading_km = 'End meter reading is required.';

        return;
    }

    if (Number.isFinite(startMeter) && startMeter > 0 && endMeter < startMeter) {
        fieldErrors.end_meter_reading_km = 'End Meter Reading cannot be less than Start Meter Reading.';

        return;
    }

    if (Number.isFinite(truckMeter) && truckMeter > 0 && endMeter < truckMeter) {
        fieldErrors.end_meter_reading_km =
            'End Meter Reading cannot be less than the current Truck Running Meter Reading. Please enter a valid meter reading.';
    }
}

function validate() {
    formError.value = '';
    Object.keys(fieldErrors).forEach((key) => delete fieldErrors[key]);

    if (props.status === 'CANCELLED') {
        if (!cancelledAt.value) {
            fieldErrors.cancelled_at = 'Cancellation date is required.';
        }

        if (!cancellationReason.value.trim()) {
            fieldErrors.cancellation_reason = 'Please provide a reason for cancelling this trip.';
        }

        validateEndMeterReading();
    }

    if (props.status === 'COMPLETED') {
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
.status-final-confirm__icon {
    font-size: 2.5rem;
    line-height: 1;
}
</style>
