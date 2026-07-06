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
                    <span v-if="payDaysModeLabel" class="badge bg-light text-dark border">
                        Pay days: {{ payDaysModeLabel }}
                    </span>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <button
                        v-if="canViewSettlement"
                        type="button"
                        class="btn btn-outline-success btn-sm"
                        :disabled="loading"
                        @click="downloadExcel"
                    >
                        <i class="bi bi-file-earmark-excel"></i>
                        Download Excel
                    </button>
                    <button
                        v-if="canManageWages && monthLock.status === 'open' && canLockSelectedMonth"
                        type="button"
                        class="btn btn-dark btn-sm"
                        :disabled="locking"
                        @click="handleLockMonth"
                    >
                        {{ locking ? 'Locking…' : 'Lock Month' }}
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
                        class="btn btn-outline-secondary btn-sm"
                        :disabled="unlocking"
                        @click="handleUnlockMonth"
                    >
                        {{ unlocking ? 'Unlocking…' : 'Unlock Month' }}
                    </button>
                </div>
            </div>
        </div>

        <div class="container-fluid">
            <div v-if="loading" class="text-center py-5 text-muted">Loading settlement sheet…</div>

            <div v-else-if="loadError" class="alert alert-danger">
                <div>{{ loadError }}</div>
                <button type="button" class="btn btn-outline-danger btn-sm mt-2" @click="loadSheet">
                    Retry
                </button>
            </div>

            <template v-else>
                <div
                    v-if="canViewSettlement && !canManageWages"
                    class="alert alert-light border py-2 mb-3"
                >
                    <strong>View only.</strong> You can review payroll and download Excel. Contact an admin to
                    lock the month, edit lines, or mark payments.
                </div>

                <div
                    v-if="monthLock.status === 'open' && canLockSelectedMonth"
                    class="alert alert-info py-2 mb-3"
                >
                    <strong>Step 1:</strong> Review attendance below, then click
                    <strong>Lock Month</strong> to freeze attendance and generate payroll lines.
                    <span v-if="!canManageWages" class="d-block mt-1 small mb-0">
                        You need wage management permission to lock the month.
                    </span>
                </div>

                <div
                    v-else-if="monthLock.status === 'open'"
                    class="alert alert-light border py-2 mb-3"
                >
                    <strong>Month still in progress.</strong>
                    Lock Month is available only after this month ends. Review attendance below and return
                    next month to lock payroll.
                </div>

                <div
                    v-else-if="monthLock.status === 'closed'"
                    class="alert alert-warning py-2 mb-3"
                >
                    <strong>Attendance locked.</strong>
                    <template v-if="canManageWages">
                        Review payroll, mark all paid when ready, or unlock to edit attendance again.
                    </template>
                    <template v-else>
                        Payroll is ready for review. Download Excel or contact an admin to process payments.
                    </template>
                </div>

                <div
                    v-else-if="monthLock.status === 'paid'"
                    class="alert alert-secondary py-2 mb-3"
                >
                    <strong>Month finalized.</strong> All payments are recorded and no further changes are allowed.
                </div>

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
                            Truck Attendance (P / A / S)
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
                                    <th title="Days a substitute worked on this driver's truck">Sub.P</th>
                                    <th title="Substitute earnings (paid on substitute driver's row)">Sub.Pay</th>
                                    <th>Gross</th>
                                    <th>Bonus</th>
                                    <th>Absent Ded.</th>
                                    <th>Base</th>
                                    <th>Shortage Ded.</th>
                                    <th>Adv. Bal.</th>
                                    <th>Adv. Ded.</th>
                                    <th>Diesel Ded.</th>
                                    <th>Net</th>
                                    <th>Rnd</th>
                                    <th>Remarks</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="payrollLines.length === 0">
                                    <td colspan="19" class="text-center text-muted py-4">
                                        <div>No payroll lines for {{ sheet.month_label || selectedMonth }}.</div>
                                        <div class="small mt-1">
                                            Assign primary drivers to trucks and mark attendance first, then lock the month.
                                        </div>
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
                                            v-model.number="line.bonus"
                                            v-no-scroll-change
                                            type="number"
                                            step="0.01"
                                            class="form-control form-control-sm settlement-input"
                                            :disabled="!canEditSheet || !line.monthly_wage_id"
                                            title="Bonus — added to net"
                                            @change="savePayrollLine(line)"
                                        />
                                    </td>
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
                                            :disabled="!canEditSheet || !line.monthly_wage_id"
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
                                            :disabled="!canEditSheet || !line.monthly_wage_id"
                                            @change="savePayrollLine(line)"
                                        />
                                    </td>
                                    <td>
                                        <input
                                            v-model.number="line.diesel_deduction"
                                            v-no-scroll-change
                                            type="number"
                                            step="0.01"
                                            class="form-control form-control-sm settlement-input"
                                            :disabled="!canEditSheet || !line.monthly_wage_id"
                                            title="Diesel paid separately — deducted from net"
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
                                                (Number(line.gross_earning) > 0 ||
                                                    Number(line.substitute_pay) > 0 ||
                                                    Number(line.net_salary) > 0)
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

        <SettlementActionConfirmModal
            :show="pendingAction !== null"
            :config="settlementConfirmConfig"
            :processing="settlementConfirmProcessing"
            @cancel="pendingAction = null"
            @confirm="confirmSettlementAction"
        />
    </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import SettlementActionConfirmModal from '../../components/attendance/SettlementActionConfirmModal.vue';
import {
    closeMonthWages,
    fetchSettlementSheet,
    markAllMonthlyWagesPaid,
    markMonthlyWagePaid,
    unlockMonthWages,
    settlementSheetExportUrl,
    updateSettlementLine,
    updateVehicleSettlementLine,
} from '../../api/attendances';
import { usePermissions } from '../../composables/usePermissions';
import { useAppToast } from '../../composables/useAppToast';
import { friendlyApiErrorMessage } from '../../utils/formErrors';
import { formatMonthYear, currentMonthValue } from '../../utils/datetime';

const route = useRoute();
const toast = useAppToast();
const { canInCompany } = usePermissions();

const canManageWages = computed(() => canInCompany('wages.manage'));
const canViewSettlement = computed(() => canInCompany('wages.view') || canManageWages.value);
const canEditSheet = computed(() => canManageWages.value && monthLock.value.status !== 'paid');
const canLockSelectedMonth = computed(() => {
    if (!selectedMonth.value) {
        return false;
    }

    return selectedMonth.value < currentMonth();
});

const loading = ref(true);
const loadError = ref('');
const pendingAction = ref(null);
const locking = ref(false);
const unlocking = ref(false);
const markingAll = ref(false);
const markingDriverId = ref(null);
const activeTab = ref('payroll');
const selectedMonth = ref(route.query.month ?? currentMonth());
const sheet = ref({});
const payrollLines = ref([]);
const vehicleMatrix = ref([]);

const monthLock = computed(() => sheet.value.month_lock ?? { status: 'open' });
const calendarDays = computed(() => sheet.value.calendar_days ?? 30);

const calendarDayNumbers = computed(() =>
    Array.from({ length: calendarDays.value }, (_, index) => index + 1),
);

const monthLockLabel = computed(() => {
    if (monthLock.value.status === 'paid') {
        return 'Finalized';
    }

    if (monthLock.value.status === 'closed') {
        return 'Attendance Locked';
    }

    return 'Open';
});

const payDaysModeLabel = computed(() => {
    const mode = sheet.value.payroll_pay_days_mode ?? 'calendar';

    return mode === 'fixed_30' ? 'Fixed 30 days' : 'Calendar days';
});

const unpaidCount = computed(
    () => payrollLines.value.filter((line) => line.payment_status !== 'paid').length,
);

const payrollDriverCount = computed(
    () => payrollLines.value.filter((line) => !line.is_substitute).length,
);

const monthLabel = computed(() => {
    if (sheet.value.month_label) {
        return sheet.value.month_label;
    }

    if (!selectedMonth.value) {
        return '—';
    }

    return formatMonthYear(selectedMonth.value);
});

const settlementConfirmProcessing = computed(() => {
    if (pendingAction.value === 'lock') {
        return locking.value;
    }

    if (pendingAction.value === 'markAllPaid') {
        return markingAll.value;
    }

    if (pendingAction.value === 'unlock') {
        return unlocking.value;
    }

    return false;
});

const settlementConfirmConfig = computed(() => {
    if (!pendingAction.value) {
        return null;
    }

    const baseStats = [
        { label: 'Pay days', value: payDaysModeLabel.value },
        { label: 'Drivers on payroll', value: String(payrollLines.value.length) },
    ];

    if (pendingAction.value === 'lock') {
        const stats = [
            ...baseStats,
            { label: 'Trucks in attendance', value: String(vehicleMatrix.value.length) },
        ];

        return {
            title: 'Confirm Lock Month',
            message: `Review ${monthLabel.value} before locking. This freezes attendance and generates payroll lines.`,
            banner: 'Attendance for this month will be frozen and payroll register lines will be created.',
            bannerIcon: 'bi-lock-fill',
            bannerTone: 'success',
            icon: 'bi-shield-lock',
            iconTone: 'info',
            monthLabel: monthLabel.value,
            stats,
            emptyNote:
                vehicleMatrix.value.length === 0 && payrollLines.value.length === 0
                    ? 'No attendance rows yet. Locking will still freeze the month and create payroll when drivers are assigned.'
                    : '',
            confirmLabel: 'Yes, Lock Month',
            confirmIcon: 'bi-lock-fill',
            confirmClass: 'btn-dark',
        };
    }

    if (pendingAction.value === 'markAllPaid') {
        return {
            title: 'Confirm Mark All Paid',
            message: `Mark all unpaid drivers as paid for ${monthLabel.value}.`,
            banner: 'This finalizes the month permanently. No further attendance or payment changes will be allowed.',
            bannerIcon: 'bi-cash-coin',
            bannerTone: 'success',
            icon: 'bi-question-circle',
            iconTone: 'info',
            monthLabel: monthLabel.value,
            stats: [
                ...baseStats,
                { label: 'Unpaid drivers', value: String(unpaidCount.value) },
            ],
            emptyNote: unpaidCount.value === 0 ? 'All drivers are already marked paid.' : '',
            confirmLabel: 'Yes, Mark All Paid',
            confirmIcon: 'bi-check-lg',
            confirmClass: 'btn-success',
        };
    }

    if (pendingAction.value === 'unlock') {
        return {
            title: 'Confirm Unlock Month',
            message: `Unlock ${monthLabel.value} to edit attendance and recalculate payroll.`,
            banner: 'Unlocking reopens attendance for this month. Lock again after your changes.',
            bannerIcon: 'bi-unlock',
            bannerTone: 'warning',
            icon: 'bi-unlock',
            iconTone: 'warning',
            monthLabel: monthLabel.value,
            stats: baseStats,
            emptyNote: '',
            confirmLabel: 'Yes, Unlock Month',
            confirmIcon: 'bi-unlock',
            confirmClass: 'btn-warning',
        };
    }

    return null;
});

function currentMonth() {
    return currentMonthValue();
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

    if (mark === 'S') {
        return 'bg-warning-subtle';
    }

    if (mark === 'N') {
        return 'bg-secondary-subtle';
    }

    return '';
}

function downloadExcel() {
    if (!selectedMonth.value) {
        return;
    }

    window.location.href = settlementSheetExportUrl(selectedMonth.value);
}

async function loadSheet() {
    if (!selectedMonth.value) {
        return;
    }

    loading.value = true;
    loadError.value = '';

    try {
        sheet.value = await fetchSettlementSheet(selectedMonth.value);
        payrollLines.value = (sheet.value.payroll_lines ?? []).map((line) => ({ ...line }));
        vehicleMatrix.value = (sheet.value.vehicle_matrix ?? []).map((row) => ({ ...row }));

        if (payrollLines.value.length === 0 && vehicleMatrix.value.length > 0 && activeTab.value === 'payroll') {
            activeTab.value = 'vehicles';
        }
    } catch (error) {
        sheet.value = {};
        payrollLines.value = [];
        vehicleMatrix.value = [];
        const message = friendlyApiErrorMessage(error, 'Unable to load settlement sheet.');
        loadError.value = message;
        toast.error(message);
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
            bonus: line.bonus,
            extra_deductions: line.extra_deductions,
            advance_deducted: line.advance_deducted,
            diesel_deduction: line.diesel_deduction,
            round_net: line.round_net,
            remarks: line.remarks,
        });

        line.net_salary = updated.net_salary;
        line.gross_earning = updated.gross_earning;
        line.total_advance = updated.total_advance;
        line.advance_deducted = updated.advance_deducted;
        line.diesel_deduction = updated.diesel_deduction;
        line.bonus = updated.bonus;
        line.extra_deductions =
            Number(updated.pf ?? 0) + Number(updated.esi ?? 0) + Number(updated.other_deduction ?? 0);
    } catch (error) {
        toast.error(error.response?.data?.message ?? 'Could not save line.');
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
        toast.error(error.response?.data?.message ?? 'Could not save truck line.');
    }
}

async function refreshSheetForAction() {
    if (!selectedMonth.value) {
        return false;
    }

    await loadSheet();

    return true;
}

function handleLockMonth() {
    if (!selectedMonth.value) {
        return;
    }

    if (!canLockSelectedMonth.value || monthLock.value.status !== 'open') {
        toast.error('Only completed past months can be locked.');

        return;
    }

    pendingAction.value = 'lock';
}

async function handleMarkAllPaid() {
    if (!selectedMonth.value) {
        return;
    }

    if (!(await refreshSheetForAction())) {
        return;
    }

    if (monthLock.value.status === 'paid') {
        toast.error('This month is already finalized.');

        return;
    }

    if (monthLock.value.status !== 'closed') {
        toast.error('Lock the month before marking payments.');

        return;
    }

    if (unpaidCount.value === 0) {
        toast.error('All drivers are already marked paid.');

        return;
    }

    pendingAction.value = 'markAllPaid';
}

async function handleUnlockMonth() {
    if (!selectedMonth.value) {
        return;
    }

    if (!(await refreshSheetForAction())) {
        return;
    }

    if (monthLock.value.status === 'paid') {
        toast.error('This month is finalized and cannot be unlocked.');

        return;
    }

    if (monthLock.value.status !== 'closed') {
        toast.error('This month is not locked.');

        return;
    }

    pendingAction.value = 'unlock';
}

async function confirmSettlementAction() {
    if (!selectedMonth.value || !pendingAction.value) {
        return;
    }

    const action = pendingAction.value;

    if (action === 'lock') {
        locking.value = true;
    } else if (action === 'markAllPaid') {
        markingAll.value = true;
    } else if (action === 'unlock') {
        unlocking.value = true;
    }

    try {
        if (action === 'lock') {
            await closeMonthWages(selectedMonth.value);
            activeTab.value = 'payroll';
            toast.success('Month locked. Review payroll and truck sheets.');
        } else if (action === 'markAllPaid') {
            await markAllMonthlyWagesPaid(selectedMonth.value, {});
            toast.success('All drivers marked paid. This month is now finalized.');
        } else if (action === 'unlock') {
            await unlockMonthWages(selectedMonth.value);
            toast.success('Month unlocked. Attendance can be edited again.');
        }

        pendingAction.value = null;
        await loadSheet();
    } catch (error) {
        const fallback = {
            lock: 'Unable to lock month.',
            markAllPaid: 'Unable to mark all paid.',
            unlock: 'Unable to unlock month.',
        }[action];

        pendingAction.value = null;
        toast.error(friendlyApiErrorMessage(error, fallback));
        await loadSheet();
    } finally {
        locking.value = false;
        markingAll.value = false;
        unlocking.value = false;
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
        toast.success(`${line.name} marked paid.`);
    } catch (error) {
        toast.error(error.response?.data?.message ?? 'Unable to mark driver paid.');
    } finally {
        markingDriverId.value = null;
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
