<template>
    <div class="container mt-4 app-index-page app-index-page--standard">
        <AppIndexListCard title="Goods Types" :loading="loading" loading-text="Loading goods types…">
            <template #toolbar>
                <AppIndexHeaderButton
                    icon="filter"
                    :aria-expanded="filtersOpen"
                    aria-controls="goods-types-filters-panel"
                    aria-label="Toggle search filters"
                    @click="toggleFilters"
                >
                    Search filter
                </AppIndexHeaderButton>
                <AppIndexHeaderButton
                    v-if="canCreate"
                    icon="plus"
                    aria-label="Add Goods Type"
                    @click="openCreate"
                >
                    Add Goods Type
                </AppIndexHeaderButton>
            </template>

            <AppIndexCollapsibleFilters
                v-show="filtersOpen"
                panel-id="goods-types-filters-panel"
                :loading="loading"
                @search="loadGoodsTypes(1)"
                @reset="clearFilters"
            >
                <div>
                    <label class="form-label" for="goodsTypesIndex_search">Search</label>
                    <AppIndexSearchInput
                        v-model="search"
                        input-id="goodsTypesIndex_search"
                        placeholder="Search goods type"
                        max-width="100%"
                        :disabled="loading"
                        @keydown.enter="loadGoodsTypes(1)"
                    />
                </div>
            </AppIndexCollapsibleFilters>

            <table class="table table-striped table-hover align-middle mb-0 app-index-table">
                <thead class="table-light">
                    <tr>
                        <th>Name</th>
                        <th>Code</th>
                        <th v-if="canEdit" class="app-index-actions-cell text-end" style="width: 120px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="goodsTypes.length === 0" class="app-index-empty-row">
                        <td :colspan="canEdit ? 3 : 2" class="text-center text-muted py-4">No goods types found.</td>
                    </tr>
                    <tr v-for="goodsType in goodsTypes" :key="goodsType.id">
                        <td>{{ goodsType.name }}</td>
                        <td>{{ goodsType.code }}</td>
                        <td v-if="canEdit" class="app-index-actions-cell text-end">
                            <div class="app-index-table-actions">
                                <button
                                    type="button"
                                    class="btn btn-sm btn-info text-white app-index-action-btn"
                                    title="Edit"
                                    aria-label="Edit"
                                    @click="openEdit(goodsType)"
                                >
                                    <i class="fas fa-edit" aria-hidden="true"></i>
                                </button>
                                <button
                                    type="button"
                                    class="btn btn-sm btn-danger app-index-action-btn"
                                    title="Remove"
                                    aria-label="Remove"
                                    @click="handleDelete(goodsType)"
                                >
                                    <i class="fas fa-trash" aria-hidden="true"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>

            <template #footer>
                <AppIndexPartloadPagination
                    :pagination="pagination"
                    :loading="loading"
                    aria-label="Goods types pagination"
                    @page-change="goToPage"
                />
            </template>
        </AppIndexListCard>

        <AppModal
            :show="showModal"
            :loading="saving"
            :form-error="formError"
            as-form
            @close="closeModal"
            @submit="save"
        >
            <template #title>{{ editing ? 'Edit Goods Type' : 'Add Goods Type' }}</template>

            <label class="form-label">Goods Type Name *</label>
            <input
                v-model="form.name"
                type="text"
                class="form-control"
                :class="{ 'is-invalid': fieldErrors.name }"
                placeholder="e.g. Cement"
                required
            />
            <div v-if="fieldErrors.name" class="invalid-feedback d-block">
                {{ fieldErrors.name[0] }}
            </div>
            <div class="form-text">Shared across all branches in this company.</div>

            <template #footer>
                <button type="button" class="btn btn-secondary" :disabled="saving" @click="closeModal">Cancel</button>
                <AppButton type="submit" label="Save" :loading="saving" />
            </template>
        </AppModal>

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
import { computed, onMounted, reactive, ref } from 'vue';
import { createGoodsType, deleteGoodsType, listGoodsTypes, updateGoodsType } from '../../api/goodsTypes';
import AppModal from '../../components/ui/AppModal.vue';
import AppButton from '../../components/ui/AppButton.vue';
import AppIndexCollapsibleFilters from '../../components/ui/AppIndexCollapsibleFilters.vue';
import AppIndexHeaderButton from '../../components/ui/AppIndexHeaderButton.vue';
import AppIndexListCard from '../../components/ui/AppIndexListCard.vue';
import AppIndexPartloadPagination from '../../components/ui/AppIndexPartloadPagination.vue';
import AppIndexSearchInput from '../../components/ui/AppIndexSearchInput.vue';
import MasterCrudSaveConfirmModal from '../../components/ui/MasterCrudSaveConfirmModal.vue';
import { useApiFormErrors } from '../../composables/useApiFormErrors';
import { useAppToast } from '../../composables/useAppToast';
import { useListPagination } from '../../composables/useListPagination';
import { useMasterCrudSaveConfirm } from '../../composables/useMasterCrudSaveConfirm';
import { usePermissions } from '../../composables/usePermissions';

const toast = useAppToast();
const {
    showSaveConfirm,
    saveConfirmSummary,
    pendingPayload,
    promptSaveConfirm,
    cancelSaveConfirm,
    finishSaveConfirm,
} = useMasterCrudSaveConfirm();
const { canCreateInCompany, canEditInCompany } = usePermissions();
const canCreate = computed(() => canCreateInCompany('goods_types'));
const canEdit = computed(() => canEditInCompany('goods_types'));

const goodsTypes = ref([]);
const loading = ref(true);
const saving = ref(false);
const search = ref('');
const filtersOpen = ref(false);
const { pagination, syncFromMeta, reset: resetPagination } = useListPagination(10);
const showModal = ref(false);
const editing = ref(null);
const form = reactive({ name: '' });
const { fieldErrors, formError, clearErrors, applyApiError } = useApiFormErrors();

function toggleFilters() {
    filtersOpen.value = !filtersOpen.value;
}

function clearFilters() {
    search.value = '';
    pagination.currentPage = 1;
    loadGoodsTypes(1);
}

async function loadGoodsTypes(page = pagination.currentPage) {
    loading.value = true;

    try {
        const response = await listGoodsTypes({
            page,
            perPage: pagination.perPage,
            search: search.value.trim(),
        });

        goodsTypes.value = response.data;
        syncFromMeta(response.meta, goodsTypes.value.length);
    } catch {
        toast.error('Could not load goods types.');
        goodsTypes.value = [];
        resetPagination();
    } finally {
        loading.value = false;
    }
}

function goToPage(page) {
    if (page < 1 || page > pagination.lastPage || page === pagination.currentPage) {
        return;
    }

    loadGoodsTypes(page);
}

function openCreate() {
    editing.value = null;
    form.name = '';
    clearErrors();
    showModal.value = true;
}

function openEdit(goodsType) {
    editing.value = goodsType;
    form.name = goodsType.name;
    clearErrors();
    showModal.value = true;
}

function closeModal() {
    showModal.value = false;
    editing.value = null;
    clearErrors();
}

function buildPayload() {
    return {
        name: form.name.trim(),
    };
}

function save() {
    clearErrors();
    const payload = buildPayload();

    promptSaveConfirm({
        payload,
        entityLabel: 'Goods Type',
        mode: editing.value ? 'edit' : 'create',
        fields: [
            {
                label: 'Name',
                value: payload.name,
                previousValue: editing.value?.name,
            },
        ],
    });
}

async function confirmSave() {
    const payload = pendingPayload.value;

    if (!payload) {
        return;
    }

    saving.value = true;

    try {
        if (editing.value) {
            await updateGoodsType(editing.value.id, payload);
            toast.success('Goods type updated successfully.');
        } else {
            await createGoodsType(payload);
            toast.success('Goods type added successfully.');
        }

        finishSaveConfirm();
        closeModal();
        await loadGoodsTypes();
    } catch (error) {
        finishSaveConfirm();
        applyApiError(error, 'Please check the highlighted fields.');
    } finally {
        saving.value = false;
    }
}

async function handleDelete(goodsType) {
    if (!window.confirm(`Remove goods type "${goodsType.name}"?`)) {
        return;
    }

    try {
        await deleteGoodsType(goodsType.id);
        toast.success('Goods type removed successfully.');
        await loadGoodsTypes();
    } catch (error) {
        toast.error(error.response?.data?.message ?? 'Could not remove goods type.');
    }
}

onMounted(async () => {
    await loadGoodsTypes();

    if (search.value.trim() !== '') {
        filtersOpen.value = true;
    }
});
</script>
