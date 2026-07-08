import { computed, inject, ref, unref } from 'vue';
import { tripArchivedListIndexLocation } from '../utils/tripArchivedListFilterState';
import { tripListIndexLocation } from '../utils/tripListFilterState';

export function useTripListReturn() {
    const injectedUser = inject('currentUser', null);
    const currentUser = computed(() => unref(injectedUser) ?? window.__AUTH_USER__ ?? null);

    const tenantId = computed(
        () => currentUser.value?.effective_tenant_id ?? currentUser.value?.company_id ?? null,
    );

    const tripListReturnTo = computed(() => tripListIndexLocation({}, tenantId.value));

    function tripListReturnWithFlash(flashQuery = {}) {
        return tripListIndexLocation(flashQuery, tenantId.value);
    }

    return {
        tripListReturnTo,
        tripListReturnWithFlash,
    };
}

export function useTripArchivedListReturn() {
    const injectedUser = inject('currentUser', null);
    const currentUser = computed(() => unref(injectedUser) ?? window.__AUTH_USER__ ?? null);

    const tenantId = computed(
        () => currentUser.value?.effective_tenant_id ?? currentUser.value?.company_id ?? null,
    );

    const tripArchivedListReturnTo = computed(() => tripArchivedListIndexLocation({}, tenantId.value));

    function tripArchivedListReturnWithFlash(flashQuery = {}) {
        return tripArchivedListIndexLocation(flashQuery, tenantId.value);
    }

    return {
        tripArchivedListReturnTo,
        tripArchivedListReturnWithFlash,
    };
}

export function useTripDetailListReturn(tripRef) {
    const { tripListReturnTo: activeListReturnTo } = useTripListReturn();
    const { tripArchivedListReturnTo } = useTripArchivedListReturn();

    const tripListReturnTo = computed(() => {
        if (tripRef.value?.is_archived) {
            return tripArchivedListReturnTo.value;
        }

        return activeListReturnTo.value;
    });

    return {
        tripListReturnTo,
    };
}
