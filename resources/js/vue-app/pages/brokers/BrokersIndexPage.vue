<template>
    <div class="container mt-4 app-index-page app-index-page--standard references-index-page">
        <AppIndexListCard title="References" :loading="loading" loading-text="Loading references…">
            <template #toolbar>
                <AppIndexHeaderButton
                    v-if="canCreate"
                    icon="upload"
                    aria-label="Bulk Upload References"
                    @click="showBulkUpload = true"
                >
                    Bulk Upload
                </AppIndexHeaderButton>
                <AppIndexHeaderButton
                    v-if="canCreate"
                    icon="plus"
                    aria-label="Add Reference"
                    @click="openCreate"
                >
                    Add Reference
                </AppIndexHeaderButton>
            </template>

            <AppIndexCollapsibleFilters
                panel-id="references-filters-panel"
                :loading="loading"
                @search="loadBrokers(1)"
                @reset="clearFilters"
            >
                <div class="filter-search-col">
                    <label class="form-label" for="brokersIndex_search">Search</label>
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
            template-file-name="brokers_template.csv"
            :columns="bulkUploadColumns"
            @success="loadBrokers(1)"
        />
    </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
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

const toast = useAppToast();
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
const { pagination, syncFromMeta, reset: resetPagination } = useListPagination(10);
const showModal = ref(false);
const editing = ref(null);
const showBulkUpload = ref(false);

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

async function handleDelete(broker) {
    if (!window.confirm(`Delete reference "${broker.name}"?`)) {
        return;
    }

    try {
        await deleteBroker(broker.id);
        toast.success('Reference deleted successfully.');
        await loadBrokers();
    } catch (error) {
        toast.error(error.response?.data?.message ?? 'Could not delete reference.');
    }
}

onMounted(async () => {
    await loadBrokers();
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
