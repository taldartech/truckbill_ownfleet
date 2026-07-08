import { addDaysToDateInput, formatYmdDuration, toDateInputValue } from './datetime';
import { formatDateRangeLabel } from './dateRangePresets';

export const COMPLIANCE_ALERT_WINDOW_DAYS = 30;

/**
 * Label for the compliance alert window: today through today + 30 days.
 */
export function complianceAlertWindowLabel() {
    const today = toDateInputValue();
    const windowEnd = addDaysToDateInput(today, COMPLIANCE_ALERT_WINDOW_DAYS);

    return formatDateRangeLabel(today, windowEnd);
}

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
 * @param {{
 *     document_type?: string,
 *     issue_date?: string|null,
 *     expiry_date?: string|null,
 *     reminder_days_before?: number|null,
 * }} document
 */
export function defaultRenewFormValues(document) {
    const today = toDateInputValue();
    let validityDays = 365;

    if (document.issue_date && document.expiry_date) {
        const issue = new Date(document.issue_date);
        const expiry = new Date(document.expiry_date);
        const diff = Math.round((expiry - issue) / (1000 * 60 * 60 * 24));

        if (diff > 0) {
            validityDays = diff;
        }
    }

    return {
        document_type: document.document_type ?? '',
        issue_date: today,
        expiry_date: addDaysToDateInput(today, validityDays),
        reminder_days_before: document.reminder_days_before ?? 7,
    };
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
 * @param {{ days_remaining?: number|null, expiry_date?: string|null, license_expiry_date?: string|null }} document
 */
export function formatDaysLeft(document) {
    const days = document?.days_remaining;
    const expiryDate = document?.expiry_date ?? document?.license_expiry_date;
    const today = toDateInputValue();

    if (days == null) {
        return '—';
    }

    if (days === 0) {
        return 'Today';
    }

    if (expiryDate) {
        if (days < 0) {
            return formatYmdDuration(expiryDate, today);
        }

        return formatYmdDuration(today, expiryDate);
    }

    if (days < 0) {
        return `${Math.abs(days)}D`;
    }

    return `${days}D`;
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
 * @param {string|null|undefined} expiryDate
 */
export function licenseStatusFromDays(daysRemaining, expiryDate = null) {
    const today = toDateInputValue();
    const resolvedExpiry = expiryDate ? String(expiryDate).trim().slice(0, 10) : null;

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
        const label = resolvedExpiry
            ? `${formatYmdDuration(resolvedExpiry, today)} Overdue`
            : `${Math.abs(daysRemaining)}D Overdue`;

        return {
            status_tier: 'expired',
            status_label: label,
            status_sublabel: null,
            days_remaining: daysRemaining,
            badge_color: 'danger',
            row_color: 'danger',
        };
    }

    if (daysRemaining === 0) {
        return {
            status_tier: 'today',
            status_label: 'Expires Today',
            status_sublabel: null,
            days_remaining: 0,
            badge_color: 'warning',
            row_color: 'warning',
        };
    }

    if (daysRemaining <= 30) {
        const label = resolvedExpiry
            ? `${formatYmdDuration(today, resolvedExpiry)} Left`
            : `${daysRemaining}D Left`;

        return {
            status_tier: 'reminder',
            status_label: label,
            status_sublabel: null,
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
