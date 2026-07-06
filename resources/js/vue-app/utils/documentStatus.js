/**
 * @param {Array<{ days_remaining?: number|null }>} documents
 */
export function sortDocumentsByUrgency(documents) {
    return [...documents].sort((left, right) => {
        const leftDays = left.days_remaining ?? Number.MAX_SAFE_INTEGER;
        const rightDays = right.days_remaining ?? Number.MAX_SAFE_INTEGER;

        return leftDays - rightDays;
    });
}

/**
 * @param {{ row_color?: string }} document
 */
export function rowBorderClass(document) {
    const tier = document?.status_tier ?? 'valid';

    return `compliance-row compliance-row--${tier}`;
}

/**
 * @param {{ badge_color?: string }} document
 */
export function statusBadgeClass(document) {
    const color = document?.badge_color ?? 'secondary';

    return `text-bg-${color}`;
}

/**
 * Soft pill for dashboard compliance tables.
 * @param {{ status_tier?: string }} document
 */
export function statusBadgeSoftClass(document) {
    const tier = document?.status_tier ?? 'valid';

    return `compliance-badge compliance-badge--${tier}`;
}

/**
 * @param {{ days_remaining?: number|null }} document
 */
export function formatDaysLeft(document) {
    const days = document?.days_remaining;

    if (days == null) {
        return '—';
    }

    if (days === 0) {
        return 'Today';
    }

    if (days < 0) {
        return `${Math.abs(days)} Days`;
    }

    return `${days} Days`;
}

/**
 * @param {{ status_tier?: string }} document
 */
export function canRenewDocument(document) {
    const tier = document?.status_tier;

    return tier === 'expired' || tier === 'today' || tier === 'reminder';
}

/**
 * @param {{ status_tier?: string }} license
 */
export function canUpdateLicense(license) {
    return canRenewDocument(license);
}

/**
 * @param {number|null|undefined} daysRemaining
 */
export function licenseStatusFromDays(daysRemaining) {
    if (daysRemaining == null) {
        return {
            status_tier: 'valid',
            status_label: 'Valid',
            status_sublabel: null,
            days_remaining: null,
            badge_color: 'success',
            row_color: 'success',
        };
    }

    if (daysRemaining < 0) {
        const overdue = Math.abs(daysRemaining);

        return {
            status_tier: 'expired',
            status_label: overdue === 1 ? '1 Day Overdue' : `${overdue} Days Overdue`,
            status_sublabel: `${overdue}d`,
            days_remaining: daysRemaining,
            badge_color: 'danger',
            row_color: 'danger',
        };
    }

    if (daysRemaining === 0) {
        return {
            status_tier: 'today',
            status_label: 'Expires Today',
            status_sublabel: 'Today',
            days_remaining: 0,
            badge_color: 'warning',
            row_color: 'warning',
        };
    }

    if (daysRemaining <= 30) {
        const label = daysRemaining === 1 ? '1 Day Left' : `${daysRemaining} Days Left`;

        return {
            status_tier: 'reminder',
            status_label: label,
            status_sublabel: `${daysRemaining}d`,
            days_remaining: daysRemaining,
            badge_color: 'warning',
            row_color: 'warning',
        };
    }

    return {
        status_tier: 'valid',
        status_label: 'Valid',
        status_sublabel: `${daysRemaining}d`,
        days_remaining: daysRemaining,
        badge_color: 'success',
        row_color: 'success',
    };
}
