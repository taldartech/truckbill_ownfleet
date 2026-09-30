import { inject, ref } from 'vue';
import { useRouter } from 'vue-router';
import { fetchCurrentUser } from '../api';
import { enterBranch } from '../api/branches';
import { useAppToast } from './useAppToast';

/**
 * Enters a branch (and its company) from the group dashboard, then opens the requested page
 * inside that company context.
 */
export function useEnterBranch() {
    const router = useRouter();
    const toast = useAppToast();
    const currentUser = inject('currentUser', ref(null));
    const enteringBranchId = ref(null);

    async function enterBranchAndOpen(branchId, { label = '', to = { name: 'dashboard' } } = {}) {
        if (!branchId || enteringBranchId.value) {
            return;
        }

        enteringBranchId.value = branchId;

        try {
            const data = await enterBranch(branchId);
            const user = data?.user ?? (await fetchCurrentUser());

            currentUser.value = user;
            window.__AUTH_USER__ = user;

            if (label) {
                toast.success(`Entered ${label}.`);
            }

            await router.push(to);
        } catch (error) {
            toast.error(error.response?.data?.message ?? 'Could not enter branch.');
        } finally {
            enteringBranchId.value = null;
        }
    }

    return { enteringBranchId, enterBranchAndOpen };
}
