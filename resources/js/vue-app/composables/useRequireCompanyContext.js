import { useRouter } from 'vue-router';
import { usePermissions } from './usePermissions';

/**
 * Super admin must "Enter" a company before tenant-scoped APIs work.
 */
export function useRequireCompanyContext() {
    const router = useRouter();
    const { user } = usePermissions();

    function needsCompanySelection() {
        return Boolean(user.value?.requires_company_selection);
    }

    function redirectToCompanySelection(message = 'Select a company before continuing.') {
        return router.push({
            name: 'companies.index',
            query: { notice: message },
        });
    }

    function guardCompanyContext() {
        if (needsCompanySelection()) {
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
        guardCompanyContext,
        handleCompanyContextError,
        redirectToCompanySelection,
    };
}
