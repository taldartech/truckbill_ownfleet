import { formatDate } from './datetime';

function formatMoney(value) {
    return Number(value ?? 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function formatOdometer(value) {
    if (value === null || value === undefined || value === '') {
        return '0';
    }

    return Number(value).toLocaleString('en-IN');
}

function lookupLabel(items, id, fallback = '—') {
    if (!id) {
        return fallback;
    }

    return items.find((item) => String(item.id) === String(id))?.name ?? fallback;
}

function buildField(label, value, previousValue, trackChanges = false) {
    const displayValue = value === null || value === undefined || value === '' ? '—' : String(value);
    const displayPrevious =
        previousValue === null || previousValue === undefined || previousValue === ''
            ? '—'
            : String(previousValue);
    const changed = trackChanges && displayValue !== displayPrevious;

    return {
        label,
        value: displayValue,
        changed,
        previousValue: trackChanges ? displayPrevious : '',
    };
}

/**
 * @param {object} form
 * @param {object} options
 * @returns {object}
 */
export function buildMaintenanceSaveConfirmViewModel(form, options = {}) {
    const {
        mode = 'create',
        vehicles = [],
        serviceTypes = [],
        originalRecord = null,
        vehicleLabel = '',
    } = options;

    const truck =
        vehicleLabel ||
        lookupLabel(vehicles, form.vehicle_id, originalRecord?.vehicle?.registration_number ?? '—');
    const serviceType =
        lookupLabel(
            serviceTypes,
            form.maintenance_service_type_id,
            originalRecord?.service_type_label ?? originalRecord?.service_type ?? '—',
        ) || '—';

    const isCreate = mode === 'create';
    const trackChanges = !isCreate && originalRecord != null;

    const basicInfoFields = [
        buildField('Truck', truck, originalRecord?.vehicle?.registration_number ?? truck, trackChanges),
        buildField('Service Date', formatDate(form.service_date), formatDate(originalRecord?.service_date), trackChanges),
        buildField(
            'Meter Reading',
            `${formatOdometer(form.odometer_km)} km`,
            `${formatOdometer(originalRecord?.odometer_km)} km`,
            trackChanges,
        ),
    ];

    const serviceFields = [
        buildField(
            'Service Type',
            serviceType,
            originalRecord?.service_type_label ?? originalRecord?.service_type ?? '—',
            trackChanges,
        ),
        buildField('Service Provider', form.service_provider?.trim(), originalRecord?.service_provider, trackChanges),
    ];

    const costFields = [
        buildField(
            'Cost Amount',
            `₹${formatMoney(form.cost_amount)}`,
            `₹${formatMoney(originalRecord?.cost_amount)}`,
            trackChanges,
        ),
    ];

    const nextDueFields = [
        buildField(
            'Next Due KM',
            form.next_service_due_km ? `${formatOdometer(form.next_service_due_km)} km` : '—',
            originalRecord?.next_service_due_km ? `${formatOdometer(originalRecord.next_service_due_km)} km` : '—',
            trackChanges,
        ),
        buildField(
            'Next Due Date',
            form.next_service_due_date ? formatDate(form.next_service_due_date) : '—',
            originalRecord?.next_service_due_date ? formatDate(originalRecord.next_service_due_date) : '—',
            trackChanges,
        ),
    ];

    const notesField = buildField('Remarks', form.remarks?.trim(), originalRecord?.remarks, trackChanges);

    const changedLabels = [...basicInfoFields, ...serviceFields, ...costFields, ...nextDueFields, notesField]
        .filter((field) => field.changed)
        .map((field) => field.label);

    return {
        isCreate,
        title: isCreate ? 'Confirm Save Maintenance Record' : 'Confirm Update Maintenance Record',
        message: isCreate
            ? 'Are you sure you want to save this maintenance record?'
            : 'Please review what changed below. Old and new values are shown — update only if everything is correct.',
        confirmLabel: isCreate ? 'Yes, Save Record' : 'Yes, Update Record',
        showAddAnotherAction: isCreate,
        addAnotherLabel: 'Save & Add Another',
        basicInfoFields,
        serviceFields,
        costFields,
        nextDueFields,
        notesField,
        changeSummary: changedLabels.length > 0 ? changedLabels.join(', ') : '',
    };
}

export function buildMaintenanceDeleteConfirmViewModel(record) {
    const truck = record?.vehicle?.registration_number ?? 'this truck';

    return {
        title: 'Confirm Delete Maintenance Record',
        message: `Are you sure you want to delete this maintenance record for ${truck} on ${formatDate(record?.service_date)}?`,
        confirmLabel: 'Yes, Delete Record',
        isDelete: true,
        truck,
        serviceDate: formatDate(record?.service_date),
        serviceType: record?.service_type_label ?? record?.service_type ?? '—',
        provider: record?.service_provider || '—',
        cost: formatMoney(record?.cost_amount),
    };
}
