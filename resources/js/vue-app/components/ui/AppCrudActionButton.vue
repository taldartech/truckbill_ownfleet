<template>
    <button
        type="button"
        :class="buttonClasses"
        :disabled="disabled"
        :title="buttonTitle"
        :aria-label="buttonTitle"
        v-bind="$attrs"
    >
        <i :class="preset.icon" aria-hidden="true"></i>
    </button>
</template>

<script setup>
import { computed } from 'vue';
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
});

const preset = computed(() => CRUD_ACTION_PRESETS[props.action]);

const buttonClasses = computed(() => crudActionButtonClasses(props.action));

const buttonTitle = computed(() => props.title || preset.value.title);
</script>
