<template>
    <div class="monthly-settlement-page">
        <div class="settlement-toolbar sticky-top bg-white border-bottom py-3 mb-3">
            <div class="container-fluid d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div class="d-flex flex-wrap align-items-center gap-3">
                    <RouterLink :to="{ name: 'attendance.index' }" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-arrow-left"></i> Attendance
                    </RouterLink>
                    <h3 class="mb-0">Monthly Wage Settlement</h3>
                    <input
                        v-model="selectedMonth"
                        type="month"
                        class="form-control form-control-sm"
                        style="width: 10rem"
                        @change="loadSheet"
                    />
                    <span v-if="sheet.month_label" class="text-muted">{{ sheet.month_label }}</span>
                    <span
                        v-if="monthLock.status"
                        class="badge"
                        :class="monthLock.status === 'paid' ? 'bg-secondary' : 'bg-warning text-dark'"
                    >
                        {{ monthLockLabel }}
                    </span>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <button
                        type="button"
                        class="btn btn-outline-success btn-sm"
                        :disabled="loading"
                        @click="downloadExcel"
                    >
                        <i class="bi bi-file-earmark-excel"></i>
                        Download Excel
                    </button>
                    <button
                        v-if="canManageWages && monthLock.status === 'open'"
                        type="button"
                        class="btn btn-outline-primary btn-sm"
                        :disabled="closing"
                        @click="handleCloseMonth"
                    >
                        {{ closing ? 'Closing…' : 'Close Month' }}
                    </button>
                    <button
                        v-if="canManageWages && monthLock.status === 'closed' && unpaidCount > 0"
                        type="button"
                        class="btn btn-outline-success btn-sm"
                        :disabled="markingAll"
                        @click="handleMarkAllPaid"
                    >
                        Mark All Paid
                    </button>
                    <button
                        v-if="canManageWages && monthLock.status === 'closed'"
                        type="button"
                        class="btn btn-dark btn-sm"
                        :disabled="locking"
                        @click="handleLockMonth"
                    >
                        Lock Month
                    </button>
                    <button
                        v-if="canManageWages && monthLock.status === 'closed'"
                        type="button"
                        class="btn btn-outline-secondary btn-sm"
                        :disabled="reopening"
                        @click="handleReopenMonth"
                    >
                        Reopen Month
                    </button>
                </div>
            </div>
        </div>

        <div class="container-fluid">
            <div v-if="flash.success" class="alert alert-success py-2">{{ flash.success }}</div>
            <div v-if="flash.error" class="alert alert-danger py-2">{{ flash.error }}</div>

            <div v-if="loading" class="text-center py-5 text-muted">Loading settlement sheet…</div>

            <template v-else>
                <ul class="nav nav-tabs mb-3">
                    <li class="nav-item">
                        <button
                            type="button"
                            class="nav-link"
                            :class="{ active: activeTab === 'payroll' }"
                            @click="activeTab = 'payroll'"
                        >
                            Payroll Register
                        </button>
                    </li>
                    <li class="nav-item">
                        <button
                            type="button"
                            class="nav-link"
                            :class="{ active: activeTab === 'vehicles' }"
                            @click="activeTab = 'vehicles'"
                        >
                            Truck Attendance (P / A)
                        </button>
                    </li>
                </ul>

                <div v-show="activeTab === 'payroll'" class="settlement-scroll-wrap">
                    <div class="table-responsive settlement-table-wrap">
                        <table class="table table-bordered table-sm settlement-table mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>S.No.</th>
                                    <th>Name</th>
                                    <th>Pay Days</th>
                                    <th>P</th>
                                    <th>A</th>
                                    <th>Sub.P</th>
                                    <th>Sub.Pay</th>
                                    <th>Gross</th>
                                    <th>Absent Ded.</th>
                                    <th>Base</th>
                                    <th>Extra Ded.</th>
                                    <th>Adv. Bal.</th>
                                    <th>Adv. Ded.</th>
                                    <th>Net</th>
                                    <th>Rnd</th>
                                    <th>Remarks</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="payrollLines.length === 0">
                                    <td colspan="17" class="text-center text-muted py-4">
                                        Close the month to generate payroll lines.
                                    </td>
                                </tr>
                                <tr
                                    v-for="line in payrollLines"
                                    :key="line.driver_id"
                                    :class="{ 'table-secondary': line.is_substitute }"
                                >
                                    <td>{{ line.serial }}</td>
                                    <td>
                                        {{ line.name }}
                                        <span
                                            v-if="line.is_substitute"
                                            class="badge bg-info text-dark ms-1"
                                        >
                                            Substitute
                                        </span>
                                    </td>
                                    <td>{{ line.pay_days }}</td>
                                    <td>{{ line.present_days }}</td>
                                    <td>{{ line.absent_days }}</td>
                                    <td>{{ line.substitute_days ?? 0 }}</td>
                                    <td class="text-end">{{ formatMoney(line.substitute_pay) }}</td>
                                    <td class="text-end">{{ formatMoney(line.gross_earning) }}</td>
                                    <td>
                                        <input
                                            v-model.number="line.absent_deduction"
                                            v-no-scroll-change
                                            type="number"
                                            step="0.01"
                                            class="form-control form-control-sm settlement-input"
                                            :disabled="!canEditSheet || !line.monthly_wage_id"
                                            @change="savePayrollLine(line)"
                                        />
                                    </td>
                                    <td>
                                        <input
                                            v-model.number="line.base_salary"
                                            v-no-scroll-change
                                            type="number"
                                            step="0.01"
                                            class="form-control form-control-sm settlement-input"
                                            :disabled="!canEditSheet || !line.monthly_wage_id"
                                            @change="savePayrollLine(line)"
                                        />
                                    </td>
                                    <td>
                                        <input
                                            v-model.number="line.extra_deductions"
                                            v-no-scroll-change
                                            type="number"
                                            step="0.01"
                                            class="form-control form-control-sm settlement-input"
                                            :disabled="!canEditSheet || !line.monthly_wage_id || line.is_substitute"
                                            title="PF + ESI + other deductions"
                                            @change="savePayrollLine(line)"
                                        />
                                    </td>
                                    <td class="text-end text-muted">{{ formatMoney(line.total_advance) }}</td>
                                    <td>
                                        <input
                                            v-model.number="line.advance_deducted"
                                            v-no-scroll-change
                                            type="number"
                                            step="0.01"
                                            class="form-control form-control-sm settlement-input"
                                            :disabled="!canEditSheet || !line.monthly_wage_id || line.is_substitute"
                                            @change="savePayrollLine(line)"
                                        />
                                    </td>
                                    <td class="text-end fw-semibold">{{ formatMoney(line.net_salary) }}</td>
                                    <td class="text-center">
                                        <input
                                            v-model="line.round_net"
                                            type="checkbox"
                                            :disabled="!canEditSheet || !line.monthly_wage_id"
                                            @change="savePayrollLine(line)"
                                        />
                                    </td>
                                    <td>
                                        <input
                                            v-model="line.remarks"
                                            type="text"
                                            class="form-control form-control-sm"
                                            style="min-width: 8rem"
                                            :disabled="!canEditSheet || !line.monthly_wage_id"
                                            @change="savePayrollLine(line)"
                                        />
                                    </td>
                                    <td class="text-nowrap">
                                        <span
                                            class="badge"
                                            :class="
                                                line.payment_status === 'paid'
                                                    ? 'bg-success'
                                                    : 'bg-warning text-dark'
                                            "
                                        >
                                            {{ line.payment_status === 'paid' ? 'Paid' : 'Unpaid' }}
                                        </span>
                                        <button
                                            v-if="
                                                canManageWages &&
                                                monthLock.status === 'closed' &&
                                                line.monthly_wage_id &&
                                                line.payment_status !== 'paid' &&
                                                Number(line.gross_earning) > 0
                                            "
                                            type="button"
                                            class="btn btn-success btn-sm ms-1"
                                            :disabled="markingDriverId === line.monthly_wage_id"
                                            @click="handleMarkDriverPaid(line)"
                                        >
                                            {{
                                                markingDriverId === line.monthly_wage_id
                                                    ? '…'
                                                    : 'Mark Paid'
                                            }}
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div v-show="activeTab === 'vehicles'" class="settlement-scroll-wrap">
                    <div class="table-responsive settlement-table-wrap">
                        <table class="table table-bordered table-sm settlement-table mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="sticky-col">S.No.</th>
                                    <th class="sticky-col-2">Truck No.</th>
                                    <th class="sticky-col-3">Driver</th>
                                    <th v-for="day in calendarDayNumbers" :key="day" class="day-col text-center">
                                        {{ day }}
                                    </th>
                                    <th>Salary</th>
                                    <th>Remarks</th>
                                    <th>Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="vehicleMatrix.length === 0">
                                    <td
                                        :colspan="calendarDayNumbers.length + 6"
                                        class="text-center text-muted py-4"
                                    >
                                        No truck attendance for this month. Assign primary drivers on trucks
                                        first, then close the month again.
                                    </td>
                                </tr>
                                <tr
                                    v-for="row in vehicleMatrix"
                                    :key="row.row_key"
                                    :class="{ 'table-secondary': row.is_substitute }"
                                >
                                    <td class="sticky-col">{{ row.serial }}</td>
                                    <td class="sticky-col-2">{{ row.vehicle_label }}</td>
                                    <td class="sticky-col-3">{{ row.driver_name }}</td>
                                    <td
                                        v-for="day in calendarDayNumbers"
                                        :key="`${row.row_key}-${day}`"
                                        class="day-col text-center"
                                        :class="dayCellClass(row.days[day])"
                                    >
                                        {{ row.days[day] || '·' }}
                                    </td>
                                    <td>
                                        <input
                                            v-model.number="row.salary"
                                            v-no-scroll-change
                                            type="number"
                                            step="0.01"
                                            class="form-control form-control-sm settlement-input"
                                            :disabled="!canEditSheet"
                                            @change="saveVehicleLine(row)"
                                        />
                                    </td>
                                    <td>
                                        <input
                                            v-model="row.remarks"
                                            type="text"
                                            class="form-control form-control-sm"
                                            :disabled="!canEditSheet"
                                            @change="saveVehicleLine(row)"
                                        />
                                    </td>
                                    <td>
                                        <input
                                            v-model.number="row.total"
                                            v-no-scroll-change
                                            type="number"
                                            step="0.01"
                                            class="form-control form-control-sm settlement-input"
                                            :disabled="!canEditSheet"
                                            @change="saveVehicleLine(row)"
                                        />
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </template>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import {
    closeMonthWages,
    fetchSettlementSheet,
    lockMonthWages,
    markAllMonthlyWagesPaid,
    markMonthlyWagePaid,
    reopenMonthWages,
    settlementSheetExportUrl,
    updateSettlementLine,
    updateVehicleSettlementLine,
} from '../../api/attendances';
import { usePermissions } from '../../composables/usePermissions';

const route = useRoute();
const { canInCompany } = usePermissions();

const canManageWages = computed(() => canInCompany('wages.manage'));
const canEditSheet = computed(() => canManageWages.value && monthLock.value.status !== 'paid');

const loading = ref(true);
const closing = ref(false);
const locking = ref(false);
const reopening = ref(false);
const markingAll = ref(false);
const markingDriverId = ref(null);
const activeTab = ref('payroll');
const selectedMonth = ref(route.query.month ?? currentMonth());
const sheet = ref({});
const payrollLines = ref([]);
const vehicleMatrix = ref([]);
const flash = reactive({ success: '', error: '' });

const monthLock = computed(() => sheet.value.month_lock ?? { status: 'open' });
const calendarDays = computed(() => sheet.value.calendar_days ?? 30);

const calendarDayNumbers = computed(() =>
    Array.from({ length: calendarDays.value }, (_, index) => index + 1),
);

const monthLockLabel = computed(() => {
    if (monthLock.value.status === 'paid') {
        return 'Locked';
    }

    if (monthLock.value.status === 'closed') {
        return 'Closed';
    }

    return 'Open';
});

const unpaidCount = computed(
    () => payrollLines.value.filter((line) => line.payment_status !== 'paid').length,
);

function currentMonth() {
    const now = new Date();

    return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`;
}

function formatMoney(value) {
    const amount = Number(value ?? 0);

    return Number.isNaN(amount) ? '0.00' : amount.toFixed(2);
}

function dayCellClass(mark) {
    if (mark === 'P') {
        return 'bg-success-subtle';
    }

    if (mark === 'A') {
        return 'bg-danger-subtle';
    }

    if (mark === 'N') {
        return 'bg-secondary-subtle';
    }

    return '';
}

function showToast(message, type = 'success') {
    flash.success = type === 'success' ? message : '';
    flash.error = type === 'error' ? message : '';
}

function downloadExcel() {
    if (!selectedMonth.value) {
        return;
    }

    window.location.href = settlementSheetExportUrl(selectedMonth.value);
}

async function loadSheet() {
    loading.value = true;
    flash.error = '';

    try {
        sheet.value = await fetchSettlementSheet(selectedMonth.value);
        payrollLines.value = (sheet.value.payroll_lines ?? []).map((line) => ({ ...line }));
        vehicleMatrix.value = (sheet.value.vehicle_matrix ?? []).map((row) => ({ ...row }));
    } catch {
        sheet.value = {};
        payrollLines.value = [];
        vehicleMatrix.value = [];
        showToast('Unable to load settlement sheet.', 'error');
    } finally {
        loading.value = false;
    }
}

async function savePayrollLine(line) {
    if (!line.monthly_wage_id || !canEditSheet.value) {
        return;
    }

    try {
        const updated = await updateSettlementLine(line.monthly_wage_id, {
            absent_deduction: line.absent_deduction,
            base_salary: line.base_salary,
            extra_deductions: line.extra_deductions,
            advance_deducted: line.advance_deducted,
            round_net: line.round_net,
            remarks: line.remarks,
        });

        line.net_salary = updated.net_salary;
        line.gross_earning = updated.gross_earning;
        line.total_advance = updated.total_advance;
        line.advance_deducted = updated.advance_deducted;
        line.extra_deductions =
            Number(updated.pf ?? 0) + Number(updated.esi ?? 0) + Number(updated.other_deduction ?? 0);
    } catch (error) {
        showToast(error.response?.data?.message ?? 'Could not save line.', 'error');
    }
}

async function saveVehicleLine(row) {
    if (!canEditSheet.value) {
        return;
    }

    try {
        await updateVehicleSettlementLine({
            month: selectedMonth.value,
            vehicle_id: row.vehicle_id,
            row_key: row.row_key,
            remarks: row.remarks,
            salary: row.salary,
            total: row.total,
        });
    } catch (error) {
        showToast(error.response?.data?.message ?? 'Could not save truck line.', 'error');
    }
}

async function handleCloseMonth() {
    closing.value = true;

    try {
        await closeMonthWages(selectedMonth.value);
        await loadSheet();
        showToast('Month closed. Review payroll and truck sheets.');
    } catch {
        showToast('Unable to close month.', 'error');
    } finally {
        closing.value = false;
    }
}

async function handleMarkAllPaid() {
    markingAll.value = true;

    try {
        await markAllMonthlyWagesPaid(selectedMonth.value, {});
        await loadSheet();
        showToast('All drivers marked paid.');
    } catch (error) {
        showToast(error.response?.data?.message ?? 'Unable to mark all paid.', 'error');
    } finally {
        markingAll.value = false;
    }
}

async function handleMarkDriverPaid(line) {
    if (!line.monthly_wage_id) {
        return;
    }

    const notes = window.prompt('Payment notes (optional):', line.payment_notes ?? '') ?? null;

    if (notes === null) {
        return;
    }

    markingDriverId.value = line.monthly_wage_id;

    try {
        const updated = await markMonthlyWagePaid(line.monthly_wage_id, {
            payment_notes: notes.trim() || undefined,
        });

        line.payment_status = updated.payment_status ?? 'paid';
        line.payment_notes = updated.payment_notes ?? notes;
        showToast(`${line.name} marked paid.`);
    } catch (error) {
        showToast(error.response?.data?.message ?? 'Unable to mark driver paid.', 'error');
    } finally {
        markingDriverId.value = null;
    }
}

async function handleLockMonth() {
    if (!window.confirm('Lock this month? Attendance exceptions cannot be changed after lock.')) {
        return;
    }

    locking.value = true;

    try {
        await lockMonthWages(selectedMonth.value);
        await loadSheet();
        showToast('Month locked.');
    } catch (error) {
        showToast(error.response?.data?.message ?? 'Unable to lock month.', 'error');
    } finally {
        locking.value = false;
    }
}

async function handleReopenMonth() {
    if (!window.confirm('Reopen month for attendance edits?')) {
        return;
    }

    reopening.value = true;

    try {
        await reopenMonthWages(selectedMonth.value);
        await loadSheet();
        showToast('Month reopened.');
    } catch (error) {
        showToast(error.response?.data?.message ?? 'Unable to reopen.', 'error');
    } finally {
        reopening.value = false;
    }
}

watch(
    () => route.query.month,
    (month) => {
        if (month && month !== selectedMonth.value) {
            selectedMonth.value = month;
            loadSheet();
        }
    },
);

onMounted(loadSheet);
</script>

<style scoped>
.monthly-settlement-page {
    margin: -1rem -0.75rem 0;
    min-height: calc(100vh - 4rem);
}

.settlement-toolbar {
    z-index: 20;
    padding-left: 1rem;
    padding-right: 1rem;
}

.settlement-scroll-wrap {
    overflow: auto;
    max-height: calc(100vh - 11rem);
}

.settlement-table-wrap {
    min-width: 100%;
}

.settlement-table {
    font-size: 0.75rem;
    white-space: nowrap;
}

.settlement-table th,
.settlement-table td {
    vertical-align: middle;
}

.settlement-input {
    width: 5.5rem;
    min-width: 4.5rem;
}

.day-col {
    min-width: 1.6rem;
    padding: 0.15rem !important;
}

.sticky-col,
.sticky-col-2,
.sticky-col-3 {
    position: sticky;
    left: 0;
    background: #fff;
    z-index: 2;
}

.sticky-col-2 {
    left: 2.5rem;
}

.sticky-col-3 {
    left: 7.5rem;
}

thead .sticky-col,
thead .sticky-col-2,
thead .sticky-col-3 {
    background: #f8f9fa;
}
</style>
