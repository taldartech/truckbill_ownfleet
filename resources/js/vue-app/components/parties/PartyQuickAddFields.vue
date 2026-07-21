<template>
    <div class="row g-2">
        <IndiaLocationFields
            v-if="!locationAfterContact"
            :states="states"
            :cities="cities"
            :state-id="stateId"
            :city-id="cityId"
            :field-errors="fieldErrors"
            @update:state-id="$emit('update:stateId', $event)"
            @update:city-id="$emit('update:cityId', $event)"
        />

        <div class="col-12">
            <label class="form-label">{{ nameLabel }} *</label>
            <input
                :value="name"
                type="text"
                class="form-control"
                :class="{ 'is-invalid': displayFieldErrors.name }"
                required
                @input="onNameInput"
            />
            <div v-if="displayFieldErrors.name" class="invalid-feedback d-block">
                {{ displayFieldErrors.name[0] }}
            </div>
        </div>

        <div class="col-md-4">
            <label class="form-label">GST Number</label>
            <input
                :value="gstNumber"
                type="text"
                class="form-control text-uppercase"
                :class="{ 'is-invalid': displayFieldErrors.gst_number }"
                :placeholder="GST_NUMBER_HINT"
                :maxlength="GST_NUMBER_LENGTH"
                @input="onGstInput"
            />
            <div v-if="displayFieldErrors.gst_number" class="invalid-feedback d-block">
                {{ displayFieldErrors.gst_number[0] }}
            </div>
        </div>
        <div class="col-md-4">
            <label class="form-label">PAN Number</label>
            <input
                :value="panNumber"
                type="text"
                class="form-control text-uppercase"
                maxlength="10"
                :class="{ 'is-invalid': displayFieldErrors.pan_number }"
                :placeholder="PAN_NUMBER_HINT"
                @input="onPanInput"
            />
            <div v-if="displayFieldErrors.pan_number" class="invalid-feedback d-block">
                {{ displayFieldErrors.pan_number[0] }}
            </div>
        </div>
        <div class="col-md-4">
            <label class="form-label">Contact Number<span v-if="contactRequired"> *</span></label>
            <input
                :value="contactNumber"
                type="text"
                inputmode="numeric"
                maxlength="10"
                class="form-control"
                placeholder="10-digit mobile"
                :required="contactRequired"
                :class="{ 'is-invalid': displayFieldErrors.contact_number }"
                @input="onContactInput"
            />
            <div v-if="displayFieldErrors.contact_number" class="invalid-feedback d-block">
                {{ displayFieldErrors.contact_number[0] }}
            </div>
        </div>

        <IndiaLocationFields
            v-if="locationAfterContact"
            :states="states"
            :cities="cities"
            :state-id="stateId"
            :city-id="cityId"
            :field-errors="fieldErrors"
            @update:state-id="$emit('update:stateId', $event)"
            @update:city-id="$emit('update:cityId', $event)"
        />

        <div class="col-12">
            <label class="form-label">Address</label>
            <textarea
                :value="address"
                class="form-control"
                rows="2"
                :class="{ 'is-invalid': displayFieldErrors.address }"
                @input="$emit('update:address', $event.target.value)"
            />
            <div v-if="displayFieldErrors.address" class="invalid-feedback d-block">
                {{ displayFieldErrors.address[0] }}
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import {
    GST_NUMBER_HINT,
    GST_NUMBER_LENGTH,
    PAN_NUMBER_HINT,
    normalizeGstNumber,
    normalizePanNumber,
} from '../../utils/taxIdentity';
import { normalizeMobile } from '../../utils/phone';
import IndiaLocationFields from '../locations/IndiaLocationFields.vue';

const props = defineProps({
    nameLabel: { type: String, required: true },
    name: { type: String, default: '' },
    gstNumber: { type: String, default: '' },
    panNumber: { type: String, default: '' },
    contactNumber: { type: String, default: '' },
    contactRequired: { type: Boolean, default: false },
    address: { type: String, default: '' },
    states: { type: Array, default: () => [] },
    cities: { type: Array, default: () => [] },
    stateId: { type: [String, Number], default: '' },
    cityId: { type: [String, Number], default: '' },
    locationAfterContact: { type: Boolean, default: false },
    fieldErrors: { type: Object, default: () => ({}) },
    localFieldErrors: { type: Object, default: () => ({}) },
});

const emit = defineEmits([
    'update:name',
    'update:gstNumber',
    'update:panNumber',
    'update:contactNumber',
    'update:address',
    'update:stateId',
    'update:cityId',
    'clear-field-error',
]);

const displayFieldErrors = computed(() => ({
    ...props.localFieldErrors,
    ...props.fieldErrors,
}));

function onGstInput(event) {
    emit('update:gstNumber', normalizeGstNumber(event.target.value));
    emit('clear-field-error', 'gst_number');
}

function onPanInput(event) {
    emit('update:panNumber', normalizePanNumber(event.target.value));
    emit('clear-field-error', 'pan_number');
}

function onContactInput(event) {
    emit('update:contactNumber', normalizeMobile(event.target.value));
    emit('clear-field-error', 'contact_number');
}

function onNameInput(event) {
    emit('update:name', event.target.value);
    emit('clear-field-error', 'name');
}
</script>
