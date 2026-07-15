<template>
    <div class="container mt-4 references-detail-page">
        <div v-if="loading" class="text-center py-5 text-muted">Loading reference details…</div>

        <template v-else-if="broker">
            <div class="row g-3">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header">
                            <strong>Reference Information ({{ broker?.name }})</strong>
                        </div>
                        <div class="card-body row g-3 small">
                            <div class="col-md-4">
                                <span class="text-muted">Reference Name</span>
                                <div class="fw-semibold">{{ broker.name }}</div>
                            </div>
                            <div class="col-md-4">
                                <span class="text-muted">Contact Number</span>
                                <div>{{ broker.contact_number || '—' }}</div>
                            </div>
                            <div class="col-md-4">
                                <span class="text-muted">GST Number</span>
                                <div>{{ broker.gst_number || '—' }}</div>
                            </div>
                            <div class="col-md-4">
                                <span class="text-muted">PAN Number</span>
                                <div>{{ broker.pan_number || '—' }}</div>
                            </div>
                            <div class="col-md-4">
                                <span class="text-muted">City</span>
                                <div>{{ broker.city_name || '—' }}</div>
                            </div>
                            <div class="col-md-4">
                                <span class="text-muted">State</span>
                                <div>{{ broker.state_name || '—' }}</div>
                            </div>
                            <div class="col-12">
                                <span class="text-muted">Address</span>
                                <div>{{ broker.address || '—' }}</div>
                            </div>
                        </div>
                        <div class="card-footer bg-white border-top-0 d-flex justify-content-between align-items-center pt-0 pb-3 pe-3 ps-3" style="display: flex !important; justify-content: space-between !important; align-items: center !important; width: 100% !important;">
                            <div class="d-flex gap-2 ms-auto" style="margin-left: auto !important;">
                                <button
                                    type="button"
                                    class="btn btn-sm btn-outline-secondary btn-icon"
                                    title="Back to References"
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
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header"><strong>Record Info</strong></div>
                        <div class="card-body small">
                            <div class="mb-2">
                                <span class="text-muted">Created</span>
                                <div>{{ formatDateTime(broker.created_at) }}</div>
                                <div v-if="broker.created_by_name" class="text-muted">
                                    by {{ broker.created_by_name }}
                                </div>
                            </div>
                            <div>
                                <span class="text-muted">Last Updated</span>
                                <div>{{ formatDateTime(broker.updated_at) }}</div>
                                <div v-if="broker.updated_by_name" class="text-muted">
                                    by {{ broker.updated_by_name }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </template>

        <div v-else class="alert alert-danger">
            Reference not found or you do not have access.
        </div>

        <BrokerFormModal
            :show="showForm"
            :saving="saving"
            :broker="broker"
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
import { deleteBroker, fetchBroker, updateBroker } from '../../api/brokers';
import AppCrudActionButton from '../../components/ui/AppCrudActionButton.vue';
import MasterCrudSaveConfirmModal from '../../components/ui/MasterCrudSaveConfirmModal.vue';
import BrokerFormModal from '../../components/brokers/BrokerFormModal.vue';
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

const canEdit = computed(() => canEditInCompany('brokers'));
const canDelete = computed(() => canEditInCompany('brokers'));

const broker = ref(null);
const loading = ref(true);
const saving = ref(false);
const showForm = ref(false);
const { fieldErrors, formError, clearErrors, applyApiError } = useApiFormErrors();

async function loadBroker() {
    const id = route.params.id;
    if (!id) {
        broker.value = null;
        loading.value = false;
        return;
    }

    loading.value = true;
    try {
        broker.value = await fetchBroker(id);
    } catch {
        broker.value = null;
        toast.error('Could not load reference details.');
    } finally {
        loading.value = false;
    }
}

function goBack() {
    if (window.history.length > 1) {
        router.back();
    } else {
        router.push({ name: 'references.index' });
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
        entityLabel: 'Reference',
        mode: 'edit',
        fields,
    });
}

async function confirmSave() {
    const payload = pendingPayload.value;
    if (!payload || !broker.value?.id) {
        return;
    }

    saving.value = true;
    try {
        broker.value = await updateBroker(broker.value.id, payload);
        toast.success('Reference updated successfully.');
        finishSaveConfirm();
        closeForm();
        await loadBroker();
    } catch (error) {
        finishSaveConfirm();
        applyApiError(error, 'Please check the highlighted fields.');
    } finally {
        saving.value = false;
    }
}

async function handleDelete() {
    if (!broker.value?.id) {
        return;
    }

    if (!window.confirm(`Delete reference "${broker.value.name}"?`)) {
        return;
    }

    try {
        await deleteBroker(broker.value.id);
        toast.success('Reference deleted successfully.');
        router.push({ name: 'references.index' });
    } catch (error) {
        toast.error(error.response?.data?.message ?? 'Could not delete reference.');
    }
}

onMounted(async () => {
    await loadBroker();
});
</script>
