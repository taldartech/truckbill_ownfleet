<template>
    <div class="container mt-4 app-index-page">
        <AppIndexPageHeader title="References">
            <template v-if="canCreate" #actions>
                <AppButton icon="bi bi-plus" label="Create" @click="openCreate" />
            </template>
        </AppIndexPageHeader>

        <AppIndexFiltersCard :active="filtersActive" @apply="loadBrokers" @clear="clearFilters">
            <div>
                <label class="form-label">Search</label>
                <AppIndexSearchInput
                    v-model="search"
                    input-id="brokersIndex_search"
                    placeholder="Search name, GST, PAN, contact"
                    max-width="100%"
                    :disabled="loading"
                />
            </div>
        </AppIndexFiltersCard>

        <AppIndexListCard :loading="loading" loading-text="Loading references…">
            <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Name</th>
                            <th>Contact</th>
                            <th>GST</th>
                            <th>PAN</th>
                            <th>City</th>
                            <th>State</th>
                            <th v-if="canEdit" class="app-index-actions-cell" style="width: 120px">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="brokers.length === 0" class="app-index-empty-row">
                            <td :colspan="canEdit ? 7 : 6" class="text-center text-muted py-4">No references found.</td>
                        </tr>
                        <tr v-for="broker in brokers" :key="broker.id">
                            <td>{{ broker.name }}</td>
                            <td>{{ broker.contact_number || '—' }}</td>
                            <td>{{ broker.gst_number || '—' }}</td>
                            <td>{{ broker.pan_number || '—' }}</td>
                            <td>{{ broker.city_name || '—' }}</td>
                            <td>{{ broker.state_name || '—' }}</td>
                            <td v-if="canEdit" class="app-index-actions-cell">
                                <AppTableActions>
                                    <AppCrudActionButton action="edit" @click="openEdit(broker)" />
                                    <AppCrudActionButton action="delete" @click="handleDelete(broker)" />
                                </AppTableActions>
                            </td>
                        </tr>
                    </tbody>
            </table>
        </AppIndexListCard>

        <AppModal
            :show="showModal"
            :loading="saving"
            :form-error="formError"
            as-form
            size="lg"
            @close="closeModal"
            @submit="save"
        >
            <template #title>{{ editing ? 'Edit Reference' : 'Add Reference' }}</template>

            <div class="row g-2">
                <IndiaLocationFields
                    v-model:state-id="stateId"
                    v-model:city-id="cityId"
                    :states="states"
                    :cities="cities"
                    :field-errors="fieldErrors"
                />

                <div class="col-12">
                    <label class="form-label">Reference Name *</label>
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
                </div>

                <div class="col-md-4">
                    <label class="form-label">GST Number</label>
                    <input
                        v-model="form.gst_number"
                        type="text"
                        class="form-control"
                        :class="{ 'is-invalid': fieldErrors.gst_number }"
                    />
                    <div v-if="fieldErrors.gst_number" class="invalid-feedback d-block">
                        {{ fieldErrors.gst_number[0] }}
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">PAN Number</label>
                    <input
                        v-model="form.pan_number"
                        type="text"
                        class="form-control text-uppercase"
                        :class="{ 'is-invalid': fieldErrors.pan_number }"
                        maxlength="10"
                    />
                    <div v-if="fieldErrors.pan_number" class="invalid-feedback d-block">
                        {{ fieldErrors.pan_number[0] }}
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Contact Number *</label>
                    <input
                        :value="form.contact_number"
                        type="text"
                        inputmode="numeric"
                        maxlength="10"
                        class="form-control"
                        placeholder="10-digit mobile"
                        required
                        :class="{ 'is-invalid': fieldErrors.contact_number }"
                        @input="form.contact_number = normalizeMobile($event.target.value)"
                    />
                    <div v-if="fieldErrors.contact_number" class="invalid-feedback d-block">
                        {{ fieldErrors.contact_number[0] }}
                    </div>
                </div>
                <div class="col-12">
                    <label class="form-label">Address</label>
                    <textarea
                        v-model="form.address"
                        class="form-control"
                        rows="2"
                        :class="{ 'is-invalid': fieldErrors.address }"
                    />
                    <div v-if="fieldErrors.address" class="invalid-feedback d-block">
                        {{ fieldErrors.address[0] }}
                    </div>
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
import { createBroker, deleteBroker, listAllBrokers, updateBroker } from '../../api/brokers';
import AppModal from '../../components/ui/AppModal.vue';
import AppButton from '../../components/ui/AppButton.vue';
import AppCrudActionButton from '../../components/ui/AppCrudActionButton.vue';
import AppIndexFiltersCard from '../../components/ui/AppIndexFiltersCard.vue';
import AppIndexListCard from '../../components/ui/AppIndexListCard.vue';
import AppIndexPageHeader from '../../components/ui/AppIndexPageHeader.vue';
import AppIndexSearchInput from '../../components/ui/AppIndexSearchInput.vue';
import AppTableActions from '../../components/ui/AppTableActions.vue';
import MasterCrudSaveConfirmModal from '../../components/ui/MasterCrudSaveConfirmModal.vue';
import IndiaLocationFields from '../../components/locations/IndiaLocationFields.vue';
import { useApiFormErrors } from '../../composables/useApiFormErrors';
import { useAppToast } from '../../composables/useAppToast';
import { useLocationLookups } from '../../composables/useLocationLookups';
import { useMasterCrudSaveConfirm } from '../../composables/useMasterCrudSaveConfirm';
import { usePermissions } from '../../composables/usePermissions';
import { buildPartyLocationFields } from '../../utils/masterCrudSaveConfirm';
import { normalizeMobile } from '../../utils/phone';

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

const brokers = ref([]);
const loading = ref(true);
const saving = ref(false);
const search = ref('');
const showModal = ref(false);
const editing = ref(null);
const form = reactive({
    name: '',
    gst_number: '',
    pan_number: '',
    contact_number: '',
    address: '',
});

function resetForm() {
    form.name = '';
    form.gst_number = '';
    form.pan_number = '';
    form.contact_number = '';
    form.address = '';
}
const { fieldErrors, formError, clearErrors, applyApiError } = useApiFormErrors();

const filtersActive = computed(() => search.value.trim() !== '');

function clearFilters() {
    search.value = '';
    loadBrokers();
}

async function loadBrokers() {
    loading.value = true;

    try {
        brokers.value = await listAllBrokers({ search: search.value || undefined });
    } finally {
        loading.value = false;
    }
}

async function openCreate() {
    editing.value = null;
    resetForm();
    clearErrors();
    resetLocationFields();
    showModal.value = true;
    await initialize();
}

async function openEdit(broker) {
    editing.value = broker;
    form.name = broker.name;
    form.gst_number = broker.gst_number ?? '';
    form.pan_number = broker.pan_number ?? '';
    form.contact_number = normalizeMobile(broker.contact_number ?? '');
    form.address = broker.address ?? '';
    clearErrors();
    await initialize({
        countryId: broker.country_id || countryId.value,
        stateId: broker.state_id,
        cityId: broker.city_id,
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
        gst_number: form.gst_number.trim() || null,
        pan_number: form.pan_number.trim().toUpperCase() || null,
        contact_number: normalizeMobile(form.contact_number),
        address: form.address.trim() || null,
    };
}

function save() {
    clearErrors();
    const payload = buildPayload();

    promptSaveConfirm({
        payload,
        entityLabel: 'Reference',
        mode: editing.value ? 'edit' : 'create',
        fields: buildPartyLocationFields(payload, editing.value, { states: states.value, cities: cities.value }),
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
    await initialize();
    await loadBrokers();
});
</script>
