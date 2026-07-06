export function resolveMaintenanceDueStatus(record, today = null) {
    const referenceDate = today ?? new Date().toISOString().slice(0, 10);
    const odometer = record?.vehicle?.odometer_km ?? record?.vehicle_odometer_km;
    const dueKm = record?.next_service_due_km;
    const dueDate = record?.next_service_due_date;

    if ((dueKm === null || dueKm === undefined || dueKm === '') && !dueDate) {
        return 'none';
    }

    const parsedOdometer = odometer === '' || odometer == null ? null : Number(odometer);
    const parsedDueKm = dueKm === '' || dueKm == null ? null : Number(dueKm);
    const kmDue = parsedDueKm !== null && parsedOdometer !== null && parsedOdometer >= parsedDueKm;
    const dateDue = Boolean(dueDate && dueDate < referenceDate);

    if (kmDue || dateDue) {
        return 'due';
    }

    return 'upcoming';
}

export function maintenanceDueStatusMeta(status) {
    switch (status) {
        case 'due':
            return { label: 'Due', badge: 'bg-danger' };
        case 'upcoming':
            return { label: 'Upcoming', badge: 'bg-warning text-dark' };
        case 'none':
            return { label: 'No Schedule', badge: 'bg-secondary' };
        default:
            return { label: '—', badge: 'bg-light text-dark border' };
    }
}

export function isMaintenanceKmOverdue(record) {
    return record?.overdue_reason === 'km' || record?.overdue_reason === 'both';
}

export function isMaintenanceDateOverdue(record) {
    return record?.overdue_reason === 'date' || record?.overdue_reason === 'both';
}
