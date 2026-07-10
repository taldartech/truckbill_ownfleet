<template>
    <div class="number-stepper d-inline-flex align-items-center">
        <button
            type="button"
            class="btn btn-outline-secondary btn-sm number-stepper__btn"
            :disabled="disabled || modelValue <= min"
            :aria-label="`Decrease ${label}`"
            @click="decrement"
        >
            <i class="fas fa-minus" aria-hidden="true"></i>
        </button>
        <span class="number-stepper__value" :aria-label="`${label}: ${modelValue}`">{{ modelValue }}</span>
        <button
            type="button"
            class="btn btn-outline-secondary btn-sm number-stepper__btn"
            :disabled="disabled || modelValue >= max"
            :aria-label="`Increase ${label}`"
            @click="increment"
        >
            <i class="fas fa-plus" aria-hidden="true"></i>
        </button>
    </div>
</template>

<script setup>
const props = defineProps({
    modelValue: { type: Number, default: 0 },
    min: { type: Number, default: 0 },
    max: { type: Number, default: 99 },
    step: { type: Number, default: 1 },
    disabled: { type: Boolean, default: false },
    label: { type: String, default: 'value' },
});

const emit = defineEmits(['update:modelValue']);

function clamp(value) {
    return Math.min(props.max, Math.max(props.min, value));
}

function decrement() {
    emit('update:modelValue', clamp(props.modelValue - props.step));
}

function increment() {
    emit('update:modelValue', clamp(props.modelValue + props.step));
}
</script>

<style scoped>
.number-stepper {
    gap: 0.35rem;
}

.number-stepper__btn {
    width: 1.75rem;
    height: 1.75rem;
    padding: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.number-stepper__value {
    min-width: 2rem;
    text-align: center;
    font-weight: 600;
    font-variant-numeric: tabular-nums;
}
</style>
