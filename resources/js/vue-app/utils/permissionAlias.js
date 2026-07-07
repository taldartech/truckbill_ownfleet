/** Mirrors backend App\Support\PermissionAliasResolver for UI permission checks. */

const SYNONYMS = {
    'attendance.bulk_mark': ['attendances.bulk_manage'],
    'attendances.bulk_manage': ['attendance.bulk_mark'],
    'attendance_reports.view': ['reports.view'],
    'reports.view': ['attendance_reports.view'],
    'attendance_reports.export': ['reports.export'],
    'reports.export': ['attendance_reports.export'],
    'companies.switch': ['companies.enter'],
    'companies.enter': ['companies.switch'],
    'companies.edit': ['companies.update'],
    'companies.update': ['companies.edit'],
    'vehicle_documents.create': ['vehicle_documents.manage'],
    'vehicle_documents.manage': ['vehicle_documents.create', 'vehicle_documents.edit'],
    'driver_advances.manage': ['drivers.manage'],
    'drivers.archive': ['drivers.manage'],
};

function matchesLegacyManage(requestedSlug, ownedSlugs) {
    if (ownedSlugs.includes(requestedSlug)) {
        return true;
    }

    const base = requestedSlug.slice(0, -'.manage'.length);

    if (ownedSlugs.includes(`${base}.create`)) {
        return true;
    }

    return ownedSlugs.includes(`${base}.create`) && ownedSlugs.includes(`${base}.edit`);
}

function matchesGranularAction(requestedSlug, ownedSlugs) {
    if (ownedSlugs.includes(requestedSlug)) {
        return true;
    }

    const base = requestedSlug.endsWith('.create')
        ? requestedSlug.slice(0, -'.create'.length)
        : requestedSlug.slice(0, -'.edit'.length);

    return ownedSlugs.includes(`${base}.manage`);
}

function permissionAliasMatches(requestedSlug, ownedSlugs) {
    for (const synonym of SYNONYMS[requestedSlug] ?? []) {
        if (ownedSlugs.includes(synonym)) {
            return true;
        }
    }

    if (requestedSlug.endsWith('.manage')) {
        return matchesLegacyManage(requestedSlug, ownedSlugs);
    }

    if (requestedSlug.endsWith('.create') || requestedSlug.endsWith('.edit')) {
        return matchesGranularAction(requestedSlug, ownedSlugs);
    }

    return false;
}

/**
 * @param {string[]} ownedSlugs
 * @param {string} requestedSlug
 */
export function userHasPermission(ownedSlugs, requestedSlug) {
    if (ownedSlugs.includes(requestedSlug)) {
        return true;
    }

    return permissionAliasMatches(requestedSlug, ownedSlugs);
}

/**
 * @param {string[]} ownedSlugs
 * @param {string[]} requestedSlugs
 */
export function userHasAnyPermission(ownedSlugs, requestedSlugs) {
    return requestedSlugs.some((slug) => userHasPermission(ownedSlugs, slug));
}
