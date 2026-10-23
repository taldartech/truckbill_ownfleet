<template>
    <div class="container mt-4 app-index-page app-index-page--standard app-index-page--inline-filters stations-index-page">
        <AppIndexListCard title="Stations" :loading="loading" loading-text="Loading stations…">
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
                    title="Bulk Upload Stations"
                    aria-label="Bulk Upload Stations"
                    @click="showBulkUpload = true"
                >
                    <span class="d-none d-md-inline ms-1">Bulk Upload</span>
                </AppIndexHeaderButton>
                <AppIndexHeaderButton
                    v-if="canCreate"
                    icon="plus"
                    title="Add Station"
                    aria-label="Add Station"
                    @click="openCreate"
                >
                    <span class="d-none d-md-inline ms-1">Add Station</span>
                </AppIndexHeaderButton>
            </template>

            <template #filters>
                <div v-show="!isMobile || filtersOpen" class="w-100">
                    <AppIndexCollapsibleFilters
                        panel-id="stations-filters-panel"
                        :loading="loading"
                        @search="loadStations(1)"
                        @reset="clearFilters"
                    >
                        <div class="filter-search-col">
                            <label class="form-label d-none d-md-block" for="stationsIndex_search">Search</label>
                            <AppIndexSearchInput
                                v-model="search"
                                input-id="stationsIndex_search"
                                placeholder="Search station, city, state"
                                max-width="100%"
                                size="md"
                                :disabled="loading"
                                @keydown.enter="loadStations(1)"
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
                        <th>City</th>
                        <th>State</th>
                        <th v-if="canEdit" class="app-index-actions-cell text-end" style="width: 120px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="stations.length === 0" class="app-index-empty-row">
                        <td :colspan="canEdit ? 5 : 4" class="text-center text-muted py-4">No stations found.</td>
                    </tr>
                    <tr v-for="(station, index) in stations" :key="station.id">
                        <td>{{ (pagination.currentPage - 1) * pagination.perPage + index + 1 }}</td>
                        <td>
                            <RouterLink
                                :to="{ name: 'stations.show', params: { id: station.id } }"
                                class="station-name-link"
                                title="View station details"
                            >
                                {{ station.name }}
                            </RouterLink>
                        </td>
                        <td>{{ station.city_name || '—' }}</td>
                        <td>{{ station.state_name || '—' }}</td>
                        <td v-if="canEdit" class="app-index-actions-cell text-end">
                            <AppTableActions>
                                <AppCrudActionButton
                                    action="edit"
                                    @click="openEdit(station)"
                                />
                                <AppCrudActionButton
                                    action="delete"
                                    @click="handleDelete(station)"
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
                    aria-label="Stations pagination"
                    @page-change="goToPage"
                />
            </template>
        </AppIndexListCard>

        <StationFormModal
            :show="showModal"
            :saving="saving"
            :station="editing"
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
            title="Bulk Upload Stations"
            upload-url="/app/api/stations/bulk-upload"
            template-download-url="/app/api/stations/bulk-upload/template"
            template-file-name="stations_template.xlsx"
            :columns="bulkUploadColumns"
            @success="loadStations(1)"
        />
    </div>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { RouterLink } from 'vue-router';
import { createStation, deleteStation, listStations, updateStation } from '../../api/stations';
import AppCrudActionButton from '../../components/ui/AppCrudActionButton.vue';
import AppIndexCollapsibleFilters from '../../components/ui/AppIndexCollapsibleFilters.vue';
import AppIndexHeaderButton from '../../components/ui/AppIndexHeaderButton.vue';
import AppIndexListCard from '../../components/ui/AppIndexListCard.vue';
import AppIndexPartloadPagination from '../../components/ui/AppIndexPartloadPagination.vue';
import AppIndexSearchInput from '../../components/ui/AppIndexSearchInput.vue';
import AppTableActions from '../../components/ui/AppTableActions.vue';
import MasterCrudSaveConfirmModal from '../../components/ui/MasterCrudSaveConfirmModal.vue';
import BulkUploadModal from '../../components/ui/BulkUploadModal.vue';
import StationFormModal from '../../components/stations/StationFormModal.vue';
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
const canCreate = computed(() => canCreateInCompany('stations') || canInCompany('trips.create'));
const canEdit = computed(() => canEditInCompany('stations'));

const stations = ref([]);
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
    { name: 'name', label: 'Station Name', required: true, description: 'Unique station name' },
    { name: 'state', label: 'State', required: true, description: 'State name or state code' },
    { name: 'city', label: 'City', required: true, description: 'City name in the State' },
];

const { fieldErrors, formError, clearErrors, applyApiError } = useApiFormErrors();

function clearFilters() {
    search.value = '';
    pagination.currentPage = 1;
    loadStations(1);
}

async function loadStations(page = pagination.currentPage) {
    loading.value = true;

    try {
        const response = await listStations({
            page,
            perPage: pagination.perPage,
            search: search.value.trim(),
        });

        stations.value = response.data;
        syncFromMeta(response.meta, stations.value.length);
    } catch {
        toast.error('Could not load stations.');
        stations.value = [];
        resetPagination();
    } finally {
        loading.value = false;
    }
}

function goToPage(page) {
    if (page < 1 || page > pagination.lastPage || page === pagination.currentPage) {
        return;
    }

    loadStations(page);
}

function openCreate() {
    editing.value = null;
    clearErrors();
    showModal.value = true;
}

function openEdit(station) {
    editing.value = station;
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
        entityLabel: 'Station',
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
            await updateStation(editing.value.id, payload);
            toast.success('Station updated successfully.');
        } else {
            await createStation(payload);
            toast.success('Station added successfully.');
        }

        finishSaveConfirm();
        closeModal();
        await loadStations();
    } catch (error) {
        finishSaveConfirm();
        applyApiError(error, 'Please check the highlighted fields.');
    } finally {
        saving.value = false;
    }
}

function handleDelete(station) {
    promptDelete({
        title: 'Delete Station?',
        itemName: station.name,
        details: [
            { label: 'Name', value: station.name },
            { label: 'City', value: station.city },
            { label: 'State', value: station.state },
        ],
        onConfirm: async (remark) => {
            await deleteStation(station.id, { deleted_reason: remark, remark });
            toast.success('Station deleted successfully.');

            const page =
                stations.value.length === 1 && pagination.currentPage > 1
                    ? pagination.currentPage - 1
                    : pagination.currentPage;

            await loadStations(page);
        },
    });
}

onMounted(async () => {
    handleResize();
    if (typeof window !== 'undefined') {
        window.addEventListener('resize', handleResize);
    }
    await loadStations();
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

.station-name-link {
    font-weight: 600;
    color: var(--bs-link-color, #0d6efd);
    text-decoration: none;
}

.station-name-link:hover {
    text-decoration: underline;
}
</style>
