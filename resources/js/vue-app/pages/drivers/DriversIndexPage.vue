<template>
    <div class="container mt-4 app-index-page app-index-page--standard">
        <AppIndexListCard title="Drivers Management" :loading="loading" loading-text="Loading drivers…">
            <template #toolbar>
                <AppIndexHeaderButton
                    icon="filter"
                    :aria-expanded="filtersOpen"
                    aria-controls="drivers-filters-panel"
                    aria-label="Toggle search filters"
                    @click="toggleFilters"
                >
                    Search filter
                </AppIndexHeaderButton>
                <AppIndexHeaderButton
                    v-if="canCreate"
                    icon="plus"
                    aria-label="Create driver"
                    @click="openCreate"
                >
                    Create
                </AppIndexHeaderButton>
            </template>

            <AppIndexCollapsibleFilters
                v-show="filtersOpen"
                panel-id="drivers-filters-panel"
                :loading="loading"
                @search="loadDrivers(1)"
                @reset="clearFilters"
            >
                <div>
                    <label class="form-label" for="driversIndex_search">Search</label>
                    <AppIndexSearchInput
                        v-model="searchQuery"
                        input-id="driversIndex_search"
                        placeholder="Search name, mobile, license…"
                        aria-label="Search drivers"
                        max-width="100%"
                        :disabled="loading"
                        @keydown.enter="loadDrivers(1)"
                    />
                </div>
            </AppIndexCollapsibleFilters>

            <table class="table table-striped table-hover align-middle mb-0 app-index-table">
                <thead class="table-light">
                    <tr>
                        <th style="width: 70px">#</th>
                        <th>Name</th>
                        <th>Mobile</th>
                        <th>License Number</th>
                        <th class="app-index-table__truncate-col">Address</th>
                        <th v-if="showRowActions" class="app-index-actions-cell text-end" style="width: 150px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="drivers.length === 0" class="app-index-empty-row">
                        <td :colspan="showRowActions ? 6 : 5" class="text-center text-muted py-4">
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
                        <td
                            class="app-index-table__truncate-col"
                            :title="addressValue(driver.address) || undefined"
                        >
                            {{ formatAddress(driver.address) }}
                        </td>
                        <td v-if="showRowActions" class="app-index-actions-cell text-end">
                            <div class="app-index-table-actions">
                                <button
                                    v-if="canEdit"
                                    type="button"
                                    class="btn btn-sm btn-info text-white app-index-action-btn"
                                    title="Edit"
                                    aria-label="Edit"
                                    @click="openEdit(driver)"
                                >
                                    <i class="fas fa-edit" aria-hidden="true"></i>
                                </button>
                                <AppCrudActionButton
                                    v-if="canViewAdvances"
                                    action="advance"
                                    @click="openAdvance(driver)"
                                />
                                <button
                                    v-if="canArchive"
                                    type="button"
                                    class="btn btn-sm btn-danger app-index-action-btn"
                                    title="Delete"
                                    aria-label="Delete"
                                    @click="handleDelete(driver)"
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
                    item-label="drivers"
                    aria-label="Drivers pagination"
                    @page-change="goToPage"
                />
            </template>
        </AppIndexListCard>

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
            :can-manage="canManageAdvances"
            :saving="advanceSaving"
            :field-errors="advanceFieldErrors"
            @close="closeAdvance"
            @loaded="handleAdvanceLoaded"
            @submit="handleAdvanceSubmit"
        />

        <MasterCrudSaveConfirmModal
            :show="showSaveConfirm"
            :summary="saveConfirmSummary"
            :saving="saving"
            @cancel="cancelSaveConfirm"
            @confirm="confirmSave"
        />

        <MasterCrudSaveConfirmModal
            :show="showAdvanceSaveConfirm"
            :summary="advanceSaveConfirmSummary"
            :saving="advanceSaving"
            @cancel="cancelAdvanceSaveConfirm"
            @confirm="confirmAdvanceSave"
        />
    </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { createDriverAdvanceEntry } from '../../api/driverAdvances';
import { createDriver, deleteDriver, listDrivers, updateDriver } from '../../api/drivers';
import DriverAdvanceModal from '../../components/drivers/DriverAdvanceModal.vue';
import DriverFormModal from '../../components/drivers/DriverFormModal.vue';
import AppCrudActionButton from '../../components/ui/AppCrudActionButton.vue';
import AppIndexCollapsibleFilters from '../../components/ui/AppIndexCollapsibleFilters.vue';
import AppIndexHeaderButton from '../../components/ui/AppIndexHeaderButton.vue';
import AppIndexListCard from '../../components/ui/AppIndexListCard.vue';
import AppIndexPartloadPagination from '../../components/ui/AppIndexPartloadPagination.vue';
import AppIndexSearchInput from '../../components/ui/AppIndexSearchInput.vue';
import MasterCrudSaveConfirmModal from '../../components/ui/MasterCrudSaveConfirmModal.vue';
import { useListPagination } from '../../composables/useListPagination';
import { useAppToast } from '../../composables/useAppToast';
import { usePermissions } from '../../composables/usePermissions';
import { useApiFormErrors } from '../../composables/useApiFormErrors';
import { useMasterCrudSaveConfirm } from '../../composables/useMasterCrudSaveConfirm';
import { buildDriverAdvanceFields, buildDriverFields } from '../../utils/masterCrudSaveConfirm';

const toast = useAppToast();
const {
    showSaveConfirm,
    saveConfirmSummary,
    pendingPayload,
    promptSaveConfirm,
    cancelSaveConfirm,
    finishSaveConfirm,
} = useMasterCrudSaveConfirm();
const {
    showSaveConfirm: showAdvanceSaveConfirm,
    saveConfirmSummary: advanceSaveConfirmSummary,
    pendingPayload: pendingAdvancePayload,
    promptSaveConfirm: promptAdvanceSaveConfirm,
    cancelSaveConfirm: cancelAdvanceSaveConfirm,
    finishSaveConfirm: finishAdvanceSaveConfirm,
} = useMasterCrudSaveConfirm();
const pendingDriverMode = ref('create');
const advanceWalletBalance = ref(null);

const { fieldErrors, formError: driverFormError, clearErrors, applyApiError } = useApiFormErrors();
const {
    fieldErrors: advanceFieldErrors,
    formError: advanceFormError,
    clearErrors: clearAdvanceErrors,
    applyApiError: applyAdvanceApiError,
} = useApiFormErrors();

const { canInCompany } = usePermissions();
const canCreate = computed(() => canInCompany('drivers.create'));
const canEdit = computed(() => canInCompany('drivers.edit'));
const canArchive = computed(() => canInCompany('drivers.archive'));
const canViewAdvances = computed(
    () => canInCompany('driver_advances.view') || canInCompany('drivers.view'),
);
const canManageAdvances = computed(() => canInCompany('driver_advances.manage'));
const showRowActions = computed(
    () => canEdit.value || canArchive.value || canViewAdvances.value,
);

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
const filtersOpen = ref(false);
const { pagination, syncFromMeta, reset: resetPagination } = useListPagination(10);

function toggleFilters() {
    filtersOpen.value = !filtersOpen.value;
}

function clearFilters() {
    searchQuery.value = '';
    pagination.currentPage = 1;
    loadDrivers(1);
}

function formatPhone(phone) {
    const value = String(phone ?? '').trim();

    return value || '—';
}

function addressValue(address) {
    return String(address ?? '').trim();
}

function formatAddress(address) {
    return addressValue(address) || '—';
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
        syncFromMeta(response.meta, drivers.value.length);
    } catch {
        toast.error('Could not load drivers.');
        drivers.value = [];
        resetPagination();
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
    advanceWalletBalance.value = null;
    clearAdvanceErrors();
}

function handleAdvanceLoaded(data) {
    advanceWalletBalance.value = Number(data?.balance ?? 0);
}

function handleAdvanceSubmit(payload) {
    if (!advanceDriver.value?.id) {
        return;
    }

    clearAdvanceErrors();

    promptAdvanceSaveConfirm({
        payload,
        entityLabel: 'Advance Entry',
        mode: 'create',
        fields: buildDriverAdvanceFields(payload, {
            driverName: advanceDriver.value.name ?? '',
            walletBalance: advanceWalletBalance.value,
        }),
    });
}

async function confirmAdvanceSave() {
    const payload = pendingAdvancePayload.value;

    if (!payload || !advanceDriver.value?.id) {
        return;
    }

    advanceSaving.value = true;
    clearAdvanceErrors();

    try {
        await createDriverAdvanceEntry(advanceDriver.value.id, payload);
        toast.success('Advance entry saved.');
        finishAdvanceSaveConfirm();
        advanceModalRef.value?.resetForm?.();
        await advanceModalRef.value?.loadLedger(1);
    } catch (error) {
        finishAdvanceSaveConfirm();

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

function handleCreate(payload) {
    clearErrors();
    pendingDriverMode.value = 'create';

    promptSaveConfirm({
        payload,
        entityLabel: 'Driver',
        mode: 'create',
        fields: buildDriverFields(payload, null),
    });
}

function handleUpdate(payload) {
    if (!editingDriver.value?.id) {
        return;
    }

    clearErrors();
    pendingDriverMode.value = 'edit';

    promptSaveConfirm({
        payload,
        entityLabel: 'Driver',
        mode: 'edit',
        fields: buildDriverFields(payload, editingDriver.value),
    });
}

async function confirmSave() {
    const payload = pendingPayload.value;

    if (!payload) {
        return;
    }

    saving.value = true;
    clearErrors();

    try {
        if (pendingDriverMode.value === 'edit' && editingDriver.value?.id) {
            await updateDriver(editingDriver.value.id, payload);
            toast.success('Driver has been updated successfully.');
            finishSaveConfirm();
            closeEdit();
            await loadDrivers();
        } else {
            await createDriver(payload);
            toast.success('Driver has been added successfully.');
            finishSaveConfirm();
            closeCreate();
            await loadDrivers(1);
        }
    } catch (error) {
        finishSaveConfirm();
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

onMounted(() => {
    loadDrivers();

    if (searchQuery.value.trim() !== '') {
        filtersOpen.value = true;
    }
});
</script>
