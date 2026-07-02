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
                <div class="modal-header bg-gradient-primary">
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
                                    class="form-control text-uppercase"
                                    :class="{ 'is-invalid': registrationError }"
                                    :placeholder="VEHICLE_REGISTRATION_HINT"
                                    maxlength="12"
                                    autocomplete="off"
                                    spellcheck="false"
                                    required
                                    @input="onRegistrationInput"
                                />
                                <div v-if="registrationError" class="invalid-feedback d-block">
                                    {{ registrationError }}
                                </div>
                                <div v-else class="form-text">Letters and numbers only. Saved as uppercase (e.g. RJ27AV5055).</div>
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
                                <label :for="`${modalId}_type`" class="form-label">Truck Type *</label>
                                <div class="truck-type-picker">
                                    <SearchableSelect
                                        :input-id="`${modalId}_type`"
                                        v-model="form.vehicle_type"
                                        :items="truckTypeOptions"
                                        placeholder="Select type"
                                        search-placeholder="Search truck type…"
                                        :invalid="Boolean(props.fieldErrors.vehicle_type)"
                                        :error-message="props.fieldErrors.vehicle_type?.[0] ?? ''"
                                    />
                                    <button
                                        v-if="canManageTypes"
                                        type="button"
                                        class="btn btn-outline-success btn-sm truck-type-picker__add"
                                        title="Quick add truck type"
                                        @click="showTypeQuickAdd = true"
                                    >
                                        <i class="bi bi-plus-lg"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label :for="`${modalId}_odometer`" class="form-label">Truck Running Meter Reading (KM)</label>
                                <input
                                    :id="`${modalId}_odometer`"
                                    v-model="form.odometer_km"
                                    v-no-scroll-change
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

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label :for="`${modalId}_default_mileage`" class="form-label">Default Mileage (KM/Ltr)</label>
                                <input
                                    :id="`${modalId}_default_mileage`"
                                    v-model="form.default_mileage_km_per_liter"
                                    v-no-scroll-change
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    class="form-control"
                                    :class="{ 'is-invalid': props.fieldErrors.default_mileage_km_per_liter }"
                                />
                                <div v-if="props.fieldErrors.default_mileage_km_per_liter" class="invalid-feedback d-block">
                                    {{ props.fieldErrors.default_mileage_km_per_liter[0] }}
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label :for="`${modalId}_max_load`" class="form-label">Max Load (Ton)</label>
                                <input
                                    :id="`${modalId}_max_load`"
                                    v-model="form.max_load_ton"
                                    v-no-scroll-change
                                    type="number"
                                    min="0"
                                    step="0.001"
                                    class="form-control"
                                    :class="{ 'is-invalid': props.fieldErrors.max_load_ton }"
                                />
                                <div v-if="props.fieldErrors.max_load_ton" class="invalid-feedback d-block">
                                    {{ props.fieldErrors.max_load_ton[0] }}
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
                                    v-no-scroll-change
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

                        <div v-if="isEdit && auditInfo" class="vehicle-audit-card border rounded px-3 py-2 bg-light">
                            <div class="row g-2 small text-muted">
                                <div class="col-md-6">
                                    <span class="fw-medium text-body">Created by:</span>
                                    {{ auditInfo.createdBy }}
                                    <span v-if="auditInfo.createdAt">· {{ auditInfo.createdAt }}</span>
                                </div>
                                <div class="col-md-6">
                                    <span class="fw-medium text-body">Updated by:</span>
                                    {{ auditInfo.updatedBy }}
                                    <span v-if="auditInfo.updatedAt">· {{ auditInfo.updatedAt }}</span>
                                </div>
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

        <TruckTypeQuickAddModal
            :show="showTypeQuickAdd"
            @close="showTypeQuickAdd = false"
            @created="onTruckTypeCreated"
        />
    </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { listAllTyreLayouts } from '../../api/tyreLayouts';
import { listAllTruckTypes } from '../../api/truckTypes';
import {
    VEHICLE_REGISTRATION_ERROR,
    VEHICLE_REGISTRATION_HINT,
    isValidVehicleRegistration,
    normalizeVehicleRegistration,
} from '../../utils/vehicleRegistration';
import { formatDateTime } from '../../utils/datetime';
import SearchableSelect from '../ui/SearchableSelect.vue';
import TruckTypeQuickAddModal from './TruckTypeQuickAddModal.vue';

const DEFAULT_TYRE_LAYOUT = 'TYRES_10';
const DEFAULT_TRUCK_TYPE = 'TRUCK';

const props = defineProps({
    show: { type: Boolean, default: false },
    mode: { type: String, default: 'create' },
    vehicle: { type: Object, default: null },
    saving: { type: Boolean, default: false },
    fieldErrors: { type: Object, default: () => ({}) },
    formError: { type: String, default: '' },
    modalId: { type: String, required: true },
    canManageTypes: { type: Boolean, default: true },
});

const emit = defineEmits(['close', 'submit']);

const modalElement = ref(null);
let bootstrapModal = null;
const isEdit = computed(() => props.mode === 'edit');
const form = ref(emptyForm());
const tyreLayoutOptions = ref([]);
const truckTypes = ref([]);
const showTypeQuickAdd = ref(false);
const localRegistrationError = ref('');

const truckTypeOptions = computed(() =>
    truckTypes.value.map((type) => ({
        id: type.code,
        name: type.name,
    })),
);

const registrationError = computed(() => {
    return props.fieldErrors.registration_number?.[0] ?? localRegistrationError.value;
});

const auditInfo = computed(() => {
    if (!isEdit.value || !props.vehicle) {
        return null;
    }

    return {
        createdBy: props.vehicle.created_by_name ?? '—',
        updatedBy: props.vehicle.updated_by_name ?? '—',
        createdAt: formatDateTime(props.vehicle.created_at, ''),
        updatedAt: formatDateTime(props.vehicle.updated_at, ''),
    };
});

function emptyForm() {
    return {
        registration_number: '',
        vehicle_type: DEFAULT_TRUCK_TYPE,
        tyre_layout: DEFAULT_TYRE_LAYOUT,
        make: '',
        model: '',
        manufacture_year: '',
        odometer_km: '0',
        default_mileage_km_per_liter: '',
        max_load_ton: '',
    };
}

function onRegistrationInput(event) {
    form.value.registration_number = normalizeVehicleRegistration(event.target.value);
    localRegistrationError.value = '';
}

function fillForm(vehicle) {
    form.value = {
        registration_number: normalizeVehicleRegistration(vehicle?.registration_number ?? ''),
        vehicle_type: vehicle?.vehicle_type ?? DEFAULT_TRUCK_TYPE,
        tyre_layout: vehicle?.tyre_layout ?? DEFAULT_TYRE_LAYOUT,
        make: vehicle?.make ?? '',
        model: vehicle?.model ?? '',
        manufacture_year: vehicle?.manufacture_year ? String(vehicle.manufacture_year) : '',
        odometer_km: vehicle?.odometer_km !== undefined ? String(vehicle.odometer_km) : '0',
        default_mileage_km_per_liter:
            vehicle?.default_mileage_km_per_liter != null && vehicle?.default_mileage_km_per_liter !== ''
                ? String(vehicle.default_mileage_km_per_liter)
                : '',
        max_load_ton:
            vehicle?.max_load_ton != null && vehicle?.max_load_ton !== '' ? String(vehicle.max_load_ton) : '',
    };
}

async function loadTyreLayoutOptions() {
    try {
        tyreLayoutOptions.value = await listAllTyreLayouts({ activeOnly: true });
        if (!form.value.tyre_layout && tyreLayoutOptions.value.length) {
            form.value.tyre_layout = tyreLayoutOptions.value[0].code;
        }
    } catch {
        tyreLayoutOptions.value = [];
    }
}

async function loadTruckTypes() {
    try {
        truckTypes.value = await listAllTruckTypes();
        if (!form.value.vehicle_type && truckTypes.value.length) {
            form.value.vehicle_type = truckTypes.value[0].code;
        }
    } catch {
        truckTypes.value = [];
    }
}

function onTruckTypeCreated(truckType) {
    truckTypes.value = [...truckTypes.value, truckType].sort((left, right) =>
        left.name.localeCompare(right.name),
    );
    form.value.vehicle_type = truckType.code;
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
            localRegistrationError.value = '';
            showTypeQuickAdd.value = false;
            fillForm(props.vehicle);
            await Promise.all([loadTyreLayoutOptions(), loadTruckTypes()]);
            bootstrapModal.show();
        } else {
            localRegistrationError.value = '';
            showTypeQuickAdd.value = false;
            bootstrapModal.hide();
        }
    },
);

function close() {
    emit('close');
}

function submit() {
    const registrationNumber = normalizeVehicleRegistration(form.value.registration_number);

    if (!isValidVehicleRegistration(registrationNumber)) {
        localRegistrationError.value = VEHICLE_REGISTRATION_ERROR;

        return;
    }

    emit('submit', {
        registration_number: registrationNumber,
        vehicle_type: form.value.vehicle_type,
        tyre_layout: form.value.tyre_layout,
        make: form.value.make || null,
        model: form.value.model || null,
        manufacture_year: form.value.manufacture_year ? Number(form.value.manufacture_year) : null,
        odometer_km: form.value.odometer_km === '' ? 0 : Number(form.value.odometer_km),
        default_mileage_km_per_liter:
            form.value.default_mileage_km_per_liter === '' || form.value.default_mileage_km_per_liter == null
                ? 0
                : Number(form.value.default_mileage_km_per_liter),
        max_load_ton:
            form.value.max_load_ton === '' || form.value.max_load_ton == null ? 0 : Number(form.value.max_load_ton),
    });
}
</script>

<style scoped>
.truck-type-picker {
    display: flex;
    align-items: flex-start;
    gap: 0.5rem;
}

.truck-type-picker :deep(.searchable-select) {
    flex: 1;
    min-width: 0;
}

.truck-type-picker__add {
    margin-top: 0;
    height: 38px;
    width: 38px;
    padding: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.vehicle-audit-card {
    font-size: 0.85rem;
}
</style>
