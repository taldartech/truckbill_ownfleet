<template>
    <button
        type="button"
        class="btn btn-brand-secondary btn-sm app-index-header-btn"
        :aria-expanded="ariaExpanded"
        :aria-controls="ariaControls"
        :aria-label="ariaLabel"
        :disabled="disabled"
        v-bind="$attrs"
        @click="$emit('click', $event)"
    >
        <span
            v-if="icon"
            class="app-index-header-btn__icon"
            :class="`app-index-header-btn__icon--${icon}`"
            aria-hidden="true"
        ></span>
        <slot />
    </button>
</template>

<script setup>
defineOptions({
    inheritAttrs: false,
});

defineProps({
    icon: {
        type: String,
        default: '',
        validator: (value) => !value || ['filter', 'plus', 'export', 'return', 'grid', 'import'].includes(value),
    },
    ariaExpanded: {
        type: [Boolean, String],
        default: undefined,
    },
    ariaControls: {
        type: String,
        default: '',
    },
    ariaLabel: {
        type: String,
        default: '',
    },
    disabled: {
        type: Boolean,
        default: false,
    },
});

defineEmits(['click']);
</script>
