import { normalizeMobile } from './phone';
import { getGstNumberError, getPanNumberError } from './taxIdentity';

export function buildPartyTaxFieldErrors({ gstNumber, panNumber }) {
    const errors = {};
    const gstError = getGstNumberError(gstNumber);

    if (gstError) {
        errors.gst_number = [gstError];
    }

    const panError = getPanNumberError(panNumber);

    if (panError) {
        errors.pan_number = [panError];
    }

    return errors;
}

export function findDuplicatePartyInCity({ name, cityId, parties }) {
    const trimmed = String(name ?? '').trim();

    if (!trimmed || !cityId) {
        return null;
    }

    const normalized = trimmed.toLowerCase();

    return (
        parties.find(
            (party) =>
                String(party.city_id) === String(cityId)
                && String(party.name ?? '').trim().toLowerCase() === normalized,
        ) ?? null
    );
}

export function findDuplicatePartyContact({ contactNumber, parties, excludeId = null }) {
    const mobile = normalizeMobile(contactNumber);

    if (!mobile) {
        return null;
    }

    return (
        parties.find(
            (party) =>
                party.id !== excludeId && normalizeMobile(party.contact_number) === mobile,
        ) ?? null
    );
}

export function buildPartyDuplicateFieldErrors({
    name,
    cityId,
    contactNumber,
    parties,
    entityLabel = 'customer',
}) {
    const errors = {};

    if (findDuplicatePartyInCity({ name, cityId, parties })) {
        errors.name = [`This name is already used for another ${entityLabel} in the selected city.`];
    }

    if (findDuplicatePartyContact({ contactNumber, parties })) {
        errors.contact_number = [
            `This contact number is already registered for another ${entityLabel} in your company.`,
        ];
    }

    return errors;
}
