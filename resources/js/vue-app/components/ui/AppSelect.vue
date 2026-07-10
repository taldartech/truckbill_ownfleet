<template>
    <SearchableSelect
        :input-id="inputId"
        :model-value="modelValue"
        :items="selectItems"
        :placeholder="placeholder"
        :search-placeholder="searchPlaceholder"
        :empty-message="emptyMessage"
        :attach-dropdown-to-body="attachDropdownToBody"
        :disabled="disabled"
        :invalid="invalid"
        :error-message="errorMessage"
        :editable="editable"
        :input-mode="inputMode"
        :max-length="maxLength"
        :clearable="clearable"
        @update:model-value="onModelValueUpdate"
        @blur="emit('blur', $event)"
    />
</template>

<script setup>
import { computed } from 'vue';
import { buildSimpleSelectOptions } from '../../utils/buildSelectOptions';
import SearchableSelect from './SearchableSelect.vue';

const props = defineProps({
    modelValue: { type: [String, Number], default: '' },
    options: { type: Array, default: () => [] },
    inputId: { type: String, default: '' },
    placeholder: { type: String, default: 'Select…' },
    searchPlaceholder: { type: String, default: 'Search…' },
    emptyMessage: { type: String, default: 'No results found.' },
    disabled: { type: Boolean, default: false },
    invalid: { type: Boolean, default: false },
    errorMessage: { type: String, default: '' },
    attachDropdownToBody: { type: Boolean, default: true },
    editable: { type: Boolean, default: false },
    inputMode: { type: String, default: 'text' },
    maxLength: { type: Number, default: null },
    clearable: { type: Boolean, default: true },
});

const emit = defineEmits(['update:modelValue', 'change', 'blur']);

const selectItems = computed(() => buildSimpleSelectOptions(props.options));

function onModelValueUpdate(value) {
    emit('update:modelValue', value);
    emit('change', value);
}
</script>
