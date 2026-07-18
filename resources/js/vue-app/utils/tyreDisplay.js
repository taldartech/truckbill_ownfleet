export const TYRE_STATUSES = [
    { value: 'IN_STOCK', label: 'In Stock' },
    { value: 'INSTALLED', label: 'Installed' },
    { value: 'SCRAPPED', label: 'Scrapped' },
];

/** URL query value meaning "All statuses" (empty filter), so detail → back keeps the list unfiltered. */
export const TYRE_STATUS_FILTER_ALL = 'ALL';

export const TYRE_TYPES = [
    { value: 'RADIAL', label: 'Radial' },
    { value: 'BIAS', label: 'Bias' },
    { value: 'TUBELESS', label: 'Tubeless' },
    { value: 'TUBED', label: 'Tubed' },
    { value: 'OTHER', label: 'Other' },
];

export const TYRE_SIZES = [
    { value: '10.00-20', label: '10.00-20' },
    { value: '295/90 R20', label: '295/90 R20' },
    { value: '11R22.5', label: '11R22.5' },
    { value: '12R22.5', label: '12R22.5' },
    { value: '315/80 R22.5', label: '315/80 R22.5' },
];

export function formatTyreStatus(status) {
    return TYRE_STATUSES.find((item) => item.value === status)?.label ?? status ?? '—';
}

export function formatTyreType(type) {
    return TYRE_TYPES.find((item) => item.value === type)?.label ?? type ?? '—';
}

export function tyreStatusBadgeClass(status) {
    const map = {
        IN_STOCK: 'bg-primary',
        INSTALLED: 'bg-success',
        SCRAPPED: 'bg-secondary',
    };

    return map[status] ?? 'bg-light text-dark border';
}

export function hasTyreWarranty(tyre) {
    if (!tyre || typeof tyre !== 'object') {
        return Boolean(String(tyre ?? '').trim());
    }

    return Boolean(
        tyre.warranty_valid_until
            || (tyre.warranty_km != null && Number(tyre.warranty_km) > 0),
    );
}

export function warrantyBadgeClass(tyre) {
    return hasTyreWarranty(tyre) ? 'bg-success' : 'bg-light text-muted border';
}

export function formatWarrantyLabel(tyre) {
    return hasTyreWarranty(tyre) ? 'Yes' : 'No';
}

import { formatDate } from './datetime';

function formatWarrantyDate(value) {
    return formatDate(value, null);
}

function formatWarrantyKm(value) {
    if (value == null || value === '') {
        return null;
    }

    return `${Number(value).toLocaleString('en-IN')} km`;
}

export function formatWarrantySummary(tyre) {
    if (!hasTyreWarranty(tyre)) {
        return '—';
    }

    const parts = [];

    if (tyre.warranty_valid_until) {
        parts.push(`Until ${formatWarrantyDate(tyre.warranty_valid_until)}`);
    }

    // if (tyre.warranty_km != null && Number(tyre.warranty_km) > 0) {
    //     parts.push(formatWarrantyKm(tyre.warranty_km));
    // }

    return parts.length > 0 ? parts.join(' · ') : '—';
}

export function formatWarrantyTooltip(tyre) {
    if (!hasTyreWarranty(tyre)) {
        return 'No warranty on file';
    }

    return formatWarrantySummary(tyre);
}

const DAMAGE_TYPE_LABELS = {
    PUNCTURE: 'Puncture',
    BURST: 'Burst',
    CUT: 'Cut',
    SIDEWALL: 'Sidewall',
    RECAPPED: 'Regrip',
    OTHER: 'Other',
};

export function formatDamageType(type) {
    return DAMAGE_TYPE_LABELS[type] ?? type ?? '—';
}
