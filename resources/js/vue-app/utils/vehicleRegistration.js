const REGISTRATION_PATTERN = /^(?:[A-Z]{2}[0-9]{1,2}[A-Z]{1,3}[0-9]{4}|[0-9]{2}BH[0-9]{4}[A-Z]{1,2})$/;

export function normalizeVehicleRegistration(value) {
    return String(value ?? '')
        .toUpperCase()
        .replace(/[^A-Z0-9]/g, '')
        .slice(0, 12);
}

export function isValidVehicleRegistration(value) {
    const normalized = normalizeVehicleRegistration(value);

    return normalized !== '' && REGISTRATION_PATTERN.test(normalized);
}

export const VEHICLE_REGISTRATION_HINT = 'e.g. RJ27AV5055';

export const VEHICLE_REGISTRATION_ERROR =
    'Please enter a valid vehicle registration number (e.g. RJ27AV5055).';
