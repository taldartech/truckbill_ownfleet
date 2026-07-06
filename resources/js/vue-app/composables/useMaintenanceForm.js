import { computed, ref } from 'vue';
import { addDaysToDateInput, toDateInputValue } from '../utils/datetime';

export function emptyMaintenanceForm() {
    return {
        vehicle_id: '',
        service_date: '',
        odometer_km: '',
        maintenance_service_type_id: '',
        service_provider: '',
        cost_amount: '',
        next_service_due_km: '',
        next_service_due_date: '',
        remarks: '',
    };
}

export function maintenanceFormFromRecord(record) {
    if (!record) {
        return emptyMaintenanceForm();
    }

    return {
        vehicle_id: record.vehicle_id ?? '',
        service_date: record.service_date ?? '',
        odometer_km:
            record.odometer_km !== null && record.odometer_km !== undefined ? String(record.odometer_km) : '',
        maintenance_service_type_id:
            record.maintenance_service_type_id !== null && record.maintenance_service_type_id !== undefined
                ? String(record.maintenance_service_type_id)
                : '',
        service_provider: record.service_provider ?? '',
        cost_amount:
            record.cost_amount !== null && record.cost_amount !== undefined ? String(record.cost_amount) : '',
        next_service_due_km:
            record.next_service_due_km !== null && record.next_service_due_km !== undefined
                ? String(record.next_service_due_km)
                : '',
        next_service_due_date: record.next_service_due_date ?? '',
        remarks: record.remarks ?? '',
    };
}

function optionalNumber(value) {
    if (value === '' || value === null || value === undefined) {
        return null;
    }

    const parsed = Number(value);

    return Number.isNaN(parsed) ? null : parsed;
}

export function validateMaintenanceForm(form) {
    const errors = {};
    const today = toDateInputValue();

    if (!form.vehicle_id) {
        errors.vehicle_id = ['Please select a truck.'];
    }

    if (!form.service_date) {
        errors.service_date = ['Service date is required.'];
    } else if (form.service_date > today) {
        errors.service_date = ['Service date cannot be in the future.'];
    }

    if (form.odometer_km === '' || form.odometer_km === null || form.odometer_km === undefined) {
        errors.odometer_km = ['Meter reading is required.'];
    } else {
        const odometer = optionalNumber(form.odometer_km);

        if (odometer === null || odometer < 0) {
            errors.odometer_km = ['Meter reading must be 0 or greater.'];
        }
    }

    if (!form.maintenance_service_type_id) {
        errors.maintenance_service_type_id = ['Service type is required.'];
    }

    if (!form.service_provider.trim()) {
        errors.service_provider = ['Service provider is required.'];
    }

    if (form.cost_amount === '' || form.cost_amount === null || form.cost_amount === undefined) {
        errors.cost_amount = ['Cost amount is required.'];
    } else {
        const costAmount = optionalNumber(form.cost_amount);

        if (costAmount === null || costAmount <= 0) {
            errors.cost_amount = ['Cost amount must be greater than 0.'];
        }
    }

    if (
        form.next_service_due_date &&
        form.service_date &&
        form.next_service_due_date <= form.service_date
    ) {
        errors.next_service_due_date = ['Next due date must be after service date.'];
    }

    return errors;
}

export function buildMaintenancePayload(form) {
    const odometer = optionalNumber(form.odometer_km);
    const costAmount = optionalNumber(form.cost_amount);

    return {
        vehicle_id: form.vehicle_id,
        service_date: form.service_date,
        odometer_km: odometer,
        maintenance_service_type_id: Number(form.maintenance_service_type_id),
        service_provider: form.service_provider.trim(),
        cost_amount: costAmount,
        next_service_due_km: optionalNumber(form.next_service_due_km),
        next_service_due_date: form.next_service_due_date || null,
        remarks: form.remarks.trim() || null,
    };
}

export function useMaintenanceForm(initialRecord = null) {
    const form = ref(maintenanceFormFromRecord(initialRecord));
    const baselineSnapshot = ref(JSON.stringify(form.value));

    const minNextDueDate = computed(() => addDaysToDateInput(form.value.service_date, 1));

    function resetForm(record = null) {
        form.value = maintenanceFormFromRecord(record);
        baselineSnapshot.value = JSON.stringify(form.value);
    }

    function captureBaseline() {
        baselineSnapshot.value = JSON.stringify(form.value);
    }

    const isDirty = computed(() => JSON.stringify(form.value) !== baselineSnapshot.value);

    return {
        form,
        minNextDueDate,
        resetForm,
        captureBaseline,
        isDirty,
        validate: () => validateMaintenanceForm(form.value),
        buildPayload: () => buildMaintenancePayload(form.value),
    };
}
