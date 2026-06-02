<template>
    <component
        :is="tag"
        :type="tag === 'button' ? type : undefined"
        :href="tag === 'a' ? href : undefined"
        :to="tag === 'router-link' ? to : undefined"
        :class="buttonClasses"
        :disabled="disabled || loading"
        v-bind="$attrs"
    >
        <span v-if="loading" class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>
        <i v-if="icon && !loading" :class="[icon, label ? 'me-1' : '']"></i>
        <slot>{{ label }}</slot>
    </component>
</template>

<script setup>
import { computed } from 'vue';
import { RouterLink } from 'vue-router';
import { appButtonClasses } from '../../utils/appButtonClasses';

defineOptions({
    inheritAttrs: false,
});

const props = defineProps({
    variant: {
        type: String,
        default: 'primary',
    },
    size: {
        type: String,
        default: '',
    },
    outline: {
        type: Boolean,
        default: false,
    },
    type: {
        type: String,
        default: 'button',
    },
    label: {
        type: String,
        default: '',
    },
    icon: {
        type: String,
        default: '',
    },
    disabled: {
        type: Boolean,
        default: false,
    },
    loading: {
        type: Boolean,
        default: false,
    },
    href: {
        type: String,
        default: '',
    },
    to: {
        type: [String, Object],
        default: null,
    },
});

const tag = computed(() => {
    if (props.to) {
        return RouterLink;
    }

    if (props.href) {
        return 'a';
    }

    return 'button';
});

const buttonClasses = computed(() =>
    appButtonClasses({
        variant: props.variant,
        size: props.size,
        outline: props.outline,
    }),
);
</script>
