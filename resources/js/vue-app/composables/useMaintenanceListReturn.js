import { computed, inject, unref } from 'vue';
import {
    getMaintenanceListReturnSource,
    maintenanceRecordsListIndexLocation,
} from '../utils/maintenanceRecordsListFilterState';
import { maintenanceDueListIndexLocation } from '../utils/maintenanceDueListFilterState';

export function useMaintenanceListReturn() {
    const injectedUser = inject('currentUser', null);
    const currentUser = computed(() => unref(injectedUser) ?? window.__AUTH_USER__ ?? null);

    const tenantId = computed(
        () => currentUser.value?.effective_tenant_id ?? currentUser.value?.company_id ?? null,
    );

    const returnSource = computed(() => getMaintenanceListReturnSource());

    const listReturnTo = computed(() => {
        if (returnSource.value === 'due') {
            return maintenanceDueListIndexLocation({}, tenantId.value);
        }

        return maintenanceRecordsListIndexLocation({}, tenantId.value);
    });

    const listReturnLabel = computed(() =>
        returnSource.value === 'due' ? 'Back to Due Services' : 'Back to Service Records',
    );

    function listReturnWithFlash(flashQuery = {}) {
        if (returnSource.value === 'due') {
            return maintenanceDueListIndexLocation(flashQuery, tenantId.value);
        }

        return maintenanceRecordsListIndexLocation(flashQuery, tenantId.value);
    }

    return {
        tenantId,
        returnSource,
        listReturnTo,
        listReturnLabel,
        listReturnWithFlash,
    };
}
