<template>
    <div class="container mt-4 stations-detail-page">
        <div v-if="loading" class="text-center py-5 text-muted">Loading station details…</div>

        <template v-else-if="station">
            <div class="row g-3">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <strong>Station Information ({{ station?.name }})</strong>
                            <div class="app-index-table-actions ms-auto" style="margin-left: auto !important; display: inline-flex !important; gap: 0.375rem !important;">
                                <button
                                    type="button"
                                    class="btn btn-sm btn-outline-secondary btn-icon"
                                    title="Back to Stations"
                                    style="background-color: #fff; border-color: #cbd5e1; color: #475569; display: inline-flex; align-items: center; justify-content: center; width: 32px; height: 32px;"
                                    @click="goBack"
                                >
                                    <i class="fas fa-arrow-left" style="color: #475569 !important;" aria-hidden="true"></i>
                                </button>
                                <AppCrudActionButton
                                    v-if="canEdit"
                                    action="edit"
                                    @click="openEdit"
                                />
                                <AppCrudActionButton
                                    v-if="canDelete"
                                    action="delete"
                                    @click="handleDelete"
                                />
                            </div>
                        </div>
                        <div class="card-body row g-3 small">
                            <div class="col-md-4">
                                <span class="text-muted">Station Name</span>
                                <div class="fw-semibold">{{ station.name }}</div>
                            </div>
                            <div class="col-md-4">
                                <span class="text-muted">City</span>
                                <div>{{ station.city_name || '—' }}</div>
                            </div>
                            <div class="col-md-4">
                                <span class="text-muted">State</span>
                                <div>{{ station.state_name || '—' }}</div>
                            </div>
                        </div>

                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header"><strong>Record Info</strong></div>
                        <div class="card-body small">
                            <div class="mb-2">
                                <span class="text-muted">Created</span>
                                <div>{{ formatDateTime(station.created_at) }}</div>
                                <div v-if="station.created_by_name" class="text-muted">
                                    by {{ station.created_by_name }}
                                </div>
                            </div>
                            <div>
                                <span class="text-muted">Last Updated</span>
                                <div>{{ formatDateTime(station.updated_at) }}</div>
                                <div v-if="station.updated_by_name" class="text-muted">
                                    by {{ station.updated_by_name }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </template>

        <div v-else class="alert alert-danger">
            Station not found or you do not have access.
        </div>

        <StationFormModal
            :show="showForm"
            :saving="saving"
            :station="station"
            :field-errors="fieldErrors"
            :form-error="formError"
            @close="closeForm"
            @submit="handleSubmit"
        />

        <MasterCrudSaveConfirmModal
            :show="showSaveConfirm"
            :summary="saveConfirmSummary"
            :saving="saving"
            @cancel="cancelSaveConfirm"
            @confirm="confirmSave"
        />
    </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { deleteStation, fetchStation, updateStation } from '../../api/stations';
import AppCrudActionButton from '../../components/ui/AppCrudActionButton.vue';
import MasterCrudSaveConfirmModal from '../../components/ui/MasterCrudSaveConfirmModal.vue';
import StationFormModal from '../../components/stations/StationFormModal.vue';
import { useApiFormErrors } from '../../composables/useApiFormErrors';
import { usePermissions } from '../../composables/usePermissions';
import { useAppToast } from '../../composables/useAppToast';
import { useMasterCrudSaveConfirm } from '../../composables/useMasterCrudSaveConfirm';
import { formatDateTime } from '../../utils/datetime';

const route = useRoute();
const router = useRouter();
const toast = useAppToast();
const { canInCompany, canEditInCompany } = usePermissions();

const {
    showSaveConfirm,
    saveConfirmSummary,
    pendingPayload,
    promptSaveConfirm,
    cancelSaveConfirm,
    finishSaveConfirm,
} = useMasterCrudSaveConfirm();

const canEdit = computed(() => canEditInCompany('stations'));
const canDelete = computed(() => canEditInCompany('stations'));

const station = ref(null);
const loading = ref(true);
const saving = ref(false);
const showForm = ref(false);
const { fieldErrors, formError, clearErrors, applyApiError } = useApiFormErrors();

async function loadStation() {
    const id = route.params.id;
    if (!id) {
        station.value = null;
        loading.value = false;
        return;
    }

    loading.value = true;
    try {
        station.value = await fetchStation(id);
    } catch {
        station.value = null;
        toast.error('Could not load station details.');
    } finally {
        loading.value = false;
    }
}

function goBack() {
    if (window.history.length > 1) {
        router.back();
    } else {
        router.push({ name: 'stations.index' });
    }
}

function openEdit() {
    clearErrors();
    showForm.value = true;
}

function closeForm() {
    showForm.value = false;
    clearErrors();
}

function handleSubmit({ payload, fields }) {
    clearErrors();

    promptSaveConfirm({
        payload,
        entityLabel: 'Station',
        mode: 'edit',
        fields,
    });
}

async function confirmSave() {
    const payload = pendingPayload.value;
    if (!payload || !station.value?.id) {
        return;
    }

    saving.value = true;
    try {
        station.value = await updateStation(station.value.id, payload);
        toast.success('Station updated successfully.');
        finishSaveConfirm();
        closeForm();
        await loadStation();
    } catch (error) {
        finishSaveConfirm();
        applyApiError(error, 'Please check the highlighted fields.');
    } finally {
        saving.value = false;
    }
}

async function handleDelete() {
    if (!station.value?.id) {
        return;
    }

    if (!window.confirm(`Delete station "${station.value.name}"?`)) {
        return;
    }

    try {
        await deleteStation(station.value.id);
        toast.success('Station deleted successfully.');
        router.push({ name: 'stations.index' });
    } catch (error) {
        toast.error(error.response?.data?.message ?? 'Could not delete station.');
    }
}

onMounted(async () => {
    await loadStation();
});
</script>
