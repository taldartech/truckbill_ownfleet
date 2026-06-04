<template>
    <div class="container mt-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <div>
                <h3 class="mb-0">States & Cities</h3>
                <p class="text-muted small mb-0">Country: India (fixed)</p>
            </div>
            <AppButton
                v-if="canManage && activeTab === 'states'"
                icon="bi bi-plus"
                label="Add State"
                @click="openStateCreate"
            />
            <AppButton
                v-if="canManage && activeTab === 'cities'"
                icon="bi bi-plus"
                label="Add City"
                @click="openCityCreate"
            />
        </div>

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

        <div v-if="formError" class="alert alert-danger">{{ formError }}</div>

        <div v-show="activeTab === 'states'" class="card">
            <div class="card-header d-flex gap-2 align-items-center">
                <h3 class="card-title mb-0">State List</h3>
                <input
                    v-model="stateSearch"
                    type="search"
                    class="form-control form-control-sm ms-auto"
                    style="max-width: 260px"
                    placeholder="Search state or code"
                    @input="debouncedLoadStates"
                />
            </div>
            <div class="card-body table-responsive p-0">
                <div v-if="loadingStates" class="text-center py-4 text-muted">Loading…</div>
                <table v-else class="table table-bordered table-striped mb-0">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Code</th>
                            <th>Cities</th>
                            <th v-if="canManage" style="width: 120px">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="states.length === 0">
                            <td :colspan="canManage ? 4 : 3" class="text-center text-muted">No states found.</td>
                        </tr>
                        <tr v-for="state in states" :key="state.id">
                            <td>{{ state.name }}</td>
                            <td>{{ state.code || '—' }}</td>
                            <td>{{ state.cities_count ?? 0 }}</td>
                            <td v-if="canManage">
                                <AppTableActions>
                                    <AppCrudActionButton action="edit" @click="openStateEdit(state)" />
                                </AppTableActions>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div
                v-if="statePagination.lastPage > 1"
                class="card-footer d-flex justify-content-between align-items-center"
            >
                <small class="text-muted">
                    Showing {{ statePagination.from }} to {{ statePagination.to }} of
                    {{ statePagination.total }}
                </small>
                <div class="btn-group btn-group-sm">
                    <button
                        type="button"
                        class="btn btn-outline-secondary"
                        :disabled="loadingStates || statePagination.currentPage <= 1"
                        @click="loadStates(statePagination.currentPage - 1)"
                    >
                        Previous
                    </button>
                    <button type="button" class="btn btn-outline-secondary" disabled>
                        Page {{ statePagination.currentPage }} / {{ statePagination.lastPage }}
                    </button>
                    <button
                        type="button"
                        class="btn btn-outline-secondary"
                        :disabled="loadingStates || statePagination.currentPage >= statePagination.lastPage"
                        @click="loadStates(statePagination.currentPage + 1)"
                    >
                        Next
                    </button>
                </div>
            </div>
        </div>

        <div v-show="activeTab === 'cities'" class="card">
            <div class="card-header d-flex flex-wrap gap-2 align-items-center">
                <h3 class="card-title mb-0">City List</h3>
                <select
                    v-model="cityStateFilter"
                    class="form-select form-select-sm"
                    style="max-width: 220px"
                    @change="onCityFilterChange"
                >
                    <option value="">All states</option>
                    <option v-for="state in stateOptions" :key="state.id" :value="state.id">
                        {{ state.name }}
                    </option>
                </select>
                <input
                    v-model="citySearch"
                    type="search"
                    class="form-control form-control-sm ms-auto"
                    style="max-width: 260px"
                    placeholder="Search city"
                    @input="debouncedLoadCities"
                />
            </div>
            <div class="card-body table-responsive p-0">
                <div v-if="loadingCities" class="text-center py-4 text-muted">Loading…</div>
                <table v-else class="table table-bordered table-striped mb-0">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>State</th>
                            <th v-if="canManage" style="width: 120px">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="cities.length === 0">
                            <td :colspan="canManage ? 3 : 2" class="text-center text-muted">No cities found.</td>
                        </tr>
                        <tr v-for="city in cities" :key="city.id">
                            <td>{{ city.name }}</td>
                            <td>{{ city.state_name || '—' }}</td>
                            <td v-if="canManage">
                                <AppTableActions>
                                    <AppCrudActionButton action="edit" @click="openCityEdit(city)" />
                                </AppTableActions>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div
                v-if="cityPagination.lastPage > 1"
                class="card-footer d-flex justify-content-between align-items-center"
            >
                <small class="text-muted">
                    Showing {{ cityPagination.from }} to {{ cityPagination.to }} of
                    {{ cityPagination.total }}
                </small>
                <div class="btn-group btn-group-sm">
                    <button
                        type="button"
                        class="btn btn-outline-secondary"
                        :disabled="loadingCities || cityPagination.currentPage <= 1"
                        @click="loadCities(cityPagination.currentPage - 1)"
                    >
                        Previous
                    </button>
                    <button type="button" class="btn btn-outline-secondary" disabled>
                        Page {{ cityPagination.currentPage }} / {{ cityPagination.lastPage }}
                    </button>
                    <button
                        type="button"
                        class="btn btn-outline-secondary"
                        :disabled="loadingCities || cityPagination.currentPage >= cityPagination.lastPage"
                        @click="loadCities(cityPagination.currentPage + 1)"
                    >
                        Next
                    </button>
                </div>
            </div>
        </div>

        <div
            v-if="showStateModal"
            class="modal fade show d-block"
            tabindex="-1"
            style="background: rgba(0, 0, 0, 0.45)"
            @click.self="closeStateModal"
        >
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header bg-success text-white">
                        <h5 class="modal-title">{{ editingState ? 'Edit State' : 'Add State' }}</h5>
                        <button type="button" class="btn-close btn-close-white" @click="closeStateModal"></button>
                    </div>
                    <form @submit.prevent="saveState">
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">Country</label>
                                <input type="text" class="form-control" value="India" disabled />
                            </div>
                            <div class="mb-3">
                                <label class="form-label">State Name *</label>
                                <input v-model="stateForm.name" type="text" class="form-control" required />
                                <div v-if="fieldErrors.name" class="text-danger small mt-1">{{ fieldErrors.name[0] }}</div>
                            </div>
                            <div class="mb-0">
                                <label class="form-label">State Code</label>
                                <input v-model="stateForm.code" type="text" class="form-control" maxlength="10" />
                                <div v-if="fieldErrors.code" class="text-danger small mt-1">{{ fieldErrors.code[0] }}</div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" @click="closeStateModal">Cancel</button>
                            <AppButton type="submit" label="Save" :loading="saving" />
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div
            v-if="showCityModal"
            class="modal fade show d-block"
            tabindex="-1"
            style="background: rgba(0, 0, 0, 0.45)"
            @click.self="closeCityModal"
        >
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header bg-success text-white">
                        <h5 class="modal-title">{{ editingCity ? 'Edit City' : 'Add City' }}</h5>
                        <button type="button" class="btn-close btn-close-white" @click="closeCityModal"></button>
                    </div>
                    <form @submit.prevent="saveCity">
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">Country</label>
                                <input type="text" class="form-control" value="India" disabled />
                            </div>
                            <div class="mb-3">
                                <label class="form-label">State *</label>
                                <select v-model="cityForm.state_id" class="form-select" required>
                                    <option value="">Select state</option>
                                    <option v-for="state in stateOptions" :key="state.id" :value="state.id">
                                        {{ state.name }}
                                    </option>
                                </select>
                                <div v-if="fieldErrors.state_id" class="text-danger small mt-1">
                                    {{ fieldErrors.state_id[0] }}
                                </div>
                            </div>
                            <div class="mb-0">
                                <label class="form-label">City Name *</label>
                                <input v-model="cityForm.name" type="text" class="form-control" required />
                                <div v-if="fieldErrors.name" class="text-danger small mt-1">{{ fieldErrors.name[0] }}</div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" @click="closeCityModal">Cancel</button>
                            <AppButton type="submit" label="Save" :loading="saving" />
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { createCity, listCities, updateCity } from '../../api/cities';
import { listStates as listLookupStates } from '../../api/locations';
import { createState, listStates, updateState } from '../../api/states';
import AppButton from '../../components/ui/AppButton.vue';
import AppCrudActionButton from '../../components/ui/AppCrudActionButton.vue';
import AppTableActions from '../../components/ui/AppTableActions.vue';
import { useApiFormErrors } from '../../composables/useApiFormErrors';
import { useAppToast } from '../../composables/useAppToast';
import { usePermissions } from '../../composables/usePermissions';

const PER_PAGE = 15;

const { canInCompany } = usePermissions();
const canManage = computed(() => canInCompany('locations.manage'));
const { success: toastSuccess, error: toastError } = useAppToast();
const { fieldErrors, formError, clearErrors, applyApiError } = useApiFormErrors();

const activeTab = ref('states');
const states = ref([]);
const stateOptions = ref([]);
const cities = ref([]);
const loadingStates = ref(true);
const loadingCities = ref(true);
const saving = ref(false);
const stateSearch = ref('');
const citySearch = ref('');
const cityStateFilter = ref('');
const showStateModal = ref(false);
const showCityModal = ref(false);
const editingState = ref(null);
const editingCity = ref(null);
const stateForm = reactive({ name: '', code: '' });
const cityForm = reactive({ name: '', state_id: '' });

const statePagination = reactive({
    currentPage: 1,
    lastPage: 1,
    from: 0,
    to: 0,
    total: 0,
});

const cityPagination = reactive({
    currentPage: 1,
    lastPage: 1,
    from: 0,
    to: 0,
    total: 0,
});

let stateDebounceTimer = null;
let cityDebounceTimer = null;

function applyPagination(target, meta) {
    target.currentPage = meta.current_page ?? 1;
    target.lastPage = meta.last_page ?? 1;
    target.from = meta.from ?? 0;
    target.to = meta.to ?? 0;
    target.total = meta.total ?? 0;
}

function debouncedLoadStates() {
    clearTimeout(stateDebounceTimer);
    stateDebounceTimer = setTimeout(() => loadStates(1), 350);
}

function debouncedLoadCities() {
    clearTimeout(cityDebounceTimer);
    cityDebounceTimer = setTimeout(() => loadCities(1), 350);
}

async function loadStateOptions() {
    stateOptions.value = await listLookupStates();
}

async function loadStates(page = 1) {
    loadingStates.value = true;

    try {
        const result = await listStates({
            page,
            perPage: PER_PAGE,
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
            perPage: PER_PAGE,
            stateId: cityStateFilter.value || undefined,
            search: citySearch.value || undefined,
        });

        cities.value = result.data;
        applyPagination(cityPagination, result.meta);
    } finally {
        loadingCities.value = false;
    }
}

function onCityFilterChange() {
    loadCities(1);
}

function switchTab(tab) {
    activeTab.value = tab;
    clearErrors();
}

function openStateCreate() {
    editingState.value = null;
    stateForm.name = '';
    stateForm.code = '';
    clearErrors();
    showStateModal.value = true;
}

function openStateEdit(state) {
    editingState.value = state;
    stateForm.name = state.name;
    stateForm.code = state.code || '';
    clearErrors();
    showStateModal.value = true;
}

function closeStateModal() {
    showStateModal.value = false;
}

function openCityCreate() {
    editingCity.value = null;
    cityForm.name = '';
    cityForm.state_id = cityStateFilter.value || '';
    clearErrors();
    showCityModal.value = true;
}

function openCityEdit(city) {
    editingCity.value = city;
    cityForm.name = city.name;
    cityForm.state_id = city.state_id;
    clearErrors();
    showCityModal.value = true;
}

function closeCityModal() {
    showCityModal.value = false;
}

async function saveState() {
    saving.value = true;
    clearErrors();

    const payload = {
        name: stateForm.name.trim(),
        code: stateForm.code.trim() || null,
    };

    try {
        if (editingState.value) {
            await updateState(editingState.value.id, payload);
            toastSuccess('State updated.');
        } else {
            await createState(payload);
            toastSuccess('State created.');
        }

        closeStateModal();
        await Promise.all([loadStateOptions(), loadStates(statePagination.currentPage)]);
    } catch (error) {
        if (!applyApiError(error)) {
            toastError('Could not save state.');
        }
    } finally {
        saving.value = false;
    }
}

async function saveCity() {
    saving.value = true;
    clearErrors();

    const payload = {
        name: cityForm.name.trim(),
        state_id: cityForm.state_id,
    };

    try {
        if (editingCity.value) {
            await updateCity(editingCity.value.id, payload);
            toastSuccess('City updated.');
        } else {
            await createCity(payload);
            toastSuccess('City created.');
        }

        closeCityModal();
        await Promise.all([
            loadCities(cityPagination.currentPage),
            loadStates(statePagination.currentPage),
        ]);
    } catch (error) {
        if (!applyApiError(error)) {
            toastError('Could not save city.');
        }
    } finally {
        saving.value = false;
    }
}

onMounted(async () => {
    await loadStateOptions();
    await Promise.all([loadStates(1), loadCities(1)]);
});
</script>
