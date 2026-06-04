<template>
    <div class="container mt-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <h3 class="mb-0">Stations</h3>
            <AppButton v-if="canManage" icon="bi bi-plus" label="Add Station" @click="openCreate" />
        </div>

        <div class="card">
            <div class="card-header d-flex gap-2 align-items-center">
                <h3 class="card-title mb-0">Station List</h3>
                <input
                    v-model="search"
                    type="search"
                    class="form-control form-control-sm ms-auto"
                    style="max-width: 260px"
                    placeholder="Search station, city, state"
                    @input="debouncedLoad"
                />
            </div>
            <div class="card-body table-responsive">
                <div v-if="loading" class="text-center py-4 text-muted">Loading…</div>
                <table v-else class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>City</th>
                            <th>State</th>
                            <th v-if="canManage" style="width: 120px">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="stations.length === 0">
                            <td :colspan="canManage ? 4 : 3" class="text-center text-muted">No stations found.</td>
                        </tr>
                        <tr v-for="station in stations" :key="station.id">
                            <td>{{ station.name }}</td>
                            <td>{{ station.city_name || '—' }}</td>
                            <td>{{ station.state_name || '—' }}</td>
                            <td v-if="canManage">
                                <AppTableActions>
                                    <AppCrudActionButton action="edit" @click="openEdit(station)" />
                                    <AppCrudActionButton action="delete" @click="handleDelete(station)" />
                                </AppTableActions>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div
            v-if="showModal"
            class="modal fade show d-block"
            tabindex="-1"
            style="background: rgba(0, 0, 0, 0.45)"
            @click.self="closeModal"
        >
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header bg-success text-white">
                        <h5 class="modal-title">{{ editing ? 'Edit Station' : 'Add Station' }}</h5>
                        <button type="button" class="btn-close btn-close-white" @click="closeModal"></button>
                    </div>
                    <form @submit.prevent="save">
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">Country</label>
                                <input type="text" class="form-control" :value="indiaCountry?.name || 'India'" disabled />
                            </div>

                            <div class="mb-3">
                                <label class="form-label">State *</label>
                                <select v-model="stateId" class="form-select" required>
                                    <option value="">Select state</option>
                                    <option v-for="state in states" :key="state.id" :value="state.id">
                                        {{ state.name }}
                                    </option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">City *</label>
                                <select v-model="cityId" class="form-select" :disabled="!stateId" required>
                                    <option value="">Select city</option>
                                    <option v-for="city in cities" :key="city.id" :value="city.id">
                                        {{ city.name }}
                                    </option>
                                </select>
                            </div>

                            <div class="mb-0">
                                <label class="form-label">Station Name *</label>
                                <input v-model="form.name" type="text" class="form-control" required />
                                <div class="form-text">Example: Depot, Yard, or local area name.</div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" @click="closeModal">Cancel</button>
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
import { createStation, deleteStation, listAllStations, updateStation } from '../../api/stations';
import AppButton from '../../components/ui/AppButton.vue';
import AppCrudActionButton from '../../components/ui/AppCrudActionButton.vue';
import AppTableActions from '../../components/ui/AppTableActions.vue';
import { useLocationLookups } from '../../composables/useLocationLookups';
import { usePermissions } from '../../composables/usePermissions';

const { canInCompany } = usePermissions();
const canManage = computed(() => canInCompany('stations.manage'));

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
const showModal = ref(false);
const editing = ref(null);
const form = reactive({ name: '' });

let debounceTimer = null;

function debouncedLoad() {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => loadStations(), 350);
}

async function loadStations() {
    loading.value = true;

    try {
        stations.value = await listAllStations({ search: search.value || undefined });
    } finally {
        loading.value = false;
    }
}

async function openCreate() {
    editing.value = null;
    form.name = '';
    resetLocationFields();
    showModal.value = true;
    await initialize();
}

async function openEdit(station) {
    editing.value = station;
    form.name = station.name;
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
    resetLocationFields();
}

async function save() {
    saving.value = true;

    const payload = {
        name: form.name.trim(),
        country_id: countryId.value || indiaCountry.value?.id,
        state_id: stateId.value,
        city_id: cityId.value,
    };

    try {
        if (editing.value) {
            await updateStation(editing.value.id, payload);
        } else {
            await createStation(payload);
        }

        closeModal();
        await loadStations();
    } finally {
        saving.value = false;
    }
}

async function handleDelete(station) {
    if (!window.confirm(`Delete station "${station.name}"?`)) {
        return;
    }

    await deleteStation(station.id);
    await loadStations();
}

onMounted(async () => {
    await initialize();
    await loadStations();
});
</script>
