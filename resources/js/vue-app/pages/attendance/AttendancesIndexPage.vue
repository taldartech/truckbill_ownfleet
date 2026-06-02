<template>
    <div class="container mt-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <h3 class="mb-0">Driver Attendance &amp; Wages</h3>
            <div v-if="canManageAttendance" class="d-flex gap-2">
                <AppButton icon="bi bi-plus-lg" label="Mark Attendance" @click="openCreate" />
            </div>
        </div>

        <div v-if="flash.success" class="alert alert-success">{{ flash.success }}</div>
        <div v-if="flash.error" class="alert alert-danger">{{ flash.error }}</div>

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
            </div>
            <div class="card-body">
                <div v-if="overviewLoading" class="text-center py-4 text-muted">Loading overview…</div>
                <AttendanceMonthGrid
                    v-else
                    :month="filters.month"
                    :overview="monthOverview"
                    :can-edit="canManageAttendance"
                    @select="openEditFromGrid"
                />
            </div>
        </div>

        <div class="card">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                <h3 class="card-title mb-0">Day-wise Details</h3>
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
                                {{ driver.name }}
                            </option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label" for="detail_status">Status</label>
                        <select id="detail_status" v-model="detailFilters.status" class="form-select">
                            <option value="">All</option>
                            <option value="PRESENT">Present</option>
                            <option value="ABSENT">Absent</option>
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
                                    No attendance records found.
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
                                        <AppCrudActionButton
                                            v-if="
                                                canManageWages &&
                                                isPayable(row) &&
                                                row.wage_payment_status !== 'paid'
                                            "
                                            action="markPaid"
                                            @click="handleMarkPaid(row)"
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
            @close="closeForm"
            @submit="handleSubmit"
        />
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { listAllDrivers } from '../../api/drivers';
import {
    fetchMonthOverview,
    getAttendance,
    listAttendancesPaginated,
    markAttendance,
    markWagePaid,
    updateAttendance,
} from '../../api/attendances';
import { listAllVehicles } from '../../api/vehicles';
import AttendanceFormModal from '../../components/attendance/AttendanceFormModal.vue';
import AttendanceMonthGrid from '../../components/attendance/AttendanceMonthGrid.vue';
import AppButton from '../../components/ui/AppButton.vue';
import AppCrudActionButton from '../../components/ui/AppCrudActionButton.vue';
import AppTableActions from '../../components/ui/AppTableActions.vue';
import { usePermissions } from '../../composables/usePermissions';

const { canInCompany } = usePermissions();

const canManageAttendance = computed(() => canInCompany('attendances.manage'));
const canManageWages = computed(() => canInCompany('wages.manage'));
const actionColspan = computed(() => (canManageAttendance.value || canManageWages.value ? 8 : 7));

const attendances = ref([]);
const monthOverview = ref({
    summary: { marked: 0, present: 0, absent: 0, paid: 0, unpaid: 0 },
    rows: [],
});
const vehicles = ref([]);
const drivers = ref([]);
const overviewLoading = ref(true);
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
    if (row.status === 'PRESENT') {
        return 'Present';
    }

    if (hasSubstitute(row)) {
        return 'Absent + substitute';
    }

    return 'Absent';
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
        const [vehicleRows, driverRows] = await Promise.all([listAllVehicles(), listAllDrivers()]);

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
    await Promise.all([loadMonthOverview(), loadAttendances()]);
}

function openCreate() {
    formMode.value = 'create';
    editingRecord.value = null;
    showForm.value = true;
}

function openEdit(row) {
    formMode.value = 'edit';
    editingRecord.value = { ...row };
    showForm.value = true;
}

async function openEditFromGrid(cell) {
    if (!cell?.id) {
        return;
    }

    try {
        editingRecord.value = await getAttendance(cell.id);
        formMode.value = 'edit';
        showForm.value = true;
    } catch {
        showToast('Could not load attendance for editing.', 'error');
    }
}

function closeForm() {
    showForm.value = false;
    editingRecord.value = null;
}

async function handleSubmit(payload) {
    saving.value = true;

    try {
        if (formMode.value === 'edit' && editingRecord.value?.id) {
            await updateAttendance(editingRecord.value.id, payload);
            showToast('Attendance updated successfully.');
        } else {
            await markAttendance(payload);
            showToast('Attendance saved successfully.');
        }

        closeForm();
        await reloadAll();
    } catch (error) {
        if (error.response?.status === 422) {
            showToast('Please check the form fields and try again.', 'error');
        } else if (error.response?.status === 403) {
            showToast('You do not have permission for this action.', 'error');
        } else {
            showToast('Unable to save attendance.', 'error');
        }
    } finally {
        saving.value = false;
    }
}

async function handleMarkPaid(row) {
    const notes = window.prompt('Wage transfer notes (optional):', row.wage_transfer_notes ?? '');

    if (notes === null) {
        return;
    }

    try {
        await markWagePaid(row.id, { wage_transfer_notes: notes || null });
        showToast('Wage marked as paid.');
        await reloadAll();
    } catch {
        showToast('Unable to mark wage as paid.', 'error');
    }
}

onMounted(async () => {
    await loadLookups();
    await reloadAll();
});
</script>
