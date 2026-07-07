<template>
    <div class="container mt-4 app-index-page">
        <AppIndexPageHeader title="States & Cities" subtitle="Country: India (fixed)">
            <template v-if="canManage" #actions>
                <AppButton
                    v-if="activeTab === 'states'"
                    icon="bi bi-plus"
                    label="Create"
                    @click="openStateCreate"
                />
                <AppButton
                    v-if="activeTab === 'cities'"
                    icon="bi bi-plus"
                    label="Create"
                    @click="openCityCreate"
                />
            </template>
        </AppIndexPageHeader>

        <ul class="nav nav-tabs mb-3">
            <li class="nav-item">
                <button
                    type="button"
                    class="nav-link"
                    :class="{ active: activeTab === 'states' }"
                    @click="switchTab('states')"
                >
                    States
                </button>
            </li>
            <li class="nav-item">
                <button
                    type="button"
                    class="nav-link"
                    :class="{ active: activeTab === 'cities' }"
                    @click="switchTab('cities')"
                >
                    Cities
                </button>
            </li>
        </ul>

        <AppIndexFiltersCard
            v-show="activeTab === 'states'"
            :active="stateFiltersActive"
            @apply="loadStates(1)"
            @clear="clearStateFilters"
        >
            <div>
                <label class="form-label">Search</label>
                <AppIndexSearchInput
                    v-model="stateSearch"
                    input-id="locationsStates_search"
                    placeholder="Search state or code"
                    max-width="100%"
                    :disabled="loadingStates"
                />
            </div>
        </AppIndexFiltersCard>

        <AppIndexListCard v-show="activeTab === 'states'" :loading="loadingStates" loading-text="Loading states…">
            <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Name</th>
                            <th>Code</th>
                            <th>Cities</th>
                            <th v-if="canManage" class="app-index-actions-cell" style="width: 120px">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="states.length === 0" class="app-index-empty-row">
                            <td :colspan="canManage ? 4 : 3" class="text-center text-muted py-4">No states found.</td>
                        </tr>
                        <tr v-for="state in states" :key="state.id">
                            <td>{{ state.name }}</td>
                            <td>{{ state.code || '—' }}</td>
                            <td>{{ state.cities_count ?? 0 }}</td>
                            <td v-if="canManage" class="app-index-actions-cell">
                                <AppTableActions>
                                    <AppCrudActionButton action="edit" @click="openStateEdit(state)" />
                                </AppTableActions>
                            </td>
                        </tr>
                    </tbody>
            </table>
            <template #footer>
                <AppIndexPaginationFooter
                    page-id="locationsStates"
                    :loading="loadingStates"
                    :current-page="statePagination.currentPage"
                    :last-page="statePagination.lastPage"
                    :per-page="statePagination.perPage"
                    :total="statePagination.total"
                    :from="statePagination.from"
                    :to="statePagination.to"
                    item-label="states"
                    :per-page-options="[15, 25, 50]"
                    @page-change="loadStates"
                    @update:per-page="onStatePerPageUpdate"
                />
            </template>
        </AppIndexListCard>

        <AppIndexFiltersCard
            v-show="activeTab === 'cities'"
            :active="cityFiltersActive"
            @apply="loadCities(1)"
            @clear="clearCityFilters"
        >
            <div>
                <label class="form-label">State</label>
                <AppSelect
                    v-model="cityStateFilter"
                    :options="stateOptions"
                    placeholder="All states"
                    search-placeholder="Search state…"
                    :disabled="loadingCities"
                />
            </div>
            <div>
                <label class="form-label">Search</label>
                <AppIndexSearchInput
                    v-model="citySearch"
                    input-id="locationsCities_search"
                    placeholder="Search city"
                    max-width="100%"
                    :disabled="loadingCities"
                />
            </div>
        </AppIndexFiltersCard>

        <AppIndexListCard v-show="activeTab === 'cities'" :loading="loadingCities" loading-text="Loading cities…">
            <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Name</th>
                            <th>State</th>
                            <th v-if="canManage" class="app-index-actions-cell" style="width: 120px">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="cities.length === 0" class="app-index-empty-row">
                            <td :colspan="canManage ? 3 : 2" class="text-center text-muted py-4">No cities found.</td>
                        </tr>
                        <tr v-for="city in cities" :key="city.id">
                            <td>{{ city.name }}</td>
                            <td>{{ city.state_name || '—' }}</td>
                            <td v-if="canManage" class="app-index-actions-cell">
                                <AppTableActions>
                                    <AppCrudActionButton action="edit" @click="openCityEdit(city)" />
                                </AppTableActions>
                            </td>
                        </tr>
                    </tbody>
            </table>
            <template #footer>
                <AppIndexPaginationFooter
                    page-id="locationsCities"
                    :loading="loadingCities"
                    :current-page="cityPagination.currentPage"
                    :last-page="cityPagination.lastPage"
                    :per-page="cityPagination.perPage"
                    :total="cityPagination.total"
                    :from="cityPagination.from"
                    :to="cityPagination.to"
                    item-label="cities"
                    :per-page-options="[15, 25, 50]"
                    @page-change="loadCities"
                    @update:per-page="onCityPerPageUpdate"
                />
            </template>
        </AppIndexListCard>

        <StateFormModal
            modal-id="addStateModal"
            :show="showStateCreate"
            mode="create"
            :saving="stateSaving"
            :field-errors="stateFieldErrors"
            :form-error="stateFormError"
            @close="closeStateCreate"
            @submit="handleStateCreate"
        />

        <StateFormModal
            modal-id="editStateModal"
            :show="showStateEdit"
            mode="edit"
            :state="editingState"
            :saving="stateSaving"
            :field-errors="stateFieldErrors"
            :form-error="stateFormError"
            @close="closeStateEdit"
            @submit="handleStateUpdate"
        />

        <CityFormModal
            modal-id="addCityModal"
            :show="showCityCreate"
            mode="create"
            :state-options="stateOptions"
            :initial-state-id="cityCreateStateId"
            :saving="citySaving"
            :field-errors="cityFieldErrors"
            :form-error="cityFormError"
            @close="closeCityCreate"
            @submit="handleCityCreate"
        />

        <CityFormModal
            modal-id="editCityModal"
            :show="showCityEdit"
            mode="edit"
            :city="editingCity"
            :state-options="stateOptions"
            :saving="citySaving"
            :field-errors="cityFieldErrors"
            :form-error="cityFormError"
            @close="closeCityEdit"
            @submit="handleCityUpdate"
        />

        <MasterCrudSaveConfirmModal
            :show="showSaveConfirm"
            :summary="saveConfirmSummary"
            :saving="locationConfirmSaving"
            @cancel="cancelSaveConfirm"
            @confirm="confirmSave"
        />
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { createCity, listCities, updateCity } from '../../api/cities';
import { listStates as listLookupStates } from '../../api/locations';
import { createState, listStates, updateState } from '../../api/states';
import CityFormModal from '../../components/locations/CityFormModal.vue';
import StateFormModal from '../../components/locations/StateFormModal.vue';
import AppButton from '../../components/ui/AppButton.vue';
import AppSelect from '../../components/ui/AppSelect.vue';
import AppCrudActionButton from '../../components/ui/AppCrudActionButton.vue';
import AppIndexFiltersCard from '../../components/ui/AppIndexFiltersCard.vue';
import AppIndexListCard from '../../components/ui/AppIndexListCard.vue';
import AppIndexPageHeader from '../../components/ui/AppIndexPageHeader.vue';
import AppIndexPaginationFooter from '../../components/ui/AppIndexPaginationFooter.vue';
import AppIndexSearchInput from '../../components/ui/AppIndexSearchInput.vue';
import AppTableActions from '../../components/ui/AppTableActions.vue';
import MasterCrudSaveConfirmModal from '../../components/ui/MasterCrudSaveConfirmModal.vue';
import { useApiFormErrors } from '../../composables/useApiFormErrors';
import { useAppToast } from '../../composables/useAppToast';
import { useMasterCrudSaveConfirm } from '../../composables/useMasterCrudSaveConfirm';
import { usePermissions } from '../../composables/usePermissions';
import { buildCityFields, buildStateFields } from '../../utils/masterCrudSaveConfirm';

const { canInCompany } = usePermissions();
const canManage = computed(() => canInCompany('locations.manage'));
const toast = useAppToast();
const {
    showSaveConfirm,
    saveConfirmSummary,
    pendingPayload,
    promptSaveConfirm,
    cancelSaveConfirm,
    finishSaveConfirm,
} = useMasterCrudSaveConfirm();
const pendingLocationSaveKind = ref(null);
const locationConfirmSaving = computed(() => stateSaving.value || citySaving.value);

const {
    fieldErrors: stateFieldErrors,
    formError: stateFormError,
    clearErrors: clearStateErrors,
    applyApiError: applyStateApiError,
} = useApiFormErrors();

const {
    fieldErrors: cityFieldErrors,
    formError: cityFormError,
    clearErrors: clearCityErrors,
    applyApiError: applyCityApiError,
} = useApiFormErrors();

const activeTab = ref('states');
const states = ref([]);
const stateOptions = ref([]);
const cities = ref([]);
const loadingStates = ref(true);
const loadingCities = ref(true);
const stateSaving = ref(false);
const citySaving = ref(false);
const stateSearch = ref('');
const citySearch = ref('');
const cityStateFilter = ref('');
const showStateCreate = ref(false);
const showStateEdit = ref(false);
const showCityCreate = ref(false);
const showCityEdit = ref(false);
const editingState = ref(null);
const editingCity = ref(null);
const cityCreateStateId = ref('');

const statePagination = reactive({
    currentPage: 1,
    perPage: 15,
    lastPage: 1,
    from: 0,
    to: 0,
    total: 0,
});

const cityPagination = reactive({
    currentPage: 1,
    perPage: 15,
    lastPage: 1,
    from: 0,
    to: 0,
    total: 0,
});

const stateFiltersActive = computed(() => stateSearch.value.trim() !== '');

const cityFiltersActive = computed(
    () => citySearch.value.trim() !== '' || cityStateFilter.value !== '',
);

function clearStateFilters() {
    stateSearch.value = '';
    loadStates(1);
}

function clearCityFilters() {
    citySearch.value = '';
    cityStateFilter.value = '';
    loadCities(1);
}

function applyPagination(target, meta) {
    target.currentPage = meta.current_page ?? 1;
    target.lastPage = meta.last_page ?? 1;
    target.perPage = meta.per_page ?? target.perPage;
    target.from = meta.from ?? 0;
    target.to = meta.to ?? 0;
    target.total = meta.total ?? 0;
}

function applyStateValidationError(error) {
    if (applyStateApiError(error)) {
        if (stateFormError.value) {
            toast.error(stateFormError.value);
        }

        return;
    }

    if (error.response?.status === 419) {
        toast.error('Your session has expired. Please refresh the page and sign in again.');

        return;
    }

    if (error.response?.status === 403) {
        toast.error('You do not have permission to perform this action.');

        return;
    }

    toast.error('Unable to save state. Please try again.');
}

function applyCityValidationError(error) {
    if (applyCityApiError(error)) {
        if (cityFormError.value) {
            toast.error(cityFormError.value);
        }

        return;
    }

    if (error.response?.status === 419) {
        toast.error('Your session has expired. Please refresh the page and sign in again.');

        return;
    }

    if (error.response?.status === 403) {
        toast.error('You do not have permission to perform this action.');

        return;
    }

    toast.error('Unable to save city. Please try again.');
}

function onStatePerPageUpdate(perPage) {
    statePagination.perPage = perPage;
    loadStates(1);
}

function onCityPerPageUpdate(perPage) {
    cityPagination.perPage = perPage;
    loadCities(1);
}

async function loadStateOptions() {
    stateOptions.value = await listLookupStates();
}

async function loadStates(page = 1) {
    loadingStates.value = true;

    try {
        const result = await listStates({
            page,
            perPage: statePagination.perPage,
            search: stateSearch.value || undefined,
        });

        states.value = result.data;
        applyPagination(statePagination, result.meta);
    } finally {
        loadingStates.value = false;
    }
}

async function loadCities(page = 1) {
    loadingCities.value = true;

    try {
        const result = await listCities({
            page,
            perPage: cityPagination.perPage,
            stateId: cityStateFilter.value || undefined,
            search: citySearch.value || undefined,
        });

        cities.value = result.data;
        applyPagination(cityPagination, result.meta);
    } finally {
        loadingCities.value = false;
    }
}

function switchTab(tab) {
    activeTab.value = tab;
}

function openStateCreate() {
    clearStateErrors();
    showStateCreate.value = true;
}

function closeStateCreate() {
    showStateCreate.value = false;
    clearStateErrors();
}

function openStateEdit(state) {
    editingState.value = { ...state };
    clearStateErrors();
    showStateEdit.value = true;
}

function closeStateEdit() {
    showStateEdit.value = false;
    editingState.value = null;
    clearStateErrors();
}

function openCityCreate() {
    cityCreateStateId.value = cityStateFilter.value || '';
    clearCityErrors();
    showCityCreate.value = true;
}

function closeCityCreate() {
    showCityCreate.value = false;
    clearCityErrors();
}

function openCityEdit(city) {
    editingCity.value = { ...city };
    clearCityErrors();
    showCityEdit.value = true;
}

function closeCityEdit() {
    showCityEdit.value = false;
    editingCity.value = null;
    clearCityErrors();
}

function handleStateCreate(payload) {
    clearStateErrors();
    pendingLocationSaveKind.value = 'state-create';

    promptSaveConfirm({
        payload,
        entityLabel: 'State',
        mode: 'create',
        fields: buildStateFields(payload, null),
    });
}

function handleStateUpdate(payload) {
    if (!editingState.value?.id) {
        return;
    }

    clearStateErrors();
    pendingLocationSaveKind.value = 'state-update';

    promptSaveConfirm({
        payload,
        entityLabel: 'State',
        mode: 'edit',
        fields: buildStateFields(payload, editingState.value),
    });
}

function handleCityCreate(payload) {
    clearCityErrors();
    pendingLocationSaveKind.value = 'city-create';

    promptSaveConfirm({
        payload,
        entityLabel: 'City',
        mode: 'create',
        fields: buildCityFields(payload, null, { states: stateOptions.value }),
    });
}

function handleCityUpdate(payload) {
    if (!editingCity.value?.id) {
        return;
    }

    clearCityErrors();
    pendingLocationSaveKind.value = 'city-update';

    promptSaveConfirm({
        payload,
        entityLabel: 'City',
        mode: 'edit',
        fields: buildCityFields(payload, editingCity.value, { states: stateOptions.value }),
    });
}

async function confirmSave() {
    const payload = pendingPayload.value;
    const kind = pendingLocationSaveKind.value;

    if (!payload || !kind) {
        return;
    }

    if (kind.startsWith('state')) {
        stateSaving.value = true;
    } else {
        citySaving.value = true;
    }

    try {
        if (kind === 'state-create') {
            await createState(payload);
            toast.success('State has been added successfully.');
            finishSaveConfirm();
            closeStateCreate();
            await Promise.all([loadStateOptions(), loadStates(1)]);
        } else if (kind === 'state-update') {
            await updateState(editingState.value.id, payload);
            toast.success('State has been updated successfully.');
            finishSaveConfirm();
            closeStateEdit();
            await Promise.all([loadStateOptions(), loadStates(statePagination.currentPage)]);
        } else if (kind === 'city-create') {
            await createCity(payload);
            toast.success('City has been added successfully.');
            finishSaveConfirm();
            closeCityCreate();
            await Promise.all([
                loadCities(cityPagination.currentPage),
                loadStates(statePagination.currentPage),
            ]);
        } else if (kind === 'city-update') {
            await updateCity(editingCity.value.id, payload);
            toast.success('City has been updated successfully.');
            finishSaveConfirm();
            closeCityEdit();
            await Promise.all([
                loadCities(cityPagination.currentPage),
                loadStates(statePagination.currentPage),
            ]);
        }
    } catch (error) {
        finishSaveConfirm();

        if (kind.startsWith('state')) {
            applyStateValidationError(error);
        } else {
            applyCityValidationError(error);
        }
    } finally {
        stateSaving.value = false;
        citySaving.value = false;
        pendingLocationSaveKind.value = null;
    }
}

onMounted(async () => {
    await loadStateOptions();
    await Promise.all([loadStates(1), loadCities(1)]);
});
</script>
