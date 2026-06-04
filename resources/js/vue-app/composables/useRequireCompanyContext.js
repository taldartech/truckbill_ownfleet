import { computed } from 'vue';
import { useRouter } from 'vue-router';
import { usePermissions } from './usePermissions';

/**
 * Super admin must "Enter" a company before tenant-scoped APIs work.
 */
export function useRequireCompanyContext() {
    const router = useRouter();
    const { user } = usePermissions();

    const needsCompanySelection = computed(() => Boolean(user.value?.requires_company_selection));

    const hasCompanyContext = computed(() => !needsCompanySelection.value);

    function redirectToCompanySelection(message = 'Select a company before continuing.') {
        return router.push({
            name: 'companies.index',
            query: { notice: message },
        });
    }

    function guardCompanyContext() {
        if (needsCompanySelection.value) {
            return redirectToCompanySelection();
        }

        return true;
    }

    function handleCompanyContextError(error, fallbackMessage = 'Unable to complete this action.') {
        const data = error?.response?.data;

        if (error?.response?.status === 403 && data?.redirect) {
            return redirectToCompanySelection(data.message ?? fallbackMessage);
        }

        return false;
    }

    return {
        user,
        needsCompanySelection,
        hasCompanyContext,
        guardCompanyContext,
        handleCompanyContextError,
        redirectToCompanySelection,
    };
}
