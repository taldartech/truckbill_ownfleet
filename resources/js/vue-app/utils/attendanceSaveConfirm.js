import { formatDate } from './datetime';

const STATUS_LABELS = {
    PRESENT: 'Present (P)',
    ABSENT: 'Absent (A)',
    SUBSTITUTE: 'Substitute (S)',
};

const EXCEPTION_LABELS = {
    BLANK: 'Clear mark (back to Present)',
    ABSENT: 'Absent (A)',
    ABSENT_SUBSTITUTE: 'Substitute (S)',
};

function formatAttendanceDate(date) {
    return formatDate(date);
}

function driverNameById(drivers, driverId) {
    if (!driverId) {
        return '—';
    }

    const driver = drivers.find((item) => String(item.id) === String(driverId));

    return driver?.name ?? '—';
}

function resolveSubstituteName(row, drivers) {
    if (row.substitute_name) {
        return row.substitute_name;
    }

    return driverNameById(drivers, row.temporary_driver_id);
}

function resolveInitialSubstituteName(row, drivers) {
    if (row.initialSubstituteName) {
        return row.initialSubstituteName;
    }

    return driverNameById(drivers, row.initialTemporaryDriverId);
}

function buildChangedFields({ statusChanged, substituteChanged, remarksChanged }) {
    const fields = [];

    if (statusChanged) {
        fields.push('Status');
    }

    if (substituteChanged) {
        fields.push('Substitute');
    }

    if (remarksChanged) {
        fields.push('Remarks');
    }

    return fields;
}

function buildDayPanelRowSummary(row, { drivers }) {
    const statusChanged = row.statusType !== row.initialStatusType;
    const substituteChanged =
        String(row.temporary_driver_id ?? '') !== String(row.initialTemporaryDriverId ?? '');
    const remarksChanged =
        String(row.remarks ?? '').trim() !== String(row.initialRemarks ?? '').trim();

    const previousStatusLabel = STATUS_LABELS[row.initialStatusType] ?? '—';

    if (!statusChanged && !substituteChanged && !remarksChanged) {
        if (row.statusType === 'PRESENT' && !row.attendance_id) {
            return {
                truck: row.vehicle_label,
                driver: row.driver_name,
                action: 'lock_present',
                actionLabel: 'Lock Present',
                statusLabel: STATUS_LABELS.PRESENT,
                previousStatusLabel: 'System default',
                substitute: null,
                previousSubstitute: null,
                remarks: null,
                previousRemarks: null,
                locksData: true,
                isDelete: false,
                changedFields: ['Status'],
            };
        }

        return null;
    }
    const nextStatusLabel = STATUS_LABELS[row.statusType] ?? '—';
    const previousSubstitute = resolveInitialSubstituteName(row, drivers);
    const nextSubstitute = resolveSubstituteName(row, drivers);

    if (row.statusType === 'PRESENT' && !row.attendance_id) {
        return {
            truck: row.vehicle_label,
            driver: row.driver_name,
            action: 'lock_present',
            actionLabel: 'Lock Present',
            statusLabel: nextStatusLabel,
            previousStatusLabel,
            substitute: null,
            previousSubstitute: null,
            remarks: null,
            previousRemarks: null,
            locksData: true,
            isDelete: false,
            changedFields: ['Status'],
        };
    }

    if (row.statusType === 'PRESENT' && row.attendance_id) {
        return {
            truck: row.vehicle_label,
            driver: row.driver_name,
            action: 'clear_exception',
            actionLabel: 'Clear exception',
            statusLabel: nextStatusLabel,
            previousStatusLabel,
            substitute: null,
            previousSubstitute: row.initialStatusType === 'SUBSTITUTE' ? previousSubstitute : null,
            remarks: null,
            previousRemarks: row.initialRemarks?.trim() || null,
            locksData: true,
            isDelete: true,
            changedFields: buildChangedFields({ statusChanged: true, substituteChanged, remarksChanged }),
        };
    }

    return {
        truck: row.vehicle_label,
        driver: row.driver_name,
        action: row.attendance_id ? 'update_exception' : 'create_exception',
        actionLabel: row.attendance_id ? 'Update exception' : 'Mark exception',
        statusLabel: nextStatusLabel,
        previousStatusLabel,
        substitute: row.statusType === 'SUBSTITUTE' ? nextSubstitute : null,
        previousSubstitute: row.initialStatusType === 'SUBSTITUTE' ? previousSubstitute : null,
        remarks: row.remarks?.trim() || null,
        previousRemarks: row.initialRemarks?.trim() || null,
        locksData: false,
        isDelete: false,
        changedFields: buildChangedFields({ statusChanged, substituteChanged, remarksChanged }),
    };
}

export function buildDayPanelConfirmSummary(rows, { date, drivers = [] }) {
    const summaryRows = rows
        .map((row) => buildDayPanelRowSummary(row, { drivers }))
        .filter(Boolean);

    if (summaryRows.length === 0) {
        return null;
    }

    const hasLock = summaryRows.some((row) => row.locksData);
    const isDelete = summaryRows.every((row) => row.isDelete);
    const count = summaryRows.length;

    let title = 'Confirm Attendance Save';
    let message = `You are about to save ${count} truck${count === 1 ? '' : 's'} for ${formatAttendanceDate(date)}.`;

    if (isDelete) {
        title = count === 1 ? 'Confirm Clear Mark' : 'Confirm Clear Marks';
        message = `This will remove the saved exception and restore default Present (P) for ${count} truck${count === 1 ? '' : 's'}.`;
    } else if (hasLock) {
        message = `Present marks are locked after save. Review ${count} change${count === 1 ? '' : 's'} for ${formatAttendanceDate(date)}.`;
    }

    return {
        title,
        message,
        date,
        dateLabel: formatAttendanceDate(date),
        rows: summaryRows,
        hasLock,
        isDelete,
        confirmLabel: isDelete ? 'Yes, Clear Mark' : 'Yes, Save Attendance',
    };
}

export function buildFormModalConfirmSummary({
    record,
    payload,
    drivers = [],
    vehicles = [],
}) {
    const vehicle = vehicles.find((item) => item.id === payload.vehicle_id);
    const truck = vehicle?.registration_number ?? record?.vehicle?.registration_number ?? '—';
    const driver =
        drivers.find((item) => item.id === payload.primary_driver_id)?.name
        ?? record?.primary_driver?.name
        ?? '—';
    const date = payload.attendance_date ?? record?.attendance_date ?? '';
    const exceptionType = payload.exception_type ?? 'ABSENT';

    if (exceptionType === 'BLANK') {
        const previousStatus =
            record?.temporary_driver_id || record?.temporary_driver?.name ? 'Substitute (S)' : 'Absent (A)';

        return {
            title: 'Confirm Clear Mark',
            message: `This will remove the exception and restore default Present (P) for ${formatAttendanceDate(date)}.`,
            date,
            dateLabel: formatAttendanceDate(date),
            hasLock: true,
            isDelete: true,
            confirmLabel: 'Yes, Clear Mark',
            rows: [
                {
                    truck,
                    driver,
                    action: 'clear_exception',
                    actionLabel: 'Clear exception',
                    statusLabel: 'Present (P)',
                    previousStatusLabel: previousStatus,
                    substitute: null,
                    previousSubstitute: record?.temporary_driver?.name ?? null,
                    remarks: null,
                    previousRemarks: record?.temporary_assignment_remarks?.trim() || null,
                    locksData: true,
                    isDelete: true,
                    changedFields: ['Status'],
                },
            ],
        };
    }

    const nextStatusLabel = EXCEPTION_LABELS[exceptionType] ?? '—';
    const previousStatusLabel = record?.id
        ? record?.temporary_driver_id || record?.temporary_driver?.name
            ? 'Substitute (S)'
            : 'Absent (A)'
        : 'Present (P)';
    const nextSubstitute =
        exceptionType === 'ABSENT_SUBSTITUTE'
            ? driverNameById(drivers, payload.temporary_driver_id)
            : null;
    const previousSubstitute = record?.temporary_driver?.name ?? null;
    const nextRemarks = payload.temporary_assignment_remarks?.trim() || null;
    const previousRemarks = record?.temporary_assignment_remarks?.trim() || null;

    const statusChanged = nextStatusLabel !== previousStatusLabel;
    const substituteChanged = String(nextSubstitute ?? '') !== String(previousSubstitute ?? '');
    const remarksChanged = String(nextRemarks ?? '') !== String(previousRemarks ?? '');

    return {
        title: record?.id ? 'Confirm Attendance Update' : 'Confirm Attendance Mark',
        message: record?.id
            ? `Review changes before updating attendance for ${formatAttendanceDate(date)}.`
            : `This will save an attendance exception for ${formatAttendanceDate(date)}.`,
        date,
        dateLabel: formatAttendanceDate(date),
        hasLock: false,
        isDelete: false,
        confirmLabel: record?.id ? 'Yes, Update Attendance' : 'Yes, Save Attendance',
        rows: [
            {
                truck,
                driver,
                action: record?.id ? 'update_exception' : 'create_exception',
                actionLabel: record?.id ? 'Update exception' : 'Mark exception',
                statusLabel: nextStatusLabel,
                previousStatusLabel,
                substitute: nextSubstitute,
                previousSubstitute,
                remarks: nextRemarks,
                previousRemarks,
                locksData: false,
                isDelete: false,
                changedFields: buildChangedFields({ statusChanged, substituteChanged, remarksChanged }),
            },
        ],
    };
}
