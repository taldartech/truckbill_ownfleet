<template>
    <div class="col-12">
        <label
            class="broker-party-toggle"
            :class="{
                'broker-party-toggle--on': modelValue,
                'broker-party-toggle--invalid': invalid,
            }"
            :for="inputId"
        >
            <span class="broker-party-toggle__icon" aria-hidden="true">
                <i class="bi bi-briefcase"></i>
            </span>
            <span class="broker-party-toggle__copy">
                <span class="broker-party-toggle__title">Broker</span>
                <span class="broker-party-toggle__hint">
                    GST will not be applied on invoices for this customer.
                </span>
            </span>
            <span class="form-check form-switch broker-party-toggle__switch mb-0">
                <input
                    :id="inputId"
                    class="form-check-input"
                    type="checkbox"
                    role="switch"
                    :checked="modelValue"
                    :aria-invalid="invalid ? 'true' : undefined"
                    @change="$emit('update:modelValue', $event.target.checked)"
                />
            </span>
        </label>
        <div v-if="errorMessage" class="invalid-feedback d-block">
            {{ errorMessage }}
        </div>
    </div>
</template>

<script setup>
defineProps({
    modelValue: { type: Boolean, default: false },
    inputId: { type: String, default: 'customer_is_broker' },
    invalid: { type: Boolean, default: false },
    errorMessage: { type: String, default: '' },
});

defineEmits(['update:modelValue']);
</script>

<style scoped>
.broker-party-toggle {
    display: flex;
    align-items: center;
    gap: 0.85rem;
    width: 100%;
    margin: 0;
    padding: 0.85rem 1rem;
    border: 1px solid #e2e8f0;
    border-radius: 0.65rem;
    background: #f8fafc;
    cursor: pointer;
    transition:
        border-color 0.15s ease,
        background-color 0.15s ease,
        box-shadow 0.15s ease;
}

.broker-party-toggle:hover {
    border-color: #cbd5e1;
    background: #f1f5f9;
}

.broker-party-toggle--on {
    border-color: #93c5fd;
    background: #eff6ff;
    box-shadow: inset 0 0 0 1px rgba(59, 130, 246, 0.12);
}

.broker-party-toggle--invalid {
    border-color: #f87171;
}

.broker-party-toggle__icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    width: 2.25rem;
    height: 2.25rem;
    border-radius: 0.5rem;
    background: #e2e8f0;
    color: #475569;
    font-size: 1rem;
}

.broker-party-toggle--on .broker-party-toggle__icon {
    background: #dbeafe;
    color: #1d4ed8;
}

.broker-party-toggle__copy {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
    flex: 1 1 auto;
    min-width: 0;
}

.broker-party-toggle__title {
    font-size: 0.9rem;
    font-weight: 700;
    color: #0f172a;
    line-height: 1.25;
}

.broker-party-toggle__hint {
    font-size: 0.75rem;
    font-weight: 500;
    color: #64748b;
    line-height: 1.35;
}

.broker-party-toggle__switch {
    flex-shrink: 0;
    padding-left: 2.4rem;
    min-height: 1.5rem;
}

.broker-party-toggle__switch .form-check-input {
    width: 2.4rem;
    height: 1.3rem;
    margin-left: -2.4rem;
    cursor: pointer;
}

.broker-party-toggle--on .broker-party-toggle__switch .form-check-input {
    background-color: #2563eb;
    border-color: #2563eb;
}
</style>
