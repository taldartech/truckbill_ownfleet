<template>
    <div class="container mt-4 app-index-page app-index-page--standard">
        <div
            v-if="monthLock.status !== 'open'"
            class="alert"
            :class="monthLock.status === 'paid' ? 'alert-secondary' : 'alert-warning'"
        >
            <strong>{{ monthLockLabel }} for {{ monthLabel() }}</strong>
            <template v-if="monthLock.status === 'closed'">
                <p v-if="canViewSettlement" class="mb-0 mt-2 small">
                    Attendance is frozen. Open
                    <RouterLink
                        :to="{ name: 'attendance.settlement', query: { month: filters.month } }"
                        class="alert-link"
                    >
                        Monthly Settlement
                    </RouterLink>
                    to mark all paid or unlock the month.
                </p>
                <p v-else class="mb-0 mt-2 small">
                    Attendance is frozen. Contact an admin to complete monthly settlement.
                </p>
            </template>
            <p v-else-if="canViewSettlement" class="mb-0 mt-2 small">
                Month is finalized. No further attendance changes are allowed.
            </p>
            <p v-else class="mb-0 mt-2 small">
                Month is finalized. Contact an admin if you need help reviewing payroll.
            </p>
        </div>

        <ul class="nav nav-tabs mb-3">
            <li class="nav-item">
                <button
                    type="button"
                    class="nav-link"
                    :class="{ active: activeView === 'day' }"
                    @click="setActiveView('day')"
                >
                    Daily Attendance
                </button>
            </li>
            <li class="nav-item">
                <button
                    type="button"
                    class="nav-link"
                    :class="{ active: activeView === 'month' }"
                    @click="setActiveView('month')"
                >
                    Month View
                </button>
            </li>
        </ul>

        <div
            v-if="activeView === 'day'"
            class="mb-3"
            :class="{ 'daily-attendance-complete': dayAttendanceComplete }"
        >
            <AppIndexListCard title="Daily Attendance">
                <template #toolbar>
                    <AppIndexHeaderButton
                        icon="filter"
                        :aria-expanded="filtersOpen"
                        aria-controls="attendances-day-filters-panel"
                        aria-label="Toggle date filter"
                        @click="toggleFilters"
                    >
                        Date filter
                    </AppIndexHeaderButton>
                    <AppIndexHeaderButton
                        v-if="canViewSettlement"
                        aria-label="Monthly Settlement"
                        @click="goToMonthlySettlement"
                    >
                        Monthly Settlement
                    </AppIndexHeaderButton>
                    <AppIndexHeaderButton
                        v-if="canBulkManageAttendance && canEditDay"
                        aria-label="Bulk Mark"
                        @click="openBulkMark"
                    >
                        Bulk Mark
                    </AppIndexHeaderButton>
                </template>

                <AppIndexCollapsibleFilters
                    v-show="filtersOpen"
                    panel-id="attendances-day-filters-panel"
                    :loading="dayOverviewLoading"
                    @search="applyDayFilters"
                    @reset="clearDayFilters"
                >
                    <div>
                        <label class="form-label" for="attendances_day_date">Date</label>
                        <AppDateInput
                            input-id="attendances_day_date"
                            v-model="dayDate"
                            :max="today"
                            :disabled="dayOverviewLoading || dailySaving"
                        />
                    </div>
                    <template v-if="dayFiltersActive" #active-filters>
                        <div class="app-index-active-search">
                            <span class="app-index-active-search__label">Date:</span>
                            <span class="app-index-active-search__pill">
                                {{ formatDate(dayDate) }}
                                <button
                                    type="button"
                                    aria-label="Reset to today"
                                    title="Reset to today"
                                    @click="clearDayFilters"
                                >
                                    <i class="fas fa-times" aria-hidden="true"></i>
                                </button>
                            </span>
                        </div>
                    </template>
                </AppIndexCollapsibleFilters>

                <AttendanceDayPanel
                    ref="dayPanelRef"
                    v-model:selected-date="dayDate"
                    :show-date-controls="false"
                    :vehicles="vehicles"
                    :drivers="drivers"
                    :overview-rows="dayOverview.rows"
                    :substitute-busy-by-date="dayOverview.substitute_busy_by_date ?? {}"
                    :loading="dayOverviewLoading"
                    :saving="dailySaving"
                    :can-edit="canEditDay"
                    @save="handleDailySave"
                    @complete-change="dayAttendanceComplete = $event"
                />
            </AppIndexListCard>
        </div>

        <div v-else class="mb-3 month-overview-card">
            <AppIndexListCard title="Month View">
                <template #toolbar>
                    <AppIndexHeaderButton
                        icon="filter"
                        :aria-expanded="filtersOpen"
                        aria-controls="attendances-month-filters-panel"
                        aria-label="Toggle month filter"
                        @click="toggleFilters"
                    >
                        Month filter
                    </AppIndexHeaderButton>
                    <AppIndexHeaderButton
                        v-if="canViewSettlement"
                        aria-label="Monthly Settlement"
                        @click="goToMonthlySettlement"
                    >
                        Monthly Settlement
                    </AppIndexHeaderButton>
                    <AppIndexHeaderButton
                        v-if="canBulkManageAttendance && canEditDay"
                        aria-label="Bulk Mark"
                        @click="openBulkMark"
                    >
                        Bulk Mark
                    </AppIndexHeaderButton>
                </template>

                <AppIndexCollapsibleFilters
                    v-show="filtersOpen"
                    panel-id="attendances-month-filters-panel"
                    :loading="overviewLoading"
                    @search="applyListFilters"
                    @reset="clearListFilters"
                >
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
                    <template v-if="listFiltersActive" #active-filters>
                        <div class="app-index-active-search">
                            <span class="app-index-active-search__label">Month:</span>
                            <span class="app-index-active-search__pill">
                                {{ monthLabel() }}
                                <button
                                    type="button"
                                    aria-label="Clear month filter"
                                    title="Clear month"
                                    @click="clearListFilters"
                                >
                                    <i class="fas fa-times" aria-hidden="true"></i>
                                </button>
                            </span>
                        </div>
                    </template>
                </AppIndexCollapsibleFilters>

                <div class="month-overview-body px-2 py-2">
                    <div v-if="overviewLoading" class="text-center py-4 text-muted">Loading overview…</div>
                    <AttendanceMonthGrid
                        v-else
                        :key="monthGridKey"
                        :month="filters.month"
                        :overview="monthOverview"
                        :can-edit="canEditGrid"
                        :link-trucks="true"
                        @select="openEditFromGrid"
                    />
                </div>
            </AppIndexListCard>
        </div>

        <div id="attendance-log" ref="attendanceLogRef">
            <AppIndexListCard
                :title="attendanceLogTitle"
                :loading="tableLoading"
                loading-text="Loading records…"
            >
                <template v-if="detailFilters.vehicle_id" #toolbar>
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle">
                        Truck: {{ selectedTruckLabel }}
                    </span>
                    <button type="button" class="btn btn-outline-secondary btn-sm" @click="clearTruckFilter">
                        Clear filter
                    </button>
                </template>
                <table class="table table-striped table-hover align-middle mb-0 app-index-table">
                    <thead class="table-light">
                        <tr>
                            <th>Date</th>
                            <th>Truck</th>
                            <th>Primary Driver</th>
                            <th>Status</th>
                            <th>Alternate</th>
                            <th>Wage</th>
                            <th>Payment</th>
                            <th v-if="canEditAttendanceRecords || canManageWages" class="app-index-actions-cell text-end" style="width: 160px">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="attendances.length === 0" class="app-index-empty-row">
                            <td :colspan="actionColspan" class="text-center text-muted py-4">
                                {{
                                    detailFilters.vehicle_id
                                        ? `No A or S records for ${selectedTruckLabel || 'this truck'}.`
                                        : 'No A or S attendance marked for this period.'
                                }}
                            </td>
                        </tr>
                        <tr v-for="row in attendances" :key="row.id">
                            <td>{{ formatDate(row.attendance_date) }}</td>
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
                            <td v-if="canEditAttendanceRecords || canManageWages" class="app-index-actions-cell text-end">
                                <div class="app-index-table-actions">
                                    <AppCrudActionButton
                                        v-if="canEditAttendanceRecords"
                                        action="edit"
                                        @click="openEdit(row)"
                                    />
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <template #footer>
                    <AppIndexPartloadPagination
                        :pagination="pagination"
                        :loading="tableLoading"
                        item-label="records"
                        aria-label="Attendance records pagination"
                        @page-change="goToPage"
                    />
                </template>
            </AppIndexListCard>
        </div>

        <AttendanceBulkMarkModal
            modal-id="attendanceBulkMarkModal"
            :show="showBulkForm"
            :vehicles="vehicles"
            :drivers="drivers"
            :saving="bulkSaving"
            :default-vehicle-id="bulkPrefilledVehicleId"
            :default-from-date="dayDate"
            :field-errors="bulkFieldErrors"
            :form-error="bulkFormError"
            @close="closeBulkForm"
            @submit="handleBulkSubmit"
        />

        <AttendanceFormModal
            modal-id="attendanceFormModal"
            :show="showForm"
            :mode="formMode"
            :attendance="editingRecord"
            :vehicles="vehicles"
            :drivers="drivers"
            :month-overview="activeView === 'day' ? dayOverview : monthOverview"
            :saving="saving"
            :prefilled-date="formPrefilledDate"
            :prefilled-vehicle-id="formPrefilledVehicleId"
            :field-errors="attendanceFieldErrors"
            :form-error="attendanceFormError"
            @close="closeForm"
            @submit="requestFormSave"
            @delete="requestFormDelete"
        />

        <AttendanceSaveConfirmModal
            :show="showFormSaveConfirm"
            :summary="formSaveConfirmSummary"
            :saving="saving"
            @cancel="cancelFormSaveConfirm"
            @confirm="confirmFormSave"
        />
    </div>
</template>

<script setup>
import { computed, nextTick, onMounted, reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { listAllDrivers } from '../../api/drivers';
import {
    bulkMarkAttendance,
    deleteAttendance,
    fetchMonthOverview,
    getAttendance,
    listAttendancesPaginated,
    markAttendance,
    updateAttendance,
} from '../../api/attendances';
import AttendanceBulkMarkModal from '../../components/attendance/AttendanceBulkMarkModal.vue';
import { listAllVehicles } from '../../api/vehicles';
import AttendanceDayPanel from '../../components/attendance/AttendanceDayPanel.vue';
import AttendanceFormModal from '../../components/attendance/AttendanceFormModal.vue';
import AttendanceMonthGrid from '../../components/attendance/AttendanceMonthGrid.vue';
import AttendanceSaveConfirmModal from '../../components/attendance/AttendanceSaveConfirmModal.vue';
import { buildFormModalConfirmSummary } from '../../utils/attendanceSaveConfirm';
import AppDateInput from '../../components/ui/AppDateInput.vue';
import AppIndexCollapsibleFilters from '../../components/ui/AppIndexCollapsibleFilters.vue';
import AppIndexHeaderButton from '../../components/ui/AppIndexHeaderButton.vue';
import AppIndexListCard from '../../components/ui/AppIndexListCard.vue';
import AppIndexPartloadPagination from '../../components/ui/AppIndexPartloadPagination.vue';
import AppCrudActionButton from '../../components/ui/AppCrudActionButton.vue';
import { useListPagination } from '../../composables/useListPagination';
import { usePermissions } from '../../composables/usePermissions';
import { useAppToast } from '../../composables/useAppToast';
import { extractValidationErrors, friendlyApiErrorMessage } from '../../utils/formErrors';
import { formatDate, formatMonthYear, toDateInputValue, currentMonthValue } from '../../utils/datetime';

const route = useRoute();
const router = useRouter();
const toast = useAppToast();
const { canInCompany, canAnyInCompany } = usePermissions();
const { pagination, syncFromMeta } = useListPagination(10);

const canManageAttendance = computed(() =>
    canAnyInCompany(['attendances.create', 'attendances.edit', 'attendances.manage']),
);
const canBulkManageAttendance = computed(() =>
    canAnyInCompany(['attendance.bulk_mark', 'attendances.bulk_manage']),
);
const canViewSettlement = computed(() => canInCompany('wages.view'));
const canManageWages = computed(() => canInCompany('wages.manage'));
const actionColspan = computed(() => (canEditAttendanceRecords.value || canManageWages.value ? 8 : 7));
const monthLock = computed(() => monthOverview.value.month_lock ?? { status: 'open' });
const isMonthEditable = computed(() => monthLock.value.status === 'open');
const canEditGrid = computed(() => canBulkManageAttendance.value && isMonthEditable.value);
const activeView = ref('day');
const dayDate = ref(toDateInputValue());
const today = toDateInputValue();
const filtersOpen = ref(false);
const dayOverview = ref({
    summary: { marked: 0, present: 0, absent: 0, paid: 0, unpaid: 0, exceptions: 0, no_work: 0 },
    rows: [],
    month_lock: { status: 'open', closed_at: null, locked_at: null },
});
const dayOverviewLoading = ref(false);
const dayMonthLock = computed(() => dayOverview.value.month_lock ?? { status: 'open' });
const isDayMonthEditable = computed(() => dayMonthLock.value.status === 'open');
const canEditDay = computed(() => canManageAttendance.value && isDayMonthEditable.value);
const canEditAttendanceRecords = computed(() => {
    if (!canManageAttendance.value) {
        return false;
    }

    if (activeView.value === 'day') {
        return isDayMonthEditable.value;
    }

    return canBulkManageAttendance.value && isMonthEditable.value;
});
const monthLockLabel = computed(() => {
    if (monthLock.value.status === 'paid') {
        return 'Month finalized';
    }

    if (monthLock.value.status === 'closed') {
        return 'Attendance locked';
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
const dailySaving = ref(false);
const dayAttendanceComplete = ref(false);
const monthGridKey = ref(0);
const dayPanelRef = ref(null);
const attendanceLogRef = ref(null);
const bulkSaving = ref(false);
const showBulkForm = ref(false);
const bulkPrefilledVehicleId = ref('');
const bulkFieldErrors = ref({});
const bulkFormError = ref('');
const showForm = ref(false);
const formMode = ref('create');
const editingRecord = ref(null);
const showFormSaveConfirm = ref(false);
const formSaveConfirmSummary = ref(null);
const pendingFormPayload = ref(null);
const pendingFormDeleteRecord = ref(null);
const filters = reactive({
    month: currentMonth(),
});
const detailFilters = reactive({
    vehicle_id: '',
    primary_driver_id: '',
    status: '',
    wage_payment_status: '',
});

const listFiltersActive = computed(() => filters.month !== currentMonth());
const dayFiltersActive = computed(() => dayDate.value !== today);

const selectedTruckLabel = computed(() => {
    const vehicle = vehicles.value.find((item) => item.id === detailFilters.vehicle_id);

    return vehicle?.registration_number ?? '';
});

const attendanceLogTitle = computed(() => {
    const base = activeView.value === 'day' ? `Attendance Log — ${dayDate.value}` : 'Attendance Log';

    if (!selectedTruckLabel.value) {
        return base;
    }

    return `${base} · ${selectedTruckLabel.value}`;
});

function currentMonth() {
    return currentMonthValue();
}

function toggleFilters() {
    filtersOpen.value = !filtersOpen.value;
}

function settlementMonth() {
    if (activeView.value === 'day' && dayDate.value) {
        return dayDate.value.slice(0, 7);
    }

    return filters.month || currentMonth();
}

function goToMonthlySettlement() {
    router.push({
        name: 'attendance.settlement',
        query: { month: settlementMonth() },
    });
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
    const range =
        activeView.value === 'day'
            ? { from_date: dayDate.value, to_date: dayDate.value }
            : monthDateRange(filters.month);
    const params = {
        page: pagination.currentPage,
        per_page: pagination.perPage,
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

    return formatMonthYear(`${year}-${month}`);
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
    if (hasSubstitute(row)) {
        return 'S — Substitute';
    }

    if (row.status === 'ABSENT' || row.status === 'NO_WORK_DAY') {
        return 'A — Absent';
    }

    return 'Present';
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

async function loadDayOverview() {
    dayOverviewLoading.value = true;

    try {
        dayOverview.value = await fetchMonthOverview({
            from_date: dayDate.value,
            to_date: dayDate.value,
        });
    } catch {
        dayOverview.value = {
            summary: { marked: 0, present: 0, absent: 0, paid: 0, unpaid: 0 },
            rows: [],
            month_lock: { status: 'open', closed_at: null, locked_at: null },
        };
    } finally {
        dayOverviewLoading.value = false;
    }
}

function setActiveView(view) {
    activeView.value = view;

    if (view === 'day') {
        filters.month = dayDate.value.slice(0, 7);
        pagination.currentPage = 1;
        reloadActiveView();
        return;
    }

    reloadActiveView();
}

async function reloadActiveView() {
    const tasks = [loadAttendances()];

    if (activeView.value === 'day') {
        tasks.push(loadDayOverview());
    } else {
        tasks.push(loadMonthOverview());
    }

    await Promise.all(tasks);
}

async function loadAttendances() {
    tableLoading.value = true;

    try {
        const { rows, meta } = await listAttendancesPaginated(detailQueryParams());

        attendances.value = rows;
        syncFromMeta(meta, rows.length);
    } catch {
        toast.error('Could not load attendance records.');
        attendances.value = [];
        syncFromMeta({}, 0);
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
    pagination.currentPage = 1;
    reloadAll();
}

function clearListFilters() {
    filters.month = currentMonth();
    pagination.currentPage = 1;
    reloadAll();
}

function applyDayFilters() {
    pagination.currentPage = 1;
    reloadActiveView();
}

function clearDayFilters() {
    dayDate.value = toDateInputValue();
    pagination.currentPage = 1;
    reloadActiveView();
}

async function filterByTruck(vehicleId) {
    if (!vehicleId) {
        return;
    }

    detailFilters.vehicle_id = vehicleId;
    pagination.currentPage = 1;
    await loadAttendances();

    await nextTick(() => {
        attendanceLogRef.value?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
}

function clearTruckFilter() {
    detailFilters.vehicle_id = '';
    pagination.currentPage = 1;
    loadAttendances();
}

function goToPage(page) {
    if (page < 1 || page > pagination.lastPage || page === pagination.currentPage) {
        return;
    }

    pagination.currentPage = page;
    loadAttendances();
}

async function reloadAll() {
    await reloadActiveView();
}

function openBulkMark() {
    bulkPrefilledVehicleId.value = '';
    bulkFieldErrors.value = {};
    bulkFormError.value = '';
    showBulkForm.value = true;
}

function closeBulkForm() {
    showBulkForm.value = false;
    bulkFieldErrors.value = {};
    bulkFormError.value = '';
}

async function persistDailyEntry(entry) {
    if (entry.statusType === 'PRESENT') {
        if (entry.attendance_id) {
            await deleteAttendance(entry.attendance_id);
        }

        return;
    }

    const payload = {
        vehicle_id: entry.vehicle_id,
        primary_driver_id: entry.primary_driver_id,
        attendance_date: entry.attendance_date,
        status: 'ABSENT',
        temporary_driver_id: entry.temporary_driver_id,
        temporary_assignment_remarks: entry.remarks,
        marked_via: 'admin_panel',
    };

    if (entry.attendance_id) {
        await updateAttendance(entry.attendance_id, payload);
    } else {
        await markAttendance(payload);
    }
}

async function handleDailySave({ entries, presentConfirmIds = [] }) {
    dailySaving.value = true;

    try {
        for (const entry of entries) {
            await persistDailyEntry(entry);
        }

        await reloadActiveView();
        dayPanelRef.value?.syncFormRows();

        const savedVehicleIds = [...new Set([...presentConfirmIds, ...entries.map((entry) => entry.vehicle_id)])];
        dayPanelRef.value?.markVehiclesSaved(savedVehicleIds);
        monthGridKey.value += 1;

        const totalSaved = savedVehicleIds.length;
        toast.success(
            totalSaved === 1 ? 'Attendance saved.' : `${totalSaved} truck${totalSaved === 1 ? '' : 's'} saved.`,
        );
    } catch (error) {
        toast.error(friendlyApiErrorMessage(error, 'Unable to save daily attendance.'));
    } finally {
        dailySaving.value = false;
        dayPanelRef.value?.clearSavingState();
    }
}

async function handleBulkSubmit(payload) {
    bulkSaving.value = true;
    bulkFieldErrors.value = {};
    bulkFormError.value = '';

    try {
        await bulkMarkAttendance(payload);
        closeBulkForm();
        toast.success('Bulk attendance saved.');
        await reloadActiveView();
    } catch (error) {
        const { fieldErrors, message } = extractValidationErrors(error);

        bulkFieldErrors.value = fieldErrors;
        bulkFormError.value =
            message ??
            (Object.keys(fieldErrors).length > 0
                ? 'Please check the highlighted fields.'
                : friendlyApiErrorMessage(error, 'Unable to save bulk attendance.'));
    } finally {
        bulkSaving.value = false;
    }
}

function openCreateFromCell(cell) {
    formMode.value = 'create';
    editingRecord.value = {
        attendance_date: cell.attendance_date,
        vehicle_id: cell.vehicle_id,
        primary_driver_id: cell.primary_driver_id,
    };
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
        toast.error('Could not load attendance for editing.');
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

function stripFormPayload(payload) {
    const { exception_type: _exceptionType, ...apiPayload } = payload;

    return apiPayload;
}

function requestFormSave(payload) {
    formSaveConfirmSummary.value = buildFormModalConfirmSummary({
        record: editingRecord.value,
        payload,
        drivers: drivers.value,
        vehicles: vehicles.value,
    });
    pendingFormPayload.value = payload;
    pendingFormDeleteRecord.value = null;
    showFormSaveConfirm.value = true;
}

function requestFormDelete({ record }) {
    if (!record?.id) {
        return;
    }

    formSaveConfirmSummary.value = buildFormModalConfirmSummary({
        record,
        payload: {
            attendance_date: record.attendance_date,
            vehicle_id: record.vehicle_id,
            primary_driver_id: record.primary_driver_id,
            exception_type: 'BLANK',
        },
        drivers: drivers.value,
        vehicles: vehicles.value,
    });
    pendingFormDeleteRecord.value = record;
    pendingFormPayload.value = null;
    showFormSaveConfirm.value = true;
}

function cancelFormSaveConfirm() {
    showFormSaveConfirm.value = false;
    formSaveConfirmSummary.value = null;
    pendingFormPayload.value = null;
    pendingFormDeleteRecord.value = null;
}

async function confirmFormSave() {
    if (pendingFormDeleteRecord.value?.id) {
        await executeFormDelete(pendingFormDeleteRecord.value);

        return;
    }

    if (!pendingFormPayload.value) {
        return;
    }

    await executeFormSave(pendingFormPayload.value);
}

async function executeFormSave(payload) {
    saving.value = true;
    attendanceFieldErrors.value = {};
    attendanceFormError.value = '';

    const apiPayload = stripFormPayload(payload);

    try {
        if (formMode.value === 'edit' && editingRecord.value?.id) {
            await updateAttendance(editingRecord.value.id, apiPayload);
            toast.success('Attendance updated successfully.');
        } else {
            await markAttendance(apiPayload);
            toast.success('Attendance saved successfully.');
        }

        cancelFormSaveConfirm();
        closeForm();
        await reloadAll();
    } catch (error) {
        cancelFormSaveConfirm();
        const { fieldErrors, message } = extractValidationErrors(error);

        attendanceFieldErrors.value = fieldErrors;

        if (error.response?.status === 403) {
            attendanceFormError.value = 'You do not have permission for this action.';
        } else {
            attendanceFormError.value =
                message ??
                (Object.keys(fieldErrors).length > 0
                    ? 'Please check the highlighted fields and try again.'
                    : friendlyApiErrorMessage(error, 'Unable to save attendance.'));
        }
    } finally {
        saving.value = false;
    }
}

async function executeFormDelete(record) {
    if (!record?.id) {
        return;
    }

    saving.value = true;

    try {
        await deleteAttendance(record.id);
        cancelFormSaveConfirm();
        closeForm();
        toast.success('Mark cleared — day is default present.');
        await reloadAll();
    } catch {
        cancelFormSaveConfirm();
        toast.error('Unable to change attendance.');
    } finally {
        saving.value = false;
    }
}

watch(dayDate, (date) => {
    if (!date) {
        return;
    }

    filters.month = date.slice(0, 7);

    if (activeView.value === 'day') {
        pagination.currentPage = 1;
        reloadActiveView();
    }
});

watch(
    () => filters.month,
    (month) => {
        if (activeView.value === 'month' && month) {
            pagination.currentPage = 1;
            reloadActiveView();
        }
    },
);

async function applyVehicleQueryFromRoute() {
    const vehicleId = route.query.vehicle_id;

    if (!vehicleId || typeof vehicleId !== 'string') {
        return;
    }

    await filterByTruck(vehicleId);
}

onMounted(async () => {
    await loadLookups();
    await reloadActiveView();
    await applyVehicleQueryFromRoute();
});

watch(
    () => route.query.vehicle_id,
    async (vehicleId) => {
        if (!vehicleId || typeof vehicleId !== 'string') {
            if (detailFilters.vehicle_id) {
                clearTruckFilter();
            }

            return;
        }

        if (detailFilters.vehicle_id !== vehicleId) {
            await filterByTruck(vehicleId);
        }
    },
);
</script>
