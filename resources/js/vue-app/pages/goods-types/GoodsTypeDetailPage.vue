<template>
    <div class="container mt-4 goods-types-detail-page">
        <div v-if="loading" class="text-center py-5 text-muted">Loading goods type details…</div>

        <template v-else-if="goodsType">
            <div class="row g-3">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header">
                            <strong>Goods Type Information ({{ goodsType?.name }})</strong>
                        </div>
                        <div class="card-body row g-3 small">
                            <div class="col-md-12">
                                <span class="text-muted">Goods Type Name</span>
                                <div class="fw-semibold fs-5 text-dark">{{ goodsType.name }}</div>
                            </div>
                        </div>
                        <div class="card-footer bg-white border-top-0 d-flex justify-content-between align-items-center pt-0 pb-3 pe-3 ps-3" style="display: flex !important; justify-content: space-between !important; align-items: center !important; width: 100% !important;">
                            <div class="d-flex gap-2 ms-auto" style="margin-left: auto !important;">
                                <button
                                    type="button"
                                    class="btn btn-sm btn-outline-secondary btn-icon"
                                    title="Back to Goods Types"
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
                                <div>{{ formatDateTime(goodsType.created_at) }}</div>
                                <div v-if="goodsType.created_by_name" class="text-muted">
                                    by {{ goodsType.created_by_name }}
                                </div>
                            </div>
                            <div>
                                <span class="text-muted">Last Updated</span>
                                <div>{{ formatDateTime(goodsType.updated_at) }}</div>
                                <div v-if="goodsType.updated_by_name" class="text-muted">
                                    by {{ goodsType.updated_by_name }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </template>

        <div v-else class="alert alert-danger">
            Goods type not found or you do not have access.
        </div>

        <GoodsTypeFormModal
            :show="showForm"
            :saving="saving"
            :goods-type="goodsType"
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
import { deleteGoodsType, fetchGoodsType, updateGoodsType } from '../../api/goodsTypes';
import AppCrudActionButton from '../../components/ui/AppCrudActionButton.vue';
import MasterCrudSaveConfirmModal from '../../components/ui/MasterCrudSaveConfirmModal.vue';
import GoodsTypeFormModal from '../../components/goods-types/GoodsTypeFormModal.vue';
import { useApiFormErrors } from '../../composables/useApiFormErrors';
import { usePermissions } from '../../composables/usePermissions';
import { useAppToast } from '../../composables/useAppToast';
import { useMasterCrudSaveConfirm } from '../../composables/useMasterCrudSaveConfirm';
import { formatDateTime } from '../../utils/datetime';

const route = useRoute();
const router = useRouter();
const toast = useAppToast();
const { canEditInCompany } = usePermissions();

const {
    showSaveConfirm,
    saveConfirmSummary,
    pendingPayload,
    promptSaveConfirm,
    cancelSaveConfirm,
    finishSaveConfirm,
} = useMasterCrudSaveConfirm();

const canEdit = computed(() => canEditInCompany('goods_types'));
const canDelete = computed(() => canEditInCompany('goods_types'));

const goodsType = ref(null);
const loading = ref(true);
const saving = ref(false);
const showForm = ref(false);
const { fieldErrors, formError, clearErrors, applyApiError } = useApiFormErrors();

async function loadGoodsType() {
    const id = route.params.id;
    if (!id) {
        goodsType.value = null;
        loading.value = false;
        return;
    }

    loading.value = true;
    try {
        goodsType.value = await fetchGoodsType(id);
    } catch {
        goodsType.value = null;
        toast.error('Could not load goods type details.');
    } finally {
        loading.value = false;
    }
}

function goBack() {
    if (window.history.length > 1) {
        router.back();
    } else {
        router.push({ name: 'goods-types.index' });
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
        entityLabel: 'Goods Type',
        mode: 'edit',
        fields,
    });
}

async function confirmSave() {
    const payload = pendingPayload.value;
    if (!payload || !goodsType.value?.id) {
        return;
    }

    saving.value = true;
    try {
        goodsType.value = await updateGoodsType(goodsType.value.id, payload);
        toast.success('Goods type updated successfully.');
        finishSaveConfirm();
        closeForm();
        await loadGoodsType();
    } catch (error) {
        finishSaveConfirm();
        applyApiError(error, 'Please check the highlighted fields.');
    } finally {
        saving.value = false;
    }
}

async function handleDelete() {
    if (!goodsType.value?.id) {
        return;
    }

    if (!window.confirm(`Remove goods type "${goodsType.value.name}"?`)) {
        return;
    }

    try {
        await deleteGoodsType(goodsType.value.id);
        toast.success('Goods type removed successfully.');
        router.push({ name: 'goods-types.index' });
    } catch (error) {
        toast.error(error.response?.data?.message ?? 'Could not remove goods type.');
    }
}

onMounted(async () => {
    await loadGoodsType();
});
</script>
