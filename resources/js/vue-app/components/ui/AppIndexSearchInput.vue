<template>
    <div
        class="input-group app-index-search"
        :class="{ 'input-group-sm': size === 'sm' }"
        :style="wrapperStyle"
    >
        <span class="input-group-text"><i class="bi bi-search" aria-hidden="true"></i></span>
        <input
            :id="inputId"
            :value="modelValue"
            type="search"
            class="form-control"
            :placeholder="placeholder"
            :aria-label="ariaLabel || placeholder"
            :disabled="disabled"
            @input="$emit('update:modelValue', $event.target.value)"
            @keydown.enter="$emit('keydown.enter', $event)"
            @search="$emit('update:modelValue', $event.target.value)"
        />
    </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    modelValue: {
        type: String,
        default: '',
    },
    inputId: {
        type: String,
        default: '',
    },
    placeholder: {
        type: String,
        default: 'Search…',
    },
    ariaLabel: {
        type: String,
        default: '',
    },
    disabled: {
        type: Boolean,
        default: false,
    },
    maxWidth: {
        type: String,
        default: '280px',
    },
    size: {
        type: String,
        default: 'sm',
        validator: (value) => ['sm', 'md'].includes(value),
    },
});

defineEmits(['update:modelValue', 'keydown.enter']);

const wrapperStyle = computed(() => (props.maxWidth ? { maxWidth: props.maxWidth } : undefined));
</script>
