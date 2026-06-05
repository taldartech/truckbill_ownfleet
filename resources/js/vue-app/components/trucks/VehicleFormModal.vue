<template>
    <div
        :id="modalId"
        ref="modalElement"
        class="modal fade"
        tabindex="-1"
        :aria-labelledby="`${modalId}Label`"
        aria-hidden="true"
    >
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 :id="`${modalId}Label`" class="modal-title">
                        <i class="bi bi-truck"></i>
                        {{ isEdit ? 'Edit Truck' : 'Add Truck' }}
                    </h5>
                    <button type="button" class="btn-close btn-close-white" aria-label="Close" @click="close"></button>
                </div>
                <form novalidate @submit.prevent="submit">
                    <div class="modal-body">
                        <div v-if="formError" class="alert alert-danger py-2">{{ formError }}</div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label :for="`${modalId}_registration`" class="form-label">Registration Number *</label>
                                <input
                                    :id="`${modalId}_registration`"
                                    v-model="form.registration_number"
                                    type="text"
                                    class="form-control"
                                    :class="{ 'is-invalid': props.fieldErrors.registration_number }"
                                    required
                                />
                                <div v-if="props.fieldErrors.registration_number" class="invalid-feedback d-block">
                                    {{ props.fieldErrors.registration_number[0] }}
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label :for="`${modalId}_layout`" class="form-label">Tyre layout *</label>
                                <select
                                    :id="`${modalId}_layout`"
                                    v-model="form.tyre_layout"
                                    class="form-select"
                                    :class="{ 'is-invalid': props.fieldErrors.tyre_layout }"
                                    required
                                >
                                    <option v-for="opt in tyreLayoutOptions" :key="opt.code" :value="opt.code">
                                        {{ opt.name }} ({{ opt.position_count }} positions)
                                    </option>
                                </select>
            
                                <div v-if="props.fieldErrors.tyre_layout" class="invalid-feedback d-block">
                                    {{ props.fieldErrors.tyre_layout[0] }}
                                </div>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label :for="`${modalId}_type`" class="form-label">Vehicle Type *</label>
                                <select
                                    :id="`${modalId}_type`"
                                    v-model="form.vehicle_type"
                                    class="form-select"
                                    :class="{ 'is-invalid': props.fieldErrors.vehicle_type }"
                                    required
                                >
                                    <option value="">Select type</option>
                                    <option value="TRUCK">Truck</option>
                                    <option value="TRAILER">Trailer</option>
                                    <option value="TANKER">Tanker</option>
                                    <option value="CONTAINER">Container</option>
                                </select>
                                <div v-if="props.fieldErrors.vehicle_type" class="invalid-feedback d-block">
                                    {{ props.fieldErrors.vehicle_type[0] }}
                                </div>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label :for="`${modalId}_make`" class="form-label">Make</label>
                                <input
                                    :id="`${modalId}_make`"
                                    v-model="form.make"
                                    type="text"
                                    class="form-control"
                                    :class="{ 'is-invalid': props.fieldErrors.make }"
                                />
                                <div v-if="props.fieldErrors.make" class="invalid-feedback d-block">
                                    {{ props.fieldErrors.make[0] }}
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label :for="`${modalId}_model`" class="form-label">Model</label>
                                <input
                                    :id="`${modalId}_model`"
                                    v-model="form.model"
                                    type="text"
                                    class="form-control"
                                    :class="{ 'is-invalid': props.fieldErrors.model }"
                                />
                                <div v-if="props.fieldErrors.model" class="invalid-feedback d-block">
                                    {{ props.fieldErrors.model[0] }}
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label :for="`${modalId}_year`" class="form-label">Year</label>
                                <input
                                    :id="`${modalId}_year`"
                                    v-model="form.manufacture_year"
                                    type="number"
                                    min="1980"
                                    class="form-control"
                                    :class="{ 'is-invalid': props.fieldErrors.manufacture_year }"
                                />
                                <div v-if="props.fieldErrors.manufacture_year" class="invalid-feedback d-block">
                                    {{ props.fieldErrors.manufacture_year[0] }}
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label :for="`${modalId}_odometer`" class="form-label">Odometer (KM)</label>
                            <input
                                :id="`${modalId}_odometer`"
                                v-model="form.odometer_km"
                                type="number"
                                min="0"
                                step="0.01"
                                class="form-control"
                                :class="{ 'is-invalid': props.fieldErrors.odometer_km }"
                            />
                            <div v-if="props.fieldErrors.odometer_km" class="invalid-feedback d-block">
                                {{ props.fieldErrors.odometer_km[0] }}
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" @click="close">Cancel</button>
                        <button type="submit" class="btn btn-success" :disabled="saving">
                            {{ saving ? 'Saving...' : isEdit ? 'Update' : 'Add Truck' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { listAllTyreLayouts } from '../../api/tyreLayouts';

const DEFAULT_TYRE_LAYOUT = 'TYRES_10';

const props = defineProps({
    show: { type: Boolean, default: false },
    mode: { type: String, default: 'create' },
    vehicle: { type: Object, default: null },
    saving: { type: Boolean, default: false },
    fieldErrors: { type: Object, default: () => ({}) },
    formError: { type: String, default: '' },
    modalId: { type: String, required: true },
});

const emit = defineEmits(['close', 'submit']);

const modalElement = ref(null);
let bootstrapModal = null;
const isEdit = computed(() => props.mode === 'edit');
const form = ref(emptyForm());
const tyreLayoutOptions = ref([]);
const loadingLayouts = ref(false);

function emptyForm() {
    return {
        registration_number: '',
        vehicle_type: 'TRUCK',
        tyre_layout: DEFAULT_TYRE_LAYOUT,
        make: '',
        model: '',
        manufacture_year: '',
        odometer_km: '0',
    };
}

function fillForm(vehicle) {
    form.value = {
        registration_number: vehicle?.registration_number ?? '',
        vehicle_type: vehicle?.vehicle_type ?? 'TRUCK',
        tyre_layout: vehicle?.tyre_layout ?? DEFAULT_TYRE_LAYOUT,
        make: vehicle?.make ?? '',
        model: vehicle?.model ?? '',
        manufacture_year: vehicle?.manufacture_year ? String(vehicle.manufacture_year) : '',
        odometer_km: vehicle?.odometer_km !== undefined ? String(vehicle.odometer_km) : '0',
    };
}

async function loadTyreLayoutOptions() {
    loadingLayouts.value = true;
    try {
        tyreLayoutOptions.value = await listAllTyreLayouts({ activeOnly: true });
        if (!form.value.tyre_layout && tyreLayoutOptions.value.length) {
            form.value.tyre_layout = tyreLayoutOptions.value[0].code;
        }
    } catch {
        tyreLayoutOptions.value = [];
    } finally {
        loadingLayouts.value = false;
    }
}

watch(
    () => props.show,
    async (visible) => {
        if (!modalElement.value || typeof window.bootstrap === 'undefined') {
            return;
        }

        if (!bootstrapModal) {
            bootstrapModal = new window.bootstrap.Modal(modalElement.value, { backdrop: false });
        }

        if (visible) {
            fillForm(props.vehicle);
            await loadTyreLayoutOptions();
            bootstrapModal.show();
        } else {
            bootstrapModal.hide();
        }
    },
);

function close() {
    emit('close');
}

function submit() {
    emit('submit', {
        registration_number: form.value.registration_number.trim().toUpperCase(),
        vehicle_type: form.value.vehicle_type,
        tyre_layout: form.value.tyre_layout,
        make: form.value.make || null,
        model: form.value.model || null,
        manufacture_year: form.value.manufacture_year ? Number(form.value.manufacture_year) : null,
        odometer_km: form.value.odometer_km === '' ? 0 : Number(form.value.odometer_km),
    });
}
</script>
