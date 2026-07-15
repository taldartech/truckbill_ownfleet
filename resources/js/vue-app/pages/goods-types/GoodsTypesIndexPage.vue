<template>
    <div class="container mt-4 app-index-page app-index-page--standard goods-types-index-page">
        <AppIndexListCard title="Goods Types" :loading="loading" loading-text="Loading goods types…">
            <template #toolbar>
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
                panel-id="goods-types-filters-panel"
                :loading="loading"
                @search="loadGoodsTypes(1)"
                @reset="clearFilters"
            >
                <div class="filter-search-col">
                    <label class="form-label" for="goodsTypesIndex_search">Search</label>
                    <AppIndexSearchInput
                        v-model="search"
                        input-id="goodsTypesIndex_search"
                        placeholder="Search goods type"
                        max-width="100%"
                        size="md"
                        :disabled="loading"
                        @keydown.enter="loadGoodsTypes(1)"
                    />
                </div>
            </AppIndexCollapsibleFilters>

            <table class="table table-striped table-hover align-middle mb-0 app-index-table">
                <thead class="table-light">
                    <tr>
                        <th style="width: 80px">S.No.</th>
                        <th>Name</th>
                        <th v-if="canEdit" class="app-index-actions-cell text-end" style="width: 120px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="goodsTypes.length === 0" class="app-index-empty-row">
                        <td :colspan="canEdit ? 3 : 2" class="text-center text-muted py-4">No goods types found.</td>
                    </tr>
                    <tr v-for="(goodsType, index) in goodsTypes" :key="goodsType.id">
                        <td>{{ (pagination.currentPage - 1) * pagination.perPage + index + 1 }}</td>
                        <td>
                            <RouterLink
                                :to="{ name: 'goods-types.show', params: { id: goodsType.id } }"
                                class="goods-type-name-link"
                                title="View goods type details"
                            >
                                {{ goodsType.name }}
                            </RouterLink>
                        </td>
                        <td v-if="canEdit" class="app-index-actions-cell text-end">
                            <AppTableActions>
                                <AppCrudActionButton
                                    action="edit"
                                    @click="openEdit(goodsType)"
                                />
                                <AppCrudActionButton
                                    action="delete"
                                    @click="handleDelete(goodsType)"
                                />
                            </AppTableActions>
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

        <GoodsTypeFormModal
            :show="showModal"
            :saving="saving"
            :goods-type="editing"
            :field-errors="fieldErrors"
            :form-error="formError"
            @close="closeModal"
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
import { RouterLink } from 'vue-router';
import { createGoodsType, deleteGoodsType, listGoodsTypes, updateGoodsType } from '../../api/goodsTypes';
import AppCrudActionButton from '../../components/ui/AppCrudActionButton.vue';
import AppIndexCollapsibleFilters from '../../components/ui/AppIndexCollapsibleFilters.vue';
import AppIndexHeaderButton from '../../components/ui/AppIndexHeaderButton.vue';
import AppIndexListCard from '../../components/ui/AppIndexListCard.vue';
import AppIndexPartloadPagination from '../../components/ui/AppIndexPartloadPagination.vue';
import AppIndexSearchInput from '../../components/ui/AppIndexSearchInput.vue';
import AppTableActions from '../../components/ui/AppTableActions.vue';
import MasterCrudSaveConfirmModal from '../../components/ui/MasterCrudSaveConfirmModal.vue';
import GoodsTypeFormModal from '../../components/goods-types/GoodsTypeFormModal.vue';
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
const { pagination, syncFromMeta, reset: resetPagination } = useListPagination(10);
const showModal = ref(false);
const editing = ref(null);

const { fieldErrors, formError, clearErrors, applyApiError } = useApiFormErrors();

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
    clearErrors();
    showModal.value = true;
}

function openEdit(goodsType) {
    editing.value = goodsType;
    clearErrors();
    showModal.value = true;
}

function closeModal() {
    showModal.value = false;
    editing.value = null;
    clearErrors();
}

function handleSubmit({ payload, fields }) {
    clearErrors();

    promptSaveConfirm({
        payload,
        entityLabel: 'Goods Type',
        mode: editing.value ? 'edit' : 'create',
        fields,
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
});
</script>

<style scoped>
/* Custom filters single row layout */
:deep(.app-index-collapsible-filters__inner) {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-end;
    gap: 12px;
}

:deep(.app-index-filters-fields) {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-end;
    gap: 12px;
    flex: 1 1 auto;
}

:deep(.app-index-collapsible-filters__actions) {
    margin-top: 0;
    align-self: flex-end;
}

.filter-search-col {
    flex: 0 0 280px;
    width: 280px;
}

.goods-type-name-link {
    font-weight: 600;
    color: var(--bs-link-color, #0d6efd);
    text-decoration: none;
}

.goods-type-name-link:hover {
    text-decoration: underline;
}
</style>
