<template>
    <div class="attendance-month-grid">
        <div class="d-flex flex-wrap align-items-center gap-3 mb-3">
            <span class="text-muted small fw-semibold">{{ monthLabel }}</span>
            <div class="d-flex flex-wrap gap-3 small">
                <span class="legend-item">
                    <span class="cell cell-present">P</span>
                    Present
                </span>
                <span class="legend-item">
                    <span class="cell cell-absent-pure">A</span>
                    Absent — no work
                </span>
                <span class="legend-item">
                    <span class="cell cell-absent-substitute">A</span>
                    Absent + substitute
                </span>
                <span class="legend-item">
                    <span class="cell cell-none">·</span>
                    Not marked
                </span>
                <span class="legend-item">
                    <span class="pay-tag pay-paid">✓</span>
                    Paid
                </span>
                <span class="legend-item">
                    <span class="pay-tag pay-unpaid">₹</span>
                    Unpaid
                </span>
            </div>
            <span v-if="summary.marked > 0" class="ms-auto text-muted small">
                {{ summary.present }} present · {{ summary.absent }} absent ·
                {{ summary.paid }} paid · {{ summary.unpaid }} unpaid
            </span>
        </div>

        <div v-if="rows.length === 0" class="text-center text-muted py-3 small">
            No attendance marked for this month yet.
        </div>

        <div v-else class="table-responsive">
            <table class="table table-sm table-bordered mb-0 grid-table">
                <thead>
                    <tr>
                        <th class="sticky-col vehicle-col">Vehicle</th>
                        <th class="sticky-col driver-col">Driver</th>
                        <th
                            v-for="day in days"
                            :key="day"
                            class="day-col text-center"
                            :class="{ 'is-weekend': isWeekend(day) }"
                        >
                            {{ day }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in rows" :key="row.vehicle_id">
                        <td class="sticky-col vehicle-col fw-semibold">{{ row.vehicle_label }}</td>
                        <td class="sticky-col driver-col text-muted small">{{ row.driver_name }}</td>
                        <td
                            v-for="day in days"
                            :key="`${row.vehicle_id}-${day}`"
                            class="text-center p-1 day-cell"
                            :class="{ 'is-weekend': isWeekend(day) }"
                        >
                            <button
                                v-if="cellRecord(row, day)"
                                type="button"
                                class="cell-btn"
                                :class="cellClass(row, day)"
                                :title="cellTitle(row, day)"
                                :disabled="!canEdit"
                                @click="emit('select', cellRecord(row, day))"
                            >
                                <span class="cell-letter">{{ cellLetter(row, day) }}</span>
                                <span
                                    v-if="isPayable(cellRecord(row, day))"
                                    class="pay-tag"
                                    :class="paymentTagClass(row, day)"
                                >
                                    {{ paymentTagLabel(row, day) }}
                                </span>
                            </button>
                            <span v-else class="cell cell-none" :title="`${dateKey(day)} — not marked`">·</span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    month: {
        type: String,
        required: true,
    },
    overview: {
        type: Object,
        default: () => ({
            summary: { marked: 0, present: 0, absent: 0, paid: 0, unpaid: 0 },
            rows: [],
        }),
    },
    canEdit: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['select']);

const summary = computed(() => props.overview.summary ?? {});
const rows = computed(() => props.overview.rows ?? []);

const monthLabel = computed(() => {
    if (!props.month) {
        return '';
    }

    const [year, monthNumber] = props.month.split('-').map(Number);
    const date = new Date(year, monthNumber - 1, 1);

    return date.toLocaleDateString('en-IN', { month: 'long', year: 'numeric' });
});

const days = computed(() => {
    if (!props.month) {
        return [];
    }

    const [year, monthNumber] = props.month.split('-').map(Number);
    const lastDay = new Date(year, monthNumber, 0).getDate();

    return Array.from({ length: lastDay }, (_, index) => index + 1);
});

function dateKey(day) {
    const [year, monthNumber] = props.month.split('-');

    return `${year}-${monthNumber}-${String(day).padStart(2, '0')}`;
}

function isWeekend(day) {
    const [year, monthNumber] = props.month.split('-').map(Number);
    const weekday = new Date(year, monthNumber - 1, day).getDay();

    return weekday === 0 || weekday === 6;
}

function cellRecord(row, day) {
    return row.days?.[dateKey(day)] ?? null;
}

function isPaid(record) {
    return record?.wage_payment_status === 'paid';
}

function isPayable(record) {
    return Number(record?.wage_amount ?? 0) > 0;
}

function hasSubstitute(record) {
    return Boolean(record?.temporary_driver_id);
}

function cellClass(row, day) {
    const record = cellRecord(row, day);

    if (!record) {
        return 'cell-none';
    }

    let statusClass = 'cell-present';

    if (record.status !== 'PRESENT') {
        statusClass = hasSubstitute(record) ? 'cell-absent-substitute' : 'cell-absent-pure';
    }

    return [statusClass, isPaid(record) ? 'is-paid' : 'is-unpaid'];
}

function paymentTagClass(row, day) {
    return isPaid(cellRecord(row, day)) ? 'pay-paid' : 'pay-unpaid';
}

function paymentTagLabel(row, day) {
    return isPaid(cellRecord(row, day)) ? '✓' : '₹';
}

function cellLetter(row, day) {
    const record = cellRecord(row, day);

    if (!record) {
        return '·';
    }

    return record.status === 'PRESENT' ? 'P' : 'A';
}

function cellTitle(row, day) {
    const record = cellRecord(row, day);

    if (!record) {
        return '';
    }

    const alternate = record.temporary_driver_name;
    const wage = Number(record.wage_amount ?? 0).toFixed(2);
    let statusLabel = 'Present';

    if (record.status !== 'PRESENT') {
        statusLabel = alternate ? 'Absent (substitute on duty)' : 'Absent (no work, wage ₹0)';
    }

    const parts = [`${dateKey(day)}: ${statusLabel}`, `Driver: ${record.primary_driver_name ?? '—'}`];

    if (alternate) {
        parts.push(`Alternate: ${alternate}`);
    }

    parts.push(`Wage: ₹${wage}`);

    if (isPayable(record)) {
        parts.push(`Payment: ${isPaid(record) ? 'Paid' : 'Unpaid'}`);
    } else {
        parts.push('Payment: not applicable');
    }

    return parts.join(' · ');
}
</script>

<style scoped>
.legend-item {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
}

.cell {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 1.35rem;
    height: 1.35rem;
    border-radius: 0.25rem;
    font-size: 0.7rem;
    font-weight: 700;
    line-height: 1;
}

.cell-present {
    background: #198754;
    color: #fff;
}

.cell-absent-pure {
    background: #dc3545;
    color: #fff;
}

.cell-absent-substitute {
    background: #fd7e14;
    color: #fff;
}

.cell-none {
    background: #e9ecef;
    color: #adb5bd;
}

.grid-table {
    font-size: 0.8rem;
}

.grid-table .day-col {
    min-width: 1.75rem;
    padding: 0.25rem 0.1rem;
    font-weight: 600;
    color: #6c757d;
}

.grid-table .is-weekend {
    background: #f8f9fa;
}

.sticky-col {
    background: #fff;
    white-space: nowrap;
}

.vehicle-col {
    min-width: 7rem;
}

.driver-col {
    min-width: 6rem;
    max-width: 8rem;
    overflow: hidden;
    text-overflow: ellipsis;
}

.day-cell {
    vertical-align: middle;
}

.pay-tag {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 0.85rem;
    height: 0.75rem;
    padding: 0 0.1rem;
    border-radius: 0.15rem;
    font-size: 0.55rem;
    font-weight: 800;
    line-height: 1;
}

.pay-paid {
    background: #fff;
    color: #198754;
}

.pay-unpaid {
    background: #fff3cd;
    color: #856404;
}

.cell-btn {
    display: inline-flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.05rem;
    width: 1.85rem;
    height: 2rem;
    padding: 0.1rem 0;
    border: none;
    border-radius: 0.25rem;
    cursor: pointer;
}

.cell-letter {
    font-size: 0.7rem;
    font-weight: 700;
    line-height: 1;
    color: #fff;
}

.cell-btn:disabled {
    cursor: default;
}

.cell-btn.cell-present {
    background: #198754;
}

.cell-btn.cell-absent-pure {
    background: #dc3545;
}

.cell-btn.cell-absent-substitute {
    background: #fd7e14;
}

.cell-btn.is-unpaid {
    box-shadow: inset 0 0 0 2px #ffc107;
}

.cell-btn.is-paid {
    box-shadow: inset 0 0 0 2px rgba(255, 255, 255, 0.85);
}

.cell-btn:not(:disabled):hover {
    filter: brightness(0.92);
    box-shadow: 0 0 0 2px rgba(13, 110, 253, 0.35);
}
</style>
