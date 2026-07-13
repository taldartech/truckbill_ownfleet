export const GST_REGISTRATION_OPTIONS = [
    { value: 'regular', label: 'Regular' },
    { value: 'composition', label: 'Composition' },
    { value: 'unregistered', label: 'Unregistered' },
];

export const TAX_LIABILITY_OPTIONS = [
    { value: 'forward_charge', label: 'Forward Charge' },
    { value: 'reverse_charge', label: 'Reverse Charge (RCM)' },
];

export const DEFAULT_GST_FORM_VALUES = {
    registration_type: '',
    tax_liability: 'forward_charge',
    gst_number: '',
    gst_rate: '',
    state_id: '',
};

export function showsGstNumber(registrationType) {
    return registrationType === 'regular' || registrationType === 'composition';
}

export function showsTaxLiability(registrationType) {
    return registrationType === 'regular';
}

export function showsGstRate(registrationType) {
    return registrationType === 'regular';
}

function mapLegacyTaxLiability(value) {
    if (!value) {
        return 'forward_charge';
    }

    if (value === 'forward charge' || value === 'forward_charge') {
        return 'forward_charge';
    }

    if (value === 'reverse charge' || value === 'reverse_charge') {
        return 'reverse_charge';
    }

    return value;
}

export function mapLegacyGstFields(entity = {}) {
    const rate = entity.gst_rate;

    return {
        registration_type: entity.registration_type ?? entity.gst_type ?? '',
        tax_liability: mapLegacyTaxLiability(entity.tax_liability ?? entity.gst_scheme ?? 'forward_charge'),
        gst_number: entity.gst_number ?? '',
        gst_rate: rate === null || rate === undefined || rate === '' ? '' : rate,
        state_id: entity.state_id ?? '',
    };
}

export function registrationTypeLabel(value) {
    return GST_REGISTRATION_OPTIONS.find((option) => option.value === value)?.label ?? value ?? '—';
}

export function taxLiabilityLabel(value) {
    return TAX_LIABILITY_OPTIONS.find((option) => option.value === value)?.label ?? value ?? '—';
}

export function normalizeGstFormForSubmit(values = {}) {
    const registrationType = values.registration_type || null;
    const showsGst = showsGstNumber(registrationType);
    const rateVisible = showsGstRate(registrationType);
    const rawRate = values.gst_rate;

    return {
        registration_type: registrationType,
        tax_liability: showsTaxLiability(registrationType)
            ? values.tax_liability || 'forward_charge'
            : null,
        gst_number: showsGst ? values.gst_number || null : null,
        gst_rate: rateVisible && rawRate !== '' && rawRate !== null && rawRate !== undefined
            ? Number(rawRate)
            : null,
        state_id: values.state_id || null,
    };
}

export function createGstFormState(initial = {}) {
    return {
        ...DEFAULT_GST_FORM_VALUES,
        ...mapLegacyGstFields(initial),
    };
}
