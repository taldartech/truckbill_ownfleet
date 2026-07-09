<template>
    <div class="container-fluid px-3 px-lg-4 mt-4 pb-5 app-index-page maintenance-create-page">
        <div v-if="needsCompanySelection" class="alert alert-warning">
            Select a company and click <strong>Enter</strong> before adding maintenance records.
        </div>

        <form v-else novalidate @submit.prevent="handleSubmit('index')">
            <MaintenanceForm
                mode="create"
                :form="form"
                :vehicles="vehicles"
                :service-types="serviceTypes"
                :field-errors="fieldErrors"
                :form-error="formError"
                :min-next-due-date="minNextDueDate"
                :lock-vehicle="Boolean(presetVehicleId)"
                @clear-field-error="clearFieldError"
            >
                <template #nav-actions>
                    <RouterLink
                        :to="listReturnTo"
                        class="btn btn-sm trip-header-btn trip-header-btn--back"
                    >
                        <i class="bi bi-arrow-left me-1"></i>Cancel
                    </RouterLink>
                </template>
                <template #save-actions>
                    <button
                        type="submit"
                        class="btn btn-sm trip-header-btn trip-header-btn--update"
                        :disabled="saving"
                    >
                        <span
                            v-if="saving"
                            class="spinner-border spinner-border-sm me-1"
                            role="status"
                            aria-hidden="true"
                        ></span>
                        <i v-else class="bi bi-floppy-fill me-1"></i>Save
                    </button>
                </template>
            </MaintenanceForm>

            <div class="trip-form-actions card shadow-sm">
                <div class="card-body d-flex flex-wrap gap-2 justify-content-end">
                    <AppButton variant="secondary" outline size="sm" icon="bi bi-x-lg" label="Cancel" :to="listReturnTo" />
                    <AppButton
                        type="button"
                        variant="success"
                        outline
                        size="sm"
                        icon="bi bi-plus-circle"
                        label="Save & Add Another"
                        :loading="saving"
                        :disabled="saving"
                        @click="handleSubmit('another')"
                    />
                    <AppButton
                        type="submit"
                        variant="primary"
                        size="sm"
                        icon="bi bi-floppy-fill"
                        label="Save"
                        :loading="saving"
                        :disabled="saving"
                    />
                </div>
            </div>
        </form>

        <MaintenanceSaveConfirmModal
            :show="showSaveConfirm"
            :summary="saveConfirmSummary"
            :saving="saving"
            :confirm-mode="saveMode"
            @cancel="cancelSaveConfirm"
            @confirm="confirmSave"
        />
    </div>
</template>

<script setup>
import { onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { listMaintenanceServiceTypes } from '../../api/maintenanceServiceTypes';
import { createVehicleMaintenance } from '../../api/vehicleMaintenances';
import { listAllVehicles } from '../../api/vehicles';
import MaintenanceForm from '../../components/maintenance/MaintenanceForm.vue';
import MaintenanceSaveConfirmModal from '../../components/maintenance/MaintenanceSaveConfirmModal.vue';
import AppButton from '../../components/ui/AppButton.vue';
import { useApiFormErrors } from '../../composables/useApiFormErrors';
import { useAppToast } from '../../composables/useAppToast';
import { useMaintenanceForm } from '../../composables/useMaintenanceForm';
import { useRequireCompanyContext } from '../../composables/useRequireCompanyContext';
import { toDateInputValue } from '../../utils/datetime';
import { scrollToFirstFormError } from '../../utils/formErrors';
import { buildMaintenanceSaveConfirmViewModel } from '../../utils/maintenanceSaveConfirm';

const route = useRoute();
const router = useRouter();
const toast = useAppToast();
const { needsCompanySelection } = useRequireCompanyContext();
const { fieldErrors, formError, clearErrors, clearFieldError, applyApiError } = useApiFormErrors();

const { form, minNextDueDate, resetForm, validate, buildPayload } = useMaintenanceForm();

const vehicles = ref([]);
const serviceTypes = ref([]);
const saving = ref(false);
const showSaveConfirm = ref(false);
const saveConfirmSummary = ref(buildMaintenanceSaveConfirmViewModel(form.value));
const pendingPayload = ref(null);
const saveMode = ref('index');
const presetVehicleId = ref('');
const presetServiceTypeId = ref('');
const selectedVehicleLabel = ref('');

const listReturnTo = { name: 'maintenance.records' };

watch(
    () => form.value.vehicle_id,
    (vehicleId) => {
        if (!vehicleId) {
            selectedVehicleLabel.value = '';

            return;
        }

        const vehicle = vehicles.value.find((item) => String(item.id) === String(vehicleId));
        selectedVehicleLabel.value = vehicle?.registration_number ?? '—';
    },
);

watch(
    () => form.value.service_date,
    (serviceDate) => {
        if (!serviceDate) {
            form.value.next_service_due_date = '';

            return;
        }

        if (form.value.next_service_due_date && form.value.next_service_due_date <= serviceDate) {
            form.value.next_service_due_date = '';
        }
    },
);

async function loadLookups() {
    const [vehicleList, typeList] = await Promise.all([listAllVehicles(), listMaintenanceServiceTypes()]);
    vehicles.value = vehicleList;
    serviceTypes.value = typeList;
}

function prepareSave() {
    clearErrors();

    const clientErrors = validate();

    if (Object.keys(clientErrors).length > 0) {
        fieldErrors.value = clientErrors;
        formError.value = 'Please fix the errors below before saving.';
        toast.error(formError.value);
        scrollToFirstFormError();

        return null;
    }

    return buildPayload();
}

function handleSubmit(mode = 'index') {
    saveMode.value = mode;
    const payload = prepareSave();

    if (!payload) {
        return;
    }

    pendingPayload.value = payload;
    saveConfirmSummary.value = buildMaintenanceSaveConfirmViewModel(form.value, {
        mode: 'create',
        vehicles: vehicles.value,
        serviceTypes: serviceTypes.value,
        vehicleLabel: selectedVehicleLabel.value,
    });
    showSaveConfirm.value = true;
}

function cancelSaveConfirm() {
    showSaveConfirm.value = false;
    pendingPayload.value = null;
}

async function confirmSave(mode = 'index') {
    if (!pendingPayload.value) {
        return;
    }

    saveMode.value = mode;
    saving.value = true;

    try {
        await createVehicleMaintenance(pendingPayload.value);
        showSaveConfirm.value = false;
        pendingPayload.value = null;

        if (saveMode.value === 'another') {
            const vehicleId = presetVehicleId.value || form.value.vehicle_id;
            const serviceTypeId = presetServiceTypeId.value || form.value.maintenance_service_type_id;
            resetForm();
            form.value.service_date = toDateInputValue();

            if (vehicleId) {
                form.value.vehicle_id = String(vehicleId);
            }

            if (serviceTypeId) {
                form.value.maintenance_service_type_id = String(serviceTypeId);
            }

            clearErrors();

            return;
        }

        await router.push({ name: 'maintenance.records', query: { created: '1' } });
    } catch (error) {
        showSaveConfirm.value = false;

        if (!applyApiError(error, 'Failed to save maintenance record.')) {
            formError.value = error?.response?.data?.message ?? 'Failed to save maintenance record.';
        }

        toast.error(formError.value);
        scrollToFirstFormError();
    } finally {
        saving.value = false;
    }
}

onMounted(async () => {
    resetForm();
    form.value.service_date = toDateInputValue();

    if (route.query.vehicle_id) {
        presetVehicleId.value = String(route.query.vehicle_id);
        form.value.vehicle_id = presetVehicleId.value;
    }

    if (route.query.maintenance_service_type_id) {
        presetServiceTypeId.value = String(route.query.maintenance_service_type_id);
        form.value.maintenance_service_type_id = presetServiceTypeId.value;
    }

    if (!needsCompanySelection.value) {
        await loadLookups();
    }
});
</script>
