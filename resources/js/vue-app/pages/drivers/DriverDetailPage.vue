<template>
    <div class="container mt-4 driver-detail-page">
        <div v-if="loading" class="text-center py-5 text-muted">Loading driver details…</div>

        <template v-else-if="driver">
            <div class="row g-3">
                <div class="col-lg-8">
                    <!-- Driver Info Card -->
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <strong>Driver Information ({{ driver.name }})</strong>
                            <div class="app-index-table-actions ms-auto" style="margin-left: auto !important; display: inline-flex !important; gap: 0.375rem !important;">
                                <RouterLink
                                    :to="{ name: 'drivers.index' }"
                                    class="btn btn-sm btn-outline-secondary btn-icon app-index-action-btn"
                                    title="Back to Drivers"
                                    style="background-color: #fff; border-color: #cbd5e1; color: #475569;"
                                >
                                    <i class="fas fa-arrow-left" style="color: #475569 !important;" aria-hidden="true"></i>
                                </RouterLink>
                                <AppCrudActionButton
                                    v-if="canEdit"
                                    action="edit"
                                    @click="openEdit"
                                />
                                <AppCrudActionButton
                                    v-if="canViewAdvances"
                                    action="advance"
                                    @click="openAdvance"
                                />
                                <AppCrudActionButton
                                    v-if="canArchive"
                                    action="delete"
                                    @click="handleDelete"
                                />
                            </div>
                        </div>
                        <div class="card-body row g-3 small">
                            <div class="col-md-4">
                                <span class="text-muted">Name</span>
                                <div class="fw-semibold">{{ driver.name }}</div>
                            </div>
                            <div class="col-md-4">
                                <span class="text-muted">Mobile</span>
                                <div>{{ formatPhone(driver.phone) }}</div>
                            </div>
                            <div class="col-md-4">
                                <span class="text-muted">License Number</span>
                                <div>{{ driver.license_number || '—' }}</div>
                            </div>
                            <div class="col-md-4">
                                <span class="text-muted">License Expiry</span>
                                <div>{{ formatDate(driver.license_expiry_date) }}</div>
                            </div>
                            <div class="col-md-4">
                                <span class="text-muted">Joining Date</span>
                                <div>{{ formatDate(driver.joining_date) }}</div>
                            </div>
                            <div class="col-md-4">
                                <span class="text-muted">Monthly Payment Base</span>
                                <div>{{ formatAmount(driver.daily_wage) }}</div>
                            </div>
                            <div class="col-md-4">
                                <span class="text-muted">Current Assigned Truck</span>
                                <div class="fw-semibold">
                                    {{ driver.current_primary_assignment?.vehicle?.registration_number ?? '—' }}
                                </div>
                            </div>
                            <div class="col-md-4">
                                <span class="text-muted">Aadhaar Number</span>
                                <div>{{ driver.aadhaar_number || '—' }}</div>
                            </div>
                            <div class="col-md-4">
                                <span class="text-muted">PAN Number</span>
                                <div>{{ driver.pan_number || '—' }}</div>
                            </div>
                            <div class="col-md-4">
                                <span class="text-muted">Bank Account</span>
                                <div>{{ driver.bank_account_number || '—' }}</div>
                            </div>
                            <div class="col-md-4">
                                <span class="text-muted">Bank IFSC</span>
                                <div>{{ driver.bank_ifsc || '—' }}</div>
                            </div>
                            <div class="col-md-12">
                                <span class="text-muted">Remarks</span>
                                <div>{{ driver.remarks || '—' }}</div>
                            </div>
                        </div>
                        <div class="card-footer bg-white border-top-0 d-flex justify-content-between align-items-center pt-0 pb-3 pe-3 ps-3" style="display: flex !important; justify-content: space-between !important; align-items: center !important; width: 100% !important;">
                            <div class="d-flex align-items-center">
                                <span class="text-muted me-2 small">Status:</span>
                                <span
                                    class="badge"
                                    :class="driver.is_active ? 'bg-success' : 'bg-secondary'"
                                >
                                    {{ driver.is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Advance Amount Log Card -->
                    <div class="card mt-3">
                        <div class="card-header">
                            <strong>Advance Wallet Summary</strong>
                        </div>
                        <div class="card-body">
                            <div class="row text-center mb-3">
                                <div class="col-md-4 border-end">
                                    <span class="text-muted small">Total Advance Given</span>
                                    <h5 class="fw-semibold text-danger mt-1">
                                        {{ formatAmount(driver.total_advance) }}
                                    </h5>
                                </div>
                                <div class="col-md-4 border-end">
                                    <span class="text-muted small">Total Recovered</span>
                                    <h5 class="fw-semibold text-success mt-1">
                                        {{ formatAmount(driver.total_recovery) }}
                                    </h5>
                                </div>
                                <div class="col-md-4">
                                    <span class="text-muted small">Current Balance</span>
                                    <h5 class="fw-semibold mt-1" :class="driver.current_advance_balance > 0 ? 'text-danger' : 'text-success'">
                                        {{ formatAmount(driver.current_advance_balance) }}
                                    </h5>
                                </div>
                            </div>

                            <hr />

                            <strong class="d-block mb-2 small">Advance Transaction Ledger</strong>
                            <div v-if="ledgerLoading" class="text-muted text-center py-3">Loading transaction ledger…</div>
                            <div v-else-if="ledgerEntries.length === 0" class="text-muted text-center py-3">
                                No advance entries recorded for this driver.
                            </div>
                            <div v-else class="table-responsive">
                                <table class="table table-hover align-middle mb-0 table-sm small">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Date</th>
                                            <th>Transaction Type</th>
                                            <th class="text-end">Amount</th>
                                            <th>Remarks</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="entry in ledgerEntries" :key="entry.id">
                                            <td>{{ formatDate(entry.entry_date) }}</td>
                                            <td>
                                                <span
                                                    class="badge"
                                                    :class="entry.entry_type === 'DR' ? 'bg-danger-subtle text-danger' : 'bg-success-subtle text-success'"
                                                >
                                                    {{ entry.entry_type === 'DR' ? 'Advance' : 'Recovery' }}
                                                </span>
                                            </td>
                                            <td class="text-end fw-semibold">
                                                {{ formatAmount(entry.amount) }}
                                            </td>
                                            <td class="text-muted">{{ entry.remarks || '—' }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            
                            <div v-if="ledgerMeta.last_page > 1" class="d-flex justify-content-end mt-2">
                                <AppIndexPartloadPagination
                                    :pagination="ledgerMeta"
                                    :loading="ledgerLoading"
                                    item-label="entries"
                                    @page-change="loadLedger"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Driver Assignment History -->
                    <div class="card mt-3">
                        <div class="card-header">
                            <strong>Driver Assignment History</strong>
                        </div>
                        <div class="card-body p-0">
                            <div v-if="assignmentsLoading" class="text-muted text-center py-4">
                                Loading assignment history…
                            </div>
                            <div v-else-if="assignments.length === 0" class="text-muted text-center py-4">
                                No truck assignments recorded for this driver.
                            </div>
                            <div v-else class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Truck Registration</th>
                                            <th>Assigned From</th>
                                            <th>Assigned To</th>
                                            <th>Role</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="assignment in assignments" :key="assignment.id">
                                            <td>
                                                <strong>
                                                    {{ assignment.vehicle?.registration_number ?? '—' }}
                                                </strong>
                                            </td>
                                            <td>{{ formatDate(assignment.assigned_from) }}</td>
                                            <td>{{ formatDate(assignment.assigned_to) }}</td>
                                            <td>
                                                <span
                                                    v-if="assignment.is_primary"
                                                    class="badge bg-primary"
                                                >
                                                    Primary
                                                </span>
                                                <span v-else class="text-muted">Substitute</span>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <!-- Current Assigned Truck Sidebar Card -->
                    <div class="card mb-3">
                        <div class="card-header"><strong>Current Vehicle</strong></div>
                        <div class="card-body">
                            <template v-if="driver.current_primary_assignment?.vehicle">
                                <div class="fw-semibold">
                                    {{ driver.current_primary_assignment.vehicle.registration_number }}
                                </div>
                                <div
                                    v-if="driver.current_primary_assignment.assigned_from"
                                    class="small text-muted mt-2"
                                >
                                    Primary driver since {{ formatDate(driver.current_primary_assignment.assigned_from) }}
                                </div>
                            </template>
                            <div v-else class="text-muted">Currently not assigned to any vehicle.</div>
                        </div>
                    </div>

                    <!-- Active Trips Sidebar Card -->
                    <div class="card mb-3">
                        <div class="card-header"><strong>Active Trips</strong></div>
                        <div class="card-body">
                            <div v-if="driver.active_trips?.length === 0" class="text-muted small">
                                No active trips assigned.
                            </div>
                            <div v-else class="d-flex flex-column gap-2">
                                <div
                                    v-for="trip in driver.active_trips"
                                    :key="trip.id"
                                    class="border rounded p-2 small"
                                >
                                    <div class="d-flex justify-content-between">
                                        <RouterLink
                                            :to="{ name: 'trips.show', params: { id: trip.id } }"
                                            class="fw-semibold text-decoration-none"
                                        >
                                            Trip Details
                                        </RouterLink>
                                        <span class="badge bg-primary-subtle text-primary">
                                            {{ trip.status }}
                                        </span>
                                    </div>
                                    <div class="text-muted mt-1">
                                        Vehicle: {{ trip.vehicle?.registration_number ?? '—' }}
                                    </div>
                                    <div v-if="trip.start_date" class="text-muted">
                                        Started: {{ formatDate(trip.start_date) }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Record Info Sidebar Card -->
                    <div class="card">
                        <div class="card-header"><strong>Record Info</strong></div>
                        <div class="card-body small">
                            <div class="mb-2">
                                <span class="text-muted">Created</span>
                                <div>{{ formatDateTime(driver.created_at) }}</div>
                                <div v-if="driver.created_by_name" class="text-muted">
                                    by {{ driver.created_by_name }}
                                </div>
                            </div>
                            <div>
                                <span class="text-muted">Last Updated</span>
                                <div>{{ formatDateTime(driver.updated_at) }}</div>
                                <div v-if="driver.updated_by_name" class="text-muted">
                                    by {{ driver.updated_by_name }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </template>

        <div v-else class="text-center py-5 text-muted">Driver details not found.</div>

        <!-- Edit Modal Dialog -->
        <DriverFormModal
            modal-id="editDriverModal"
            :show="showEdit"
            mode="edit"
            :driver="driver"
            :saving="saving"
            :field-errors="fieldErrors"
            :form-error="formError"
            @close="closeEdit"
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
import { useRoute, useRouter } from 'vue-router';
import { fetchDriver, updateDriver, deleteDriver } from '../../api/drivers';
import { fetchDriverAdvanceLedger, createDriverAdvanceEntry } from '../../api/driverAdvances';
import { listDriverAssignments } from '../../api/driverAssignments';
import DriverFormModal from '../../components/drivers/DriverFormModal.vue';
import DriverAdvanceModal from '../../components/drivers/DriverAdvanceModal.vue';
import AppCrudActionButton from '../../components/ui/AppCrudActionButton.vue';
import AppIndexPartloadPagination from '../../components/ui/AppIndexPartloadPagination.vue';
import MasterCrudSaveConfirmModal from '../../components/ui/MasterCrudSaveConfirmModal.vue';
import { useAppToast } from '../../composables/useAppToast';
import { usePermissions } from '../../composables/usePermissions';
import { useApiFormErrors } from '../../composables/useApiFormErrors';
import { useMasterCrudSaveConfirm } from '../../composables/useMasterCrudSaveConfirm';
import { formatDate, formatDateTime } from '../../utils/datetime';
import { buildDriverAdvanceFields } from '../../utils/masterCrudSaveConfirm';

const route = useRoute();
const router = useRouter();
const toast = useAppToast();
const { canInCompany } = usePermissions();

const driverId = computed(() => route.params.id);
const driver = ref(null);
const loading = ref(true);

const ledgerEntries = ref([]);
const ledgerLoading = ref(false);
const ledgerMeta = ref({ current_page: 1, per_page: 5, last_page: 1, total: 0 });

const assignments = ref([]);
const assignmentsLoading = ref(false);

const showEdit = ref(false);
const saving = ref(false);
const fieldErrors = ref({});
const formError = ref('');

const canEdit = computed(() => canInCompany('drivers.edit'));
const canArchive = computed(() => canInCompany('drivers.archive'));
const canViewAdvances = computed(
    () => canInCompany('driver_advances.view') || canInCompany('drivers.view'),
);
const canManageAdvances = computed(() => canInCompany('driver_advances.manage'));

// Advance Modal Refs and Hooks
const advanceModalRef = ref(null);
const showAdvance = ref(false);
const advanceDriver = ref(null);
const advanceSaving = ref(false);
const advanceWalletBalance = ref(null);

const {
    showSaveConfirm: showAdvanceSaveConfirm,
    saveConfirmSummary: advanceSaveConfirmSummary,
    pendingPayload: pendingAdvancePayload,
    promptSaveConfirm: promptAdvanceSaveConfirm,
    cancelSaveConfirm: cancelAdvanceSaveConfirm,
    finishSaveConfirm: finishAdvanceSaveConfirm,
} = useMasterCrudSaveConfirm();

const {
    fieldErrors: advanceFieldErrors,
    formError: advanceFormError,
    clearErrors: clearAdvanceErrors,
    applyApiError: applyAdvanceApiError,
} = useApiFormErrors();

async function loadDriver() {
    loading.value = true;
    try {
        driver.value = await fetchDriver(driverId.value);
        await Promise.all([loadLedger(1), loadAssignments()]);
    } catch {
        toast.error('Could not load driver details.');
    } finally {
        loading.value = false;
    }
}

async function loadLedger(page = 1) {
    ledgerLoading.value = true;
    try {
        const response = await fetchDriverAdvanceLedger(driverId.value, { page, perPage: 5 });
        ledgerEntries.value = response.entries ?? [];
        ledgerMeta.value = {
            current_page: response.meta?.current_page ?? page,
            per_page: response.meta?.per_page ?? 5,
            last_page: response.meta?.last_page ?? 1,
            total: response.meta?.total ?? 0,
            from: response.meta?.from ?? 0,
            to: response.meta?.to ?? 0,
        };
    } catch {
        toast.error('Could not load advance logs.');
    } finally {
        ledgerLoading.value = false;
    }
}

async function loadAssignments() {
    assignmentsLoading.value = true;
    try {
        const response = await listDriverAssignments({ driverId: driverId.value, perPage: 50 });
        assignments.value = response.data ?? [];
    } catch {
        toast.error('Could not load assignment history.');
    } finally {
        assignmentsLoading.value = false;
    }
}

function openEdit() {
    fieldErrors.value = {};
    formError.value = '';
    showEdit.value = true;
}

function closeEdit() {
    showEdit.value = false;
}

async function handleUpdate(payload) {
    saving.value = true;
    fieldErrors.value = {};
    formError.value = '';

    try {
        const updated = await updateDriver(driverId.value, payload);
        driver.value = updated;
        toast.success('Driver details updated.');
        showEdit.value = false;
    } catch (e) {
        if (e.response?.status === 422) {
            fieldErrors.value = e.response.data.errors ?? {};
            formError.value = e.response.data.message ?? 'Validation failed.';
        } else {
            formError.value = e.response?.data?.message ?? 'Could not update driver.';
        }
    } finally {
        saving.value = false;
    }
}

async function handleDelete() {
    if (confirm('Are you sure you want to delete this driver?')) {
        try {
            await deleteDriver(driverId.value);
            toast.success('Driver deleted successfully.');
            router.push({ name: 'drivers.index' });
        } catch (e) {
            toast.error(e.response?.data?.message ?? 'Could not delete driver.');
        }
    }
}

function openAdvance() {
    if (!driver.value) return;
    advanceDriver.value = { id: driver.value.id, name: driver.value.name ?? '' };
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
        await loadDriver();
        closeAdvance();
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

function formatPhone(phone) {
    return phone ? String(phone).trim() : '—';
}

function formatAmount(amount) {
    if (amount === null || amount === undefined || amount === '') return '—';
    const val = Number(amount);
    return isNaN(val) ? '—' : `₹${val.toFixed(2)}`;
}

onMounted(() => {
    loadDriver();
});
</script>
