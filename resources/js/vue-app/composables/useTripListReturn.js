import { computed, inject, ref, unref } from 'vue';
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
