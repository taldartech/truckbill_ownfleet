function formatNumber(value, { maximumFractionDigits = 2 } = {}) {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    const number = Number(value);

    if (Number.isNaN(number)) {
        return '—';
    }

    return number.toLocaleString('en-IN', { maximumFractionDigits });
}

function displayValue(value) {
    if (value === null || value === undefined || String(value).trim() === '') {
        return '—';
    }

    return String(value);
}

/**
 * @param {object} vehicle
 * @returns {object}
 */
export function buildTripVehicleSelectConfirmViewModel(vehicle) {
    const makeModel = [vehicle?.make, vehicle?.model].filter(Boolean).join(' ').trim();

    return {
        title: 'Confirm Truck Selection',
        notice: 'Review the truck details below. Confirm to select this truck, or cancel to leave the truck field blank.',
        confirmLabel: 'Select Truck',
        truckSummary: [
            { label: 'Registration', value: displayValue(vehicle?.registration_number) },
            { label: 'Type', value: displayValue(vehicle?.vehicle_type) },
            { label: 'Make / Model', value: makeModel || '—' },
            { label: 'Year', value: displayValue(vehicle?.manufacture_year) },
            { label: 'Primary Driver', value: displayValue(vehicle?.primary_driver_name) },
            { label: 'Odometer', value: `${formatNumber(vehicle?.odometer_km)} km` },
            { label: 'Max Load', value: `${formatNumber(vehicle?.max_load_ton, { maximumFractionDigits: 3 })} ton` },
            {
                label: 'Default Mileage',
                value: `${formatNumber(vehicle?.default_mileage_km_per_liter)} KM/LTR`,
            },
            {
                label: 'Status',
                value: vehicle?.vehicle_status === 'inactive' ? 'Inactive' : 'Active',
            },
        ],
    };
}
