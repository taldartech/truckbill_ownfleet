<template>
    <div class="container mt-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <h3 class="mb-0">Driver Attendance &amp; Wages</h3>
            <div v-if="canManageAttendance && isMonthEditable" class="d-flex gap-2">
                <AppButton icon="bi bi-exclamation-triangle" label="Mark Exception" @click="openCreate" />
            </div>
        </div>

        <div v-if="flash.success" class="alert alert-success">{{ flash.success }}</div>
        <div v-if="flash.error" class="alert alert-danger">{{ flash.error }}</div>

        <div
            v-if="monthLock.status !== 'open'"
            class="alert"
            :class="monthLock.status === 'paid' ? 'alert-secondary' : 'alert-warning'"
        >
            <strong>{{ monthLockLabel }} for {{ monthLabel() }}</strong>
            <template v-if="monthLock.status === 'closed'">
                <p class="mb-2 mt-2 small">
                    Step 1 done — exceptions are frozen. Next:
                    <strong>Mark Paid</strong> for each driver in
                    <a href="#monthly-wage-settlement" class="alert-link">Monthly Wage Settlement</a>
                    below, then click <strong>Lock Month</strong> (same section, top right).
                </p>
                <p v-if="unpaidMonthlyWagesCount > 0" class="mb-0 small text-muted">
                    {{ unpaidMonthlyWagesCount }} driver(s) still unpaid — use
                    <strong>Mark All Paid</strong> below, or you can <strong>Lock Month</strong> anyway.
                </p>
            </template>
            <p v-else class="mb-0 mt-2 small">
                Month is locked. Use <strong>Unlock Month</strong> below to reopen payments (closed state), or
                <strong>Reopen Month</strong> after unlock to edit attendance again.
            </p>
        </div>

        <div class="card mb-3">
            <div class="card-body row g-3">
                <div class="col-md-3">
                    <label class="form-label" for="filter_month">Month</label>
                    <input
                        id="filter_month"
                        v-model="filters.month"
                        type="month"
                        class="form-control"
                        @change="onMonthChange"
                    />
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="button" class="btn btn-outline-primary" @click="reloadAll">Apply</button>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header">
                <h3 class="card-title mb-0">Month Overview</h3>
                <span class="text-muted small">Green = auto present from assignment · click a day to mark exception</span>
            </div>
            <div class="card-body">
                <div v-if="overviewLoading" class="text-center py-4 text-muted">Loading overview…</div>
                <AttendanceMonthGrid
                    v-else
                    :month="filters.month"
                    :overview="monthOverview"
                    :can-edit="canEditGrid"
                    @select="openEditFromGrid"
                />
            </div>
        </div>

        <div id="monthly-wage-settlement" class="card mb-3">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                <h3 class="card-title mb-0">Monthly Wage Settlement</h3>
                <div v-if="canManageWages" class="d-flex flex-wrap gap-2 align-items-center">
                    <span
                        v-if="monthLock.status === 'closed'"
                        class="badge bg-warning text-dark"
                    >
                        Closed — mark payments, then lock
                    </span>
                    <span v-else-if="monthLock.status === 'paid'" class="badge bg-secondary">
                        Locked
                    </span>
                    <button
                        v-if="monthLock.status === 'open'"
                        type="button"
                        class="btn btn-outline-primary btn-sm"
                        :disabled="monthlyClosing"
                        @click="handleCloseMonth"
                    >
                        {{ monthlyClosing ? 'Preparing…' : 'Close Month' }}
                    </button>
                    <RouterLink
                        v-if="monthLock.status !== 'open'"
                        :to="{ name: 'attendance.settlement', query: { month: filters.month } }"
                        class="btn btn-primary btn-sm"
                    >
                        Open Settlement Sheet
                    </RouterLink>
                    <template v-else-if="monthLock.status === 'closed'">
                        <button
                            v-if="unpaidMonthlyWagesCount > 0"
                            type="button"
                            class="btn btn-outline-success btn-sm"
                            :disabled="monthlyMarkingAll"
                            @click="handleMarkAllPaid"
                        >
                            {{ monthlyMarkingAll ? 'Marking…' : 'Mark All Paid' }}
                        </button>
                        <button
                            type="button"
                            class="btn btn-outline-secondary btn-sm"
                            :disabled="monthlyReopening"
                            @click="handleReopenMonth"
                        >
                            {{ monthlyReopening ? 'Reopening…' : 'Reopen Month' }}
                        </button>
                        <button
                            type="button"
                            class="btn btn-dark btn-sm"
                            :disabled="monthlyLocking"
                            @click="handleLockMonth"
                        >
                            {{ monthlyLocking ? 'Locking…' : 'Lock Month' }}
                        </button>
                    </template>
                    <button
                        v-else-if="monthLock.status === 'paid'"
                        type="button"
                        class="btn btn-outline-warning btn-sm"
                        :disabled="monthlyUnlocking"
                        @click="handleUnlockMonth"
                    >
                        {{ monthlyUnlocking ? 'Unlocking…' : 'Unlock Month' }}
                    </button>
                </div>
            </div>
            <div class="card-body">
                <p class="text-muted small mb-3">
                    Totals use auto-present days plus exceptions. Monthly base ÷ days in month = day accrual.
                </p>
                <div v-if="monthlyWagesLoading" class="text-center py-4 text-muted">Loading monthly wages…</div>
                <div v-else-if="monthlyWages.length === 0" class="text-muted">No monthly wage records yet.</div>
                <div v-else class="table-responsive">
                    <table class="table table-bordered table-striped mb-0">
                        <thead>
                            <tr>
                                <th>Driver</th>
                                <th>Payable Days</th>
                                <th>Total Wage</th>
                                <th>Status</th>
                                <th>Paid At</th>
                                <th v-if="canManageWages" style="width: 130px">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in monthlyWages" :key="row.id">
                                <td>{{ row.driver?.name ?? '-' }}</td>
                                <td>{{ row.payable_days ?? 0 }}</td>
                                <td>{{ formatMoney(row.total_wage) }}</td>
                                <td>
                                    <span
                                        class="badge"
                                        :class="
                                            row.payment_status === 'paid'
                                                ? 'bg-success'
                                                : 'bg-warning text-dark'
                                        "
                                    >
                                        {{ row.payment_status === 'paid' ? 'Paid' : 'Unpaid' }}
                                    </span>
                                </td>
                                <td>{{ row.paid_at ? row.paid_at.slice(0, 10) : '—' }}</td>
                                <td v-if="canManageWages">
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-outline-success"
                                        :disabled="row.payment_status === 'paid'"
                                        @click="handleMarkMonthlyPaid(row)"
                                    >
                                        Mark Paid
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                <h3 class="card-title mb-0">Exceptions Log</h3>
                <span v-if="pagination.total" class="text-muted small">
                    {{ pagination.from }}–{{ pagination.to }} of {{ pagination.total }}
                </span>
            </div>
            <div class="card-body">
                <div class="row g-3 mb-3">
                    <div class="col-md-3">
                        <label class="form-label" for="detail_vehicle">Vehicle</label>
                        <select id="detail_vehicle" v-model="detailFilters.vehicle_id" class="form-select">
                            <option value="">All vehicles</option>
                            <option v-for="vehicle in vehicles" :key="vehicle.id" :value="vehicle.id">
                                {{ vehicle.registration_number }}
                            </option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="detail_driver">Driver</label>
                        <select id="detail_driver" v-model="detailFilters.primary_driver_id" class="form-select">
                            <option value="">All drivers</option>
                            <option v-for="driver in drivers" :key="driver.id" :value="driver.id">
                                {{ driver.is_active === false ? `${driver.name} (Inactive)` : driver.name }}
                            </option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label" for="detail_status">Status</label>
                        <select id="detail_status" v-model="detailFilters.status" class="form-select">
                            <option value="">All</option>
                            <option value="ABSENT">Absent</option>
                            <option value="NO_WORK_DAY">No working day</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label" for="detail_payment">Payment</label>
                        <select id="detail_payment" v-model="detailFilters.wage_payment_status" class="form-select">
                            <option value="">All</option>
                            <option value="paid">Paid</option>
                            <option value="unpaid">Unpaid</option>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex align-items-end gap-2">
                        <button type="button" class="btn btn-outline-primary" @click="applyDetailFilters">
                            Filter
                        </button>
                        <button type="button" class="btn btn-outline-secondary" @click="resetDetailFilters">
                            Reset
                        </button>
                    </div>
                </div>

                <div class="table-responsive">
                    <div v-if="tableLoading" class="text-center py-4 text-muted">Loading records…</div>
                    <table v-else class="table table-bordered table-striped mb-0">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Vehicle</th>
                                <th>Primary Driver</th>
                                <th>Status</th>
                                <th>Alternate</th>
                                <th>Wage</th>
                                <th>Payment</th>
                                <th v-if="canManageAttendance || canManageWages" style="width: 160px">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="attendances.length === 0">
                                <td :colspan="actionColspan" class="text-center text-muted">
                                    No exceptions logged for this month.
                                </td>
                            </tr>
                            <tr v-for="row in attendances" :key="row.id">
                                <td>{{ row.attendance_date }}</td>
                                <td>{{ row.vehicle?.registration_number ?? '-' }}</td>
                                <td>{{ row.primary_driver?.name ?? '-' }}</td>
                                <td>
                                    <span
                                        class="badge d-inline-flex align-items-center gap-1"
                                        :class="statusBadgeClass(row)"
                                    >
                                        <i class="bi" :class="statusBadgeIcon(row)"></i>
                                        {{ statusLabel(row) }}
                                    </span>
                                </td>
                                <td>{{ row.temporary_driver?.name ?? '-' }}</td>
                                <td>{{ formatMoney(row.wage_amount) }}</td>
                                <td>
                                    <span
                                        v-if="isPayable(row)"
                                        class="badge d-inline-flex align-items-center gap-1"
                                        :class="
                                            row.wage_payment_status === 'paid'
                                                ? 'bg-success'
                                                : 'bg-warning text-dark'
                                        "
                                    >
                                        <i
                                            class="bi"
                                            :class="
                                                row.wage_payment_status === 'paid'
                                                    ? 'bi-cash-coin'
                                                    : 'bi-hourglass-split'
                                            "
                                        ></i>
                                        {{ row.wage_payment_status === 'paid' ? 'Paid' : 'Unpaid' }}
                                    </span>
                                    <span v-else class="text-muted">—</span>
                                </td>
                                <td v-if="canManageAttendance || canManageWages">
                                    <AppTableActions>
                                        <AppCrudActionButton
                                            v-if="canManageAttendance"
                                            action="edit"
                                            @click="openEdit(row)"
                                        />
                                    </AppTableActions>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <nav
                    v-if="pagination.last_page > 1"
                    class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3"
                    aria-label="Attendance pagination"
                >
                    <div class="d-flex align-items-center gap-2">
                        <label class="text-muted small mb-0" for="per_page">Per page</label>
                        <select
                            id="per_page"
                            v-model.number="pagination.per_page"
                            class="form-select form-select-sm"
                            style="width: auto"
                            @change="onPerPageChange"
                        >
                            <option :value="15">15</option>
                            <option :value="25">25</option>
                            <option :value="50">50</option>
                        </select>
                    </div>
                    <ul class="pagination pagination-sm mb-0">
                        <li class="page-item" :class="{ disabled: pagination.current_page <= 1 }">
                            <button
                                type="button"
                                class="page-link"
                                :disabled="pagination.current_page <= 1"
                                @click="goToPage(pagination.current_page - 1)"
                            >
                                Previous
                            </button>
                        </li>
                        <li class="page-item disabled">
                            <span class="page-link">
                                Page {{ pagination.current_page }} / {{ pagination.last_page }}
                            </span>
                        </li>
                        <li
                            class="page-item"
                            :class="{ disabled: pagination.current_page >= pagination.last_page }"
                        >
                            <button
                                type="button"
                                class="page-link"
                                :disabled="pagination.current_page >= pagination.last_page"
                                @click="goToPage(pagination.current_page + 1)"
                            >
                                Next
                            </button>
                        </li>
                    </ul>
                </nav>
            </div>
        </div>

        <AttendanceFormModal
            modal-id="attendanceFormModal"
            :show="showForm"
            :mode="formMode"
            :attendance="editingRecord"
            :vehicles="vehicles"
            :drivers="drivers"
            :saving="saving"
            :prefilled-date="formPrefilledDate"
            :prefilled-vehicle-id="formPrefilledVehicleId"
            :field-errors="attendanceFieldErrors"
            :form-error="attendanceFormError"
            @close="closeForm"
            @submit="handleSubmit"
            @delete="handleDeleteException"
        />
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import { listAllDrivers } from '../../api/drivers';
import {
    closeMonthWages,
    deleteAttendance,
    fetchMonthOverview,
    fetchMonthlyWages,
    getAttendance,
    listAttendancesPaginated,
    lockMonthWages,
    markAllMonthlyWagesPaid,
    markAttendance,
    markMonthlyWagePaid,
    reopenMonthWages,
    unlockMonthWages,
    updateAttendance,
} from '../../api/attendances';
import { listAllVehicles } from '../../api/vehicles';
import AttendanceFormModal from '../../components/attendance/AttendanceFormModal.vue';
import AttendanceMonthGrid from '../../components/attendance/AttendanceMonthGrid.vue';
import AppButton from '../../components/ui/AppButton.vue';
import AppCrudActionButton from '../../components/ui/AppCrudActionButton.vue';
import AppTableActions from '../../components/ui/AppTableActions.vue';
import { usePermissions } from '../../composables/usePermissions';
import { extractValidationErrors } from '../../utils/formErrors';

const router = useRouter();
const { canInCompany } = usePermissions();

const canManageAttendance = computed(() => canInCompany('attendances.manage'));
const canManageWages = computed(() => canInCompany('wages.manage'));
const actionColspan = computed(() => (canManageAttendance.value || canManageWages.value ? 8 : 7));
const monthLock = computed(() => monthOverview.value.month_lock ?? { status: 'open' });
const isMonthEditable = computed(() => monthLock.value.status === 'open');
const canEditGrid = computed(() => canManageAttendance.value && isMonthEditable.value);
const monthLockLabel = computed(() => {
    if (monthLock.value.status === 'paid') {
        return 'Month locked';
    }

    if (monthLock.value.status === 'closed') {
        return 'Month closed';
    }

    return 'Month open';
});

const unpaidMonthlyWagesCount = computed(
    () => monthlyWages.value.filter((row) => row.payment_status !== 'paid').length,
);

const attendances = ref([]);
const monthOverview = ref({
    summary: { marked: 0, present: 0, absent: 0, paid: 0, unpaid: 0, exceptions: 0, no_work: 0 },
    rows: [],
    month_lock: { status: 'open', closed_at: null, locked_at: null },
});
const monthlyWages = ref([]);
const vehicles = ref([]);
const drivers = ref([]);
const overviewLoading = ref(true);
const monthlyWagesLoading = ref(true);
const monthlyClosing = ref(false);
const monthlyLocking = ref(false);
const monthlyMarkingAll = ref(false);
const monthlyReopening = ref(false);
const monthlyUnlocking = ref(false);
const formPrefilledDate = ref('');
const formPrefilledVehicleId = ref('');
const attendanceFieldErrors = ref({});
const attendanceFormError = ref('');
const tableLoading = ref(true);
const saving = ref(false);
const showForm = ref(false);
const formMode = ref('create');
const editingRecord = ref(null);
const flash = reactive({ success: '', error: '' });
const filters = reactive({
    month: currentMonth(),
});
const detailFilters = reactive({
    vehicle_id: '',
    primary_driver_id: '',
    status: '',
    wage_payment_status: '',
});
const pagination = reactive({
    current_page: 1,
    last_page: 1,
    per_page: 25,
    total: 0,
    from: 0,
    to: 0,
});

function currentMonth() {
    const now = new Date();

    return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`;
}

function monthDateRange(month) {
    if (!month) {
        return {};
    }

    const [year, monthNumber] = month.split('-').map(Number);
    const lastDay = new Date(year, monthNumber, 0).getDate();

    return {
        from_date: `${month}-01`,
        to_date: `${month}-${String(lastDay).padStart(2, '0')}`,
    };
}

function detailQueryParams() {
    const range = monthDateRange(filters.month);
    const params = {
        page: pagination.current_page,
        per_page: pagination.per_page,
        ...range,
    };

    if (detailFilters.vehicle_id) {
        params.vehicle_id = detailFilters.vehicle_id;
    }

    if (detailFilters.primary_driver_id) {
        params.primary_driver_id = detailFilters.primary_driver_id;
    }

    if (detailFilters.status) {
        params.status = detailFilters.status;
    }

    if (detailFilters.wage_payment_status) {
        params.wage_payment_status = detailFilters.wage_payment_status;
    }

    return params;
}

function formatMoney(value) {
    const amount = Number(value ?? 0);

    return Number.isNaN(amount) ? '0.00' : amount.toFixed(2);
}

function isPayable(row) {
    return Number(row?.wage_amount ?? 0) > 0;
}

function monthLabel() {
    const [year, month] = filters.month.split('-');

    if (!year || !month) {
        return filters.month;
    }

    return new Date(Number(year), Number(month) - 1, 1).toLocaleDateString(undefined, {
        month: 'long',
        year: 'numeric',
    });
}

function hasSubstitute(row) {
    return Boolean(row?.temporary_driver_id);
}

function statusBadgeClass(row) {
    if (row.status === 'PRESENT') {
        return 'bg-success';
    }

    if (hasSubstitute(row)) {
        return 'bg-warning text-dark';
    }

    return 'bg-danger';
}

function statusBadgeIcon(row) {
    if (row.status === 'PRESENT') {
        return 'bi-check-circle-fill';
    }

    if (hasSubstitute(row)) {
        return 'bi-arrow-left-right';
    }

    return 'bi-x-circle-fill';
}

function statusLabel(row) {
    if (row.status === 'NO_WORK_DAY') {
        return 'No working day';
    }

    if (hasSubstitute(row)) {
        return 'Absent + substitute';
    }

    if (row.status === 'ABSENT') {
        return 'Absent';
    }

    return 'Present';
}

function showToast(message, type = 'success') {
    flash.success = type === 'success' ? message : '';
    flash.error = type === 'error' ? message : '';
}

function syncPagination(meta) {
    pagination.current_page = meta.current_page ?? 1;
    pagination.last_page = meta.last_page ?? 1;
    pagination.per_page = meta.per_page ?? pagination.per_page;
    pagination.total = meta.total ?? 0;
    pagination.from = meta.from ?? 0;
    pagination.to = meta.to ?? 0;
}

async function loadMonthOverview() {
    overviewLoading.value = true;

    try {
        const range = monthDateRange(filters.month);
        monthOverview.value = await fetchMonthOverview(range);
    } catch {
        monthOverview.value = {
            summary: { marked: 0, present: 0, absent: 0, paid: 0, unpaid: 0 },
            rows: [],
        };
    } finally {
        overviewLoading.value = false;
    }
}

async function loadMonthlyWages() {
    monthlyWagesLoading.value = true;

    try {
        monthlyWages.value = await fetchMonthlyWages(filters.month);
    } catch {
        monthlyWages.value = [];
    } finally {
        monthlyWagesLoading.value = false;
    }
}

async function loadAttendances() {
    tableLoading.value = true;
    flash.error = '';

    try {
        const { rows, meta } = await listAttendancesPaginated(detailQueryParams());

        attendances.value = rows;
        syncPagination(meta);
    } catch {
        showToast('Could not load attendance records.', 'error');
        attendances.value = [];
        syncPagination({});
    } finally {
        tableLoading.value = false;
    }
}

async function loadLookups() {
    try {
        const [vehicleRows, driverRows] = await Promise.all([
            listAllVehicles(),
            listAllDrivers({ activeOnly: false }),
        ]);

        vehicles.value = vehicleRows;
        drivers.value = driverRows;
    } catch {
        vehicles.value = [];
        drivers.value = [];
    }
}

function onMonthChange() {
    pagination.current_page = 1;
    reloadAll();
}

function applyDetailFilters() {
    pagination.current_page = 1;
    loadAttendances();
}

function resetDetailFilters() {
    detailFilters.vehicle_id = '';
    detailFilters.primary_driver_id = '';
    detailFilters.status = '';
    detailFilters.wage_payment_status = '';
    applyDetailFilters();
}

function goToPage(page) {
    if (page < 1 || page > pagination.last_page) {
        return;
    }

    pagination.current_page = page;
    loadAttendances();
}

function onPerPageChange() {
    pagination.current_page = 1;
    loadAttendances();
}

async function reloadAll() {
    await Promise.all([loadMonthOverview(), loadMonthlyWages(), loadAttendances()]);
}

function openCreate() {
    formMode.value = 'create';
    editingRecord.value = null;
    formPrefilledDate.value = '';
    formPrefilledVehicleId.value = '';
    showForm.value = true;
}

function openCreateFromCell(cell) {
    formMode.value = 'create';
    editingRecord.value = null;
    formPrefilledDate.value = cell.attendance_date ?? '';
    formPrefilledVehicleId.value = cell.vehicle_id ?? '';
    showForm.value = true;
}

function openEdit(row) {
    formMode.value = 'edit';
    editingRecord.value = { ...row };
    showForm.value = true;
}

async function openEditFromGrid(cell) {
    if (!cell) {
        return;
    }

    if (!cell.id) {
        if (!canEditGrid.value) {
            return;
        }

        openCreateFromCell(cell);

        return;
    }

    try {
        editingRecord.value = await getAttendance(cell.id);
        formMode.value = 'edit';
        formPrefilledDate.value = '';
        formPrefilledVehicleId.value = '';
        showForm.value = true;
    } catch {
        showToast('Could not load exception for editing.', 'error');
    }
}

function closeForm() {
    showForm.value = false;
    editingRecord.value = null;
    formPrefilledDate.value = '';
    formPrefilledVehicleId.value = '';
    attendanceFieldErrors.value = {};
    attendanceFormError.value = '';
}

async function handleSubmit(payload) {
    saving.value = true;
    attendanceFieldErrors.value = {};
    attendanceFormError.value = '';

    try {
        if (formMode.value === 'edit' && editingRecord.value?.id) {
            await updateAttendance(editingRecord.value.id, payload);
            showToast('Exception updated successfully.');
        } else {
            await markAttendance(payload);
            showToast('Exception saved successfully.');
        }

        closeForm();
        await reloadAll();
    } catch (error) {
        const { fieldErrors, message } = extractValidationErrors(error);

        if (Object.keys(fieldErrors).length > 0 || message) {
            attendanceFieldErrors.value = fieldErrors;
            attendanceFormError.value = message ?? 'Please check the highlighted fields and try again.';
        } else if (error.response?.status === 403) {
            showToast('You do not have permission for this action.', 'error');
        } else {
            showToast('Unable to save attendance.', 'error');
        }
    } finally {
        saving.value = false;
    }
}

async function handleCloseMonth() {
    monthlyClosing.value = true;

    try {
        await closeMonthWages(filters.month);
        await router.push({
            name: 'attendance.settlement',
            query: { month: filters.month },
        });
    } catch {
        showToast('Unable to close month wages.', 'error');
    } finally {
        monthlyClosing.value = false;
    }
}

async function handleMarkAllPaid() {
    if (unpaidMonthlyWagesCount.value === 0) {
        return;
    }

    const notes = window.prompt('Payment notes for all drivers (optional):', '');

    if (notes === null) {
        return;
    }

    monthlyMarkingAll.value = true;

    try {
        monthlyWages.value = await markAllMonthlyWagesPaid(filters.month, {
            payment_notes: notes || null,
        });
        await reloadAll();
        showToast(`All drivers marked paid for ${monthLabel()}.`);
    } catch (error) {
        showToast(error.response?.data?.message ?? 'Unable to mark all as paid.', 'error');
    } finally {
        monthlyMarkingAll.value = false;
    }
}

async function handleReopenMonth() {
    if (
        !window.confirm(
            `Reopen ${monthLabel()} for attendance edits? Monthly wage rows stay; you can close again later.`,
        )
    ) {
        return;
    }

    monthlyReopening.value = true;

    try {
        await reopenMonthWages(filters.month);
        await reloadAll();
        showToast(`${monthLabel()} reopened — you can edit exceptions again.`);
    } catch (error) {
        showToast(error.response?.data?.message ?? 'Unable to reopen month.', 'error');
    } finally {
        monthlyReopening.value = false;
    }
}

async function handleUnlockMonth() {
    if (
        !window.confirm(
            `Unlock ${monthLabel()}? Month goes back to closed — you can change payments and lock again.`,
        )
    ) {
        return;
    }

    monthlyUnlocking.value = true;

    try {
        await unlockMonthWages(filters.month);
        await reloadAll();
        showToast(`${monthLabel()} unlocked (closed). Mark payments or lock again.`);
    } catch (error) {
        showToast(error.response?.data?.message ?? 'Unable to unlock month.', 'error');
    } finally {
        monthlyUnlocking.value = false;
    }
}

async function handleLockMonth() {
    let confirmMessage = `Lock ${monthLabel()}? No more attendance exceptions can be added or changed.`;

    if (unpaidMonthlyWagesCount.value > 0) {
        confirmMessage = `${unpaidMonthlyWagesCount.value} driver(s) are still unpaid.\n\n${confirmMessage}`;
    }

    if (!window.confirm(confirmMessage)) {
        return;
    }

    monthlyLocking.value = true;

    try {
        await lockMonthWages(filters.month);
        await reloadAll();
        showToast(`${monthLabel()} is locked.`);
    } catch (error) {
        const message = error.response?.data?.message;

        showToast(message ?? 'Unable to lock month. Close the month first.', 'error');
    } finally {
        monthlyLocking.value = false;
    }
}

async function handleDeleteException(record) {
    if (!record?.id) {
        return;
    }

    if (!window.confirm('Remove this exception? The day will return to auto-present.')) {
        return;
    }

    saving.value = true;

    try {
        await deleteAttendance(record.id);
        closeForm();
        showToast('Exception removed.');
        await reloadAll();
    } catch {
        showToast('Unable to remove exception.', 'error');
    } finally {
        saving.value = false;
    }
}

async function handleMarkMonthlyPaid(row) {
    const notes = window.prompt('Monthly payment notes (optional):', row.payment_notes ?? '');

    if (notes === null) {
        return;
    }

    try {
        await markMonthlyWagePaid(row.id, { payment_notes: notes || null });
        showToast('Monthly wage marked as paid.');
        await reloadAll();
    } catch {
        showToast('Unable to mark monthly wage as paid.', 'error');
    }
}

onMounted(async () => {
    await loadLookups();
    await reloadAll();
});
</script>
