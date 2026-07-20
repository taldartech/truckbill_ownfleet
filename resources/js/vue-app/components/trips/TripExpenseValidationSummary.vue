<template>
    <div v-if="items.length > 0" class="expense-validation-summary">
        <div class="expense-validation-summary__row">
            <i class="bi bi-exclamation-circle-fill" aria-hidden="true"></i>
            <span class="expense-validation-summary__count">
                {{ items.length }} Issue{{ items.length === 1 ? '' : 's' }}
            </span>
            <span class="expense-validation-summary__sep" aria-hidden="true">·</span>
            <div class="expense-validation-summary__chips">
                <button
                    v-for="item in items"
                    :key="item.key"
                    type="button"
                    class="expense-validation-summary__chip"
                    :title="`${item.label}: ${item.message}`"
                    @click="$emit('focus-field', item)"
                >
                    <span class="expense-validation-summary__chip-label">{{ item.label }}</span>
                    <span class="expense-validation-summary__chip-msg">{{ item.message }}</span>
                </button>
            </div>
            <span class="expense-validation-summary__hint">Click To Jump</span>
        </div>
    </div>
</template>

<script setup>
defineProps({
    items: {
        type: Array,
        default: () => [],
    },
});

defineEmits(['focus-field']);
</script>

<style scoped>
.expense-validation-summary {
    border: 1px solid #fca5a5;
    border-radius: 6px;
    background: #fff5f5;
    padding: 0.35rem 0.6rem;
    margin-bottom: 0.55rem;
}

.expense-validation-summary__row {
    display: flex;
    flex-wrap: nowrap;
    align-items: center;
    gap: 0.4rem;
    min-width: 0;
    font-size: 0.75rem;
    color: #b91c1c;
}

.expense-validation-summary__row .bi {
    flex-shrink: 0;
    font-size: 0.8rem;
}

.expense-validation-summary__count {
    flex-shrink: 0;
    font-weight: 700;
    white-space: nowrap;
}

.expense-validation-summary__sep {
    flex-shrink: 0;
    color: #f87171;
}

.expense-validation-summary__chips {
    display: flex;
    flex: 1 1 auto;
    flex-wrap: nowrap;
    align-items: center;
    gap: 0.35rem;
    min-width: 0;
    overflow: hidden;
}

.expense-validation-summary__chip {
    display: inline-flex;
    align-items: baseline;
    gap: 0.35rem;
    min-width: 0;
    max-width: 100%;
    text-align: left;
    border: 1px solid #fecaca;
    border-radius: 999px;
    background: #fff;
    padding: 0.12rem 0.55rem;
    cursor: pointer;
    transition: border-color 0.12s, background 0.12s;
}

.expense-validation-summary__chip:hover,
.expense-validation-summary__chip:focus-visible {
    border-color: #ef4444;
    background: #fef2f2;
    outline: none;
}

.expense-validation-summary__chip-label {
    flex-shrink: 0;
    font-size: 0.66rem;
    font-weight: 700;
    color: #991b1b;
    letter-spacing: 0.02em;
    white-space: nowrap;
}

.expense-validation-summary__chip-msg {
    font-size: 0.72rem;
    font-weight: 600;
    color: #374151;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    min-width: 0;
}

.expense-validation-summary__hint {
    flex-shrink: 0;
    margin-left: auto;
    color: #9ca3af;
    font-size: 0.66rem;
    white-space: nowrap;
}

@media (max-width: 575.98px) {
    .expense-validation-summary__hint {
        display: none;
    }

    .expense-validation-summary__row {
        flex-wrap: wrap;
    }
}
</style>
