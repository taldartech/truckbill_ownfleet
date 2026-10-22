<template>
    <div class="container mt-4 app-index-page app-index-page--standard">
        <AppIndexListCard
            :title="pageTitle"
            header-class="trips-card-header--gradient"
            :loading="loading"
            loading-text="Loading drivers…"
        >
            <template #toolbar>
                <button
                    v-if="licenseAlertsMode"
                    type="button"
                    class="btn btn-sm btn-light border-0 bg-white text-dark shadow-sm d-inline-flex align-items-center justify-content-center fw-semibold rounded-2 p-0 px-md-3 py-md-1"
                    style="min-width: 32px; height: 32px;"
                    title="All Drivers"
                    aria-label="View all drivers"
                    @click="clearLicenseAlertsMode"
                >
                    <i class="fas fa-undo" aria-hidden="true"></i>
                    <span class="d-none d-md-inline ms-1">All Drivers</span>
                </button>
                <button
                    v-if="canCreate"
                    type="button"
                    class="btn btn-sm bg-white text-success shadow-sm d-inline-flex align-items-center justify-content-center fw-semibold rounded-2 p-0 px-md-3 py-md-1"
                    style="min-width: 32px; height: 32px;"
                    title="Bulk Upload Drivers"
                    aria-label="Bulk Upload Drivers"
                    @click="showBulkUpload = true"
                >
                    <svg width="15" height="15" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M.5 9.9a.5.5 0 0 1 .5.5v2.5a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-2.5a.5.5 0 0 1 1 0v2.5a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2v-2.5a.5.5 0 0 1 .5-.5"/>
                        <path fill-rule="evenodd" d="M7.646 1.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1-.708.708L8.5 2.707V11.5a.5.5 0 0 1-1 0V2.707L5.354 4.854a.5.5 0 1 1-.708-.708z"/>
                    </svg>
                    <span class="d-none d-md-inline ms-1"><span class="d-none d-lg-inline">Bulk </span>Upload</span>
                </button>
                <button
                    v-if="canCreate"
                    type="button"
                    class="btn btn-sm bg-white text-success shadow-sm d-inline-flex align-items-center justify-content-center fw-semibold rounded-2 p-0 px-md-3 py-md-1"
                    style="min-width: 32px; height: 32px;"
                    title="Create Driver"
                    aria-label="Create driver"
                    @click="openCreate"
                >
                    <svg width="15" height="15" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">
                        <path d="M8 2a.5.5 0 0 1 .5.5v5h5a.5.5 0 0 1 0 1h-5v5a.5.5 0 0 1-1 0v-5h-5a.5.5 0 0 1 0-1h5v-5A.5.5 0 0 1 8 2"/>
                    </svg>
                    <span class="d-none d-md-inline ms-1"><span class="d-none d-lg-inline">Create </span>Driver</span>
                </button>
            </template>

            <template #filters>
                <div class="d-flex d-md-none align-items-center justify-content-between px-3 py-2 border-bottom bg-white">
                    <span class="fw-bold text-uppercase text-dark small text-nowrap" style="letter-spacing: 0.05em; font-size: 0.8125rem; white-space: nowrap;">SEARCH &amp; FILTERS</span>
                    <button
                        type="button"
                        class="btn btn-sm rounded-pill border bg-white text-dark shadow-sm d-inline-flex align-items-center gap-1 px-3 py-1 fw-semibold"
                        style="font-size: 0.8125rem;"
                        :class="{ 'bg-light border-secondary': filtersOpen }"
                        @click="filtersOpen = !filtersOpen"
                    >
                        <i class="bi bi-sliders me-1" aria-hidden="true"></i>
                        <span>Filters</span>
                        <i
                            class="bi ms-1 text-muted"
                            :class="filtersOpen ? 'bi-chevron-up' : 'bi-chevron-down'"
                            style="font-size: 0.75rem;"
                            aria-hidden="true"
                        ></i>
                    </button>
                </div>

                <div v-show="filtersOpen" class="drivers-filters-content">
                    <div v-if="licenseAlertsMode" class="alert alert-warning py-2 mx-3 my-2 mb-0">
                        Showing drivers with license expired or expiring within {{ licenseAlertDays }} days.
                    </div>
                    <AppIndexCollapsibleFilters
                        panel-id="drivers-filters-panel"
                        :loading="loading"
                        @search="loadDrivers(1)"
                        @reset="clearFilters"
                    >
                        <div class="filter-search-col">
                            <label class="form-label" for="driversIndex_search">Search</label>
                            <AppIndexSearchInput
                                v-model="searchQuery"
                                input-id="driversIndex_search"
                                placeholder="Search name, mobile, license…"
                                aria-label="Search drivers"
                                max-width="100%"
                                size="md"
                                :disabled="loading"
                                @keydown.enter="loadDrivers(1)"
                            />
                        </div>
                    </AppIndexCollapsibleFilters>
                </div>
            </template>

            <table class="table table-striped table-hover align-middle mb-0 app-index-table">
                <thead class="table-light">
                    <tr>
                        <th style="width: 50px" class="text-center text-nowrap">#</th>
                        <th class="text-nowrap" style="min-width: 8.5rem;">Name</th>
                        <th class="text-nowrap" style="min-width: 7.5rem;">Mobile</th>
                        <th class="text-nowrap" style="min-width: 8rem;">License Number</th>
                        <th v-if="licenseAlertsMode" class="text-nowrap" style="min-width: 7rem;">License Expiry</th>
                        <th class="text-nowrap" style="min-width: 8.5rem;">Associated Truck</th>
                        <th style="width: 80px;" class="text-nowrap">Status</th>
                        <th v-if="showRowActions" class="app-index-actions-cell text-center text-nowrap" style="width: 120px; min-width: 90px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="drivers.length === 0" class="app-index-empty-row">
                        <td :colspan="tableColspan" class="text-center text-muted py-4">
                            {{ emptyStateMessage }}
                        </td>
                    </tr>
                    <tr v-for="(driver, index) in drivers" :key="driver.id">
                        <td class="text-center">{{ rowNumber(index) }}</td>
                        <td class="text-nowrap">
                            <RouterLink
                                :to="{ name: 'drivers.show', params: { id: driver.id } }"
                                class="driver-name-link text-nowrap"
                                title="View driver details"
                            >
                                {{ driver.name }}
                            </RouterLink>
                        </td>
                        <td>{{ formatPhone(driver.phone) }}</td>
                        <td>{{ driver.license_number || '—' }}</td>
                        <td v-if="licenseAlertsMode">{{ formatDate(driver.license_expiry_date) }}</td>
                        <td>
                            {{ driver.current_primary_assignment?.vehicle?.registration_number ?? '—' }}
                        </td>
                        <td>
                            <div v-if="canToggleStatus" class="status-toggle-wrapper" style="height: 44px; display: flex; align-items: center;">
                                <div class="form-check form-switch mb-0">
                                    <input
                                        :id="`driverStatus_${driver.id}`"
                                        class="form-check-input"
                                        type="checkbox"
                                        role="switch"
                                        :checked="driver.is_active"
                                        :disabled="togglingId === driver.id"
                                        @change="handleToggleStatus(driver, $event)"
                                    />
                                </div>
                                <label
                                    :for="`driverStatus_${driver.id}`"
                                    class="status-toggle-label small ms-1"
                                >
                                    {{ driver.is_active ? 'Active' : 'Inactive' }}
                                </label>
                            </div>
                            <span
                                v-else
                                class="badge"
                                :class="driver.is_active ? 'bg-success' : 'bg-secondary'"
                            >
                                {{ driver.is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td v-if="showRowActions" class="app-index-actions-cell text-end">
                            <AppTableActions>
                                <AppCrudActionButton
                                    v-if="canEdit"
                                    action="edit"
                                    @click="openEdit(driver)"
                                />
                                <AppCrudActionButton
                                    v-if="canViewAdvances"
                                    action="advance"
                                    @click="openAdvance(driver)"
                                />
                                <AppCrudActionButton
                                    v-if="canArchive"
                                    action="delete"
                                    @click="handleDelete(driver)"
                                />
                            </AppTableActions>
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

        <BulkUploadModal
            v-model:show="showBulkUpload"
            title="Bulk Upload Drivers"
            upload-url="/app/api/drivers/bulk-upload"
            template-download-url="/app/api/drivers/bulk-upload/template"
            template-file-name="drivers_bulk_upload_template.xlsx"
            :columns="bulkUploadColumns"
            @success="loadDrivers(1)"
        />
    </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { createDriverAdvanceEntry } from '../../api/driverAdvances';
import {
    createDriver,
    deleteDriver,
    listDrivers,
    listLicenseExpiringDrivers,
    updateDriver,
} from '../../api/drivers';
import DriverAdvanceModal from '../../components/drivers/DriverAdvanceModal.vue';
import DriverFormModal from '../../components/drivers/DriverFormModal.vue';
import AppCrudActionButton from '../../components/ui/AppCrudActionButton.vue';
import AppTableActions from '../../components/ui/AppTableActions.vue';
import AppIndexCollapsibleFilters from '../../components/ui/AppIndexCollapsibleFilters.vue';
import AppIndexHeaderButton from '../../components/ui/AppIndexHeaderButton.vue';
import AppIndexListCard from '../../components/ui/AppIndexListCard.vue';
import AppIndexPartloadPagination from '../../components/ui/AppIndexPartloadPagination.vue';
import AppIndexSearchInput from '../../components/ui/AppIndexSearchInput.vue';
import MasterCrudSaveConfirmModal from '../../components/ui/MasterCrudSaveConfirmModal.vue';
import BulkUploadModal from '../../components/ui/BulkUploadModal.vue';
import { useListPagination } from '../../composables/useListPagination';
import { useAppToast } from '../../composables/useAppToast';
import { usePermissions } from '../../composables/usePermissions';
import { useApiFormErrors } from '../../composables/useApiFormErrors';
import { useMasterCrudSaveConfirm } from '../../composables/useMasterCrudSaveConfirm';
import { COMPLIANCE_ALERT_WINDOW_DAYS } from '../../utils/documentStatus';
import { formatDate } from '../../utils/datetime';
import { buildDriverAdvanceFields, buildDriverFields } from '../../utils/masterCrudSaveConfirm';

const route = useRoute();
const router = useRouter();
const toast = useAppToast();
const licenseAlertDays = COMPLIANCE_ALERT_WINDOW_DAYS;
const licenseAlertDrivers = ref([]);
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
const showBulkUpload = ref(false);

const bulkUploadColumns = [
    { name: 'name', label: 'Driver Name', required: true, description: 'Full name of driver' },
    { name: 'phone', label: 'Phone', required: true, description: 'Unique 10-digit mobile number' },
    { name: 'license_number', label: 'License Number', required: true, description: 'Driving license number (unique)' },
    { name: 'joining_date', label: 'Joining Date', required: true, description: 'Date of joining (YYYY-MM-DD)' },
    { name: 'daily_wage', label: 'Monthly Salary', required: true, description: 'Monthly salary amount' },
    { name: 'address', label: 'Address', required: false, description: 'Residential address' },
    { name: 'aadhaar_number', label: 'Aadhaar Number', required: false, description: '12-digit Aadhaar number' },
    { name: 'pan_number', label: 'PAN Number', required: false, description: '10-character PAN' },
    { name: 'license_expiry_date', label: 'License Expiry Date', required: false, description: 'Expiry date of license' },
    { name: 'bank_account_number', label: 'Bank Account Number', required: false, description: 'Bank account number' },
    { name: 'bank_ifsc', label: 'Bank IFSC', required: false, description: 'Bank IFSC code' },
    { name: 'emergency_contact_name', label: 'Emergency Contact Name', required: false, description: 'Emergency contact person' },
    { name: 'emergency_contact_phone', label: 'Emergency Contact Phone', required: false, description: '10-digit emergency contact phone' },
];

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

const licenseAlertsMode = computed(() => {
    const value = route.query.license_alerts;

    return value === '1' || value === 'true';
});

const pageTitle = computed(() =>
    licenseAlertsMode.value ? 'Driver License Alerts' : 'Drivers Management',
);

const emptyStateMessage = computed(() =>
    licenseAlertsMode.value
        ? 'No drivers with expired or expiring licenses.'
        : 'No drivers found.',
);

const tableColspan = computed(() => {
    let cols = 5;

    if (licenseAlertsMode.value) {
        cols += 1;
    }

    if (showRowActions.value) {
        cols += 1;
    }

    return cols;
});

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
const filtersActive = computed(() => searchQuery.value.trim() !== '');
const { pagination, syncFromMeta, reset: resetPagination } = useListPagination(10);

function toggleFilters() {
    filtersOpen.value = !filtersOpen.value;
}

function clearFilters() {
    searchQuery.value = '';
    pagination.currentPage = 1;
    loadDrivers(1);
}

async function clearLicenseAlertsMode() {
    searchQuery.value = '';
    await router.push({ name: 'drivers.index' });
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

const togglingId = ref(null);
const canToggleStatus = computed(() => canEdit.value);

async function handleToggleStatus(driver, event) {
    const nextActive = event.target.checked;
    togglingId.value = driver.id;

    try {
        const updated = await updateDriver(driver.id, { is_active: nextActive });
        driver.is_active = updated.is_active;
        toast.success(updated.is_active ? 'Driver activated.' : 'Driver deactivated.');
    } catch (e) {
        event.target.checked = driver.is_active;
        toast.error(
            e?.response?.data?.errors?.is_active?.[0]
            ?? e?.response?.data?.errors?.phone?.[0]
            ?? e?.response?.data?.message
            ?? 'Unable to update driver status.'
        );
    } finally {
        togglingId.value = null;
    }
}

function goToDetail(driver) {
    router.push({ name: 'drivers.show', params: { id: driver.id } });
}

function rowNumber(index) {
    return (pagination.currentPage - 1) * pagination.perPage + index + 1;
}

function matchesSearch(driver, term) {
    if (!term) {
        return true;
    }

    const haystack = [
        driver.name,
        driver.phone,
        driver.license_number,
        driver.address,
    ]
        .map((value) => String(value ?? '').toLowerCase())
        .join(' ');

    return haystack.includes(term);
}

function syncClientPagination(items, page) {
    const total = items.length;
    const perPage = pagination.perPage;
    const lastPage = Math.max(1, Math.ceil(total / perPage));
    const currentPage = Math.min(Math.max(page, 1), lastPage);
    const from = total === 0 ? 0 : (currentPage - 1) * perPage + 1;
    const to = total === 0 ? 0 : Math.min(currentPage * perPage, total);

    syncFromMeta(
        {
            current_page: currentPage,
            last_page: lastPage,
            per_page: perPage,
            total,
            from,
            to,
        },
        to - from + 1,
    );

    return items.slice(from > 0 ? from - 1 : 0, to);
}

async function loadLicenseAlertDrivers(page = 1) {
    const term = searchQuery.value.trim().toLowerCase();

    if (licenseAlertDrivers.value.length === 0) {
        licenseAlertDrivers.value = await listLicenseExpiringDrivers({
            days: licenseAlertDays,
        });
    }

    const filtered = licenseAlertDrivers.value.filter((driver) => matchesSearch(driver, term));

    drivers.value = syncClientPagination(filtered, page);
}

async function loadDrivers(page = pagination.currentPage) {
    loading.value = true;

    try {
        if (licenseAlertsMode.value) {
            await loadLicenseAlertDrivers(page);
        } else {
            const response = await listDrivers({
                page,
                perPage: pagination.perPage,
                search: searchQuery.value.trim(),
            });

            drivers.value = response.data;
            syncFromMeta(response.meta, drivers.value.length);
        }
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
            licenseAlertDrivers.value = [];
            await loadDrivers();
        } else {
            await createDriver(payload);
            toast.success('Driver has been added successfully.');
            finishSaveConfirm();
            closeCreate();
            licenseAlertDrivers.value = [];
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

        licenseAlertDrivers.value = [];
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

watch(
    () => route.query.license_alerts,
    () => {
        licenseAlertDrivers.value = [];
        pagination.currentPage = 1;
        loadDrivers(1);
    },
);
</script>

<style scoped>
.driver-name-link {
    font-weight: 600;
    color: var(--bs-link-color, #0d6efd);
    text-decoration: none;
    white-space: nowrap;
}

.driver-name-link:hover {
    text-decoration: underline;
}

/* Custom filters layout */
@media (max-width: 991.98px) {
    :deep(.app-index-collapsible-filters__inner) {
        display: flex;
        flex-wrap: wrap;
        align-items: flex-end;
        gap: 12px;
    }

    .filter-search-col {
        flex: 0 0 260px;
        width: 260px;
    }
}

:deep(.app-index-filters-fields) {
    display: contents;
}

:deep(.app-index-collapsible-filters__actions) {
    margin-top: 0;
    align-self: flex-end;
}

/* ── Tablet & Medium Screens (768px - 1199.98px) ─────────────────── */
@media (min-width: 768px) and (max-width: 1199.98px) {
    :deep(.app-index-card-toolbar) {
        gap: 6px !important;
    }

    .driver-header-btn {
        font-size: 0.8rem !important;
        padding: 0.35rem 0.5rem !important;
        height: 32px !important;
        white-space: nowrap !important;
    }

    .filter-search-col {
        flex: 1 1 240px !important;
        min-width: 200px !important;
    }
}

@media (min-width: 768px) {
    .drivers-filters-content {
        display: block !important;
    }
}

/* ── Mobile Responsive Styles (< 768px) ───────────────────────────── */
@media (max-width: 767.98px) {

    /* Filters on mobile */
    :deep(.app-index-filters-fields) {
        display: contents !important;
    }

    :deep(.app-index-collapsible-filters__inner) {
        display: flex !important;
        flex-wrap: wrap !important;
        gap: 8px !important;
        width: 100% !important;
    }

    :deep(.app-index-filters-fields > div),
    :deep(.filter-search-col),
    .filter-search-col {
        flex: 1 1 100% !important;
        width: 100% !important;
        min-width: 0 !important;
        max-width: 100% !important;
    }

    :deep(.app-index-collapsible-filters .form-label) {
        font-size: 0.73rem !important;
        font-weight: 600 !important;
        margin-bottom: 0.2rem !important;
    }

    :deep(.app-index-collapsible-filters .form-control) {
        height: 33px !important;
        min-height: 33px !important;
        font-size: 0.75rem !important;
        padding-top: 0.25rem !important;
        padding-bottom: 0.25rem !important;
        padding-left: 0.5rem !important;
        padding-right: 0.5rem !important;
        line-height: 1.4 !important;
    }

    :deep(.app-index-collapsible-filters .form-control::placeholder) {
        font-size: 0.75rem !important;
    }

    :deep(.app-index-collapsible-filters__actions) {
        width: 100% !important;
        display: flex !important;
        flex-direction: row !important;
        flex-wrap: nowrap !important;
        align-items: center !important;
        gap: 8px !important;
        margin-top: 0.35rem !important;
        margin-left: 0 !important;
    }

    :deep(.app-index-collapsible-filters__actions .btn),
    :deep(.app-index-collapsible-filters__btn) {
        flex: 1 1 0 !important;
        width: calc(50% - 4px) !important;
        min-width: 0 !important;
        max-width: calc(50% - 4px) !important;
        white-space: nowrap !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        height: 28px !important;
        min-height: 28px !important;
        font-size: 0.75rem !important;
        padding: 0.15rem 0.5rem !important;
        border-radius: 6px !important;
    }

    /* Table micro typography and padding on mobile */
    .table-responsive {
        -webkit-overflow-scrolling: touch;
    }

    .table {
        font-size: 0.72rem !important;
    }

    .table th {
        font-size: 0.72rem !important;
        padding: 0.4rem 0.35rem !important;
        white-space: nowrap !important;
    }

    .table td {
        font-size: 0.72rem !important;
        padding: 0.35rem 0.35rem !important;
        vertical-align: middle !important;
        white-space: nowrap !important;
    }

    .driver-name-link {
        font-size: 0.74rem !important;
        white-space: nowrap !important;
        display: inline-block !important;
    }

    .badge {
        font-size: 0.65rem !important;
        padding: 0.2rem 0.35rem !important;
    }

    :deep(.app-table-actions) {
        gap: 3px !important;
    }

    :deep(.app-crud-action-button),
    :deep(.app-index-action-btn) {
        width: 26px !important;
        height: 26px !important;
        min-width: 26px !important;
        min-height: 26px !important;
        font-size: 0.7rem !important;
        padding: 0 !important;
    }

    :deep(.app-crud-action-button i),
    :deep(.app-index-action-btn i),
    :deep(.app-index-action-btn .fas) {
        font-size: 0.6875rem !important;
    }

    /* Pagination centered in middle on mobile */
    :deep(.app-index-partload-pagination) {
        flex-direction: column !important;
        align-items: center !important;
        justify-content: center !important;
        text-align: center !important;
        gap: 0.45rem !important;
        padding: 0.6rem 0.6rem !important;
        width: 100% !important;
    }

    :deep(.app-index-partload-pagination__summary) {
        font-size: 0.75rem !important;
        text-align: center !important;
        width: 100% !important;
        margin: 0 auto !important;
        justify-content: center !important;
    }

    :deep(.app-index-partload-pagination__nav) {
        margin-left: auto !important;
        margin-right: auto !important;
        display: flex !important;
        justify-content: center !important;
        width: 100% !important;
    }

    :deep(.app-index-partload-pagination__list) {
        margin-left: auto !important;
        margin-right: auto !important;
        justify-content: center !important;
    }

    :deep(.app-index-partload-pagination .page-link) {
        padding: 0.22rem 0.45rem !important;
        font-size: 0.72rem !important;
    }
}

/* ── Extra-Small Mobile Devices (< 380px) ─────────────────────────── */
@media (max-width: 380px) {

    :deep(.app-index-collapsible-filters__actions .btn),
    :deep(.app-index-collapsible-filters__btn) {
        font-size: 0.7rem !important;
        height: 26px !important;
        min-height: 26px !important;
    }
}
</style>

