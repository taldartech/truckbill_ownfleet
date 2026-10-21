export const TripDraftLayout = Object.freeze({
    SINGLE: 'single',
    MULTI_POINT: 'multi_point',
});

export const DRAFT_KEY_PREFIX = Object.freeze({
    [TripDraftLayout.SINGLE]: 'truckbill_trip_create_draft',
    [TripDraftLayout.MULTI_POINT]: 'truckbill_trip_mp_create_draft',
});

/**
 * Drafts are kept per user, company and branch so a draft started in one branch
 * (with that branch's trucks, drivers and parties) is never restored in another.
 * Returns null when there is no branch context, in which case drafts fallback to user:tenant.
 */
export function tripDraftScope(user) {
    const activeUser = user || (typeof window !== 'undefined' ? window.__AUTH_USER__ : null);
    const userId = activeUser?.id;
    const tenantId = activeUser?.effective_tenant_id ?? activeUser?.company_id ?? activeUser?.tenant_id;
    const branchId = activeUser?.effective_branch_id ?? activeUser?.branch_id;

    if (!userId || !tenantId) {
        return null;
    }

    const branchPart = branchId ? String(branchId) : 'default';

    return `${userId}:${tenantId}:${branchPart}`;
}

export function tripDraftStorageKey(layout, scope) {
    const baseKey = DRAFT_KEY_PREFIX[layout] || 'truckbill_trip_create_draft';
    if (!scope) {
        return baseKey;
    }

    return `${baseKey}:${scope}`;
}

/**
 * Kept for backwards compatibility without erasing user drafts.
 */
export function discardUnscopedTripDrafts() {
    // No-op to prevent accidental draft loss
}
