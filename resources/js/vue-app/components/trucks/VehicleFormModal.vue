<template>
    <AppModal
        :show="show"
        :loading="saving"
        :form-error="formError"
        as-form
        @close="close"
        @submit="submit"
    >
        <template #title>
            <i class="bi bi-truck"></i>
            {{ isEdit ? 'Edit Truck' : 'Add Truck' }}
        </template>

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
                                <label :for="`${modalId}_layout`" class="form-label">Tyre Layout *</label>
                                <AppSelect
                                    :input-id="`${modalId}_layout`"
                                    v-model="form.tyre_layout"
                                    :options="tyreLayoutSelectOptions"
                                    placeholder="Select layout"
                                    search-placeholder="Search layout…"
                                    :invalid="Boolean(props.fieldErrors.tyre_layout)"
                                    :error-message="props.fieldErrors.tyre_layout?.[0] ?? ''"
                                />
                                <div v-if="tyreLayoutOptions.length === 0" class="mt-1 d-flex align-items-center justify-content-between">
                                    <span class="text-warning small"><i class="bi bi-exclamation-triangle me-1"></i>No layouts found.</span>
                                    <button
                                        type="button"
                                        class="btn btn-link btn-sm p-0 text-decoration-none"
                                        :disabled="seedingLayouts"
                                        @click="loadStandardLayouts"
                                    >
                                        <i class="bi bi-arrow-repeat me-1" :class="{ 'fa-spin': seedingLayouts }"></i>
                                        Load standard layouts
                                    </button>
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
                            <div class="col-md-4">
                                <label :for="`${modalId}_default_mileage`" class="form-label">Default Mileage (KM/LTR)</label>
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
                            <div class="col-md-4">
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
                            <div class="col-md-4">
                                <label :for="`${modalId}_fuel_tank`" class="form-label">Fuel Tank Capacity (LTR)</label>
                                <input
                                    :id="`${modalId}_fuel_tank`"
                                    v-model="form.fuel_tank_capacity_ltr"
                                    v-no-scroll-change
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    class="form-control"
                                    :class="{ 'is-invalid': props.fieldErrors.fuel_tank_capacity_ltr }"
                                />
                                <div v-if="props.fieldErrors.fuel_tank_capacity_ltr" class="invalid-feedback d-block">
                                    {{ props.fieldErrors.fuel_tank_capacity_ltr[0] }}
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
                                <AppSelect
                                    :input-id="`${modalId}_year`"
                                    v-model="form.manufacture_year"
                                    :options="manufactureYearSelectOptions"
                                    placeholder="Type or select year"
                                    search-placeholder="Search year…"
                                    editable
                                    input-mode="numeric"
                                    :max-length="4"
                                    :invalid="Boolean(manufactureYearError)"
                                    :error-message="manufactureYearError"
                                    :empty-message="manufactureYearEmptyMessage"
                                    @blur="onManufactureYearBlur"
                                />
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

        <template #footer>
            <button type="button" class="btn btn-secondary" :disabled="saving" @click="close">Cancel</button>
            <button type="submit" class="btn" :class="isEdit ? 'btn-warning' : 'btn-success'" :disabled="saving">
                {{ saving ? 'Saving...' : isEdit ? 'Update' : 'Add Truck' }}
            </button>
        </template>
    </AppModal>

    <TruckTypeQuickAddModal
        :show="showTypeQuickAdd"
        @close="showTypeQuickAdd = false"
        @created="onTruckTypeCreated"
    />
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import AppModal from '../ui/AppModal.vue';
import { listAllTyreLayouts, seedDefaultTyreLayouts } from '../../api/tyreLayouts';
import { listAllTruckTypes } from '../../api/truckTypes';
import {
    VEHICLE_REGISTRATION_ERROR,
    VEHICLE_REGISTRATION_HINT,
    isValidVehicleRegistration,
    normalizeVehicleRegistration,
} from '../../utils/vehicleRegistration';
import { formatDateTime } from '../../utils/datetime';
import SearchableSelect from '../ui/SearchableSelect.vue';
import AppSelect from '../ui/AppSelect.vue';
import TruckTypeQuickAddModal from './TruckTypeQuickAddModal.vue';

const MIN_MANUFACTURE_YEAR = 1980;

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

const isEdit = computed(() => props.mode === 'edit');
const form = ref(emptyForm());
const tyreLayoutOptions = ref([]);
const truckTypes = ref([]);
const showTypeQuickAdd = ref(false);
const localRegistrationError = ref('');
const localManufactureYearError = ref('');

const truckTypeOptions = computed(() =>
    truckTypes.value.map((type) => ({
        id: type.code,
        name: type.name,
    })),
);

const tyreLayoutSelectOptions = computed(() => {
    const options = tyreLayoutOptions.value.map((option) => ({
        value: option.code,
        label: `${option.name} (${option.position_count} positions)`,
    }));

    if (props.vehicle?.tyre_layout && !options.some((o) => o.value === props.vehicle.tyre_layout)) {
        options.push({
            value: props.vehicle.tyre_layout,
            label: `${props.vehicle.tyre_layout_label ?? props.vehicle.tyre_layout} (Inactive)`,
        });
    }

    return options;
});

const registrationError = computed(() => {
    return props.fieldErrors.registration_number?.[0] ?? localRegistrationError.value;
});

const manufactureYearError = computed(() => {
    return props.fieldErrors.manufacture_year?.[0] ?? localManufactureYearError.value;
});

const manufactureYearRangeMessage = computed(
    () => `Year must be between ${MIN_MANUFACTURE_YEAR} and ${currentManufactureYear.value}.`,
);

const manufactureYearEmptyMessage = computed(() => {
    if (localManufactureYearError.value) {
        return localManufactureYearError.value;
    }

    const year = String(form.value.manufacture_year ?? '').trim();

    if (year.length === 4) {
        return manufactureYearRangeMessage.value;
    }

    return 'No matching years.';
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

const currentManufactureYear = computed(() => new Date().getFullYear());

const manufactureYearSelectOptions = computed(() => {
    const maxYear = currentManufactureYear.value;
    const years = [];

    for (let year = maxYear; year >= MIN_MANUFACTURE_YEAR; year--) {
        years.push({ value: String(year), label: String(year) });
    }

    return years;
});

function emptyForm() {
    return {
        registration_number: '',
        vehicle_type: '',
        tyre_layout: '',
        make: '',
        model: '',
        manufacture_year: '',
        odometer_km: '0',
        default_mileage_km_per_liter: '',
        max_load_ton: '',
        fuel_tank_capacity_ltr: '',
    };
}

function onRegistrationInput(event) {
    form.value.registration_number = normalizeVehicleRegistration(event.target.value);
    localRegistrationError.value = '';
}

function fillForm(vehicle) {
    if (!vehicle) {
        form.value = emptyForm();

        return;
    }

    form.value = {
        registration_number: normalizeVehicleRegistration(vehicle.registration_number ?? ''),
        vehicle_type: vehicle.vehicle_type ?? '',
        tyre_layout: vehicle.tyre_layout ?? '',
        make: vehicle.make ?? '',
        model: vehicle.model ?? '',
        manufacture_year: vehicle.manufacture_year ? String(vehicle.manufacture_year) : '',
        odometer_km: vehicle.odometer_km !== undefined ? String(vehicle.odometer_km) : '0',
        default_mileage_km_per_liter:
            vehicle.default_mileage_km_per_liter != null && vehicle.default_mileage_km_per_liter !== ''
                ? String(vehicle.default_mileage_km_per_liter)
                : '',
        max_load_ton:
            vehicle.max_load_ton != null && vehicle.max_load_ton !== '' ? String(vehicle.max_load_ton) : '',
        fuel_tank_capacity_ltr:
            vehicle.fuel_tank_capacity_ltr != null && vehicle.fuel_tank_capacity_ltr !== ''
                ? String(vehicle.fuel_tank_capacity_ltr)
                : '',
    };
}

const seedingLayouts = ref(false);

async function loadTyreLayoutOptions() {
    try {
        tyreLayoutOptions.value = await listAllTyreLayouts({ activeOnly: true });

        if (!form.value.tyre_layout && !isEdit.value && tyreLayoutOptions.value.length > 0) {
            const defaultOpt = tyreLayoutOptions.value.find((o) => o.code === 'TYRES_10') ?? tyreLayoutOptions.value[0];
            if (defaultOpt) {
                form.value.tyre_layout = defaultOpt.code;
            }
        }
    } catch {
        tyreLayoutOptions.value = [];
    }
}

async function loadStandardLayouts() {
    seedingLayouts.value = true;
    try {
        await seedDefaultTyreLayouts();
        await loadTyreLayoutOptions();
    } catch {
        // Continue gracefully
    } finally {
        seedingLayouts.value = false;
    }
}

async function loadTruckTypes() {
    try {
        truckTypes.value = await listAllTruckTypes();
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
    () => form.value.manufacture_year,
    (year) => {
        localManufactureYearError.value = validateManufactureYearInput(year);
    },
);

function validateManufactureYearInput(year, { requireComplete = true } = {}) {
    if (year === '' || year == null) {
        return '';
    }

    const yearText = String(year).trim();

    if (requireComplete && yearText.length < 4) {
        return '';
    }

    const numericYear = Number(yearText);

    if (
        !Number.isInteger(numericYear) ||
        numericYear < MIN_MANUFACTURE_YEAR ||
        numericYear > currentManufactureYear.value
    ) {
        return manufactureYearRangeMessage.value;
    }

    return '';
}

function onManufactureYearBlur() {
    localManufactureYearError.value = validateManufactureYearInput(form.value.manufacture_year);
}

watch(
    () => props.show,
    async (visible) => {
        if (visible) {
            localRegistrationError.value = '';
            localManufactureYearError.value = '';
            showTypeQuickAdd.value = false;
            fillForm(props.vehicle);
            await Promise.all([loadTyreLayoutOptions(), loadTruckTypes()]);
        } else {
            localRegistrationError.value = '';
            localManufactureYearError.value = '';
            showTypeQuickAdd.value = false;
        }
    },
);

function close() {
    emit('close');
}

function isValidManufactureYear(year) {
    if (year === '' || year == null) {
        return true;
    }

    const numericYear = Number(year);

    return (
        Number.isInteger(numericYear) &&
        numericYear >= MIN_MANUFACTURE_YEAR &&
        numericYear <= currentManufactureYear.value
    );
}

function submit() {
    const registrationNumber = normalizeVehicleRegistration(form.value.registration_number);

    if (!isValidVehicleRegistration(registrationNumber)) {
        localRegistrationError.value = VEHICLE_REGISTRATION_ERROR;

        return;
    }

    if (!isValidManufactureYear(form.value.manufacture_year)) {
        localManufactureYearError.value = manufactureYearRangeMessage.value;

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
        fuel_tank_capacity_ltr:
            form.value.fuel_tank_capacity_ltr === '' || form.value.fuel_tank_capacity_ltr == null
                ? 0
                : Number(form.value.fuel_tank_capacity_ltr),
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
