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
                    <h5 class="modal-title">
                        <i class="bi" :class="status === 'CANCELLED' ? 'bi-x-circle' : 'bi-check-circle'"></i>
                        {{ modalTitle }}
                    </h5>
                    <button type="button" class="btn-close btn-close-white" @click="$emit('cancel')"></button>
                </div>
                <form @submit.prevent="submit">
                    <div class="modal-body">
                        <div v-if="formError" class="alert alert-danger py-2">{{ formError }}</div>

                        <template v-if="status === 'CANCELLED'">
                            <p class="text-muted small mb-3">
                                Please enter when this trip was cancelled and why.
                            </p>
                            <div class="mb-3">
                                <label class="form-label">Cancellation Date &amp; Time *</label>
                                <input
                                    v-model="cancelledAt"
                                    type="datetime-local"
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

                        <template v-else-if="status === 'COMPLETED'">
                            <p class="text-muted small mb-3">
                                Confirm trip completion details before saving.
                            </p>
                            <div class="mb-3">
                                <label class="form-label">Start Date</label>
                                <input :value="formatDisplayDate(startAt)" type="text" class="form-control bg-light" readonly />
                            </div>
                            <div class="mb-3">
                                <label class="form-label">End Date *</label>
                                <input
                                    v-model="endAt"
                                    type="datetime-local"
                                    class="form-control"
                                    :class="{ 'is-invalid': fieldErrors.end_at }"
                                    required
                                />
                                <div v-if="fieldErrors.end_at" class="invalid-feedback d-block">
                                    {{ fieldErrors.end_at }}
                                </div>
                            </div>
                            <div class="mb-0">
                                <label class="form-label">Actual Arrival Date &amp; Time *</label>
                                <input
                                    v-model="actualArrivalAt"
                                    type="datetime-local"
                                    class="form-control"
                                    :class="{ 'is-invalid': fieldErrors.actual_arrival_at }"
                                    required
                                />
                                <div class="form-text">When did the vehicle actually arrive back?</div>
                                <div v-if="fieldErrors.actual_arrival_at" class="invalid-feedback d-block">
                                    {{ fieldErrors.actual_arrival_at }}
                                </div>
                            </div>
                        </template>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" @click="$emit('cancel')">Back</button>
                        <AppButton
                            type="submit"
                            :label="status === 'CANCELLED' ? 'Confirm Cancellation' : 'Confirm Completion'"
                            :variant="status === 'CANCELLED' ? 'danger' : 'success'"
                        />
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue';
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

const modalTitle = computed(() => (props.status === 'CANCELLED' ? 'Cancel Trip' : 'Complete Trip'));

watch(
    () => props.show,
    (visible) => {
        if (!visible) {
            return;
        }

        formError.value = '';
        Object.keys(fieldErrors).forEach((key) => delete fieldErrors[key]);

        if (props.status === 'CANCELLED') {
            cancelledAt.value = props.cancelledAtValue || nowDatetimeLocal();
            cancellationReason.value = props.cancellationReasonValue || '';
        } else if (props.status === 'COMPLETED') {
            endAt.value = props.endAtValue || nowDatetimeLocal();
            actualArrivalAt.value = props.actualArrivalAtValue || nowDatetimeLocal();
        }
    },
);

function nowDatetimeLocal() {
    const date = new Date();
    const pad = (n) => String(n).padStart(2, '0');

    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
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

function validate() {
    formError.value = '';
    Object.keys(fieldErrors).forEach((key) => delete fieldErrors[key]);

    if (props.status === 'CANCELLED') {
        if (!cancelledAt.value) {
            fieldErrors.cancelled_at = 'Cancellation date and time is required.';
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

function submit() {
    if (!validate()) {
        formError.value = 'Please fix the errors below.';

        return;
    }

    if (props.status === 'CANCELLED') {
        emit('confirm', {
            cancelled_at: cancelledAt.value,
            cancellation_reason: cancellationReason.value.trim(),
        });

        return;
    }

    emit('confirm', {
        end_at: endAt.value,
        actual_arrival_at: actualArrivalAt.value,
    });
}
</script>
