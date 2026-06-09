<template>
    <div class="attendance-day-panel">
        <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
            <button type="button" class="btn btn-outline-secondary btn-sm" :disabled="loading" @click="shiftDay(-1)">
                <i class="bi bi-chevron-left"></i>
            </button>
            <input
                :id="dateInputId"
                :value="selectedDate"
                type="date"
                class="form-control form-control-sm"
                style="width: 11rem"
                :max="today"
                :disabled="loading"
                @change="onDateInput"
            />
            <button
                type="button"
                class="btn btn-outline-secondary btn-sm"
                :disabled="loading || selectedDate >= today"
                @click="shiftDay(1)"
            >
                <i class="bi bi-chevron-right"></i>
            </button>
            <button
                type="button"
                class="btn btn-outline-primary btn-sm"
                :disabled="loading || selectedDate === today"
                @click="goToday"
            >
                Today
            </button>
            <span class="text-muted small fw-semibold">{{ dayLabel }}</span>
            <span v-if="editableRows.length > 0" class="badge ms-1" :class="allRowsFilled ? 'bg-success' : 'bg-secondary'">
                {{ filledCount }} / {{ editableRows.length }} done
            </span>
            <button
                v-if="canEdit && editableRows.length > 0"
                type="button"
                class="btn btn-outline-secondary btn-sm ms-md-auto"
                :disabled="loading || saving"
                @click="setAllPresent"
            >
                All Present
            </button>
            <button
                v-if="canEdit && editableRows.length > 0"
                type="button"
                class="btn btn-primary btn-sm"
                :disabled="loading || saving"
                @click="saveAll"
            >
                {{ saving ? 'Saving…' : 'Save All' }}
            </button>
        </div>

        <div v-if="formError" class="alert alert-danger py-2">{{ formError }}</div>

        <div v-if="summary.total > 0" class="day-summary mb-3 small text-muted">
            <span class="badge bg-success-subtle text-success border border-success-subtle me-1">
                {{ summary.present }} P
            </span>
            <span class="badge bg-danger-subtle text-danger border border-danger-subtle me-1">
                {{ summary.absent }} A
            </span>
            <span class="badge bg-warning-subtle text-dark border border-warning-subtle me-1">
                {{ summary.substitute }} S
            </span>
            <span v-if="summary.unmarked > 0" class="text-muted">{{ summary.unmarked }} not marked</span>
            <span v-if="unassignedRows.length > 0" class="text-muted ms-1">
                · {{ unassignedRows.length }} without driver
            </span>
        </div>

        <div v-if="loading" class="text-center py-4 text-muted">Loading daily attendance…</div>

        <div v-else class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0 day-table">
                <thead class="table-light">
                    <tr>
                        <th>Truck</th>
                        <th>Primary Driver</th>
                        <th style="width: 9rem">Status</th>
                        <th style="min-width: 11rem">Substitute</th>
                        <th style="min-width: 10rem">Remark</th>
                        <th v-if="canEdit" style="width: 6rem">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <template v-if="formRows.length === 0">
                        <tr>
                            <td :colspan="canEdit ? 6 : 5" class="text-center text-muted py-4">
                                No trucks found. Add trucks in Fleet first.
                            </td>
                        </tr>
                    </template>
                    <template v-else>
                        <tr v-if="attendanceRows.length === 0">
                            <td :colspan="canEdit ? 6 : 5" class="text-center text-muted py-3">
                                No trucks with a primary driver for this date.
                            </td>
                        </tr>
                        <tr v-for="row in attendanceRows" :key="row.vehicle_id">
                        <td class="fw-semibold">{{ row.vehicle_label }}</td>
                        <td>{{ row.driver_name }}</td>
                        <td>
                            <select
                                v-if="canEdit && row.editable"
                                v-model="row.statusType"
                                class="form-select form-select-sm"
                                :class="{ 'is-invalid': row.statusError }"
                                @change="onStatusChange(row)"
                            >
                                <option value="PRESENT">Present</option>
                                <option value="ABSENT">Absent</option>
                                <option value="SUBSTITUTE">Substitute</option>
                            </select>
                            <span v-else class="mark" :class="markClass(displayMark(row))">
                                {{ displayMark(row) }}
                            </span>
                            <div v-if="row.statusError" class="invalid-feedback d-block">
                                {{ row.statusError }}
                            </div>
                        </td>
                        <td>
                            <select
                                v-if="canEdit && row.editable && row.statusType === 'SUBSTITUTE'"
                                v-model="row.temporary_driver_id"
                                class="form-select form-select-sm"
                                :class="{ 'is-invalid': row.substituteError }"
                            >
                                <option value="">Select substitute</option>
                                <option
                                    v-for="driver in substituteOptions(row)"
                                    :key="driver.id"
                                    :value="driver.id"
                                >
                                    {{ driverLabel(driver) }}
                                </option>
                            </select>
                            <span v-else class="text-muted small">{{ row.substitute_name || '—' }}</span>
                            <div v-if="row.substituteError" class="invalid-feedback d-block">
                                {{ row.substituteError }}
                            </div>
                        </td>
                        <td>
                            <input
                                v-if="canEdit && row.editable"
                                v-model="row.remarks"
                                type="text"
                                class="form-control form-control-sm"
                                placeholder="Optional"
                            />
                            <span v-else class="text-muted small">{{ row.remarks || '—' }}</span>
                        </td>
                        <td v-if="canEdit">
                            <button
                                v-if="row.editable"
                                type="button"
                                class="btn btn-outline-primary btn-sm"
                                :disabled="saving || savingRowId === row.vehicle_id"
                                @click="saveRow(row)"
                            >
                                {{ savingRowId === row.vehicle_id ? '…' : 'Save' }}
                            </button>
                            <span v-else class="text-muted small">—</span>
                        </td>
                    </tr>
                    <tr v-if="attendanceRows.length > 0 && unassignedRows.length > 0" class="section-divider-row">
                        <td :colspan="canEdit ? 6 : 5" class="p-0 border-0">
                            <hr class="my-2 mb-0 text-muted opacity-50" />
                        </td>
                    </tr>
                    <tr v-for="row in unassignedRows" :key="`unassigned-${row.vehicle_id}`" class="row-unassigned">
                        <td class="fw-semibold text-muted">{{ row.vehicle_label }}</td>
                        <td class="text-muted">—</td>
                        <td><span class="mark mark-none">—</span></td>
                        <td class="text-muted small">—</td>
                        <td class="text-muted small">—</td>
                        <td v-if="canEdit" class="text-muted small">—</td>
                    </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import {
    loadSavedVehicleIds,
    markVehiclesSavedForDate,
    persistSavedVehicleIds,
} from '../../utils/dailyAttendanceStorage';
import {
    buildBusySubstituteDriversByDate,
    isSubstituteDriverBusy,
} from '../../utils/substituteDriverAvailability';

const props = defineProps({
    selectedDate: {
        type: String,
        required: true,
    },
    vehicles: {
        type: Array,
        default: () => [],
    },
    drivers: {
        type: Array,
        default: () => [],
    },
    overviewRows: {
        type: Array,
        default: () => [],
    },
    loading: {
        type: Boolean,
        default: false,
    },
    saving: {
        type: Boolean,
        default: false,
    },
    canEdit: {
        type: Boolean,
        default: false,
    },
    dateInputId: {
        type: String,
        default: 'attendance_day_date',
    },
});

const emit = defineEmits(['update:selectedDate', 'save', 'complete-change']);

const formRows = ref([]);
const formError = ref('');
const savingRowId = ref('');
const savedVehicleIds = ref(new Set());

const today = computed(() => new Date().toISOString().slice(0, 10));

const dayLabel = computed(() => {
    if (!props.selectedDate) {
        return '';
    }

    const date = new Date(`${props.selectedDate}T12:00:00`);

    return date.toLocaleDateString('en-IN', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
});

const overviewByVehicle = computed(() => {
    const map = new Map();

    for (const row of props.overviewRows) {
        map.set(row.vehicle_id, row);
    }

    return map;
});

const editableRows = computed(() => formRows.value.filter((row) => row.editable));

const attendanceRows = computed(() =>
    formRows.value
        .filter((row) => row.has_primary_driver)
        .sort((left, right) => left.vehicle_label.localeCompare(right.vehicle_label)),
);

const unassignedRows = computed(() =>
    formRows.value
        .filter((row) => !row.has_primary_driver)
        .sort((left, right) => left.vehicle_label.localeCompare(right.vehicle_label)),
);

const filledCount = computed(() => editableRows.value.filter((row) => isRowFilled(row)).length);

const allRowsFilled = computed(
    () => editableRows.value.length > 0 && filledCount.value === editableRows.value.length,
);

const summary = computed(() => {
    const totals = { present: 0, absent: 0, substitute: 0, unmarked: 0, total: 0 };

    for (const row of formRows.value) {
        if (!row.has_primary_driver) {
            continue;
        }

        totals.total++;
        const mark = displayMark(row);

        if (mark === 'P') {
            totals.present++;
        } else if (mark === 'A') {
            totals.absent++;
        } else if (mark === 'S') {
            totals.substitute++;
        } else {
            totals.unmarked++;
        }
    }

    return totals;
});

function driverLabel(driver) {
    const name = driver?.name ?? '—';

    return driver?.is_active === false ? `${name} (Inactive)` : name;
}

function resolvePrimaryDriverId(vehicle, overviewRow, cell) {
    return (
        cell?.primary_driver_id ??
        overviewRow?.primary_driver_id ??
        vehicle?.primary_driver_id ??
        vehicle?.current_primary_assignment?.driver_id ??
        vehicle?.current_primary_assignment?.driver?.id ??
        null
    );
}

function resolveMark(cell) {
    if (!cell) {
        return '—';
    }

    if (!cell.status) {
        return cell.is_future ? '·' : '—';
    }

    if (cell.status === 'PRESENT') {
        return 'P';
    }

    if (cell.temporary_driver_id || cell.temporary_driver_name) {
        return 'S';
    }

    return 'A';
}

function markToStatusType(mark) {
    if (mark === 'A') {
        return 'ABSENT';
    }

    if (mark === 'S') {
        return 'SUBSTITUTE';
    }

    return 'PRESENT';
}

function buildFormRow(vehicle, overviewRow, date) {
    const cell = overviewRow?.days?.[date] ?? null;
    const vehicleId = vehicle?.id ?? overviewRow?.vehicle_id;
    const vehicleLabel = vehicle?.registration_number ?? overviewRow?.vehicle_label ?? '—';
    const assignmentDriver =
        vehicle?.current_primary_assignment?.driver?.name ??
        vehicle?.primary_driver?.name ??
        null;
    const primaryDriverId = resolvePrimaryDriverId(vehicle, overviewRow, cell);
    const hasPrimaryDriver = Boolean(primaryDriverId);
    const driverName = hasPrimaryDriver
        ? (cell?.primary_driver_name ?? overviewRow?.driver_name ?? assignmentDriver ?? '—')
        : '—';
    const mark = hasPrimaryDriver ? resolveMark(cell) : '—';
    const editable = hasPrimaryDriver && mark !== '·' && mark !== '—';
    const statusType = editable || (hasPrimaryDriver && mark !== '—' && mark !== '·') ? markToStatusType(mark) : null;

    return {
        vehicle_id: vehicleId,
        vehicle_label: vehicleLabel,
        driver_name: driverName,
        primary_driver_id: primaryDriverId,
        has_primary_driver: hasPrimaryDriver,
        attendance_id: cell?.id ?? null,
        statusType,
        initialStatusType: statusType,
        temporary_driver_id: cell?.temporary_driver_id ?? '',
        initialTemporaryDriverId: cell?.temporary_driver_id ?? '',
        remarks: cell?.temporary_assignment_remarks ?? '',
        initialRemarks: cell?.temporary_assignment_remarks ?? '',
        substitute_name: cell?.temporary_driver_name ?? '',
        editable,
        statusError: '',
        substituteError: '',
    };
}

function syncFormRows() {
    const date = props.selectedDate;
    const seen = new Set();
    const rows = [];

    const sortedVehicles = [...props.vehicles].sort((left, right) =>
        String(left.registration_number ?? '').localeCompare(String(right.registration_number ?? '')),
    );

    for (const vehicle of sortedVehicles) {
        seen.add(vehicle.id);
        rows.push(buildFormRow(vehicle, overviewByVehicle.value.get(vehicle.id), date));
    }

    for (const overviewRow of props.overviewRows) {
        if (seen.has(overviewRow.vehicle_id)) {
            continue;
        }

        rows.push(buildFormRow(null, overviewRow, date));
    }

    formRows.value = rows.sort((left, right) => left.vehicle_label.localeCompare(right.vehicle_label));
    formError.value = '';
}

watch(
    () => [props.selectedDate, props.overviewRows, props.vehicles],
    () => {
        if (!props.loading) {
            syncFormRows();
        }
    },
    { deep: true, immediate: true },
);

watch(
    () => props.loading,
    (isLoading) => {
        if (!isLoading) {
            syncFormRows();
        }
    },
);

watch(
    () => props.selectedDate,
    (date) => {
        savedVehicleIds.value = loadSavedVehicleIds(date);
    },
    { immediate: true },
);

watch(allRowsFilled, (complete) => {
    emit('complete-change', complete);
}, { immediate: true });

function displayMark(row) {
    if (!row.has_primary_driver || !row.statusType) {
        return '—';
    }

    if (row.statusType === 'ABSENT') {
        return 'A';
    }

    if (row.statusType === 'SUBSTITUTE') {
        return 'S';
    }

    return 'P';
}

function markClass(mark) {
    if (mark === 'P') {
        return 'mark-p';
    }

    if (mark === 'S') {
        return 'mark-s';
    }

    if (mark === 'A') {
        return 'mark-a';
    }

    return 'mark-none';
}

function substituteOptions(row) {
    const busy = buildBusySubstituteDriversByDate({
        date: props.selectedDate,
        monthOverview: { rows: props.overviewRows },
        vehicles: props.vehicles,
        excludeAttendanceId: row.attendance_id,
    });

    const available = props.drivers.filter(
        (driver) =>
            driver.is_active !== false &&
            driver.id !== row.primary_driver_id &&
            !isSubstituteDriverBusy(busy, driver.id),
    );

    if (!row.temporary_driver_id) {
        return available;
    }

    const selected = props.drivers.find((driver) => driver.id === row.temporary_driver_id);

    if (selected && !available.some((driver) => driver.id === selected.id)) {
        return [selected, ...available];
    }

    return available;
}

function onStatusChange(row) {
    row.statusError = '';
    row.substituteError = '';

    if (row.statusType !== 'SUBSTITUTE') {
        row.temporary_driver_id = '';
    }
}

function rowHasChanges(row) {
    return (
        row.statusType !== row.initialStatusType ||
        String(row.temporary_driver_id ?? '') !== String(row.initialTemporaryDriverId ?? '') ||
        String(row.remarks ?? '').trim() !== String(row.initialRemarks ?? '').trim()
    );
}

function isRowFilled(row) {
    if (!row.editable || rowHasChanges(row)) {
        return false;
    }

    if (row.statusType === 'ABSENT' || row.statusType === 'SUBSTITUTE') {
        return Boolean(row.attendance_id);
    }

    return savedVehicleIds.value.has(row.vehicle_id);
}

function setAllPresent() {
    formError.value = '';

    for (const row of editableRows.value) {
        row.statusType = 'PRESENT';
        row.temporary_driver_id = '';
        row.statusError = '';
        row.substituteError = '';
    }
}

function buildSavePayload(rows) {
    const entries = [];
    const presentConfirmIds = [];

    for (const row of rows) {
        if (row.statusType === 'PRESENT') {
            if (row.attendance_id) {
                entries.push(buildSaveEntry(row));
            } else {
                presentConfirmIds.push(row.vehicle_id);
            }
        } else {
            entries.push(buildSaveEntry(row));
        }
    }

    return { entries, presentConfirmIds };
}

function markVehiclesSaved(vehicleIds) {
    savedVehicleIds.value = markVehiclesSavedForDate(props.selectedDate, vehicleIds);
}

function commitRowSavedState(row) {
    row.initialStatusType = row.statusType;
    row.initialTemporaryDriverId = row.temporary_driver_id;
    row.initialRemarks = row.remarks;
}

function validateRow(row) {
    row.statusError = '';
    row.substituteError = '';

    if (row.statusType === 'SUBSTITUTE' && !row.temporary_driver_id) {
        row.substituteError = 'Select a substitute driver.';

        return false;
    }

    return true;
}

function buildSaveEntry(row) {
    return {
        vehicle_id: row.vehicle_id,
        primary_driver_id: row.primary_driver_id,
        attendance_date: props.selectedDate,
        attendance_id: row.attendance_id,
        statusType: row.statusType,
        temporary_driver_id: row.statusType === 'SUBSTITUTE' ? row.temporary_driver_id : null,
        remarks: row.remarks?.trim() || null,
    };
}

function saveRow(row) {
    formError.value = '';

    if (!validateRow(row)) {
        return;
    }

    if (!rowHasChanges(row) && isRowFilled(row)) {
        formError.value = 'This row is already saved.';

        return;
    }

    savingRowId.value = row.vehicle_id;
    emit('save', buildSavePayload([row]));
}

function saveAll() {
    formError.value = '';

    if (editableRows.value.length === 0) {
        return;
    }

    let valid = true;

    for (const row of editableRows.value) {
        if (!validateRow(row)) {
            valid = false;
        }
    }

    if (!valid) {
        formError.value = 'Fix highlighted rows before saving.';

        return;
    }

    emit('save', buildSavePayload(editableRows.value));
}

function onDateInput(event) {
    const value = event.target.value;

    if (!value || value > today.value) {
        return;
    }

    emit('update:selectedDate', value);
}

function shiftDay(offset) {
    const base = new Date(`${props.selectedDate}T12:00:00`);
    base.setDate(base.getDate() + offset);
    const next = base.toISOString().slice(0, 10);

    if (next > today.value) {
        return;
    }

    emit('update:selectedDate', next);
}

function goToday() {
    emit('update:selectedDate', today.value);
}

function clearSavingState() {
    savingRowId.value = '';
}

defineExpose({ clearSavingState, syncFormRows, markVehiclesSaved, commitRowSavedState });
</script>

<style scoped>
.legend-item {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
}

.mark {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 1.75rem;
    height: 1.75rem;
    padding: 0 0.35rem;
    border-radius: 0.25rem;
    font-size: 0.8rem;
    font-weight: 700;
    line-height: 1;
}

.mark-p {
    background: var(--brand-primary);
    color: #fff;
}

.mark-a {
    background: #dc3545;
    color: #fff;
}

.mark-s {
    background: #fd7e14;
    color: #fff;
}

.mark-none {
    background: #e9ecef;
    color: #adb5bd;
}

.day-table th,
.day-table td {
    vertical-align: middle;
}

.row-unassigned td {
    background-color: #fafbfc;
}

.section-divider-row td {
    background: transparent !important;
}
</style>
