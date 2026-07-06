<template>
    <div class="col-md-6">
        <label class="form-label">State *</label>
        <SearchableSelect
            :model-value="stateId"
            :items="states"
            placeholder="Select state"
            search-placeholder="Search state…"
            :invalid="Boolean(fieldErrors.state_id)"
            :error-message="fieldErrors.state_id?.[0]"
            @update:model-value="$emit('update:stateId', $event)"
        />
    </div>
    <div class="col-md-6">
        <label class="form-label">City *</label>
        <SearchableSelect
            :model-value="cityId"
            :items="cities"
            placeholder="Select city"
            search-placeholder="Search city…"
            :disabled="!stateId"
            :invalid="Boolean(fieldErrors.city_id)"
            :error-message="fieldErrors.city_id?.[0]"
            @update:model-value="$emit('update:cityId', $event)"
        />
    </div>
</template>

<script setup>
import SearchableSelect from '../ui/SearchableSelect.vue';

defineEmits(['update:stateId', 'update:cityId']);

defineProps({
    states: { type: Array, default: () => [] },
    cities: { type: Array, default: () => [] },
    stateId: { type: [String, Number], default: '' },
    cityId: { type: [String, Number], default: '' },
    fieldErrors: { type: Object, default: () => ({}) },
});
</script>
