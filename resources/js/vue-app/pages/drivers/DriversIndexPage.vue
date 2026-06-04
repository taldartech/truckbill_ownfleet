<template>
    <div class="container mt-4 drivers-page">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <h3 class="mb-0">Drivers Management</h3>
            <AppButton v-if="canManage" icon="bi bi-plus" label="Create" @click="openCreate" />
        </div>

        <div class="card">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                <h3 class="card-title mb-0">Drivers List</h3>
                <div class="input-group input-group-sm" style="max-width: 280px">
                    <span class="input-group-text"><i class="bi bi-search" aria-hidden="true"></i></span>
                    <input
                        v-model="searchQuery"
                        type="search"
                        class="form-control"
                        placeholder="Search name, mobile, license…"
                        aria-label="Search drivers"
                    />
                </div>
            </div>
            <div class="card-body table-responsive">
                <div v-if="loading" class="text-center py-4 text-muted">Loading drivers…</div>
                <table v-else class="table table-bordered table-striped mb-0">
                    <thead>
                        <tr>
                            <th style="width: 70px">#</th>
                            <th>Name</th>
                            <th>Mobile</th>
                            <th>License Number</th>
                            <th>Address</th>
                            <th v-if="canManage" style="width: 150px">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="drivers.length === 0">
                            <td :colspan="canManage ? 6 : 5" class="text-center text-muted py-4">
                                No drivers found.
                            </td>
                        </tr>
                        <tr v-for="(driver, index) in drivers" :key="driver.id">
                            <td class="text-center">{{ rowNumber(index) }}</td>
                            <td>
                                <strong>{{ driver.name }}</strong>
                                <span v-if="!driver.is_active" class="badge bg-secondary ms-1">Inactive</span>
                            </td>
                            <td>{{ formatPhone(driver.phone) }}</td>
                            <td>{{ driver.license_number || '—' }}</td>
                            <td>{{ formatAddress(driver.address) }}</td>
                            <td v-if="canManage">
                                <AppTableActions>
                                    <AppCrudActionButton action="edit" @click="openEdit(driver)" />
                                    <AppCrudActionButton action="advance" @click="openAdvance(driver)" />
                                    <AppCrudActionButton action="delete" @click="handleDelete(driver)" />
                                </AppTableActions>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div
                v-if="pagination.total > 0"
                class="card-footer d-flex flex-wrap justify-content-between align-items-center gap-2"
            >
                <small class="text-muted">
                    Showing {{ pagination.from }} to {{ pagination.to }} of {{ pagination.total }} drivers
                </small>
                <div class="d-flex align-items-center gap-2">
                    <label class="small text-muted mb-0" :for="`${pageId}_per_page`">Per page</label>
                    <select
                        :id="`${pageId}_per_page`"
                        v-model.number="pagination.perPage"
                        class="form-select form-select-sm"
                        style="width: auto"
                        :disabled="loading"
                        @change="onPerPageChange"
                    >
                        <option :value="10">10</option>
                        <option :value="25">25</option>
                        <option :value="50">50</option>
                        <option :value="100">100</option>
                    </select>
                    <div v-if="pagination.lastPage > 1" class="btn-group btn-group-sm">
                        <button
                            type="button"
                            class="btn btn-outline-secondary"
                            :disabled="loading || pagination.currentPage <= 1"
                            @click="goToPage(pagination.currentPage - 1)"
                        >
                            Previous
                        </button>
                        <button type="button" class="btn btn-outline-secondary" disabled>
                            Page {{ pagination.currentPage }} / {{ pagination.lastPage }}
                        </button>
                        <button
                            type="button"
                            class="btn btn-outline-secondary"
                            :disabled="loading || pagination.currentPage >= pagination.lastPage"
                            @click="goToPage(pagination.currentPage + 1)"
                        >
                            Next
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <DriverFormModal
            modal-id="addDriverModal"
            :show="showCreate"
            mode="create"
            :saving="saving"
            :field-errors="fieldErrors"
            @close="closeCreate"
            @submit="handleCreate"
        />

        <DriverFormModal
            modal-id="editDriverModal"
            :show="showEdit"
            mode="edit"
            :driver="editingDriver"
            :saving="saving"
            :field-errors="fieldErrors"
            @close="closeEdit"
            @submit="handleUpdate"
        />

        <DriverAdvanceModal
            ref="advanceModalRef"
            modal-id="driverAdvanceModal"
            :show="showAdvance"
            :driver-id="advanceDriver?.id ?? null"
            :driver-name="advanceDriver?.name ?? ''"
            :can-manage="canManage"
            :saving="advanceSaving"
            :field-errors="advanceFieldErrors"
            @close="closeAdvance"
            @submit="handleAdvanceSubmit"
        />
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { createDriverAdvanceEntry } from '../../api/driverAdvances';
import { createDriver, deleteDriver, listDrivers, updateDriver } from '../../api/drivers';
import DriverAdvanceModal from '../../components/drivers/DriverAdvanceModal.vue';
import DriverFormModal from '../../components/drivers/DriverFormModal.vue';
import AppButton from '../../components/ui/AppButton.vue';
import AppCrudActionButton from '../../components/ui/AppCrudActionButton.vue';
import AppTableActions from '../../components/ui/AppTableActions.vue';
import { useAppToast } from '../../composables/useAppToast';
import { usePermissions } from '../../composables/usePermissions';
import { useApiFormErrors } from '../../composables/useApiFormErrors';

const toast = useAppToast();

const pageId = 'driversIndex';

const { fieldErrors, formError: driverFormError, clearErrors, applyApiError } = useApiFormErrors();
const {
    fieldErrors: advanceFieldErrors,
    formError: advanceFormError,
    clearErrors: clearAdvanceErrors,
    applyApiError: applyAdvanceApiError,
} = useApiFormErrors();

const { canInCompany } = usePermissions();
const canManage = computed(() => canInCompany('drivers.manage'));

const drivers = ref([]);
const loading = ref(true);
const saving = ref(false);
const showCreate = ref(false);
const showEdit = ref(false);
const editingDriver = ref(null);
const showAdvance = ref(false);
const advanceDriver = ref(null);
const advanceSaving = ref(false);
const advanceModalRef = ref(null);
const searchQuery = ref('');
const pagination = reactive({
    currentPage: 1,
    perPage: 25,
    lastPage: 1,
    total: 0,
    from: 0,
    to: 0,
});

let searchDebounceTimer = null;

function formatPhone(phone) {
    const value = String(phone ?? '').trim();

    return value || '—';
}

function formatAddress(address) {
    const value = String(address ?? '').trim();

    return value || '—';
}

function rowNumber(index) {
    return pagination.from > 0 ? pagination.from + index : index + 1;
}

async function loadDrivers(page = pagination.currentPage) {
    loading.value = true;

    try {
        const response = await listDrivers({
            page,
            perPage: pagination.perPage,
            search: searchQuery.value.trim(),
        });

        drivers.value = response.data;
        pagination.currentPage = response.meta.current_page ?? page;
        pagination.lastPage = response.meta.last_page ?? 1;
        pagination.total = response.meta.total ?? drivers.value.length;
        pagination.from = response.meta.from ?? (drivers.value.length > 0 ? 1 : 0);
        pagination.to = response.meta.to ?? drivers.value.length;
    } catch {
        toast.error('Could not load drivers.');
        drivers.value = [];
        pagination.currentPage = 1;
        pagination.lastPage = 1;
        pagination.total = 0;
        pagination.from = 0;
        pagination.to = 0;
    } finally {
        loading.value = false;
    }
}

function goToPage(page) {
    if (page < 1 || page > pagination.lastPage || page === pagination.currentPage) {
        return;
    }

    loadDrivers(page);
}

function onPerPageChange() {
    pagination.currentPage = 1;
    loadDrivers(1);
}

function openCreate() {
    clearErrors();
    showCreate.value = true;
}

function closeCreate() {
    showCreate.value = false;
    clearErrors();
}

function openEdit(driver) {
    editingDriver.value = { ...driver };
    clearErrors();
    showEdit.value = true;
}

function closeEdit() {
    showEdit.value = false;
    editingDriver.value = null;
    clearErrors();
}

function openAdvance(driver) {
    advanceDriver.value = { id: driver.id, name: driver.name ?? '' };
    clearAdvanceErrors();
    showAdvance.value = true;
}

function closeAdvance() {
    showAdvance.value = false;
    advanceDriver.value = null;
    clearAdvanceErrors();
}

async function handleAdvanceSubmit(payload) {
    if (!advanceDriver.value?.id) {
        return;
    }

    advanceSaving.value = true;
    clearAdvanceErrors();

    try {
        await createDriverAdvanceEntry(advanceDriver.value.id, payload);
        toast.success('Advance entry saved.');
        await advanceModalRef.value?.loadLedger();
    } catch (error) {
        if (applyAdvanceApiError(error)) {
            if (advanceFormError.value) {
                toast.error(advanceFormError.value);
            }
        } else {
            toast.error('Unable to save advance entry.');
        }
    } finally {
        advanceSaving.value = false;
    }
}

function applyValidationError(error) {
    if (applyApiError(error)) {
        if (driverFormError.value) {
            toast.error(driverFormError.value);
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

    if (driverFormError.value) {
        toast.error(driverFormError.value);

        return;
    }

    toast.error('Unable to save driver. Please try again.');
}

async function handleCreate(payload) {
    saving.value = true;
    clearErrors();

    try {
        await createDriver(payload);
        toast.success('Driver has been added successfully.');
        closeCreate();
        await loadDrivers(1);
    } catch (error) {
        applyValidationError(error);
    } finally {
        saving.value = false;
    }
}

async function handleUpdate(payload) {
    if (!editingDriver.value?.id) {
        return;
    }

    saving.value = true;
    clearErrors();

    try {
        await updateDriver(editingDriver.value.id, payload);
        toast.success('Driver has been updated successfully.');
        closeEdit();
        await loadDrivers();
    } catch (error) {
        applyValidationError(error);
    } finally {
        saving.value = false;
    }
}

async function handleDelete(driver) {
    if (!window.confirm(`Are you sure you want to delete driver "${driver.name}"?`)) {
        return;
    }

    try {
        await deleteDriver(driver.id);
        toast.success('Driver deleted successfully.');

        const page =
            drivers.value.length === 1 && pagination.currentPage > 1
                ? pagination.currentPage - 1
                : pagination.currentPage;

        await loadDrivers(page);
    } catch {
        toast.error('Unable to delete driver. Please try again.');
    }
}

watch(searchQuery, () => {
    clearTimeout(searchDebounceTimer);
    searchDebounceTimer = setTimeout(() => {
        pagination.currentPage = 1;
        loadDrivers(1);
    }, 400);
});

onMounted(() => {
    loadDrivers();
});
</script>
