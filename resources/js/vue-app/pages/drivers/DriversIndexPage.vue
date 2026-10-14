<template>
    <div class="container mt-4 app-index-page app-index-page--standard">
        <AppIndexListCard :title="pageTitle" :loading="loading" loading-text="Loading drivers…">
            <template #toolbar>
                <AppIndexHeaderButton
                    v-if="licenseAlertsMode"
                    icon="return"
                    aria-label="View all drivers"
                    @click="clearLicenseAlertsMode"
                >
                    All Drivers
                </AppIndexHeaderButton>
                <AppIndexHeaderButton
                    v-if="canCreate"
                    icon="upload"
                    aria-label="Bulk Upload Drivers"
                    @click="showBulkUpload = true"
                >
                    Bulk Upload
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

            <div v-if="licenseAlertsMode" class="alert alert-warning py-2 mb-3">
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
                        :disabled="loading"
                        @keydown.enter="loadDrivers(1)"
                    />
                </div>
            </AppIndexCollapsibleFilters>

            <table class="table table-striped table-hover align-middle mb-0 app-index-table">
                <thead class="table-light">
                    <tr>
                        <th style="width: 70px">S.No.</th>
                        <th>Name</th>
                        <th>Mobile</th>
                        <th>License Number</th>
                        <th v-if="licenseAlertsMode">License Expiry</th>
                        <th>Associated Truck</th>
                        <th style="width: 100px;">Status</th>
                        <th v-if="showRowActions" class="app-index-actions-cell text-end" style="width: 150px">Actions</th>
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
                        <td>
                            <RouterLink
                                :to="{ name: 'drivers.show', params: { id: driver.id } }"
                                class="driver-name-link"
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
import { useDeleteConfirm } from '../../composables/useDeleteConfirm';
const route = useRoute();
const router = useRouter();
const toast = useAppToast();
const { promptDelete } = useDeleteConfirm();
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

function handleDelete(driver) {
    promptDelete({
        title: 'Delete Driver?',
        itemName: driver.name,
        details: [
            { label: 'Name', value: driver.name },
            { label: 'Mobile', value: driver.phone },
            { label: 'License Number', value: driver.license_number },
            { label: 'Associated Truck', value: driver.current_primary_assignment?.vehicle?.registration_number ?? 'Not assigned' },
            { label: 'Status', value: driver.is_active ? 'Active' : 'Inactive' },
        ],
        onConfirm: async (remark) => {
            await deleteDriver(driver.id, { deleted_reason: remark, remark });
            toast.success('Driver deleted successfully.');

            const page =
                drivers.value.length === 1 && pagination.currentPage > 1
                    ? pagination.currentPage - 1
                    : pagination.currentPage;

            licenseAlertDrivers.value = [];
            await loadDrivers(page);
        },
    });
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
}

.driver-name-link:hover {
    text-decoration: underline;
}
</style>

