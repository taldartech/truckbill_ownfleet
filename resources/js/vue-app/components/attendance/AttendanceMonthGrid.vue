<template>
    <div class="attendance-month-grid">
        <div class="grid-toolbar">
            <span class="grid-title">Month Overview</span>
            <span class="month-label">{{ monthLabel }}</span>
            <span class="legend">
                <span class="lg lg-p">P</span> Present
                <span class="lg lg-a">A</span> Absent
                <span class="lg lg-s">S</span> Substitute
                <span class="lg lg-day">9</span> Day filled
            </span>
            <span v-if="summary.present > 0 || summary.exceptions > 0" class="summary">
                {{ summary.present }} present · {{ summary.exceptions ?? summary.marked ?? 0 }} marked
            </span>
        </div>

        <div v-if="rows.length === 0" class="text-center text-muted py-2 small">
            No driver assignments for this month. Assign primary drivers to trucks first.
        </div>

        <div v-else class="table-wrap">
            <table class="table table-sm table-bordered mb-0 grid-table">
                <colgroup>
                    <col class="col-vehicle" />
                    <col class="col-driver" />
                    <col v-for="day in days" :key="`col-${day}`" class="col-day" />
                </colgroup>
                <thead>
                    <tr>
                        <th class="sticky-col vehicle-col">Truck</th>
                        <th class="sticky-col driver-col">Driver</th>
                        <th
                            v-for="day in days"
                            :key="day"
                            class="day-col text-center"
                            :class="{
                                'is-weekend': isWeekend(day),
                                'day-col-complete': isDayComplete(day),
                            }"
                            :title="dayHeaderTitle(day)"
                        >
                            {{ day }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in rows" :key="row.vehicle_id">
                        <td class="sticky-col vehicle-col fw-semibold">
                            <RouterLink
                                v-if="linkTrucks"
                                :to="truckRoute(row)"
                                class="truck-link"
                                @click.stop
                            >
                                {{ row.vehicle_label }}
                            </RouterLink>
                            <span v-else>{{ row.vehicle_label }}</span>
                        </td>
                        <td class="sticky-col driver-col text-muted small">
                            <span class="driver-name">{{ row.driver_name }}</span>
                        </td>
                        <td
                            v-for="day in days"
                            :key="`${row.vehicle_id}-${day}`"
                            class="text-center day-cell"
                            :class="{ 'is-weekend': isWeekend(day) }"
                        >
                            <button
                                v-if="cellRecord(row, day)"
                                type="button"
                                class="mark-btn"
                                :class="cellClass(row, day)"
                                :title="cellTitle(row, day)"
                                :disabled="!canEdit"
                                @click="handleCellSelect(row, day)"
                            >
                                {{ cellLetter(row, day) }}
                            </button>
                            <span v-else class="mark mark-empty" :title="`${dateKey(day)} — not marked`">·</span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { isPresentConfirmed } from '../../utils/dailyAttendanceStorage';

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
    linkTrucks: {
        type: Boolean,
        default: true,
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

function truckRoute(row) {
    return {
        name: 'trucks.index',
        query: {
            search: row.vehicle_label,
        },
    };
}

function isWeekend(day) {
    const [year, monthNumber] = props.month.split('-').map(Number);
    const weekday = new Date(year, monthNumber - 1, day).getDay();

    return weekday === 0 || weekday === 6;
}

function cellRecord(row, day) {
    return row.days?.[dateKey(day)] ?? null;
}

function hasSubstitute(record) {
    return Boolean(record?.temporary_driver_id || record?.temporary_driver_name);
}

function isRowAssignable(row, day) {
    const record = cellRecord(row, day);

    return Boolean(record?.status);
}

function isRowFilledForDay(row, day) {
    const record = cellRecord(row, day);
    const date = dateKey(day);

    if (!record?.status) {
        return false;
    }

    if (record.status === 'PRESENT') {
        if (!record.is_implicit) {
            return true;
        }

        return isPresentConfirmed(date, row.vehicle_id);
    }

    return Boolean(record.id) || record.status === 'ABSENT' || hasSubstitute(record);
}

function isDayComplete(day) {
    const assignableRows = rows.value.filter((row) => isRowAssignable(row, day));

    if (assignableRows.length === 0) {
        return false;
    }

    return assignableRows.every((row) => isRowFilledForDay(row, day));
}

function dayHeaderTitle(day) {
    const date = dateKey(day);

    if (isDayComplete(day)) {
        return `${date}: daily attendance complete for all trucks`;
    }

    return date;
}

function cellClass(row, day) {
    const record = cellRecord(row, day);

    if (!record) {
        return 'mark-empty';
    }

    if (!record.status) {
        return record.is_future ? 'mark-future' : 'mark-empty';
    }

    if (record.status === 'NO_WORK_DAY') {
        return 'mark-a';
    }

    if (record.status === 'PRESENT') {
        return record.is_implicit ? 'mark-p mark-implicit' : 'mark-p';
    }

    if (hasSubstitute(record)) {
        return 'mark-s';
    }

    return 'mark-a';
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
        return 'A';
    }

    if (record.status === 'PRESENT') {
        return 'P';
    }

    return hasSubstitute(record) ? 'S' : 'A';
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
        statusLabel = 'Absent (no work)';
    } else if (hasSubstitute(record)) {
        statusLabel = 'Substitute on duty';
    } else if (record.status !== 'PRESENT') {
        statusLabel = 'Absent';
    } else if (record.is_implicit) {
        statusLabel = isPresentConfirmed(dateKey(day), row.vehicle_id)
            ? 'Present (daily saved)'
            : 'Present (default)';
    }

    const parts = [`${dateKey(day)}: ${statusLabel}`, `Primary: ${record.primary_driver_name ?? '—'}`];

    if (alternate) {
        parts.push(`Substitute: ${alternate}`);
    }

    parts.push(`Wage: ₹${wage}`);

    if (isDayComplete(day)) {
        parts.push('Day complete');
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

</script>

<style scoped>
.attendance-month-grid {
    width: 100%;
}

.grid-toolbar {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 0.35rem;
    font-size: 0.72rem;
}

.grid-title {
    font-size: 0.9rem;
    font-weight: 600;
    color: #212529;
}

.month-label {
    font-weight: 600;
    color: #495057;
}

.legend {
    display: inline-flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.65rem;
    color: #6c757d;
}

.lg {
    font-weight: 700;
    margin-right: 0.15rem;
}

.lg-p {
    color: var(--brand-primary);
}

.lg-a {
    color: #dc3545;
}

.lg-s {
    color: #fd7e14;
}

.summary {
    margin-left: auto;
    color: #6c757d;
}

.mark-btn {
    display: inline-block;
    width: 100%;
    min-width: 0;
    padding: 0;
    border: none;
    background: none;
    font-size: 0.62rem;
    font-weight: 700;
    line-height: 1.1;
    cursor: pointer;
    text-align: center;
}

.mark-btn:disabled {
    cursor: default;
}

.mark-p {
    color: var(--brand-primary);
}

.mark-p.mark-implicit {
    color: rgba(var(--brand-primary-rgb), 0.45);
}

.mark-a {
    color: #dc3545;
}

.mark-s {
    color: #fd7e14;
}

.lg-day {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 1rem;
    height: 1rem;
    padding: 0 0.15rem;
    border-radius: 0.15rem;
    background: #146c43;
    color: #fff;
    font-size: 0.58rem;
    margin-right: 0.15rem;
}

.mark-empty {
    color: #ced4da;
    font-size: 0.62rem;
    font-weight: 700;
    line-height: 1.1;
}

.mark-future {
    color: #adb5bd;
}

.mark-btn:not(:disabled):hover {
    text-decoration: underline;
    text-underline-offset: 2px;
}

.table-wrap {
    width: 100%;
    max-height: min(75vh, 46rem);
    overflow-x: auto;
    overflow-y: auto;
    -webkit-overflow-scrolling: touch;
}

.grid-table {
    table-layout: fixed;
    width: 100%;
    min-width: 100% !important;
    font-size: 0.68rem;
    border-collapse: collapse;
    border-spacing: 0;
}

.grid-table :is(th, td) {
    padding: 0.2rem 0 !important;
}

.grid-table .sticky-col {
    padding-left: 0.35rem !important;
    padding-right: 0.35rem !important;
}

.col-vehicle {
    width: 5rem;
}

.col-driver {
    width: 6.5rem;
}

.col-day {
    width: auto;
}

.grid-table thead th {
    position: sticky;
    top: 0;
    z-index: 3;
    background: #f8f9fa;
}

.grid-table .day-col {
    padding: 0.2rem 0 !important;
    font-size: 0.62rem;
    font-weight: 600;
    color: #6c757d;
    line-height: 1.1;
}

.day-cell {
    vertical-align: middle;
}

.grid-table .is-weekend {
    background: #f8f9fa;
}

.grid-table thead th.day-col-complete {
    background: #146c43 !important;
    color: #fff !important;
}

.grid-table thead th.day-col-complete.is-weekend {
    background: #0f5132 !important;
}

.sticky-col {
    position: sticky;
    background: #fff;
    white-space: nowrap;
    z-index: 2;
    overflow: hidden;
    text-overflow: ellipsis;
}

.vehicle-col {
    left: 0;
    box-shadow: 2px 0 4px rgba(0, 0, 0, 0.05);
    font-size: 0.65rem;
}

.truck-link {
    color: var(--brand-primary);
    text-decoration: none;
    font-weight: 600;
}

.truck-link:hover {
    text-decoration: underline;
}

.driver-col {
    left: 5rem;
    box-shadow: 2px 0 4px rgba(0, 0, 0, 0.05);
}

thead .sticky-col {
    z-index: 5;
    background: #f8f9fa;
}

tbody tr:nth-of-type(even) .sticky-col {
    background: #fafbfc;
}

.driver-name {
    display: block;
    color: #212529;
    font-size: 0.62rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

@media (max-width: 1400px) {
    .col-vehicle {
        width: 4.5rem;
    }

    .col-driver {
        width: 5.5rem;
    }

    .driver-col {
        left: 4.5rem;
    }

    .grid-table {
        font-size: 0.62rem;
    }

    .grid-table .day-col,
    .mark-btn,
    .mark-empty {
        font-size: 0.58rem;
    }
}
</style>
