import { ref } from 'vue';
import { buildMasterCrudSaveConfirmViewModel } from '../utils/masterCrudSaveConfirm';

export function useMasterCrudSaveConfirm() {
    const showSaveConfirm = ref(false);
    const saveConfirmSummary = ref(null);
    const pendingPayload = ref(null);

    function promptSaveConfirm({ payload, entityLabel, mode, fields }) {
        pendingPayload.value = payload;
        saveConfirmSummary.value = buildMasterCrudSaveConfirmViewModel({
            entityLabel,
            mode,
            fields,
        });
        showSaveConfirm.value = true;
    }

    function cancelSaveConfirm() {
        showSaveConfirm.value = false;
        pendingPayload.value = null;
        saveConfirmSummary.value = null;
    }

    function finishSaveConfirm() {
        showSaveConfirm.value = false;
        pendingPayload.value = null;
        saveConfirmSummary.value = null;
    }

    return {
        showSaveConfirm,
        saveConfirmSummary,
        pendingPayload,
        promptSaveConfirm,
        cancelSaveConfirm,
        finishSaveConfirm,
    };
}
