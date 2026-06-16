<template>
    <div class="attendance-month-grid" :class="{ 'is-fullscreen': isFullscreen }">
        <div class="grid-toolbar">
            <span class="grid-title">Month Overview</span>
            <span class="month-label">{{ monthLabel }}</span>
            <span class="legend">
                <span class="lg lg-system">P</span> System
                <span class="lg lg-admin">P</span> Admin
                <span class="lg lg-a">A</span> Absent
                <span class="lg lg-s">S</span> Substitute
                <span class="lg lg-day">9</span> Day filled
            </span>
            <span v-if="summary.present > 0 || summary.exceptions > 0" class="summary">
                {{ summary.present }} present · {{ summary.exceptions ?? summary.marked ?? 0 }} marked
            </span>
            <button
                type="button"
                class="btn btn-sm btn-outline-secondary fullscreen-btn"
                :title="isFullscreen ? 'Exit fullscreen' : 'Fullscreen'"
                :aria-label="isFullscreen ? 'Exit fullscreen' : 'Fullscreen'"
                @click="toggleFullscreen"
            >
                <i class="bi" :class="isFullscreen ? 'bi-fullscreen-exit' : 'bi-arrows-fullscreen'"></i>
            </button>
        </div>

        <div v-if="rows.length === 0" class="text-center text-muted py-2">
            No driver assignments for this month. Assign primary drivers to trucks first.
        </div>

        <div v-else class="table-wrap">
            <table class="table table-bordered mb-0 grid-table">
                <colgroup>
                    <col class="col-vehicle" />
                    <col v-for="day in days" :key="`col-${day}`" class="col-day" />
                </colgroup>
                <thead>
                    <tr>
                        <th class="sticky-col vehicle-col">Truck</th>
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
                        <td class="sticky-col vehicle-col">
                            <RouterLink
                                v-if="linkTrucks"
                                :to="truckRoute(row)"
                                class="truck-link truck-number"
                                @click.stop
                            >
                                {{ row.vehicle_label }}
                            </RouterLink>
                            <span v-else class="truck-number fw-semibold">{{ row.vehicle_label }}</span>
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
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { isPresentConfirmed } from '../../utils/dailyAttendanceStorage';
import { formatMonthYear } from '../../utils/datetime';

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

const isFullscreen = ref(false);

const summary = computed(() => props.overview.summary ?? {});
const rows = computed(() => props.overview.rows ?? []);

const monthLabel = computed(() => {
    if (!props.month) {
        return '';
    }

    const [year, monthNumber] = props.month.split('-').map(Number);

    return formatMonthYear(`${year}-${String(monthNumber).padStart(2, '0')}`);
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

function isAdminMarkedPresent(record, row, day) {
    if (!record || record.status !== 'PRESENT') {
        return false;
    }

    if (!record.is_implicit) {
        return true;
    }

    return isPresentConfirmed(dateKey(day), row.vehicle_id);
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

function markedByLabel(record, row, day) {
    if (!record) {
        return null;
    }

    if (record.status === 'PRESENT') {
        if (isAdminMarkedPresent(record, row, day)) {
            return record.marked_by_name ?? 'Admin';
        }

        return 'System';
    }

    if (record.marked_source === 'system') {
        return 'System';
    }

    return record.marked_by_name ?? 'Admin';
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
        return isAdminMarkedPresent(record, row, day) ? 'mark-p mark-admin' : 'mark-p mark-system';
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
    } else if (isAdminMarkedPresent(record, row, day)) {
        statusLabel = 'Present (admin marked)';
    } else {
        statusLabel = 'Present (system default)';
    }

    const parts = [`${dateKey(day)}: ${statusLabel}`, `Primary: ${record.primary_driver_name ?? '—'}`];

    const marker = markedByLabel(record, row, day);

    if (marker) {
        parts.push(`Marked by: ${marker}`);
    }

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

function toggleFullscreen() {
    isFullscreen.value = !isFullscreen.value;
}

function onFullscreenKeydown(event) {
    if (event.key === 'Escape' && isFullscreen.value) {
        isFullscreen.value = false;
    }
}

watch(isFullscreen, (active) => {
    document.body.style.overflow = active ? 'hidden' : '';

    if (active) {
        window.addEventListener('keydown', onFullscreenKeydown);
    } else {
        window.removeEventListener('keydown', onFullscreenKeydown);
    }
});

onBeforeUnmount(() => {
    document.body.style.overflow = '';
    window.removeEventListener('keydown', onFullscreenKeydown);
});
</script>

<style scoped>
.attendance-month-grid {
    width: 100%;
}

.attendance-month-grid.is-fullscreen {
    position: fixed;
    inset: 0;
    z-index: 1080;
    background: #fff;
    padding: 1rem 1.25rem;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

.grid-toolbar {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 0.5rem;
    font-size: 0.875rem;
    flex-shrink: 0;
}

.grid-title {
    font-size: 1.05rem;
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
    gap: 0.75rem;
    color: #6c757d;
}

.lg {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 1.35rem;
    height: 1.35rem;
    padding: 0 0.2rem;
    border-radius: 0.2rem;
    font-weight: 700;
    font-size: 0.75rem;
    margin-right: 0.2rem;
}

.lg-system {
    background: #d1e7dd;
    color: #0f5132;
}

.lg-admin {
    background: #146c43;
    color: #fff;
}

.lg-a {
    background: #f8d7da;
    color: #842029;
}

.lg-s {
    background: #ffe5cc;
    color: #984c0c;
}

.lg-day {
    background: #146c43;
    color: #fff;
    font-size: 0.7rem;
}

.summary {
    margin-left: auto;
    color: #6c757d;
}

.fullscreen-btn {
    margin-left: 0.25rem;
}

.is-fullscreen .fullscreen-btn {
    margin-left: auto;
}

.is-fullscreen .summary {
    margin-left: 0;
}

.mark-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    min-width: 0;
    min-height: 1.65rem;
    padding: 0.1rem 0;
    border: none;
    border-radius: 0.2rem;
    font-size: 0.8rem;
    font-weight: 700;
    line-height: 1.1;
    cursor: pointer;
    text-align: center;
}

.mark-btn:disabled {
    cursor: default;
}

.mark-p.mark-system {
    background: #d1e7dd;
    color: #0f5132;
}

.mark-p.mark-admin {
    background: #146c43;
    color: #fff;
}

.mark-a {
    background: #f8d7da;
    color: #842029;
}

.mark-s {
    background: #ffe5cc;
    color: #984c0c;
}

.mark-empty {
    color: #ced4da;
    font-size: 0.8rem;
    font-weight: 700;
    line-height: 1.1;
}

.mark-future {
    color: #adb5bd;
}

.mark-btn:not(:disabled):hover {
    filter: brightness(0.95);
}

.table-wrap {
    width: 100%;
    max-height: min(75vh, 46rem);
    overflow-x: auto;
    overflow-y: auto;
    -webkit-overflow-scrolling: touch;
}

.is-fullscreen .table-wrap {
    flex: 1;
    max-height: none;
    min-height: 0;
}

.grid-table {
    table-layout: fixed;
    width: 100%;
    min-width: 100% !important;
    font-size: 0.85rem;
    border-collapse: collapse;
    border-spacing: 0;
}

.grid-table :is(th, td) {
    padding: 0.3rem 0.05rem !important;
}

.grid-table .sticky-col {
    padding-left: 0.5rem !important;
    padding-right: 0.5rem !important;
}

.col-vehicle {
    width: 8rem;
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
    padding: 0.35rem 0 !important;
    font-size: 0.8rem;
    font-weight: 600;
    color: #6c757d;
    line-height: 1.2;
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
    font-size: 0.82rem;
    line-height: 1.25;
}

.truck-number {
    display: block;
    font-weight: 600;
    color: #212529;
}

.truck-link {
    color: var(--brand-primary);
    text-decoration: none;
}

.truck-link:hover {
    text-decoration: underline;
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
    margin-top: 0.15rem;
    color: #6c757d;
    font-size: 0.75rem;
    font-weight: 500;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

@media (max-width: 1400px) {
    .col-vehicle {
        width: 7rem;
    }

    .grid-table {
        font-size: 0.8rem;
    }

    .mark-btn,
    .mark-empty {
        font-size: 0.75rem;
        min-height: 1.5rem;
    }
}
</style>
