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
                                Please enter when this trip was cancelled and why.
                            </p>
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
                                Confirm trip completion dates before saving.
                            </p>
                            <div class="mb-3">
                                <label class="form-label">Start Date</label>
                                <input :value="formatDate(startAt)" type="text" class="form-control bg-light" readonly />
                            </div>
                            <div class="mb-3">
                                <label class="form-label">End Date *</label>
                                <input
                                    v-model="endAt"
                                    type="date"
                                    class="form-control"
                                    :class="{ 'is-invalid': fieldErrors.end_at }"
                                    required
                                />
                                <div v-if="fieldErrors.end_at" class="invalid-feedback d-block">
                                    {{ fieldErrors.end_at }}
                                </div>
                            </div>
                            <div class="mb-0">
                                <label class="form-label">Actual Arrival Date *</label>
                                <input
                                    v-model="actualArrivalAt"
                                    type="date"
                                    class="form-control"
                                    :class="{ 'is-invalid': fieldErrors.actual_arrival_at }"
                                    required
                                />
                                <div class="form-text">When did the truck actually arrive back?</div>
                                <div v-if="fieldErrors.actual_arrival_at" class="invalid-feedback d-block">
                                    {{ fieldErrors.actual_arrival_at }}
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
import AppButton from '../ui/AppButton.vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    status: { type: String, default: '' },
    startAt: { type: String, default: '' },
    endAtValue: { type: String, default: '' },
    actualArrivalAtValue: { type: String, default: '' },
    cancelledAtValue: { type: String, default: '' },
    cancellationReasonValue: { type: String, default: '' },
});

const emit = defineEmits(['cancel', 'confirm']);

const cancelledAt = ref('');
const cancellationReason = ref('');
const endAt = ref('');
const actualArrivalAt = ref('');
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
        } else if (props.status === 'COMPLETED') {
            endAt.value = toDateInputFromIso(props.endAtValue) || toDateInputValue();
            actualArrivalAt.value = toDateInputFromIso(props.actualArrivalAtValue) || toDateInputValue();
        }
    },
);

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
    }

    if (props.status === 'COMPLETED') {
        if (!endAt.value) {
            fieldErrors.end_at = 'End date is required to complete the trip.';
        }

        if (!actualArrivalAt.value) {
            fieldErrors.actual_arrival_at = 'Actual arrival date is required.';
        }

        if (props.startAt && endAt.value && endAt.value < props.startAt) {
            fieldErrors.end_at = 'End date cannot be before start date.';
        }

        if (props.startAt && actualArrivalAt.value && actualArrivalAt.value < props.startAt) {
            fieldErrors.actual_arrival_at = 'Actual arrival cannot be before start date.';
        }
    }

    return Object.keys(fieldErrors).length === 0;
}

function buildConfirmPayload() {
    if (props.status === 'CANCELLED') {
        return {
            cancelled_at: cancelledAt.value,
            cancellation_reason: cancellationReason.value.trim(),
        };
    }

    return {
        end_at: endAt.value,
        actual_arrival_at: actualArrivalAt.value,
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
