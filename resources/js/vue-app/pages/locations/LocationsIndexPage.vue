<template>
    <div class="container mt-4 app-index-page app-index-page--standard locations-index-page">
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

        <AppIndexListCard
            v-show="activeTab === 'states'"
            title="States"
            :loading="loadingStates"
            loading-text="Loading states…"
        >
            <template #toolbar>
                <AppIndexHeaderButton
                    v-if="canManage"
                    icon="plus"
                    aria-label="Create state"
                    @click="openStateCreate"
                >
                    Add State
                </AppIndexHeaderButton>
            </template>

            <AppIndexCollapsibleFilters
                panel-id="locations-states-filters-panel"
                :loading="loadingStates"
                @search="loadStates(1)"
                @reset="clearStateFilters"
            >
                <div>
                    <label class="form-label" for="locationsStates_search">Search</label>
                    <AppIndexSearchInput
                        v-model="stateSearch"
                        input-id="locationsStates_search"
                        placeholder="Search state or code"
                        max-width="100%"
                        :disabled="loadingStates"
                        @keydown.enter="loadStates(1)"
                    />
                </div>
            </AppIndexCollapsibleFilters>

            <table class="table table-striped table-hover align-middle mb-0 app-index-table">
                <thead class="table-light">
                    <tr>
                        <th style="width: 70px">S.No.</th>
                        <th>Name</th>
                        <th>Code</th>
                        <th>Cities</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="states.length === 0" class="app-index-empty-row">
                        <td colspan="4" class="text-center text-muted py-4">No states found.</td>
                    </tr>
                    <tr v-for="(state, index) in states" :key="state.id">
                        <td>{{ (statePagination.currentPage - 1) * statePagination.perPage + index + 1 }}</td>
                        <td>
                            <RouterLink
                                :to="{ name: 'states.show', params: { id: state.id } }"
                                class="state-name-link"
                                title="View state details"
                            >
                                {{ state.name }}
                            </RouterLink>
                        </td>
                        <td>{{ state.code || '—' }}</td>
                        <td>{{ state.cities_count ?? 0 }}</td>
                    </tr>
                </tbody>
            </table>

            <template #footer>
                <AppIndexPartloadPagination
                    :pagination="statePagination"
                    :loading="loadingStates"
                    item-label="states"
                    aria-label="States pagination"
                    @page-change="goToStatePage"
                />
            </template>
        </AppIndexListCard>

        <AppIndexListCard
            v-show="activeTab === 'cities'"
            title="Cities"
            :loading="loadingCities"
            loading-text="Loading cities…"
        >
            <template #toolbar>
                <AppIndexHeaderButton
                    v-if="canManage"
                    icon="plus"
                    aria-label="Create city"
                    @click="openCityCreate"
                >
                    Add City
                </AppIndexHeaderButton>
            </template>

            <AppIndexCollapsibleFilters
                panel-id="locations-cities-filters-panel"
                :loading="loadingCities"
                @search="loadCities(1)"
                @reset="clearCityFilters"
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
                    <label class="form-label" for="locationsCities_search">Search</label>
                    <AppIndexSearchInput
                        v-model="citySearch"
                        input-id="locationsCities_search"
                        placeholder="Search city"
                        max-width="100%"
                        :disabled="loadingCities"
                        @keydown.enter="loadCities(1)"
                    />
                </div>
            </AppIndexCollapsibleFilters>

            <table class="table table-striped table-hover align-middle mb-0 app-index-table">
                <thead class="table-light">
                    <tr>
                        <th style="width: 70px">S.No.</th>
                        <th>Name</th>
                        <th>State</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="cities.length === 0" class="app-index-empty-row">
                        <td colspan="3" class="text-center text-muted py-4">No cities found.</td>
                    </tr>
                    <tr v-for="(city, index) in cities" :key="city.id">
                        <td>{{ (cityPagination.currentPage - 1) * cityPagination.perPage + index + 1 }}</td>
                        <td>
                            <RouterLink
                                :to="{ name: 'cities.show', params: { id: city.id } }"
                                class="city-name-link"
                                title="View city details"
                            >
                                {{ city.name }}
                            </RouterLink>
                        </td>
                        <td>{{ city.state_name || '—' }}</td>
                    </tr>
                </tbody>
            </table>

            <template #footer>
                <AppIndexPartloadPagination
                    :pagination="cityPagination"
                    :loading="loadingCities"
                    item-label="cities"
                    aria-label="Cities pagination"
                    @page-change="goToCityPage"
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
import { computed, onMounted, ref } from 'vue';
import { useRouter, RouterLink } from 'vue-router';
import { createCity, listCities } from '../../api/cities';
import { listStates as listLookupStates } from '../../api/locations';
import { createState, listStates } from '../../api/states';
import CityFormModal from '../../components/locations/CityFormModal.vue';
import StateFormModal from '../../components/locations/StateFormModal.vue';
import AppSelect from '../../components/ui/AppSelect.vue';
import AppCrudActionButton from '../../components/ui/AppCrudActionButton.vue';
import AppIndexCollapsibleFilters from '../../components/ui/AppIndexCollapsibleFilters.vue';
import AppIndexHeaderButton from '../../components/ui/AppIndexHeaderButton.vue';
import AppIndexListCard from '../../components/ui/AppIndexListCard.vue';
import AppIndexPartloadPagination from '../../components/ui/AppIndexPartloadPagination.vue';
import AppIndexSearchInput from '../../components/ui/AppIndexSearchInput.vue';
import AppTableActions from '../../components/ui/AppTableActions.vue';
import MasterCrudSaveConfirmModal from '../../components/ui/MasterCrudSaveConfirmModal.vue';
import { useApiFormErrors } from '../../composables/useApiFormErrors';
import { useAppToast } from '../../composables/useAppToast';
import { useListPagination } from '../../composables/useListPagination';
import { useMasterCrudSaveConfirm } from '../../composables/useMasterCrudSaveConfirm';
import { usePermissions } from '../../composables/usePermissions';
import { buildCityFields, buildStateFields } from '../../utils/masterCrudSaveConfirm';

const router = useRouter();
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
const showCityCreate = ref(false);
const cityCreateStateId = ref('');

const { pagination: statePagination, syncFromMeta: syncStatePagination } = useListPagination(10);
const { pagination: cityPagination, syncFromMeta: syncCityPagination } = useListPagination(10);

function clearStateFilters() {
    stateSearch.value = '';
    statePagination.currentPage = 1;
    loadStates(1);
}

function clearCityFilters() {
    citySearch.value = '';
    cityStateFilter.value = '';
    cityPagination.currentPage = 1;
    loadCities(1);
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

function goToStatePage(page) {
    if (page < 1 || page > statePagination.lastPage || page === statePagination.currentPage) {
        return;
    }

    loadStates(page);
}

function goToCityPage(page) {
    if (page < 1 || page > cityPagination.lastPage || page === cityPagination.currentPage) {
        return;
    }

    loadCities(page);
}

async function loadStateOptions() {
    stateOptions.value = await listLookupStates();
}

async function loadStates(page = statePagination.currentPage) {
    loadingStates.value = true;

    try {
        const result = await listStates({
            page,
            perPage: statePagination.perPage,
            search: stateSearch.value || undefined,
        });

        states.value = result.data;
        syncStatePagination(result.meta, states.value.length);
    } finally {
        loadingStates.value = false;
    }
}

async function loadCities(page = cityPagination.currentPage) {
    loadingCities.value = true;

    try {
        const result = await listCities({
            page,
            perPage: cityPagination.perPage,
            stateId: cityStateFilter.value || undefined,
            search: citySearch.value || undefined,
        });

        cities.value = result.data;
        syncCityPagination(result.meta, cities.value.length);
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

function openCityCreate() {
    cityCreateStateId.value = cityStateFilter.value || '';
    clearCityErrors();
    showCityCreate.value = true;
}

function closeCityCreate() {
    showCityCreate.value = false;
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
        } else if (kind === 'city-create') {
            await createCity(payload);
            toast.success('City has been added successfully.');
            finishSaveConfirm();
            closeCityCreate();
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

<style scoped>
.state-name-link,
.city-name-link {
    font-weight: 600;
    color: var(--bs-link-color, #0d6efd);
    text-decoration: none;
}

.state-name-link:hover,
.city-name-link:hover {
    text-decoration: underline;
}
</style>
