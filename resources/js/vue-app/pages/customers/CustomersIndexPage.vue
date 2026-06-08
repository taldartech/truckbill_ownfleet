<template>
    <div class="container mt-4 app-index-page">
        <AppIndexPageHeader title="Customers">
            <template v-if="canManage" #actions>
                <AppButton icon="bi bi-plus" label="Create" @click="openCreate" />
            </template>
        </AppIndexPageHeader>

        <AppIndexFiltersCard :active="filtersActive" @apply="loadCustomers" @clear="clearFilters">
            <div>
                <label class="form-label">Search</label>
                <AppIndexSearchInput
                    v-model="search"
                    input-id="customersIndex_search"
                    placeholder="Search name, GST, PAN, contact"
                    max-width="100%"
                    :disabled="loading"
                />
            </div>
        </AppIndexFiltersCard>

        <AppIndexListCard :loading="loading" loading-text="Loading customers…">
            <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Name</th>
                            <th>Contact</th>
                            <th>GST</th>
                            <th>PAN</th>
                            <th>City</th>
                            <th>State</th>
                            <th v-if="canManage" class="app-index-actions-cell" style="width: 120px">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="customers.length === 0" class="app-index-empty-row">
                            <td :colspan="canManage ? 7 : 6" class="text-center text-muted py-4">No customers found.</td>
                        </tr>
                        <tr v-for="customer in customers" :key="customer.id">
                            <td>{{ customer.name }}</td>
                            <td>{{ customer.contact_number || '—' }}</td>
                            <td>{{ customer.gst_number || '—' }}</td>
                            <td>{{ customer.pan_number || '—' }}</td>
                            <td>{{ customer.city_name || '—' }}</td>
                            <td>{{ customer.state_name || '—' }}</td>
                            <td v-if="canManage" class="app-index-actions-cell">
                                <AppTableActions>
                                    <AppCrudActionButton action="edit" @click="openEdit(customer)" />
                                    <AppCrudActionButton action="delete" @click="handleDelete(customer)" />
                                </AppTableActions>
                            </td>
                        </tr>
                    </tbody>
            </table>
        </AppIndexListCard>

        <div
            v-if="showModal"
            class="modal fade show d-block"
            tabindex="-1"
            style="background: rgba(0, 0, 0, 0.45)"
            @click.self="closeModal"
        >
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header bg-success text-white">
                        <h5 class="modal-title">{{ editing ? 'Edit Customer' : 'Add Customer' }}</h5>
                        <button type="button" class="btn-close btn-close-white" @click="closeModal"></button>
                    </div>
                    <form novalidate @submit.prevent="save">
                        <div class="modal-body">
                            <div v-if="formError" class="alert alert-danger py-2">{{ formError }}</div>
                            <div class="mb-3">
                                <label class="form-label">Country</label>
                                <input type="text" class="form-control" :value="indiaCountry?.name || 'India'" disabled />
                            </div>

                            <div class="mb-3">
                                <label class="form-label">State *</label>
                                <SearchableSelect
                                    v-model="stateId"
                                    :items="states"
                                    placeholder="Select state"
                                    search-placeholder="Search state…"
                                    :invalid="Boolean(fieldErrors.state_id)"
                                    :error-message="fieldErrors.state_id?.[0]"
                                />
                            </div>

                            <div class="mb-3">
                                <label class="form-label">City *</label>
                                <SearchableSelect
                                    v-model="cityId"
                                    :items="cities"
                                    placeholder="Select city"
                                    search-placeholder="Search city…"
                                    :disabled="!stateId"
                                    :invalid="Boolean(fieldErrors.city_id)"
                                    :error-message="fieldErrors.city_id?.[0]"
                                />
                            </div>

                            <div class="mb-3">
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

                            <div class="row g-3">
                                <div class="col-md-6">
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
                                <div class="col-md-6">
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
                                <div class="col-md-6">
                                    <label class="form-label">Contact Number</label>
                                    <input
                                        :value="form.contact_number"
                                        type="text"
                                        inputmode="numeric"
                                        maxlength="10"
                                        class="form-control"
                                        placeholder="10-digit mobile"
                                        :class="{ 'is-invalid': fieldErrors.contact_number }"
                                        @input="form.contact_number = normalizeMobile($event.target.value)"
                                    />
                                    <div v-if="fieldErrors.contact_number" class="invalid-feedback d-block">
                                        {{ fieldErrors.contact_number[0] }}
                                    </div>
                                </div>
                                <div class="col-md-12">
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
import { createCustomer, deleteCustomer, listAllCustomers, updateCustomer } from '../../api/customers';
import AppButton from '../../components/ui/AppButton.vue';
import AppCrudActionButton from '../../components/ui/AppCrudActionButton.vue';
import AppIndexFiltersCard from '../../components/ui/AppIndexFiltersCard.vue';
import AppIndexListCard from '../../components/ui/AppIndexListCard.vue';
import AppIndexPageHeader from '../../components/ui/AppIndexPageHeader.vue';
import AppIndexSearchInput from '../../components/ui/AppIndexSearchInput.vue';
import AppTableActions from '../../components/ui/AppTableActions.vue';
import SearchableSelect from '../../components/ui/SearchableSelect.vue';
import { useApiFormErrors } from '../../composables/useApiFormErrors';
import { useLocationLookups } from '../../composables/useLocationLookups';
import { usePermissions } from '../../composables/usePermissions';
import { normalizeMobile } from '../../utils/phone';

const { canInCompany } = usePermissions();
const canManage = computed(() => canInCompany('customers.manage'));

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
    loadCustomers();
}

async function loadCustomers() {
    loading.value = true;

    try {
        customers.value = await listAllCustomers({ search: search.value || undefined });
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

async function save() {
    saving.value = true;
    clearErrors();

    const payload = {
        name: form.name.trim(),
        country_id: countryId.value || indiaCountry.value?.id,
        state_id: stateId.value,
        city_id: cityId.value,
        gst_number: form.gst_number.trim() || null,
        pan_number: form.pan_number.trim().toUpperCase() || null,
        contact_number: normalizeMobile(form.contact_number) || null,
        address: form.address.trim() || null,
    };

    try {
        if (editing.value) {
            await updateCustomer(editing.value.id, payload);
        } else {
            await createCustomer(payload);
        }

        closeModal();
        await loadCustomers();
    } catch (error) {
        applyApiError(error, 'Please check the highlighted fields.');
    } finally {
        saving.value = false;
    }
}

async function handleDelete(customer) {
    if (!window.confirm(`Delete customer "${customer.name}"?`)) {
        return;
    }

    await deleteCustomer(customer.id);
    await loadCustomers();
}

onMounted(async () => {
    await initialize();
    await loadCustomers();
});
</script>
