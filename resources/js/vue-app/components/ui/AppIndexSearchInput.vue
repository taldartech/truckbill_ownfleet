<template>
    <div class="input-group input-group-sm app-index-search" :style="wrapperStyle">
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
});

defineEmits(['update:modelValue', 'keydown.enter']);

const wrapperStyle = computed(() => (props.maxWidth ? { maxWidth: props.maxWidth } : undefined));
</script>
