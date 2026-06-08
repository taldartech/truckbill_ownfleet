<template>
    <div>
        <div class="mb-3">
            <label class="form-label">Country</label>
            <input type="text" class="form-control" :value="indiaCountry?.name || 'India'" disabled />
        </div>

        <div class="mb-3">
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

        <div class="mb-3">
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

        <div class="mb-3">
            <label class="form-label">{{ nameLabel }} *</label>
            <input
                :value="name"
                type="text"
                class="form-control"
                :class="{ 'is-invalid': fieldErrors.name }"
                required
                @input="$emit('update:name', $event.target.value)"
            />
            <div v-if="fieldErrors.name" class="invalid-feedback d-block">
                {{ fieldErrors.name[0] }}
            </div>
        </div>

        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">GST Number</label>
                <input
                    :value="gstNumber"
                    type="text"
                    class="form-control"
                    :class="{ 'is-invalid': fieldErrors.gst_number }"
                    @input="$emit('update:gstNumber', $event.target.value)"
                />
                <div v-if="fieldErrors.gst_number" class="invalid-feedback d-block">
                    {{ fieldErrors.gst_number[0] }}
                </div>
            </div>
            <div class="col-md-6">
                <label class="form-label">PAN Number</label>
                <input
                    :value="panNumber"
                    type="text"
                    class="form-control text-uppercase"
                    maxlength="10"
                    :class="{ 'is-invalid': fieldErrors.pan_number }"
                    @input="$emit('update:panNumber', $event.target.value)"
                />
                <div v-if="fieldErrors.pan_number" class="invalid-feedback d-block">
                    {{ fieldErrors.pan_number[0] }}
                </div>
            </div>
            <div class="col-md-6">
                <label class="form-label">Contact Number</label>
                <input
                    :value="contactNumber"
                    type="text"
                    inputmode="numeric"
                    maxlength="10"
                    class="form-control"
                    placeholder="10-digit mobile"
                    :class="{ 'is-invalid': fieldErrors.contact_number }"
                    @input="onContactInput"
                />
                <div v-if="fieldErrors.contact_number" class="invalid-feedback d-block">
                    {{ fieldErrors.contact_number[0] }}
                </div>
            </div>
            <div class="col-md-12">
                <label class="form-label">Address</label>
                <textarea
                    :value="address"
                    class="form-control"
                    rows="2"
                    :class="{ 'is-invalid': fieldErrors.address }"
                    @input="$emit('update:address', $event.target.value)"
                />
                <div v-if="fieldErrors.address" class="invalid-feedback d-block">
                    {{ fieldErrors.address[0] }}
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { normalizeMobile } from '../../utils/phone';
import SearchableSelect from '../ui/SearchableSelect.vue';

const emit = defineEmits([
    'update:name',
    'update:gstNumber',
    'update:panNumber',
    'update:contactNumber',
    'update:address',
    'update:stateId',
    'update:cityId',
]);

function onContactInput(event) {
    emit('update:contactNumber', normalizeMobile(event.target.value));
}

defineProps({
    nameLabel: { type: String, required: true },
    name: { type: String, default: '' },
    gstNumber: { type: String, default: '' },
    panNumber: { type: String, default: '' },
    contactNumber: { type: String, default: '' },
    address: { type: String, default: '' },
    states: { type: Array, default: () => [] },
    cities: { type: Array, default: () => [] },
    stateId: { type: [String, Number], default: '' },
    cityId: { type: [String, Number], default: '' },
    indiaCountry: { type: Object, default: null },
    fieldErrors: { type: Object, default: () => ({}) },
});

</script>
