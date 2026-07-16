<template>
    <div class="container mt-4 company-detail-page">
        <div v-if="loading" class="text-center py-5 text-muted">Loading company details…</div>

        <template v-else-if="company">
            <div class="row g-3">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <strong>Company Information ({{ company.name }})</strong>
                            <div class="app-index-table-actions ms-auto" style="margin-left: auto !important; display: inline-flex !important; gap: 0.375rem !important;">
                                 <RouterLink
                                    :to="{ name: 'companies.index' }"
                                    class="btn btn-sm btn-outline-secondary btn-icon app-index-action-btn"
                                    title="Back to Companies"
                                    style="background-color: #fff; border-color: #cbd5e1; color: #475569;"
                                >
                                    <i class="fas fa-arrow-left" style="color: #475569 !important;" aria-hidden="true"></i>
                                </RouterLink>
                                <AppCrudActionButton
                                    v-if="canUpdate"
                                    action="edit"
                                    @click="openEdit"
                                />
                            </div>
                        </div>
                        <div class="card-body row g-3 small">
                            <div class="col-md-4">
                                <span class="text-muted">Company Name</span>
                                <div class="fw-semibold">{{ company.name }}</div>
                            </div>
                            <div class="col-md-4">
                                <span class="text-muted">Company Code</span>
                                <div>{{ company.short_name || '—' }}</div>
                            </div>
                            <div class="col-md-4">
                                <span class="text-muted">GST Number</span>
                                <div class="fw-semibold">{{ company.gst_number || '—' }}</div>
                            </div>
                            <div class="col-md-4">
                                <span class="text-muted">PAN Number</span>
                                <div>{{ company.pan_number || '—' }}</div>
                            </div>
                            <div class="col-md-4">
                                <span class="text-muted">Contact Person</span>
                                <div>{{ company.contact_person || '—' }}</div>
                            </div>
                            <div class="col-md-4">
                                <span class="text-muted">Mobile Number</span>
                                <div>{{ company.contact_number || '—' }}</div>
                            </div>
                            <div class="col-md-12">
                                <span class="text-muted">Address</span>
                                <div>{{ company.address || '—' }}</div>
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
                                <div>{{ formatDateTime(company.created_at) }}</div>
                                <div v-if="company.created_by_name" class="text-muted">
                                    by {{ company.created_by_name }}
                                </div>
                                <div v-else class="text-muted">by —</div>
                            </div>
                            <div>
                                <span class="text-muted">Last Updated</span>
                                <div>{{ formatDateTime(company.updated_at) }}</div>
                                <div v-if="company.updated_by_name" class="text-muted">
                                    by {{ company.updated_by_name }}
                                </div>
                                <div v-else class="text-muted">by —</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </template>

        <div v-else class="alert alert-danger">
            Company not found or you do not have access.
        </div>

        <CompanyFormModal
            :show="showForm"
            mode="edit"
            :company="company"
            :saving="saving"
            :field-errors="fieldErrors"
            :form-error="companyFormError"
            @close="closeForm"
            @submit="handleSubmit"
        />
    </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { RouterLink, useRoute } from 'vue-router';
import { fetchCompany, updateCompany } from '../../api/companies';
import CompanyFormModal from '../../components/companies/CompanyFormModal.vue';
import AppCrudActionButton from '../../components/ui/AppCrudActionButton.vue';
import { useApiFormErrors } from '../../composables/useApiFormErrors';
import { usePermissions } from '../../composables/usePermissions';
import { useAppToast } from '../../composables/useAppToast';
import { formatDateTime } from '../../utils/datetime';

const route = useRoute();
const toast = useAppToast();
const { can } = usePermissions();

const canUpdate = computed(() => can('companies.update'));

const company = ref(null);
const loading = ref(true);
const saving = ref(false);
const showForm = ref(false);
const { fieldErrors, formError: companyFormError, clearErrors } = useApiFormErrors();

async function loadCompany() {
    const id = route.params.id;
    if (!id) {
        company.value = null;
        loading.value = false;
        return;
    }

    loading.value = true;
    try {
        company.value = await fetchCompany(id);
    } catch {
        company.value = null;
        toast.error('Could not load company details.');
    } finally {
        loading.value = false;
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

async function handleSubmit(payload) {
    if (!company.value?.id) {
        return;
    }

    saving.value = true;
    clearErrors();

    try {
        company.value = await updateCompany(company.value.id, payload);
        toast.success('Company updated successfully.');
        closeForm();
    } catch (error) {
        if (error.response?.status === 422) {
            fieldErrors.value = error.response.data.errors ?? {};
            companyFormError.value =
                error.response.data.message ?? 'Please check the highlighted fields.';
        } else {
            toast.error('Unable to save company.');
        }
    } finally {
        saving.value = false;
    }
}

onMounted(() => {
    loadCompany();
});

watch(() => route.params.id, () => {
    loadCompany();
});
</script>

<style scoped>
</style>
