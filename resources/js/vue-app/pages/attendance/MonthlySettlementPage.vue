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
                    <button
                        v-if="canManageWages && monthLock.status === 'paid'"
                        type="button"
                        class="btn btn-outline-secondary btn-sm"
                        :disabled="unlocking"
                        @click="handleUnlockMonth"
                    >
                        Unlock Month
                    </button>
                </div>
            </div>
        </div>

        <div class="container-fluid">
            <div v-if="flash.success" class="alert alert-success py-2">{{ flash.success }}</div>
            <div v-if="flash.error" class="alert alert-danger py-2">{{ flash.error }}</div>

            <div v-if="loading" class="text-center py-5 text-muted">Loading settlement sheet…</div>

            <div v-else-if="loadError" class="alert alert-danger">
                <div>{{ loadError }}</div>
                <button type="button" class="btn btn-outline-danger btn-sm mt-2" @click="loadSheet">
                    Retry
                </button>
            </div>

            <template v-else>
                <div
                    v-if="monthLock.status === 'open'"
                    class="alert alert-info py-2 mb-3"
                >
                    <strong>Step 1:</strong> Review attendance below, then click
                    <strong>Close Month</strong> to generate payroll lines.
                    <span v-if="!canManageWages" class="d-block mt-1 small mb-0">
                        You need wage management permission to close the month.
                    </span>
                    <button
                        v-else
                        type="button"
                        class="btn btn-primary btn-sm ms-2"
                        :disabled="closing"
                        @click="handleCloseMonth"
                    >
                        {{ closing ? 'Closing…' : 'Close Month' }}
                    </button>
                </div>

                <div
                    v-else-if="monthLock.status === 'closed'"
                    class="alert alert-warning py-2 mb-3"
                >
                    <strong>Month closed.</strong> Review payroll, mark drivers paid, then lock the month.
                </div>

                <div
                    v-else-if="monthLock.status === 'paid'"
                    class="alert alert-secondary py-2 mb-3"
                >
                    <strong>Month locked.</strong> Attendance is frozen for this period. Unlock the month to
                    adjust payments, or reopen it for attendance edits.
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
                                            Assign primary drivers to trucks and mark attendance first.
                                        </div>
                                        <button
                                            v-if="canManageWages && monthLock.status === 'open'"
                                            type="button"
                                            class="btn btn-primary btn-sm mt-2"
                                            :disabled="closing"
                                            @click="handleCloseMonth"
                                        >
                                            {{ closing ? 'Closing…' : 'Close Month' }}
                                        </button>
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
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import SettlementActionConfirmModal from '../../components/attendance/SettlementActionConfirmModal.vue';
import {
    closeMonthWages,
    fetchSettlementSheet,
    lockMonthWages,
    markAllMonthlyWagesPaid,
    markMonthlyWagePaid,
    reopenMonthWages,
    unlockMonthWages,
    settlementSheetExportUrl,
    updateSettlementLine,
    updateVehicleSettlementLine,
} from '../../api/attendances';
import { usePermissions } from '../../composables/usePermissions';
import { friendlyApiErrorMessage } from '../../utils/formErrors';

const route = useRoute();
const { canInCompany } = usePermissions();

const canManageWages = computed(() => canInCompany('wages.manage'));
const canEditSheet = computed(() => canManageWages.value && monthLock.value.status !== 'paid');

const loading = ref(true);
const loadError = ref('');
const closing = ref(false);
const pendingAction = ref(null);
const locking = ref(false);
const reopening = ref(false);
const unlocking = ref(false);
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

    const date = new Date(`${selectedMonth.value}-01T12:00:00`);

    if (Number.isNaN(date.getTime())) {
        return selectedMonth.value;
    }

    return date.toLocaleDateString('en-IN', {
        month: 'long',
        year: 'numeric',
    });
});

const settlementConfirmProcessing = computed(() => {
    if (pendingAction.value === 'close') {
        return closing.value;
    }

    if (pendingAction.value === 'lock') {
        return locking.value;
    }

    if (pendingAction.value === 'reopen') {
        return reopening.value;
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

    if (pendingAction.value === 'close') {
        const stats = [
            ...baseStats,
            { label: 'Trucks in attendance', value: String(vehicleMatrix.value.length) },
        ];

        return {
            title: 'Confirm Close Month',
            message: `Review ${monthLabel.value} before closing. This freezes attendance and generates payroll lines.`,
            banner: 'Attendance for this month will be frozen and payroll register lines will be created.',
            bannerIcon: 'bi-lock',
            bannerTone: 'success',
            icon: 'bi-question-circle',
            iconTone: 'info',
            monthLabel: monthLabel.value,
            stats,
            emptyNote:
                vehicleMatrix.value.length === 0 && payrollLines.value.length === 0
                    ? 'No attendance rows yet. Closing will still lock the month and create payroll when drivers are assigned.'
                    : '',
            confirmLabel: 'Yes, Close Month',
            confirmIcon: 'bi-check-lg',
            confirmClass: 'btn-success',
        };
    }

    if (pendingAction.value === 'markAllPaid') {
        return {
            title: 'Confirm Mark All Paid',
            message: `Mark all unpaid drivers as paid for ${monthLabel.value}.`,
            banner: 'This records payment for every unpaid driver and locks the month automatically.',
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

    if (pendingAction.value === 'lock') {
        return {
            title: 'Confirm Lock Month',
            message: `Lock ${monthLabel.value} after payroll review and payment.`,
            banner: 'Attendance exceptions cannot be changed after the month is locked.',
            bannerIcon: 'bi-lock-fill',
            bannerTone: 'warning',
            icon: 'bi-shield-lock',
            iconTone: 'warning',
            monthLabel: monthLabel.value,
            stats: [
                ...baseStats,
                { label: 'Unpaid drivers', value: String(unpaidCount.value) },
            ],
            emptyNote: unpaidCount.value > 0
                ? 'Some drivers are still unpaid. You can lock anyway, but review payroll first.'
                : '',
            confirmLabel: 'Yes, Lock Month',
            confirmIcon: 'bi-lock-fill',
            confirmClass: 'btn-dark',
        };
    }

    if (pendingAction.value === 'unlock') {
        return {
            title: 'Confirm Unlock Month',
            message: `Unlock ${monthLabel.value} to adjust payroll before locking again.`,
            banner: 'Unlocking returns the month to closed status so payments can still be edited.',
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

    if (pendingAction.value === 'reopen') {
        return {
            title: 'Confirm Reopen Month',
            message: `Reopen ${monthLabel.value} for attendance edits and payroll recalculation.`,
            banner: 'Reopening allows attendance exceptions to be changed again before the next close.',
            bannerIcon: 'bi-unlock',
            bannerTone: 'warning',
            icon: 'bi-arrow-counterclockwise',
            iconTone: 'warning',
            monthLabel: monthLabel.value,
            stats: baseStats,
            emptyNote: '',
            confirmLabel: 'Yes, Reopen Month',
            confirmIcon: 'bi-arrow-counterclockwise',
            confirmClass: 'btn-warning',
        };
    }

    return null;
});

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

    if (mark === 'S') {
        return 'bg-warning-subtle';
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
    if (!selectedMonth.value) {
        return;
    }

    loading.value = true;
    loadError.value = '';
    flash.error = '';

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
        showToast(message, 'error');
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

async function refreshSheetForAction() {
    if (!selectedMonth.value) {
        return false;
    }

    await loadSheet();

    return true;
}

function handleCloseMonth() {
    if (!selectedMonth.value) {
        return;
    }

    pendingAction.value = 'close';
}

async function handleMarkAllPaid() {
    if (!selectedMonth.value) {
        return;
    }

    if (!(await refreshSheetForAction())) {
        return;
    }

    if (monthLock.value.status === 'paid') {
        showToast('This month is already locked.', 'error');

        return;
    }

    if (unpaidCount.value === 0) {
        showToast('All drivers are already marked paid.', 'error');

        return;
    }

    pendingAction.value = 'markAllPaid';
}

async function handleLockMonth() {
    if (!selectedMonth.value) {
        return;
    }

    if (!(await refreshSheetForAction())) {
        return;
    }

    if (monthLock.value.status === 'paid') {
        showToast('This month is already locked.', 'error');

        return;
    }

    if (monthLock.value.status !== 'closed') {
        showToast('Close the month before locking.', 'error');

        return;
    }

    pendingAction.value = 'lock';
}

async function handleReopenMonth() {
    if (!selectedMonth.value) {
        return;
    }

    if (!(await refreshSheetForAction())) {
        return;
    }

    if (monthLock.value.status === 'paid') {
        showToast('Unlock the month before reopening attendance.', 'error');

        return;
    }

    if (monthLock.value.status !== 'closed') {
        showToast('This month is not closed.', 'error');

        return;
    }

    pendingAction.value = 'reopen';
}

async function handleUnlockMonth() {
    if (!selectedMonth.value) {
        return;
    }

    if (!(await refreshSheetForAction())) {
        return;
    }

    if (monthLock.value.status !== 'paid') {
        showToast('This month is not locked.', 'error');

        return;
    }

    pendingAction.value = 'unlock';
}

async function confirmSettlementAction() {
    if (!selectedMonth.value || !pendingAction.value) {
        return;
    }

    const action = pendingAction.value;

    if (action === 'close') {
        closing.value = true;
    } else if (action === 'lock') {
        locking.value = true;
    } else if (action === 'reopen') {
        reopening.value = true;
    } else if (action === 'markAllPaid') {
        markingAll.value = true;
    } else if (action === 'unlock') {
        unlocking.value = true;
    }

    try {
        if (action === 'close') {
            await closeMonthWages(selectedMonth.value);
            activeTab.value = 'payroll';
            showToast('Month closed. Review payroll and truck sheets.');
        } else if (action === 'markAllPaid') {
            await markAllMonthlyWagesPaid(selectedMonth.value, {});
            showToast('All drivers marked paid. Month is now locked.');
        } else if (action === 'lock') {
            await lockMonthWages(selectedMonth.value);
            showToast('Month locked.');
        } else if (action === 'unlock') {
            await unlockMonthWages(selectedMonth.value);
            showToast('Month unlocked.');
        } else if (action === 'reopen') {
            await reopenMonthWages(selectedMonth.value);
            showToast('Month reopened.');
        }

        pendingAction.value = null;
        await loadSheet();
    } catch (error) {
        const fallback = {
            close: 'Unable to close month.',
            markAllPaid: 'Unable to mark all paid.',
            lock: 'Unable to lock month.',
            unlock: 'Unable to unlock month.',
            reopen: 'Unable to reopen.',
        }[action];

        pendingAction.value = null;
        showToast(friendlyApiErrorMessage(error, fallback), 'error');
        await loadSheet();
    } finally {
        closing.value = false;
        locking.value = false;
        reopening.value = false;
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
        showToast(`${line.name} marked paid.`);
    } catch (error) {
        showToast(error.response?.data?.message ?? 'Unable to mark driver paid.', 'error');
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
