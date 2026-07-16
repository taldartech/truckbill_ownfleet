<template>
    <div class="container mt-4 customers-detail-page">
        <div v-if="loading" class="text-center py-5 text-muted">Loading customer details…</div>

        <template v-else-if="customer">
            <div class="row g-3">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <strong>Customer Information ({{ customer?.name }})</strong>
                            <div class="app-index-table-actions ms-auto" style="margin-left: auto !important; display: inline-flex !important; gap: 0.375rem !important;">
                                <button
                                    type="button"
                                    class="btn btn-sm btn-outline-secondary btn-icon"
                                    title="Back to Customers"
                                    style="background-color: #fff; border-color: #cbd5e1; color: #475569; display: inline-flex; align-items: center; justify-content: center; width: 32px; height: 32px;"
                                    @click="goBack"
                                >
                                    <i class="fas fa-arrow-left" style="color: #475569 !important;" aria-hidden="true"></i>
                                </button>
                                <AppCrudActionButton
                                    v-if="canEdit"
                                    action="edit"
                                    @click="openEdit"
                                />
                                <AppCrudActionButton
                                    v-if="canDelete"
                                    action="delete"
                                    @click="handleDelete"
                                />
                            </div>
                        </div>
                        <div class="card-body row g-3 small">
                            <div class="col-md-4">
                                <span class="text-muted">Customer Name</span>
                                <div class="fw-semibold">{{ customer.name }}</div>
                            </div>
                            <div class="col-md-4">
                                <span class="text-muted">Contact Number</span>
                                <div>{{ customer.contact_number || '—' }}</div>
                            </div>
                            <div class="col-md-4">
                                <span class="text-muted">GST Number</span>
                                <div>{{ customer.gst_number || '—' }}</div>
                            </div>
                            <div class="col-md-4">
                                <span class="text-muted">PAN Number</span>
                                <div>{{ customer.pan_number || '—' }}</div>
                            </div>
                            <div class="col-md-4">
                                <span class="text-muted">City</span>
                                <div>{{ customer.city_name || '—' }}</div>
                            </div>
                            <div class="col-md-4">
                                <span class="text-muted">State</span>
                                <div>{{ customer.state_name || '—' }}</div>
                            </div>
                            <div class="col-12">
                                <span class="text-muted">Address</span>
                                <div>{{ customer.address || '—' }}</div>
                            </div>
                        </div>

                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header"><strong>Record Info</strong></div>
                        <div class="card-body small">
                            <div class="mb-2">
                                <span class="text-muted">Created</span>
                                <div>{{ formatDateTime(customer.created_at) }}</div>
                                <div v-if="customer.created_by_name" class="text-muted">
                                    by {{ customer.created_by_name }}
                                </div>
                            </div>
                            <div>
                                <span class="text-muted">Last Updated</span>
                                <div>{{ formatDateTime(customer.updated_at) }}</div>
                                <div v-if="customer.updated_by_name" class="text-muted">
                                    by {{ customer.updated_by_name }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </template>

        <div v-else class="alert alert-danger">
            Customer not found or you do not have access.
        </div>

        <CustomerFormModal
            :show="showForm"
            :saving="saving"
            :customer="customer"
            :field-errors="fieldErrors"
            :form-error="formError"
            @close="closeForm"
            @submit="handleSubmit"
        />

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
import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { deleteCustomer, fetchCustomer, updateCustomer } from '../../api/customers';
import AppCrudActionButton from '../../components/ui/AppCrudActionButton.vue';
import MasterCrudSaveConfirmModal from '../../components/ui/MasterCrudSaveConfirmModal.vue';
import CustomerFormModal from '../../components/customers/CustomerFormModal.vue';
import { useApiFormErrors } from '../../composables/useApiFormErrors';
import { usePermissions } from '../../composables/usePermissions';
import { useAppToast } from '../../composables/useAppToast';
import { useMasterCrudSaveConfirm } from '../../composables/useMasterCrudSaveConfirm';
import { formatDateTime } from '../../utils/datetime';

const route = useRoute();
const router = useRouter();
const toast = useAppToast();
const { canInCompany, canEditInCompany } = usePermissions();

const {
    showSaveConfirm,
    saveConfirmSummary,
    pendingPayload,
    promptSaveConfirm,
    cancelSaveConfirm,
    finishSaveConfirm,
} = useMasterCrudSaveConfirm();

const canEdit = computed(() => canEditInCompany('customers'));
const canDelete = computed(() => canEditInCompany('customers'));

const customer = ref(null);
const loading = ref(true);
const saving = ref(false);
const showForm = ref(false);
const { fieldErrors, formError, clearErrors, applyApiError } = useApiFormErrors();

async function loadCustomer() {
    const id = route.params.id;
    if (!id) {
        customer.value = null;
        loading.value = false;
        return;
    }

    loading.value = true;
    try {
        customer.value = await fetchCustomer(id);
    } catch {
        customer.value = null;
        toast.error('Could not load customer details.');
    } finally {
        loading.value = false;
    }
}

function goBack() {
    if (window.history.length > 1) {
        router.back();
    } else {
        router.push({ name: 'customers.index' });
    }
}

function openEdit() {
    clearErrors();
    showForm.value = true;
}

function closeForm() {
    showForm.value = false;
    clearErrors();
}

function handleSubmit({ payload, fields }) {
    clearErrors();

    promptSaveConfirm({
        payload,
        entityLabel: 'Customer',
        mode: 'edit',
        fields,
    });
}

async function confirmSave() {
    const payload = pendingPayload.value;
    if (!payload || !customer.value?.id) {
        return;
    }

    saving.value = true;
    try {
        customer.value = await updateCustomer(customer.value.id, payload);
        toast.success('Customer updated successfully.');
        finishSaveConfirm();
        closeForm();
        await loadCustomer();
    } catch (error) {
        finishSaveConfirm();
        applyApiError(error, 'Please check the highlighted fields.');
    } finally {
        saving.value = false;
    }
}

async function handleDelete() {
    if (!customer.value?.id) {
        return;
    }

    if (!window.confirm(`Delete customer "${customer.value.name}"?`)) {
        return;
    }

    try {
        await deleteCustomer(customer.value.id);
        toast.success('Customer deleted successfully.');
        router.push({ name: 'customers.index' });
    } catch (error) {
        toast.error(error.response?.data?.message ?? 'Could not delete customer.');
    }
}

onMounted(async () => {
    await loadCustomer();
});
</script>
