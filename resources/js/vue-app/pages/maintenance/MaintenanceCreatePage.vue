<template>
    <div class="maintenance-create-page maintenance-create-redesigned-page app-index-page">
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
            />

            <div class="trip-form-actions trip-form-actions--inline">
                <div class="d-flex flex-wrap gap-2 justify-content-center">
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
                        variant="success"
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
import { useMaintenanceListReturn } from '../../composables/useMaintenanceListReturn';
import { useRequireCompanyContext } from '../../composables/useRequireCompanyContext';
import { toDateInputValue } from '../../utils/datetime';
import { scrollToFirstFormError } from '../../utils/formErrors';
import { buildMaintenanceSaveConfirmViewModel } from '../../utils/maintenanceSaveConfirm';
import { maintenanceRecordsListLocationForServiceDate } from '../../utils/maintenanceRecordsListFilterState';

const route = useRoute();
const router = useRouter();
const toast = useAppToast();
const { needsCompanySelection } = useRequireCompanyContext();
const { fieldErrors, formError, clearErrors, clearFieldError, applyApiError } = useApiFormErrors();
const { listReturnTo, tenantId } = useMaintenanceListReturn();

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
        const serviceDate = pendingPayload.value.service_date;
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

        await router.push(
            maintenanceRecordsListLocationForServiceDate(serviceDate, { created: '1' }, tenantId.value),
        );
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

<style scoped>
.maintenance-create-redesigned-page {
    max-width: 1180px;
    margin: 0 auto;
    padding: 10px 14px 14px !important;
}

:global(.vue-app-shell) .maintenance-create-redesigned-page {
    padding-bottom: 14px !important;
}

.maintenance-create-redesigned-page .trip-form-actions {
    margin-top: 8px;
    margin-bottom: 0;
}

@media (max-width: 575.98px) {
    .maintenance-create-redesigned-page {
        padding: 6px 4px 16px !important;
    }

    .maintenance-create-redesigned-page .trip-form-actions :deep(.btn) {
        font-size: 0.74rem;
        padding: 5px 8px;
    }
}
</style>
