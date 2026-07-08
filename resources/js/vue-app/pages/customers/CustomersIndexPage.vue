<template>
    <div class="container mt-4 app-index-page app-index-page--standard">
        <AppIndexListCard title="Customers" :loading="loading" loading-text="Loading customers…">
            <template #toolbar>
                <AppIndexHeaderButton
                    icon="filter"
                    :aria-expanded="filtersOpen"
                    aria-controls="customers-filters-panel"
                    aria-label="Toggle search filters"
                    @click="toggleFilters"
                >
                    Search filter
                </AppIndexHeaderButton>
                <AppIndexHeaderButton
                    v-if="canCreate"
                    icon="plus"
                    aria-label="Add Customer"
                    @click="openCreate"
                >
                    Add Customer
                </AppIndexHeaderButton>
            </template>

            <AppIndexCollapsibleFilters
                v-show="filtersOpen"
                panel-id="customers-filters-panel"
                :loading="loading"
                @search="loadCustomers(1)"
                @reset="clearFilters"
            >
                <div>
                    <label class="form-label" for="customersIndex_search">Search</label>
                    <AppIndexSearchInput
                        v-model="search"
                        input-id="customersIndex_search"
                        placeholder="Search name, GST, PAN, contact"
                        max-width="100%"
                        :disabled="loading"
                        @keydown.enter="loadCustomers(1)"
                    />
                </div>
                <template v-if="filtersActive" #active-filters>
                    <div class="app-index-active-search">
                        <span class="app-index-active-search__label">Searching:</span>
                        <span class="app-index-active-search__pill">
                            {{ search.trim() }}
                            <button
                                type="button"
                                aria-label="Clear search filter"
                                title="Clear search"
                                @click="clearFilters"
                            >
                                <i class="fas fa-times" aria-hidden="true"></i>
                            </button>
                        </span>
                    </div>
                </template>
            </AppIndexCollapsibleFilters>

            <table class="table table-striped table-hover align-middle mb-0 app-index-table">
                <thead class="table-light">
                    <tr>
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
                    <tr v-if="customers.length === 0" class="app-index-empty-row">
                        <td :colspan="canEdit ? 7 : 6" class="text-center text-muted py-4">No customers found.</td>
                    </tr>
                    <tr v-for="customer in customers" :key="customer.id">
                        <td>{{ customer.name }}</td>
                        <td>{{ customer.contact_number || '—' }}</td>
                        <td>{{ customer.gst_number || '—' }}</td>
                        <td>{{ customer.pan_number || '—' }}</td>
                        <td>{{ customer.city_name || '—' }}</td>
                        <td>{{ customer.state_name || '—' }}</td>
                        <td v-if="canEdit" class="app-index-actions-cell text-end">
                            <div class="app-index-table-actions">
                                <button
                                    type="button"
                                    class="btn btn-sm btn-info text-white app-index-action-btn"
                                    title="Edit"
                                    aria-label="Edit"
                                    @click="openEdit(customer)"
                                >
                                    <i class="fas fa-edit" aria-hidden="true"></i>
                                </button>
                                <button
                                    type="button"
                                    class="btn btn-sm btn-danger app-index-action-btn"
                                    title="Delete"
                                    aria-label="Delete"
                                    @click="handleDelete(customer)"
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
                    aria-label="Customers pagination"
                    @page-change="goToPage"
                />
            </template>
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
            <template #title>{{ editing ? 'Edit Customer' : 'Add Customer' }}</template>

            <div class="row g-2">
                <IndiaLocationFields
                    v-model:state-id="stateId"
                    v-model:city-id="cityId"
                    :states="states"
                    :cities="cities"
                    :field-errors="fieldErrors"
                />

                <div class="col-12">
                    <label class="form-label">Customer Name *</label>
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
import { createCustomer, deleteCustomer, listCustomers, updateCustomer } from '../../api/customers';
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
const canCreate = computed(() => canCreateInCompany('customers') || canInCompany('trips.create'));
const canEdit = computed(() => canEditInCompany('customers'));

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

const customers = ref([]);
const loading = ref(true);
const saving = ref(false);
const search = ref('');
const filtersOpen = ref(false);
const { pagination, syncFromMeta, reset: resetPagination } = useListPagination(10);
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

function toggleFilters() {
    filtersOpen.value = !filtersOpen.value;
}

function clearFilters() {
    search.value = '';
    pagination.currentPage = 1;
    loadCustomers(1);
}

async function loadCustomers(page = pagination.currentPage) {
    loading.value = true;

    try {
        const response = await listCustomers({
            page,
            perPage: pagination.perPage,
            search: search.value.trim(),
        });

        customers.value = response.data;
        syncFromMeta(response.meta, customers.value.length);
    } catch {
        toast.error('Could not load customers.');
        customers.value = [];
        resetPagination();
    } finally {
        loading.value = false;
    }
}

function goToPage(page) {
    if (page < 1 || page > pagination.lastPage || page === pagination.currentPage) {
        return;
    }

    loadCustomers(page);
}

async function openCreate() {
    editing.value = null;
    resetForm();
    clearErrors();
    resetLocationFields();
    showModal.value = true;
    await initialize();
}

async function openEdit(customer) {
    editing.value = customer;
    form.name = customer.name;
    form.gst_number = customer.gst_number ?? '';
    form.pan_number = customer.pan_number ?? '';
    form.contact_number = normalizeMobile(customer.contact_number ?? '');
    form.address = customer.address ?? '';
    clearErrors();
    await initialize({
        countryId: customer.country_id || countryId.value,
        stateId: customer.state_id,
        cityId: customer.city_id,
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
        entityLabel: 'Customer',
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
            await updateCustomer(editing.value.id, payload);
            toast.success('Customer updated successfully.');
        } else {
            await createCustomer(payload);
            toast.success('Customer added successfully.');
        }

        finishSaveConfirm();
        closeModal();
        await loadCustomers();
    } catch (error) {
        finishSaveConfirm();
        applyApiError(error, 'Please check the highlighted fields.');
    } finally {
        saving.value = false;
    }
}

async function handleDelete(customer) {
    if (!window.confirm(`Delete customer "${customer.name}"?`)) {
        return;
    }

    try {
        await deleteCustomer(customer.id);
        toast.success('Customer deleted successfully.');
        await loadCustomers();
    } catch (error) {
        toast.error(error.response?.data?.message ?? 'Could not delete customer.');
    }
}

onMounted(async () => {
    await initialize();
    await loadCustomers();

    if (search.value.trim() !== '') {
        filtersOpen.value = true;
    }
});
</script>
