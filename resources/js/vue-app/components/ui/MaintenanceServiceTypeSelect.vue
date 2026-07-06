<template>
    <SearchableSelect
        :input-id="inputId"
        :model-value="modelValue"
        :items="selectOptions"
        :placeholder="placeholder"
        search-placeholder="Search service type…"
        empty-message="No service type found."
        :attach-dropdown-to-body="attachDropdownToBody"
        :disabled="disabled"
        :invalid="invalid"
        :error-message="errorMessage"
        @update:model-value="onModelValueUpdate"
    />
</template>

<script setup>
import { computed } from 'vue';
import { buildMaintenanceServiceTypeSelectOptions } from '../../api/maintenanceServiceTypes';
import { mergeLookupOption } from '../../utils/mergeLookupOption';
import SearchableSelect from './SearchableSelect.vue';

const props = defineProps({
    modelValue: { type: [String, Number], default: '' },
    serviceTypes: { type: Array, default: () => [] },
    lookupServiceType: { type: Object, default: null },
    inputId: { type: String, default: '' },
    placeholder: { type: String, default: 'Select type' },
    disabled: { type: Boolean, default: false },
    invalid: { type: Boolean, default: false },
    errorMessage: { type: String, default: '' },
    attachDropdownToBody: { type: Boolean, default: true },
});

const emit = defineEmits(['update:modelValue', 'service-type-selected']);

const mergedServiceTypes = computed(() => mergeLookupOption(props.serviceTypes, props.lookupServiceType));

const selectOptions = computed(() => buildMaintenanceServiceTypeSelectOptions(mergedServiceTypes.value));

function findServiceType(serviceTypeId) {
    if (serviceTypeId === '' || serviceTypeId == null) {
        return null;
    }

    return mergedServiceTypes.value.find((type) => String(type.id) === String(serviceTypeId)) ?? null;
}

function onModelValueUpdate(value) {
    emit('update:modelValue', value);
    emit('service-type-selected', findServiceType(value));
}
</script>
