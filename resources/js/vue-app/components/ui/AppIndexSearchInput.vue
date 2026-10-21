<template>
    <div
        v-if="showIcon"
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
    <div
        v-else
        class="app-index-search"
        :style="wrapperStyle"
    >
        <input
            :id="inputId"
            :value="modelValue"
            type="search"
            class="form-control"
            :class="{ 'form-control-sm': size === 'sm' }"
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
    showIcon: {
        type: Boolean,
        default: false,
    },
});

defineEmits(['update:modelValue', 'keydown.enter']);

const wrapperStyle = computed(() => (props.maxWidth ? { maxWidth: props.maxWidth } : undefined));
</script>
