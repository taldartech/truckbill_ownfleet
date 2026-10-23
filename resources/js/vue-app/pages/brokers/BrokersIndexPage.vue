<template>
    <div class="container mt-4 app-index-page app-index-page--standard app-index-page--inline-filters references-index-page">
        <AppIndexListCard title="References" :loading="loading" loading-text="Loading references…">
            <template #toolbar>
                <button
                    type="button"
                    class="btn btn-sm bg-white shadow-sm d-inline-flex d-md-none align-items-center justify-content-center p-0 app-index-header-btn app-index-header-btn--filter"
                    title="Filters"
                    aria-label="Toggle search filters"
                    @click="toggleFilters"
                >
                    <svg width="13" height="13" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M11.5 2a1.5 1.5 0 1 0 0 3 1.5 1.5 0 0 0 0-3zM9.05 3a2.5 2.5 0 0 1 4.9 0H15a.5.5 0 0 1 0 1h-1.05a2.5 2.5 0 0 1-4.9 0H1a.5.5 0 0 1 0-1h8.05zM4.5 7a1.5 1.5 0 1 0 0 3 1.5 1.5 0 0 0 0-3zM2.05 8a2.5 2.5 0 0 1 4.9 0H15a.5.5 0 0 1 0 1H6.95a2.5 2.5 0 0 1-4.9 0H1a.5.5 0 0 1 0-1h1.05zm9.45 4a1.5 1.5 0 1 0 0 3 1.5 1.5 0 0 0 0-3zm-2.45 1a2.5 2.5 0 0 1 4.9 0H15a.5.5 0 0 1 0 1h-1.05a2.5 2.5 0 0 1-4.9 0H1a.5.5 0 0 1 0-1h8.05z"/>
                    </svg>
                </button>
                <AppIndexHeaderButton
                    v-if="canCreate"
                    icon="upload"
                    title="Bulk Upload References"
                    aria-label="Bulk Upload References"
                    @click="showBulkUpload = true"
                >
                    <span class="d-none d-md-inline ms-1">Bulk Upload</span>
                </AppIndexHeaderButton>
                <AppIndexHeaderButton
                    v-if="canCreate"
                    icon="plus"
                    title="Add Reference"
                    aria-label="Add Reference"
                    @click="openCreate"
                >
                    <span class="d-none d-md-inline ms-1">Add Reference</span>
                </AppIndexHeaderButton>
            </template>

            <template #filters>
                <div v-show="!isMobile || filtersOpen" class="w-100">
                    <AppIndexCollapsibleFilters
                        panel-id="references-filters-panel"
                        :loading="loading"
                        @search="loadBrokers(1)"
                        @reset="clearFilters"
                    >
                        <div class="filter-search-col">
                            <label class="form-label d-none d-md-block" for="brokersIndex_search">Search</label>
                            <AppIndexSearchInput
                                v-model="search"
                                input-id="brokersIndex_search"
                                placeholder="Search name, GST, PAN, contact"
                                max-width="100%"
                                size="md"
                                :disabled="loading"
                                @keydown.enter="loadBrokers(1)"
                            />
                        </div>
                    </AppIndexCollapsibleFilters>
                </div>
            </template>

            <table class="table table-striped table-hover align-middle mb-0 app-index-table">
                <thead class="table-light">
                    <tr>
                        <th style="width: 80px">S.No.</th>
                        <th>Name</th>
                        <th>Contact</th>
                        <th>GST</th>
                        <th>PAN</th>
                        <th>City</th>
                        <th>State</th>
                        <th v-if="canEdit" class="app-index-actions-cell text-end" style="width: 120px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="brokers.length === 0" class="app-index-empty-row">
                        <td :colspan="canEdit ? 8 : 7" class="text-center text-muted py-4">No references found.</td>
                    </tr>
                    <tr v-for="(broker, index) in brokers" :key="broker.id">
                        <td>{{ (pagination.currentPage - 1) * pagination.perPage + index + 1 }}</td>
                        <td>
                            <RouterLink
                                :to="{ name: 'references.show', params: { id: broker.id } }"
                                class="reference-name-link"
                                title="View reference details"
                            >
                                {{ broker.name }}
                            </RouterLink>
                        </td>
                        <td>{{ broker.contact_number || '—' }}</td>
                        <td>{{ broker.gst_number || '—' }}</td>
                        <td>{{ broker.pan_number || '—' }}</td>
                        <td>{{ broker.city_name || '—' }}</td>
                        <td>{{ broker.state_name || '—' }}</td>
                        <td v-if="canEdit" class="app-index-actions-cell text-end">
                            <AppTableActions>
                                <AppCrudActionButton
                                    action="edit"
                                    @click="openEdit(broker)"
                                />
                                <AppCrudActionButton
                                    action="delete"
                                    @click="handleDelete(broker)"
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
                    aria-label="References pagination"
                    @page-change="goToPage"
                />
            </template>
        </AppIndexListCard>

        <BrokerFormModal
            :show="showModal"
            :saving="saving"
            :broker="editing"
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

        <BulkUploadModal
            v-model:show="showBulkUpload"
            title="Bulk Upload References"
            upload-url="/app/api/brokers/bulk-upload"
            template-download-url="/app/api/brokers/bulk-upload/template"
            template-file-name="brokers_template.xlsx"
            :columns="bulkUploadColumns"
            @success="loadBrokers(1)"
        />
    </div>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { RouterLink } from 'vue-router';
import { createBroker, deleteBroker, listBrokers, updateBroker } from '../../api/brokers';
import AppCrudActionButton from '../../components/ui/AppCrudActionButton.vue';
import AppIndexCollapsibleFilters from '../../components/ui/AppIndexCollapsibleFilters.vue';
import AppIndexHeaderButton from '../../components/ui/AppIndexHeaderButton.vue';
import AppIndexListCard from '../../components/ui/AppIndexListCard.vue';
import AppIndexPartloadPagination from '../../components/ui/AppIndexPartloadPagination.vue';
import AppIndexSearchInput from '../../components/ui/AppIndexSearchInput.vue';
import AppTableActions from '../../components/ui/AppTableActions.vue';
import MasterCrudSaveConfirmModal from '../../components/ui/MasterCrudSaveConfirmModal.vue';
import BulkUploadModal from '../../components/ui/BulkUploadModal.vue';
import BrokerFormModal from '../../components/brokers/BrokerFormModal.vue';
import { useApiFormErrors } from '../../composables/useApiFormErrors';
import { useAppToast } from '../../composables/useAppToast';
import { useListPagination } from '../../composables/useListPagination';
import { useMasterCrudSaveConfirm } from '../../composables/useMasterCrudSaveConfirm';
import { usePermissions } from '../../composables/usePermissions';
import { useDeleteConfirm } from '../../composables/useDeleteConfirm';

const toast = useAppToast();
const { promptDelete } = useDeleteConfirm();
const {
    showSaveConfirm,
    saveConfirmSummary,
    pendingPayload,
    promptSaveConfirm,
    cancelSaveConfirm,
    finishSaveConfirm,
} = useMasterCrudSaveConfirm();
const { canInCompany, canCreateInCompany, canEditInCompany } = usePermissions();
const canCreate = computed(() => canCreateInCompany('brokers') || canInCompany('trips.create'));
const canEdit = computed(() => canEditInCompany('brokers'));

const brokers = ref([]);
const loading = ref(true);
const saving = ref(false);
const search = ref('');
const isMobile = ref(false);
const filtersOpen = ref(false);
const { pagination, syncFromMeta, reset: resetPagination } = useListPagination(10);
const showModal = ref(false);
const editing = ref(null);
const showBulkUpload = ref(false);

function toggleFilters() {
    filtersOpen.value = !filtersOpen.value;
}

function handleResize() {
    if (typeof window !== 'undefined') {
        isMobile.value = window.innerWidth < 768;
    }
}

const bulkUploadColumns = [
    { name: 'name', label: 'Reference / Broker Name', required: true, description: 'Broker or Reference name' },
    { name: 'contact_number', label: 'Mobile Number', required: true, description: 'Unique 10-digit mobile number' },
    { name: 'state', label: 'State', required: true, description: 'State name or state code' },
    { name: 'city', label: 'City', required: true, description: 'City belonging to the State' },
    { name: 'address', label: 'Address', required: false, description: 'Complete office/contact address' },
    { name: 'gst_number', label: 'GST Number', required: false, description: '15-character GSTIN' },
    { name: 'pan_number', label: 'PAN Number', required: false, description: '10-character PAN' },
];

const { fieldErrors, formError, clearErrors, applyApiError } = useApiFormErrors();

function clearFilters() {
    search.value = '';
    pagination.currentPage = 1;
    loadBrokers(1);
}

async function loadBrokers(page = pagination.currentPage) {
    loading.value = true;

    try {
        const response = await listBrokers({
            page,
            perPage: pagination.perPage,
            search: search.value.trim(),
        });

        brokers.value = response.data;
        syncFromMeta(response.meta, brokers.value.length);
    } catch {
        toast.error('Could not load references.');
        brokers.value = [];
        resetPagination();
    } finally {
        loading.value = false;
    }
}

function goToPage(page) {
    if (page < 1 || page > pagination.lastPage || page === pagination.currentPage) {
        return;
    }

    loadBrokers(page);
}

function openCreate() {
    editing.value = null;
    clearErrors();
    showModal.value = true;
}

function openEdit(broker) {
    editing.value = broker;
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
        entityLabel: 'Reference',
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
            await updateBroker(editing.value.id, payload);
            toast.success('Reference updated successfully.');
        } else {
            await createBroker(payload);
            toast.success('Reference added successfully.');
        }

        finishSaveConfirm();
        closeModal();
        await loadBrokers();
    } catch (error) {
        finishSaveConfirm();
        applyApiError(error, 'Please check the highlighted fields.');
    } finally {
        saving.value = false;
    }
}

function handleDelete(broker) {
    promptDelete({
        title: 'Delete Reference / Broker?',
        itemName: broker.name,
        details: [
            { label: 'Name', value: broker.name },
            { label: 'Phone', value: broker.phone },
            { label: 'PAN Number', value: broker.pan_number },
            { label: 'City', value: broker.city },
        ],
        onConfirm: async (remark) => {
            await deleteBroker(broker.id, { deleted_reason: remark, remark });
            toast.success('Reference deleted successfully.');
            await loadBrokers();
        },
    });
}

onMounted(async () => {
    handleResize();
    if (typeof window !== 'undefined') {
        window.addEventListener('resize', handleResize);
    }
    await loadBrokers();
});

onUnmounted(() => {
    if (typeof window !== 'undefined') {
        window.removeEventListener('resize', handleResize);
    }
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

.reference-name-link {
    font-weight: 600;
    color: var(--bs-link-color, #0d6efd);
    text-decoration: none;
}

.reference-name-link:hover {
    text-decoration: underline;
}
</style>
