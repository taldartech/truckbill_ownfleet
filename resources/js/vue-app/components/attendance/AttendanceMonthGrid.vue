<template>
    <div class="attendance-month-grid">
        <div class="d-flex flex-wrap align-items-center gap-3 mb-3">
            <span class="text-muted small fw-semibold">{{ monthLabel }}</span>
            <div class="d-flex flex-wrap gap-3 small">
                <span class="legend-item">
                    <span class="cell cell-present cell-implicit">P</span>
                    Auto present
                </span>
                <span class="legend-item">
                    <span class="cell cell-absent-pure">A</span>
                    Absent
                </span>
                <span class="legend-item">
                    <span class="cell cell-absent-substitute">A</span>
                    Absent + substitute
                </span>
                <span class="legend-item">
                    <span class="cell cell-no-work">N</span>
                    No working day
                </span>
                <span class="legend-item">
                    <span class="cell cell-future">·</span>
                    Future / not marked yet
                </span>
                <span class="legend-item">
                    <span class="cell cell-none">·</span>
                    No assignment
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
            <span v-if="summary.present > 0 || summary.exceptions > 0" class="ms-auto text-muted small">
                {{ summary.present }} auto present · {{ summary.exceptions ?? summary.marked ?? 0 }} exceptions ·
                {{ summary.no_work ?? 0 }} no-work
            </span>
        </div>

        <div v-if="rows.length === 0" class="text-center text-muted py-3 small">
            No driver assignments for this month. Assign primary drivers to trucks first.
        </div>

        <div v-else class="table-wrap">
            <table class="table table-sm table-bordered mb-0 grid-table">
                <thead>
                    <tr>
                        <th class="sticky-col vehicle-col">Truck</th>
                        <th class="sticky-col driver-col">Drivers</th>
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
                    <tr v-for="row in rows" :key="row.row_id ?? `${row.vehicle_id}-${row.driver_name}`">
                        <td class="sticky-col vehicle-col fw-semibold">
                            {{ row.show_vehicle_label ? row.vehicle_label : '' }}
                        </td>
                        <td class="sticky-col driver-col text-muted small">
                            <span class="driver-name">{{ row.driver_name }}</span>
                        </td>
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
                                @click="handleCellSelect(row, day)"
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
const rows = computed(() => buildIndividualDriverRows(props.overview.rows ?? []));

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

    if (!record.status) {
        return record.is_future ? 'cell-future' : 'cell-none';
    }

    if (record.status === 'NO_WORK_DAY') {
        return ['cell-no-work', isPaid(record) ? 'is-paid' : 'is-unpaid'];
    }

    let statusClass = 'cell-present';

    if (record.status !== 'PRESENT') {
        statusClass = hasSubstitute(record) ? 'cell-absent-substitute' : 'cell-absent-pure';
    } else if (record.is_implicit) {
        statusClass = 'cell-present cell-implicit';
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

    if (!record.status) {
        return '·';
    }

    if (record.status === 'NO_WORK_DAY') {
        return 'N';
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

    if (!record.status && record.is_future) {
        statusLabel = 'Future date (not marked yet)';
    } else if (!record.status) {
        statusLabel = 'Not marked';
    } else if (record.status === 'NO_WORK_DAY') {
        statusLabel = 'No working day (truck idle)';
    } else if (record.status !== 'PRESENT') {
        statusLabel = alternate ? 'Absent (substitute on duty)' : 'Absent (no work, wage ₹0)';
    } else if (record.is_implicit) {
        statusLabel = 'Auto present (from assignment)';
    }

    const parts = [`${dateKey(day)}: ${statusLabel}`, `Driver: ${record.primary_driver_name ?? '—'}`];

    if (record.originated_as_alternate === true) {
        parts.push('Marked from alternate assignment');
    }

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

function handleCellSelect(row, day) {
    const record = cellRecord(row, day);

    if (!record) {
        return;
    }

    emit('select', {
        ...record,
        vehicle_id: row.vehicle_id,
        attendance_date: dateKey(day),
    });
}

function buildIndividualDriverRows(sourceRows) {
    const expanded = [];

    for (const sourceRow of sourceRows) {
        const perDriver = new Map();

        const ensureRow = (driverName) => {
            const normalizedName = String(driverName ?? '').trim();

            if (!normalizedName) {
                return null;
            }

            if (!perDriver.has(normalizedName)) {
                perDriver.set(normalizedName, {
                    row_id: `${sourceRow.vehicle_id}-${normalizedName}`,
                    vehicle_id: sourceRow.vehicle_id,
                    vehicle_label: sourceRow.vehicle_label,
                    driver_name: normalizedName,
                    is_primary_for_vehicle: normalizedName === String(sourceRow.driver_name ?? '').trim(),
                    days: {},
                });
            }

            return perDriver.get(normalizedName);
        };

        ensureRow(sourceRow.driver_name);

        for (const [day, record] of Object.entries(sourceRow.days ?? {})) {
            const primaryName = record?.primary_driver_name ?? sourceRow.driver_name;
            const primaryRow = ensureRow(primaryName);

            if (primaryRow) {
                primaryRow.days[day] = { ...record };
            }

            if (record?.temporary_driver_name) {
                const alternateRow = ensureRow(record.temporary_driver_name);

                if (alternateRow) {
                    alternateRow.days[day] = {
                        ...record,
                        status: 'PRESENT',
                        primary_driver_name: record.temporary_driver_name,
                        temporary_driver_id: null,
                        temporary_driver_name: null,
                        originated_as_alternate: true,
                    };
                }
            }
        }

        expanded.push(...Array.from(perDriver.values()));
    }

    const sorted = expanded.sort((left, right) => {
        const byVehicle = String(left.vehicle_label).localeCompare(String(right.vehicle_label));

        if (byVehicle !== 0) {
            return byVehicle;
        }

        if (left.is_primary_for_vehicle !== right.is_primary_for_vehicle) {
            return left.is_primary_for_vehicle ? -1 : 1;
        }

        return String(left.driver_name).localeCompare(String(right.driver_name));
    });

    let previousVehicleId = null;

    for (const row of sorted) {
        row.show_vehicle_label = row.vehicle_id !== previousVehicleId;
        previousVehicleId = row.vehicle_id;
    }

    return sorted;
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
    background: var(--brand-primary);
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

.cell-future {
    background: #f8f9fa;
    color: #6c757d;
    border: 1px dashed #ced4da;
}

.cell-no-work {
    background: #6c757d;
    color: #fff;
}

.cell-implicit {
    opacity: 0.92;
    box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.5);
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
    min-width: 9rem;
    max-width: 11rem;
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
    color: var(--brand-primary);
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
    gap: 0.1rem;
    width: 2.35rem;
    height: 2.2rem;
    padding: 0.15rem 0.05rem;
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
    background: var(--brand-primary);
}

.cell-btn.cell-implicit {
    background: rgba(var(--brand-primary-rgb), 0.55);
}

.cell-btn.cell-no-work {
    background: #6c757d;
}

.cell-btn.cell-absent-pure {
    background: #dc3545;
}

.cell-btn.cell-absent-substitute {
    background: #fd7e14;
}

.cell-btn.cell-future {
    background: #f8f9fa;
    border: 1px dashed #ced4da;
}

.cell-btn.cell-future .cell-letter {
    color: #6c757d;
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

.driver-name {
    display: block;
    color: #212529;
    font-size: 0.75rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
</style>
