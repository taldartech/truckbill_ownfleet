import { reactive } from 'vue';

const state = reactive({
    show: false,
    title: 'Delete Record?',
    message: '',
    itemName: '',
    detailsTitle: 'Record Details',
    details: [],
    warningNotice: '',
    confirmLabel: 'Yes, Delete',
    remark: '',
    loading: false,
    errorMessage: '',
    onConfirm: null,
});

export function useDeleteConfirm() {
    function promptDelete({
        title = 'Delete Record?',
        message = '',
        itemName = '',
        details = [],
        detailsTitle = 'Record Details',
        warningNotice = '',
        confirmLabel = 'Yes, Delete',
        onConfirm,
    }) {
        state.title = title;
        state.itemName = itemName;
        state.message =
            message ||
            (itemName
                ? `Are you sure you want to delete ${itemName}? This action cannot be undone.`
                : 'Are you sure you want to delete this record? This action cannot be undone.');
        state.details = details || [];
        state.detailsTitle = detailsTitle;
        state.warningNotice = warningNotice || '';
        state.confirmLabel = confirmLabel;
        state.remark = '';
        state.errorMessage = '';
        state.loading = false;
        state.onConfirm = onConfirm;
        state.show = true;
    }

    function cancelDelete() {
        if (state.loading) {
            return;
        }

        state.show = false;
        state.onConfirm = null;
        state.errorMessage = '';
        state.remark = '';
        state.warningNotice = '';
        state.details = [];
    }

    async function confirmDelete() {
        if (state.loading) {
            return;
        }

        const trimmedRemark = state.remark ? state.remark.trim() : '';

        if (!trimmedRemark) {
            state.errorMessage = 'Please enter a remark explaining the reason for deletion.';
            return;
        }

        state.errorMessage = '';
        state.loading = true;

        try {
            if (typeof state.onConfirm === 'function') {
                await state.onConfirm(trimmedRemark);
            }

            state.show = false;
            state.onConfirm = null;
            state.remark = '';
            state.warningNotice = '';
            state.details = [];
        } catch (error) {
            const serverMessage =
                error?.response?.data?.errors?.deleted_reason?.[0] ??
                error?.response?.data?.errors?.remark?.[0] ??
                error?.response?.data?.errors?.message?.[0] ??
                error?.response?.data?.message ??
                'Unable to delete record. Please check your inputs and try again.';

            state.errorMessage = serverMessage;
            throw error;
        } finally {
            state.loading = false;
        }
    }

    return {
        state,
        promptDelete,
        cancelDelete,
        confirmDelete,
    };
}
