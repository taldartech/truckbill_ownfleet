<template>
    <SearchableSelect
        :input-id="inputId"
        :model-value="modelValue"
        :items="selectOptions"
        :placeholder="placeholder"
        search-placeholder="Search truck number…"
        empty-message="No truck found."
        :attach-dropdown-to-body="attachDropdownToBody"
        :disabled="disabled"
        :invalid="invalid"
        :error-message="errorMessage"
        @update:model-value="onModelValueUpdate"
    />
</template>

<script setup>
import { computed } from 'vue';
import { buildVehicleSelectOptions } from '../../api/vehicles';
import { mergeLookupOption } from '../../utils/mergeLookupOption';
import SearchableSelect from './SearchableSelect.vue';

const props = defineProps({
    modelValue: { type: [String, Number], default: '' },
    vehicles: { type: Array, default: () => [] },
    lookupVehicle: { type: Object, default: null },
    inputId: { type: String, default: '' },
    placeholder: { type: String, default: 'Select truck' },
    disabled: { type: Boolean, default: false },
    invalid: { type: Boolean, default: false },
    errorMessage: { type: String, default: '' },
    attachDropdownToBody: { type: Boolean, default: true },
});

const emit = defineEmits(['update:modelValue', 'vehicle-selected']);

const mergedVehicles = computed(() => mergeLookupOption(props.vehicles, props.lookupVehicle));

const selectOptions = computed(() => buildVehicleSelectOptions(mergedVehicles.value));

function findVehicle(vehicleId) {
    if (vehicleId === '' || vehicleId == null) {
        return null;
    }

    return mergedVehicles.value.find((vehicle) => String(vehicle.id) === String(vehicleId)) ?? null;
}

function onModelValueUpdate(value) {
    emit('update:modelValue', value);
    emit('vehicle-selected', findVehicle(value));
}
</script>
