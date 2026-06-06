export const TYRE_STATUSES = [
    { value: 'IN_STOCK', label: 'In Stock' },
    { value: 'INSTALLED', label: 'Installed' },
    { value: 'RETREADED', label: 'Retreaded' },
    { value: 'DAMAGED', label: 'Damaged' },
    { value: 'SCRAPPED', label: 'Scrapped' },
];

export const TYRE_TYPES = [
    { value: 'RADIAL', label: 'Radial' },
    { value: 'BIAS', label: 'Bias' },
    { value: 'TUBELESS', label: 'Tubeless' },
    { value: 'TUBED', label: 'Tubed' },
    { value: 'OTHER', label: 'Other' },
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
        RETREADED: 'bg-warning text-dark',
        DAMAGED: 'bg-danger',
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
            || (tyre.warranty_km != null && Number(tyre.warranty_km) > 0)
            || tyre.warranty_details?.trim(),
    );
}

export function warrantyBadgeClass(tyre) {
    return hasTyreWarranty(tyre) ? 'bg-success' : 'bg-light text-muted border';
}

export function formatWarrantyLabel(tyre) {
    return hasTyreWarranty(tyre) ? 'Yes' : 'No';
}

function formatWarrantyDate(value) {
    if (!value) {
        return null;
    }

    return new Date(value).toLocaleDateString('en-IN', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });
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

    if (tyre.warranty_km != null && Number(tyre.warranty_km) > 0) {
        parts.push(formatWarrantyKm(tyre.warranty_km));
    }

    if (parts.length === 0 && tyre.warranty_details?.trim()) {
        return tyre.warranty_details.trim();
    }

    return parts.join(' · ');
}

export function formatWarrantyTooltip(tyre) {
    if (!hasTyreWarranty(tyre)) {
        return 'No warranty on file';
    }

    const lines = [formatWarrantySummary(tyre)];

    if (tyre.warranty_details?.trim()) {
        lines.push(tyre.warranty_details.trim());
    }

    return lines.filter(Boolean).join('\n');
}

const DAMAGE_TYPE_LABELS = {
    PUNCTURE: 'Puncture',
    BURST: 'Burst',
    CUT: 'Cut',
    SIDEWALL: 'Sidewall',
    OTHER: 'Other',
};

export function formatDamageType(type) {
    return DAMAGE_TYPE_LABELS[type] ?? type ?? '—';
}
