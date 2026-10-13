import { formatDate } from './datetime';

export function displayValue(value) {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    return String(value);
}

export function resolveLookupLabel(items, id, fallback = '—') {
    if (!id) {
        return fallback;
    }

    const item = items.find((entry) => String(entry.id) === String(id));

    if (!item) {
        return fallback;
    }

    return item.name ?? item.branch_name ?? item.label ?? fallback;
}

function formatBranchNumberExample(settings, kind) {
    if (!settings) {
        return null;
    }

    const initial = String(settings[`${kind}_initial`] ?? '').trim() || (kind === 'invoice' ? 'INV' : 'TRIP');
    const separator1 = String(settings[`${kind}_separator_1`] ?? '');
    const yearType = settings[`${kind}_year_type`] ?? 'year';
    const yearDigits = Number(settings[`${kind}_year_digits`] ?? 4) === 2 ? 2 : 4;
    const separator2 = String(settings[`${kind}_separator_2`] ?? '');
    const sequenceDigits = Math.max(1, Number(settings[`${kind}_sequence_digits`] ?? 4) || 4);
    const sequence = String(1).padStart(sequenceDigits, '0');
    const now = new Date();
    let datePart = '';

    if (yearType === 'year') {
        datePart = yearDigits === 2
            ? String(now.getFullYear()).slice(-2)
            : String(now.getFullYear());
    } else if (yearType === 'financial_year') {
        const startYear = now.getMonth() >= 3 ? now.getFullYear() : now.getFullYear() - 1;
        const endYear = startYear + 1;
        datePart = yearDigits === 2
            ? `${String(startYear).slice(-2)}-${String(endYear).slice(-2)}`
            : `${startYear}-${String(endYear).slice(-2)}`;
    }

    if (!datePart) {
        return `${initial}${separator1}${sequence}`;
    }

    return `${initial}${separator1}${datePart}${separator2}${sequence}`;
}

export function buildField(label, value, previousValue = null, trackChanges = false) {
    const normalizedValue = displayValue(value);
    const normalizedPrevious = displayValue(previousValue);
    const changed = trackChanges && normalizedValue !== normalizedPrevious;

    return {
        label,
        value: normalizedValue,
        previousValue: normalizedPrevious,
        changed,
    };
}

/**
 * @param {object} options
 * @param {string} options.entityLabel
 * @param {'create'|'edit'} [options.mode]
 * @param {Array<{ label: string, value?: unknown, previousValue?: unknown, changed?: boolean, value?: string, previousValue?: string }>} options.fields
 * @returns {object}
 */
export function buildMasterCrudSaveConfirmViewModel({ entityLabel, mode = 'create', fields = [] }) {
    const isCreate = mode === 'create';
    const trackChanges = !isCreate;

    const normalizedFields = fields.map((field) => {
        if (field.changed !== undefined) {
            return field;
        }

        return buildField(field.label, field.value, field.previousValue, trackChanges);
    });

    const changedLabels = normalizedFields.filter((field) => field.changed).map((field) => field.label);

    return {
        entityLabel,
        isCreate,
        title: isCreate ? `Confirm Add ${entityLabel}` : `Confirm Update ${entityLabel}`,
        message: isCreate
            ? `Please review the ${entityLabel.toLowerCase()} details below before saving.`
            : 'Please review what you changed below. Old and new values are shown — update only if everything is correct.',
        confirmLabel: isCreate ? `Yes, Save ${entityLabel}` : `Yes, Update ${entityLabel}`,
        fields: normalizedFields,
        changeSummary: changedLabels.length > 0 ? changedLabels.join(', ') : '',
    };
}

export function buildPartyLocationFields(payload, original, { states, cities }) {
    const fields = [
        { label: 'Name', value: payload.name, previousValue: original?.name },
        { label: 'State', value: resolveLookupLabel(states, payload.state_id), previousValue: original?.state_name },
        { label: 'City', value: resolveLookupLabel(cities, payload.city_id), previousValue: original?.city_name },
        { label: 'GST Number', value: payload.gst_number, previousValue: original?.gst_number },
        { label: 'PAN Number', value: payload.pan_number, previousValue: original?.pan_number },
        { label: 'Contact Number', value: payload.contact_number, previousValue: original?.contact_number },
        { label: 'Address', value: payload.address, previousValue: original?.address },
    ];

    if (Object.prototype.hasOwnProperty.call(payload, 'is_broker')) {
        fields.push({
            label: 'Broker',
            value: payload.is_broker ? 'Yes' : 'No',
            previousValue: original?.is_broker ? 'Yes' : 'No',
        });
    }

    return fields;
}

export function buildStationFields(payload, original, { states, cities }) {
    return [
        { label: 'Station Name', value: payload.name, previousValue: original?.name },
        { label: 'State', value: resolveLookupLabel(states, payload.state_id), previousValue: original?.state_name },
        { label: 'City', value: resolveLookupLabel(cities, payload.city_id), previousValue: original?.city_name },
    ];
}

export function buildStateFields(payload, original) {
    return [
        { label: 'State Name', value: payload.name, previousValue: original?.name },
        { label: 'Code', value: payload.code, previousValue: original?.code },
    ];
}

export function buildCityFields(payload, original, { states }) {
    return [
        { label: 'City Name', value: payload.name, previousValue: original?.name },
        { label: 'State', value: resolveLookupLabel(states, payload.state_id), previousValue: original?.state_name },
    ];
}

export function buildCompanyFields(payload, original, { states = [] } = {}) {
    const registrationLabels = {
        regular: 'Regular',
        composition: 'Composition',
        unregistered: 'Unregistered',
    };

    const taxLiabilityLabels = {
        forward_charge: 'Forward Charge',
        reverse_charge: 'Reverse Charge (RCM)',
    };

    return [
        { label: 'Company Name', value: payload.name, previousValue: original?.name },
        {
            label: 'GST Registration Type',
            value: registrationLabels[payload.registration_type] ?? payload.registration_type,
            previousValue:
                registrationLabels[original?.registration_type ?? original?.gst_type]
                ?? original?.registration_type
                ?? original?.gst_type,
        },
        {
            label: 'Tax Liability',
            value: taxLiabilityLabels[payload.tax_liability] ?? payload.tax_liability,
            previousValue:
                taxLiabilityLabels[original?.tax_liability ?? original?.gst_scheme]
                ?? original?.tax_liability
                ?? original?.gst_scheme,
        },
        { label: 'GST Number', value: payload.gst_number, previousValue: original?.gst_number },
        {
            label: 'GST Rate (%)',
            value: payload.gst_rate,
            previousValue: original?.gst_rate,
        },
        {
            label: 'State',
            value: resolveLookupLabel(states, payload.state_id),
            previousValue: original?.state_name ?? resolveLookupLabel(states, original?.state_id),
        },
        { label: 'PAN Number', value: payload.pan_number, previousValue: original?.pan_number },
        { label: 'Contact Person', value: payload.contact_person, previousValue: original?.contact_person },
        { label: 'Contact Number', value: payload.contact_number, previousValue: original?.contact_number },
        { label: 'Address', value: payload.address, previousValue: original?.address },
        {
            label: 'Company Logo',
            value: payload.remove_logo
                ? 'Remove logo'
                : payload.company_logo instanceof File
                    ? payload.company_logo.name
                    : original?.logo_url
                        ? 'Keep existing'
                        : 'No logo',
            previousValue: original?.logo_url ? 'Uploaded' : 'No logo',
        },
        {
            label: 'Document Color',
            value: payload.document_color,
            previousValue: original?.document_color,
        },
    ];
}

export function buildBranchFields(payload, original, { companies, states = [] } = {}) {
    const registrationLabels = {
        regular: 'Regular',
        composition: 'Composition',
        unregistered: 'Unregistered',
    };

    const taxLiabilityLabels = {
        forward_charge: 'Forward Charge',
        reverse_charge: 'Reverse Charge (RCM)',
    };

    const fields = [
        {
            label: 'Company',
            value: resolveLookupLabel(companies, payload.company_id),
            previousValue: original?.company?.name ?? original?.company_name,
        },
        { label: 'Branch Name', value: payload.branch_name, previousValue: original?.branch_name },
        {
            label: 'Contact Number',
            value: payload.branch_contact_number,
            previousValue: original?.branch_contact_number,
        },
        { label: 'Address', value: payload.branch_address, previousValue: original?.branch_address },
        {
            label: 'Head Office',
            value: payload.head_office ? 'Yes' : 'No',
            previousValue: original?.head_office ? 'Yes' : 'No',
        },
        {
            label: 'Trip Number Example',
            value: formatBranchNumberExample(payload.number_settings, 'trip'),
            previousValue: original?.number_settings?.trip_example,
        },
        {
            label: 'Invoice Number Example',
            value: formatBranchNumberExample(payload.number_settings, 'invoice'),
            previousValue: original?.number_settings?.invoice_example,
        },
        {
            label: 'Inherit Company GST',
            value: payload.inherit_company_gst ? 'Yes' : 'No',
            previousValue: original?.inherit_company_gst === false ? 'No' : 'Yes',
        },
    ];

    if (!payload.inherit_company_gst) {
        fields.push(
            {
                label: 'GST Registration Type',
                value: registrationLabels[payload.registration_type] ?? payload.registration_type,
                previousValue:
                    registrationLabels[original?.registration_type]
                    ?? original?.registration_type,
            },
            {
                label: 'Tax Liability',
                value: taxLiabilityLabels[payload.tax_liability] ?? payload.tax_liability,
                previousValue:
                    taxLiabilityLabels[original?.tax_liability]
                    ?? original?.tax_liability,
            },
            {
                label: 'GST Number',
                value: payload.gst_number,
                previousValue: original?.gst_number,
            },
            {
                label: 'GST Rate (%)',
                value: payload.gst_rate,
                previousValue: original?.gst_rate,
            },
            {
                label: 'State',
                value: resolveLookupLabel(states, payload.state_id),
                previousValue: original?.state_name ?? resolveLookupLabel(states, original?.state_id),
            },
        );
    }

    fields.push({
        label: 'Use Company Logo',
        value: payload.use_company_logo ? 'Yes' : 'No',
        previousValue: original?.use_company_logo === false ? 'No' : 'Yes',
    });

    if (!payload.use_company_logo) {
        fields.push({
            label: 'Branch Logo',
            value: payload.remove_logo
                ? 'Remove logo'
                : payload.branch_logo instanceof File
                    ? payload.branch_logo.name
                    : original?.logo_url && !original?.use_company_logo
                        ? 'Keep existing'
                        : 'No logo',
            previousValue:
                original?.use_company_logo === false && original?.logo_url ? 'Uploaded' : 'No logo',
        });
    }

    return fields;
}

export function buildDriverFields(payload, original) {
    return [
        { label: 'Name', value: payload.name, previousValue: original?.name },
        { label: 'Mobile', value: payload.phone, previousValue: original?.phone },
        { label: 'License Number', value: payload.license_number, previousValue: original?.license_number },
        {
            label: 'License Expiry',
            value: payload.license_expiry_date ? formatDate(payload.license_expiry_date) : null,
            previousValue: original?.license_expiry_date ? formatDate(original.license_expiry_date) : null,
        },
        { label: 'Address', value: payload.address, previousValue: original?.address },
        { label: 'Aadhaar', value: payload.aadhaar_number, previousValue: original?.aadhaar_number },
        { label: 'PAN', value: payload.pan_number, previousValue: original?.pan_number },
        { label: 'Bank Account', value: payload.bank_account_number, previousValue: original?.bank_account_number },
        { label: 'IFSC', value: payload.bank_ifsc, previousValue: original?.bank_ifsc },
        {
            label: 'Joining Date',
            value: payload.joining_date ? formatDate(payload.joining_date) : null,
            previousValue: original?.joining_date ? formatDate(original.joining_date) : null,
        },
        {
            label: 'Emergency Contact',
            value: payload.emergency_contact_name,
            previousValue: original?.emergency_contact_name,
        },
        {
            label: 'Emergency Phone',
            value: payload.emergency_contact_phone,
            previousValue: original?.emergency_contact_phone,
        },
        {
            label: 'Daily Wage',
            value: displayValue(payload.daily_wage),
            previousValue: displayValue(original?.daily_wage),
            changed: Number(payload.daily_wage || 0) !== Number(original?.daily_wage || 0),
        },
        {
            label: 'Status',
            value: payload.is_active ? 'Active' : 'Inactive',
            previousValue: original?.is_active ? 'Active' : 'Inactive',
        },
    ];
}

export function buildTruckFields(payload, original) {
    return [
        {
            label: 'Registration Number',
            value: payload.registration_number,
            previousValue: original?.registration_number,
        },
        { label: 'Vehicle Type', value: payload.vehicle_type, previousValue: original?.vehicle_type },
        { label: 'Tyre Layout', value: payload.tyre_layout, previousValue: original?.tyre_layout },
        { label: 'Make', value: payload.make, previousValue: original?.make },
        { label: 'Model', value: payload.model, previousValue: original?.model },
        { label: 'Manufacture Year', value: payload.manufacture_year, previousValue: original?.manufacture_year },
        { label: 'Odometer (km)', value: payload.odometer_km, previousValue: original?.odometer_km },
        {
            label: 'Default Mileage',
            value: payload.default_mileage_km_per_liter,
            previousValue: original?.default_mileage_km_per_liter,
        },
        { label: 'Max Load (ton)', value: payload.max_load_ton, previousValue: original?.max_load_ton },
        {
            label: 'Fuel Tank Capacity (LTR)',
            value: payload.fuel_tank_capacity_ltr,
            previousValue: original?.fuel_tank_capacity_ltr,
        },
    ];
}

export function buildUserFields(payload, original, { roles, companies, branches }) {
    const fields = [
        { label: 'Name', value: payload.name, previousValue: original?.name },
        { label: 'Email', value: payload.email, previousValue: original?.email },
        {
            label: 'Role',
            value: resolveLookupLabel(roles, payload.user_role_id, original?.role?.name),
            previousValue: original?.role?.name,
        },
    ];

    if (payload.tenant_id !== undefined) {
        fields.push({
            label: 'Company',
            value: resolveLookupLabel(companies, payload.tenant_id),
            previousValue: original?.company?.name ?? original?.tenant_name,
        });
    }

    if (payload.branch_id !== undefined) {
        fields.push({
            label: 'Branch',
            value: resolveLookupLabel(branches, payload.branch_id),
            previousValue: original?.branch?.branch_name ?? original?.branch_name,
        });
    }

    if (payload.password) {
        fields.push({
            label: 'Password',
            value: '••••••••',
            previousValue: original ? '—' : null,
        });
    }

    return fields;
}

export function buildDriverAdvanceFields(payload, { driverName = '', walletBalance = null } = {}) {
    const entryTypeLabels = {
        dr: 'Dr — Advance given',
        cr: 'Cr — Amount returned',
    };

    const fields = [
        { label: 'Driver', value: driverName },
        {
            label: 'Type',
            value: entryTypeLabels[payload.entry_type] ?? payload.entry_type,
        },
        {
            label: 'Amount',
            value: `₹${Number(payload.amount ?? 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`,
        },
        {
            label: 'Date',
            value: payload.entry_date ? formatDate(payload.entry_date) : '—',
        },
        { label: 'Remarks', value: payload.remarks?.trim() || '—' },
    ];

    if (walletBalance !== null) {
        fields.push({
            label: 'Current Wallet Balance',
            value: `₹${Number(walletBalance).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`,
        });
    }

    return fields;
}

export function buildPermissionFields(payload, original) {
    return [
        { label: 'Slug', value: payload.slug, previousValue: original?.slug },
        { label: 'Name', value: payload.name, previousValue: original?.name },
        { label: 'Group', value: payload.group, previousValue: original?.group },
    ];
}

export function buildTyreLayoutFields(payload, original, { layoutTypeLabel }) {
    const fields = [
        { label: 'Layout Name', value: payload.name, previousValue: original?.name },
        { label: 'Code', value: payload.code, previousValue: original?.code },
        {
            label: 'Layout Type',
            value: layoutTypeLabel?.(payload.layout_type) ?? payload.layout_type,
            previousValue: layoutTypeLabel?.(original?.layout_type) ?? original?.layout_type,
        },
        { label: 'Sort Order', value: payload.sort_order, previousValue: original?.sort_order },
        {
            label: 'Status',
            value: payload.is_active ? 'Active' : 'Inactive',
            previousValue: original?.is_active ? 'Active' : 'Inactive',
        },
    ];

    if (payload.layout_type === 'standard') {
        fields.push(
            { label: 'Front Steering Axles', value: payload.steer_axles, previousValue: original?.steer_axles },
            { label: 'Spare Tyres', value: payload.spare_count, previousValue: original?.spare_count },
            {
                label: 'Dual Wheel Axles',
                value: Array.isArray(payload.dual_axles) ? payload.dual_axles.join(', ') : payload.dual_axles,
                previousValue: Array.isArray(original?.dual_axles)
                    ? original.dual_axles.join(', ')
                    : original?.dual_axles,
            },
            {
                label: 'Single Wheel Axles',
                value: Array.isArray(payload.single_axles) ? payload.single_axles.join(', ') : payload.single_axles,
                previousValue: Array.isArray(original?.single_axles)
                    ? original.single_axles.join(', ')
                    : original?.single_axles,
            },
        );
    }

    return fields;
}
