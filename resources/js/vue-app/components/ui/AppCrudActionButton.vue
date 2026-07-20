<template>
    <component
        :is="rootTag"
        v-bind="{ ...$attrs, ...rootBindings }"
        :class="buttonClasses"
        :disabled="isDisabled"
        :title="buttonTitle"
        :aria-label="buttonTitle"
    >
        <i :class="preset.icon" aria-hidden="true"></i>
    </component>
</template>

<script setup>
import { computed } from 'vue';
import { RouterLink } from 'vue-router';
import { CRUD_ACTION_PRESETS, crudActionButtonClasses } from '../../utils/crudActionPresets';

defineOptions({
    inheritAttrs: false,
});

const props = defineProps({
    action: {
        type: String,
        required: true,
        validator: (value) => value in CRUD_ACTION_PRESETS,
    },
    title: {
        type: String,
        default: '',
    },
    disabled: {
        type: Boolean,
        default: false,
    },
    to: {
        type: [String, Object],
        default: null,
    },
});

const preset = computed(() => CRUD_ACTION_PRESETS[props.action]);

const buttonClasses = computed(() => crudActionButtonClasses(props.action));

const buttonTitle = computed(() => props.title || preset.value.title);

const isLink = computed(() => Boolean(props.to) && !props.disabled);

const rootTag = computed(() => (isLink.value ? RouterLink : 'button'));

const isDisabled = computed(() => (isLink.value ? undefined : props.disabled));

const rootBindings = computed(() => {
    if (isLink.value) {
        return { to: props.to };
    }

    return { type: 'button' };
});
</script>
