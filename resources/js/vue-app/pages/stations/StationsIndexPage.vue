<template>
    <div class="container mt-4 app-index-page app-index-page--standard">
        <AppIndexListCard title="Stations" :loading="loading" loading-text="Loading stations…">
            <template #toolbar>
                <AppIndexHeaderButton
                    icon="filter"
                    :aria-expanded="filtersOpen"
                    aria-controls="stations-filters-panel"
                    aria-label="Toggle search filters"
                    @click="toggleFilters"
                >
                    Search filter
                </AppIndexHeaderButton>
                <AppIndexHeaderButton
                    v-if="canCreate"
                    icon="plus"
                    aria-label="Add Station"
                    @click="openCreate"
                >
                    Add Station
                </AppIndexHeaderButton>
            </template>

            <AppIndexCollapsibleFilters
                v-show="filtersOpen"
                panel-id="stations-filters-panel"
                :loading="loading"
                @search="loadStations(1)"
                @reset="clearFilters"
            >
                <div>
                    <label class="form-label" for="stationsIndex_search">Search</label>
                    <AppIndexSearchInput
                        v-model="search"
                        input-id="stationsIndex_search"
                        placeholder="Search station, city, state"
                        max-width="100%"
                        :disabled="loading"
                        @keydown.enter="loadStations(1)"
                    />
                </div>
            </AppIndexCollapsibleFilters>

            <table class="table table-striped table-hover align-middle mb-0 app-index-table">
                <thead class="table-light">
                    <tr>
                        <th>Name</th>
                        <th>City</th>
                        <th>State</th>
                        <th v-if="canEdit" class="app-index-actions-cell text-end" style="width: 120px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="stations.length === 0" class="app-index-empty-row">
                        <td :colspan="canEdit ? 4 : 3" class="text-center text-muted py-4">No stations found.</td>
                    </tr>
                    <tr v-for="station in stations" :key="station.id">
                        <td>{{ station.name }}</td>
                        <td>{{ station.city_name || '—' }}</td>
                        <td>{{ station.state_name || '—' }}</td>
                        <td v-if="canEdit" class="app-index-actions-cell text-end">
                            <div class="app-index-table-actions">
                                <button
                                    type="button"
                                    class="btn btn-sm btn-info text-white app-index-action-btn"
                                    title="Edit"
                                    aria-label="Edit"
                                    @click="openEdit(station)"
                                >
                                    <i class="fas fa-edit" aria-hidden="true"></i>
                                </button>
                                <button
                                    type="button"
                                    class="btn btn-sm btn-danger app-index-action-btn"
                                    title="Delete"
                                    aria-label="Delete"
                                    @click="handleDelete(station)"
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
                    aria-label="Stations pagination"
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
            <template #title>{{ editing ? 'Edit Station' : 'Add Station' }}</template>

            <div class="row g-2">
                <IndiaLocationFields
                    v-model:state-id="stateId"
                    v-model:city-id="cityId"
                    :states="states"
                    :cities="cities"
                    :field-errors="fieldErrors"
                />

                <div class="col-12">
                    <label class="form-label">Station Name *</label>
                    <input
                        v-model="form.name"
                        type="text"
                        class="form-control"
                        :class="{ 'is-invalid': fieldErrors.name }"
                        required
                    />
                    <div v-if="fieldErrors.name" class="invalid-feedback d-block">
                        {{ fieldErrors.name[0] }}
                    </div>
                    <div class="form-text">Example: Depot, Yard, or local area name.</div>
                </div>
            </div>

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
import { createStation, deleteStation, listStations, updateStation } from '../../api/stations';
import AppModal from '../../components/ui/AppModal.vue';
import AppButton from '../../components/ui/AppButton.vue';
import AppIndexCollapsibleFilters from '../../components/ui/AppIndexCollapsibleFilters.vue';
import AppIndexHeaderButton from '../../components/ui/AppIndexHeaderButton.vue';
import AppIndexListCard from '../../components/ui/AppIndexListCard.vue';
import AppIndexPartloadPagination from '../../components/ui/AppIndexPartloadPagination.vue';
import AppIndexSearchInput from '../../components/ui/AppIndexSearchInput.vue';
import MasterCrudSaveConfirmModal from '../../components/ui/MasterCrudSaveConfirmModal.vue';
import IndiaLocationFields from '../../components/locations/IndiaLocationFields.vue';
import { useApiFormErrors } from '../../composables/useApiFormErrors';
import { useAppToast } from '../../composables/useAppToast';
import { useListPagination } from '../../composables/useListPagination';
import { useLocationLookups } from '../../composables/useLocationLookups';
import { useMasterCrudSaveConfirm } from '../../composables/useMasterCrudSaveConfirm';
import { usePermissions } from '../../composables/usePermissions';
import { buildStationFields } from '../../utils/masterCrudSaveConfirm';

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
const canCreate = computed(() => canCreateInCompany('stations') || canInCompany('trips.create'));
const canEdit = computed(() => canEditInCompany('stations'));

const {
    states,
    cities,
    countryId,
    stateId,
    cityId,
    indiaCountry,
    initialize,
    resetLocationFields,
} = useLocationLookups();

const stations = ref([]);
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

async function openCreate() {
    editing.value = null;
    form.name = '';
    clearErrors();
    resetLocationFields();
    showModal.value = true;
    await initialize();
}

async function openEdit(station) {
    editing.value = station;
    form.name = station.name;
    clearErrors();
    await initialize({
        countryId: station.country_id || countryId.value,
        stateId: station.state_id,
        cityId: station.city_id,
    });
    showModal.value = true;
}

function closeModal() {
    showModal.value = false;
    editing.value = null;
    clearErrors();
    resetLocationFields();
}

function buildPayload() {
    return {
        name: form.name.trim(),
        country_id: countryId.value || indiaCountry.value?.id,
        state_id: stateId.value,
        city_id: cityId.value,
    };
}

function save() {
    clearErrors();
    const payload = buildPayload();

    promptSaveConfirm({
        payload,
        entityLabel: 'Station',
        mode: editing.value ? 'edit' : 'create',
        fields: buildStationFields(payload, editing.value, { states: states.value, cities: cities.value }),
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

async function handleDelete(station) {
    if (!window.confirm(`Delete station "${station.name}"?`)) {
        return;
    }

    try {
        await deleteStation(station.id);
        toast.success('Station deleted successfully.');
        await loadStations();
    } catch (error) {
        toast.error(error.response?.data?.message ?? 'Could not delete station.');
    }
}

onMounted(async () => {
    await initialize();
    await loadStations();

    if (search.value.trim() !== '') {
        filtersOpen.value = true;
    }
});
</script>
