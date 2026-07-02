<template>
    <div
        v-if="show"
        class="modal fade show d-block"
        tabindex="-1"
        style="background: rgba(0, 0, 0, 0.45)"
        @click.self="$emit('cancel')"
    >
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-gradient-primary">
                    <h5 class="modal-title">
                        <i class="bi bi-signpost-split me-2"></i>Select Trip Type
                    </h5>
                    <button type="button" class="btn-close btn-close-white" @click="$emit('cancel')"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small mb-3">
                        Choose the type of trip before continuing to the create form.
                    </p>
                    <div v-if="formError" class="alert alert-danger py-2">{{ formError }}</div>
                    <div class="d-grid gap-2">
                        <button
                            type="button"
                            class="trip-type-option"
                            :class="{ 'trip-type-option--selected': selectedType === 'ROUND_TRIP' }"
                            @click="selectedType = 'ROUND_TRIP'"
                        >
                            <span class="trip-type-option__icon">
                                <i class="bi bi-arrow-repeat"></i>
                            </span>
                            <span class="trip-type-option__body">
                                <strong>Round Trip</strong>
                                <span class="trip-type-option__desc">Start → stops → return to start station</span>
                            </span>
                            <i
                                v-if="selectedType === 'ROUND_TRIP'"
                                class="bi bi-check-circle-fill trip-type-option__check"
                            ></i>
                        </button>
                        <button
                            type="button"
                            class="trip-type-option"
                            :class="{ 'trip-type-option--selected': selectedType === 'ONE_WAY' }"
                            @click="selectedType = 'ONE_WAY'"
                        >
                            <span class="trip-type-option__icon">
                                <i class="bi bi-arrow-right"></i>
                            </span>
                            <span class="trip-type-option__body">
                                <strong>One Way Trip</strong>
                                <span class="trip-type-option__desc">Start → one or more stops (no return required)</span>
                            </span>
                            <i
                                v-if="selectedType === 'ONE_WAY'"
                                class="bi bi-check-circle-fill trip-type-option__check"
                            ></i>
                        </button>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" @click="$emit('cancel')">Cancel</button>
                    <AppButton type="button" label="Continue" variant="success" @click="confirm" />
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, watch } from 'vue';
import AppButton from '../ui/AppButton.vue';

defineProps({
    show: { type: Boolean, default: false },
});

const emit = defineEmits(['cancel', 'confirm']);

const selectedType = ref('');
const formError = ref('');

watch(
    () => selectedType.value,
    () => {
        formError.value = '';
    },
);

function confirm() {
    if (!selectedType.value) {
        formError.value = 'Please select a trip type to continue.';

        return;
    }

    emit('confirm', selectedType.value);
    selectedType.value = '';
    formError.value = '';
}
</script>

<style scoped>
.trip-type-option {
    display: flex;
    align-items: center;
    gap: 0.85rem;
    width: 100%;
    padding: 0.9rem 1rem;
    text-align: left;
    background: #fff;
    border: 2px solid #dee2e6;
    border-radius: 12px;
    color: #212529;
    font-size: 1rem;
    font-weight: 400;
    box-shadow: none;
    transition: background 0.15s, border-color 0.15s, box-shadow 0.15s;
    cursor: pointer;
}

.trip-type-option:hover {
    background: #f8f9fa;
    border-color: #ced4da;
}

.trip-type-option--selected {
    background: #e8f5ee;
    border-color: #2fb36f;
    box-shadow: 0 0 0 3px rgba(47, 179, 111, 0.18);
}

.trip-type-option--selected:hover {
    background: #dff0e7;
    border-color: #23965c;
}

.trip-type-option__icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 2.25rem;
    height: 2.25rem;
    flex-shrink: 0;
    border-radius: 0.5rem;
    background: #e9ecef;
    color: #495057;
    font-size: 1.1rem;
}

.trip-type-option--selected .trip-type-option__icon {
    background: #2fb36f;
    color: #fff;
}

.trip-type-option__body {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 0.15rem;
    flex: 1;
    min-width: 0;
}

.trip-type-option__body strong {
    font-size: 1.05rem;
    color: #212529;
}

.trip-type-option--selected .trip-type-option__body strong {
    color: #1a6b42;
}

.trip-type-option__desc {
    font-size: 0.85rem;
    color: #6c757d;
    line-height: 1.35;
}

.trip-type-option--selected .trip-type-option__desc {
    color: #3d7a57;
}

.trip-type-option__check {
    flex-shrink: 0;
    font-size: 1.35rem;
    color: #2fb36f;
}
</style>
