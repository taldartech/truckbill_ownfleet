<template>
    <div class="container mt-4 app-index-page">
        <AppIndexPageHeader title="Driver Attendance &amp; Wages">
            <template #actions>
                <AppButton
                    variant="secondary"
                    outline
                    size="sm"
                    icon="bi bi-table"
                    label="Monthly Settlement"
                    :to="{ name: 'attendance.settlement', query: { month: filters.month } }"
                />
                <AppButton
                    v-if="canManageAttendance && isMonthEditable"
                    icon="bi bi-exclamation-triangle"
                    label="Mark Attendance"
                    @click="openCreate"
                />
            </template>
        </AppIndexPageHeader>

        <div v-if="flash.success" class="alert alert-success">{{ flash.success }}</div>
        <div v-if="flash.error" class="alert alert-danger">{{ flash.error }}</div>

        <div
            v-if="monthLock.status !== 'open'"
            class="alert"
            :class="monthLock.status === 'paid' ? 'alert-secondary' : 'alert-warning'"
        >
            <strong>{{ monthLockLabel }} for {{ monthLabel() }}</strong>
            <template v-if="monthLock.status === 'closed'">
                <p class="mb-0 mt-2 small">
                    Step 1 done — exceptions are frozen. Open
                    <RouterLink
                        :to="{ name: 'attendance.settlement', query: { month: filters.month } }"
                        class="alert-link"
                    >
                        Monthly Settlement
                    </RouterLink>
                    to mark payments and lock the month.
                </p>
            </template>
            <p v-else class="mb-0 mt-2 small">
                Month is locked. Use
                <RouterLink
                    :to="{ name: 'attendance.settlement', query: { month: filters.month } }"
                    class="alert-link"
                >
                    Monthly Settlement
                </RouterLink>
                to unlock or reopen the month.
            </p>
        </div>

        <AppIndexFiltersCard :active="listFiltersActive" @apply="applyListFilters" @clear="clearListFilters">
            <div>
                <label class="form-label" for="filter_month">Month</label>
                <input
                    id="filter_month"
                    v-model="filters.month"
                    type="month"
                    class="form-control"
                    aria-label="Select month"
                    :disabled="overviewLoading || tableLoading"
                />
            </div>
            <div>
                <label class="form-label" for="detail_vehicle">Truck</label>
                <select
                    id="detail_vehicle"
                    v-model="detailFilters.vehicle_id"
                    class="form-select"
                    :disabled="tableLoading"
                >
                    <option value="">All trucks</option>
                    <option v-for="vehicle in vehicles" :key="vehicle.id" :value="vehicle.id">
                        {{ vehicle.registration_number }}
                    </option>
                </select>
            </div>
            <div>
                <label class="form-label" for="detail_driver">Driver</label>
                <select
                    id="detail_driver"
                    v-model="detailFilters.primary_driver_id"
                    class="form-select"
                    :disabled="tableLoading"
                >
                    <option value="">All drivers</option>
                    <option v-for="driver in drivers" :key="driver.id" :value="driver.id">
                        {{ driver.is_active === false ? `${driver.name} (Inactive)` : driver.name }}
                    </option>
                </select>
            </div>
            <div>
                <label class="form-label" for="detail_status">Status</label>
                <select id="detail_status" v-model="detailFilters.status" class="form-select" :disabled="tableLoading">
                    <option value="">All statuses</option>
                    <option value="ABSENT">Absent</option>
                    <option value="NO_WORK_DAY">No working day</option>
                </select>
            </div>
            <div>
                <label class="form-label" for="detail_payment">Payment</label>
                <select
                    id="detail_payment"
                    v-model="detailFilters.wage_payment_status"
                    class="form-select"
                    :disabled="tableLoading"
                >
                    <option value="">All payments</option>
                    <option value="paid">Paid</option>
                    <option value="unpaid">Unpaid</option>
                </select>
            </div>
        </AppIndexFiltersCard>

        <div class="card mb-3 app-index-list-card">
            <div class="card-header">
                <h3 class="card-title mb-0">Month Overview</h3>
                <span class="text-muted small">Green P = auto present (today and past only) · future days stay blank until you mark an exception</span>
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

        <AppIndexListCard title="Exceptions Log" :loading="tableLoading" loading-text="Loading records…">
            <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Date</th>
                                <th>Truck</th>
                                <th>Primary Driver</th>
                                <th>Status</th>
                                <th>Alternate</th>
                                <th>Wage</th>
                                <th>Payment</th>
                                <th v-if="canManageAttendance || canManageWages" class="app-index-actions-cell" style="width: 160px">Actions</th>
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
                                <td v-if="canManageAttendance || canManageWages" class="app-index-actions-cell">
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
            <template #footer>
                <AppIndexPaginationFooter
                    page-id="attendancesIndex"
                    :loading="tableLoading"
                    :current-page="pagination.current_page"
                    :last-page="pagination.last_page"
                    :per-page="pagination.per_page"
                    :total="pagination.total"
                    :from="pagination.from"
                    :to="pagination.to"
                    item-label="records"
                    :per-page-options="[15, 25, 50]"
                    @page-change="goToPage"
                    @update:per-page="onAttendancePerPageUpdate"
                />
            </template>
        </AppIndexListCard>

        <AttendanceFormModal
            modal-id="attendanceFormModal"
            :show="showForm"
            :mode="formMode"
            :attendance="editingRecord"
            :vehicles="vehicles"
            :drivers="drivers"
            :month-overview="monthOverview"
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
import { listAllDrivers } from '../../api/drivers';
import {
    deleteAttendance,
    fetchMonthOverview,
    getAttendance,
    listAttendancesPaginated,
    markAttendance,
    updateAttendance,
} from '../../api/attendances';
import { listAllVehicles } from '../../api/vehicles';
import AttendanceFormModal from '../../components/attendance/AttendanceFormModal.vue';
import AttendanceMonthGrid from '../../components/attendance/AttendanceMonthGrid.vue';
import AppButton from '../../components/ui/AppButton.vue';
import AppIndexFiltersCard from '../../components/ui/AppIndexFiltersCard.vue';
import AppIndexListCard from '../../components/ui/AppIndexListCard.vue';
import AppIndexPageHeader from '../../components/ui/AppIndexPageHeader.vue';
import AppIndexPaginationFooter from '../../components/ui/AppIndexPaginationFooter.vue';
import AppCrudActionButton from '../../components/ui/AppCrudActionButton.vue';
import AppTableActions from '../../components/ui/AppTableActions.vue';
import { usePermissions } from '../../composables/usePermissions';
import { extractValidationErrors } from '../../utils/formErrors';

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

const attendances = ref([]);
const monthOverview = ref({
    summary: { marked: 0, present: 0, absent: 0, paid: 0, unpaid: 0, exceptions: 0, no_work: 0 },
    rows: [],
    month_lock: { status: 'open', closed_at: null, locked_at: null },
});
const vehicles = ref([]);
const drivers = ref([]);
const overviewLoading = ref(true);
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

const listFiltersActive = computed(
    () =>
        filters.month !== currentMonth() ||
        detailFilters.vehicle_id !== '' ||
        detailFilters.primary_driver_id !== '' ||
        detailFilters.status !== '' ||
        detailFilters.wage_payment_status !== '',
);

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

function applyListFilters() {
    pagination.current_page = 1;
    reloadAll();
}

function clearListFilters() {
    filters.month = currentMonth();
    detailFilters.vehicle_id = '';
    detailFilters.primary_driver_id = '';
    detailFilters.status = '';
    detailFilters.wage_payment_status = '';
    pagination.current_page = 1;
    reloadAll();
}

function goToPage(page) {
    if (page < 1 || page > pagination.last_page) {
        return;
    }

    pagination.current_page = page;
    loadAttendances();
}

function onAttendancePerPageUpdate(perPage) {
    pagination.per_page = perPage;
    pagination.current_page = 1;
    loadAttendances();
}

async function reloadAll() {
    await Promise.all([loadMonthOverview(), loadAttendances()]);
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

onMounted(async () => {
    await loadLookups();
    await reloadAll();
});
</script>
