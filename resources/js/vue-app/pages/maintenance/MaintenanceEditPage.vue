<template>
    <div class="container-fluid px-3 px-lg-4 mt-4 pb-5 app-index-page maintenance-edit-page">
        <div v-if="needsCompanySelection" class="alert alert-warning">
            Select a company and click <strong>Enter</strong> before editing maintenance records.
        </div>

        <div v-else-if="loading" class="text-center py-5 text-muted">Loading maintenance record…</div>

        <form v-else novalidate @submit.prevent="handleSubmit">
            <MaintenanceForm
                mode="edit"
                :form="form"
                :vehicles="vehicles"
                :service-types="serviceTypes"
                :lookup-vehicle="record?.vehicle ?? null"
                :lookup-service-type="lookupServiceType"
                :field-errors="fieldErrors"
                :form-error="formError"
                :min-next-due-date="minNextDueDate"
                @clear-field-error="clearFieldError"
            >
                <template #nav-actions>
                    <RouterLink
                        :to="listReturnTo"
                        class="btn btn-sm trip-header-btn trip-header-btn--back"
                    >
                        <i class="bi bi-arrow-left me-1"></i>Back
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
                        <i v-else class="bi bi-floppy-fill me-1"></i>Update
                    </button>
                </template>
            </MaintenanceForm>

            <div class="trip-form-actions card shadow-sm">
                <div class="card-body d-flex flex-wrap gap-2 justify-content-end">
                    <AppButton variant="secondary" outline size="sm" icon="bi bi-x-lg" label="Cancel" :to="listReturnTo" />
                    <AppButton
                        type="submit"
                        variant="primary"
                        size="sm"
                        icon="bi bi-floppy-fill"
                        label="Update"
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
            confirm-mode="index"
            @cancel="cancelSaveConfirm"
            @confirm="confirmSave"
        />
    </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { listMaintenanceServiceTypes } from '../../api/maintenanceServiceTypes';
import { fetchVehicleMaintenance, updateVehicleMaintenance } from '../../api/vehicleMaintenances';
import { listAllVehicles } from '../../api/vehicles';
import MaintenanceForm from '../../components/maintenance/MaintenanceForm.vue';
import MaintenanceSaveConfirmModal from '../../components/maintenance/MaintenanceSaveConfirmModal.vue';
import AppButton from '../../components/ui/AppButton.vue';
import { useApiFormErrors } from '../../composables/useApiFormErrors';
import { useAppToast } from '../../composables/useAppToast';
import { useMaintenanceForm } from '../../composables/useMaintenanceForm';
import { useMaintenanceListReturn } from '../../composables/useMaintenanceListReturn';
import { useRequireCompanyContext } from '../../composables/useRequireCompanyContext';
import { useUnsavedFormGuard } from '../../composables/useUnsavedFormGuard';
import { scrollToFirstFormError } from '../../utils/formErrors';
import { buildMaintenanceSaveConfirmViewModel } from '../../utils/maintenanceSaveConfirm';

const route = useRoute();
const router = useRouter();
const toast = useAppToast();
const { needsCompanySelection } = useRequireCompanyContext();
const { fieldErrors, formError, clearErrors, clearFieldError, applyApiError } = useApiFormErrors();
const { listReturnTo, listReturnWithFlash } = useMaintenanceListReturn();

const record = ref(null);
const vehicles = ref([]);
const serviceTypes = ref([]);
const loading = ref(true);
const saving = ref(false);
const showSaveConfirm = ref(false);
const saveConfirmSummary = ref(null);
const pendingPayload = ref(null);

const { form, minNextDueDate, resetForm, captureBaseline, isDirty, validate, buildPayload } = useMaintenanceForm();

useUnsavedFormGuard(isDirty);

const lookupServiceType = computed(() => {
    if (!record.value?.maintenance_service_type_id) {
        return null;
    }

    const currentId = String(record.value.maintenance_service_type_id);
    const hasCurrent = serviceTypes.value.some((type) => String(type.id) === currentId);

    if (hasCurrent) {
        return null;
    }

    return {
        id: record.value.maintenance_service_type_id,
        name: record.value.service_type_label ?? record.value.service_type ?? 'Selected type',
        label: record.value.service_type_label ?? record.value.service_type ?? 'Selected type',
    };
});

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

async function loadRecord() {
    loading.value = true;

    try {
        const [fetchedRecord, vehicleList, typeList] = await Promise.all([
            fetchVehicleMaintenance(route.params.id),
            listAllVehicles(),
            listMaintenanceServiceTypes(),
        ]);

        record.value = fetchedRecord;
        vehicles.value = vehicleList;
        serviceTypes.value = typeList;
        resetForm(fetchedRecord);
    } catch {
        toast.error('Could not load maintenance record.');
        await router.replace(listReturnTo.value);
    } finally {
        loading.value = false;
    }
}

function handleSubmit() {
    clearErrors();

    const clientErrors = validate();

    if (Object.keys(clientErrors).length > 0) {
        fieldErrors.value = clientErrors;
        formError.value = 'Please fix the errors below before saving.';
        toast.error(formError.value);
        scrollToFirstFormError();

        return;
    }

    pendingPayload.value = buildPayload();
    saveConfirmSummary.value = buildMaintenanceSaveConfirmViewModel(form.value, {
        mode: 'edit',
        vehicles: vehicles.value,
        serviceTypes: serviceTypes.value,
        originalRecord: record.value,
    });
    showSaveConfirm.value = true;
}

function cancelSaveConfirm() {
    showSaveConfirm.value = false;
    pendingPayload.value = null;
}

async function confirmSave() {
    if (!pendingPayload.value || !record.value?.id) {
        return;
    }

    saving.value = true;

    try {
        await updateVehicleMaintenance(record.value.id, pendingPayload.value);
        showSaveConfirm.value = false;
        pendingPayload.value = null;
        captureBaseline();
        await router.push(listReturnWithFlash({ updated: '1' }));
    } catch (error) {
        showSaveConfirm.value = false;

        if (!applyApiError(error, 'Failed to update maintenance record.')) {
            formError.value = error?.response?.data?.message ?? 'Failed to update maintenance record.';
        }

        toast.error(formError.value);
        scrollToFirstFormError();
    } finally {
        saving.value = false;
    }
}

onMounted(async () => {
    if (!needsCompanySelection.value) {
        await loadRecord();
    } else {
        loading.value = false;
    }
});
</script>
