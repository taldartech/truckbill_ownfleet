export const TripDraftLayout = Object.freeze({
    SINGLE: 'single',
    MULTI_POINT: 'multi_point',
});

const DRAFT_KEY_PREFIX = Object.freeze({
    [TripDraftLayout.SINGLE]: 'truckbill_trip_create_draft',
    [TripDraftLayout.MULTI_POINT]: 'truckbill_trip_mp_create_draft',
});

/**
 * Drafts are kept per user, company and branch so a draft started in one branch
 * (with that branch's trucks, drivers and parties) is never restored in another.
 * Returns null when there is no branch context, in which case drafts are disabled.
 */
export function tripDraftScope(user) {
    const userId = user?.id;
    const tenantId = user?.effective_tenant_id ?? user?.tenant_id;
    const branchId = user?.effective_branch_id ?? user?.branch_id;

    if (!userId || !tenantId || !branchId) {
        return null;
    }

    return `${userId}:${tenantId}:${branchId}`;
}

export function tripDraftStorageKey(layout, scope) {
    if (!scope) {
        return null;
    }

    return `${DRAFT_KEY_PREFIX[layout]}:${scope}`;
}

/**
 * Drafts saved before branch scoping cannot be attributed to a branch, so they are dropped.
 */
export function discardUnscopedTripDrafts() {
    try {
        Object.values(DRAFT_KEY_PREFIX).forEach((key) => localStorage.removeItem(key));
    } catch {
        // Ignore storage failures.
    }
}
