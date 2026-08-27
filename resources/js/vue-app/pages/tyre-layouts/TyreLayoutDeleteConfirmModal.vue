<template>
    <AppConfirmModal
        :show="show"
        title="Delete Tyre Layout?"
        :message="`Are you sure you want to delete ${tyreLayout?.name ?? 'this tyre layout'}? This action cannot be undone.`"
        icon="bi bi-trash3"
        icon-tone="danger"
        :loading="deleting"
        cancel-label="Cancel"
        confirm-label="Yes, Delete"
        loading-label="Deleting…"
        confirm-variant="danger"
        confirm-icon="bi bi-trash3"
        @cancel="handleCancel"
        @confirm="handleConfirm"
    >
        <div class="tyre-layout-delete-confirm-card">
            <div class="tyre-layout-delete-confirm-card__header">
                <i class="bi bi-diagram-3"></i>
                <span>Tyre Layout Details</span>
            </div>

            <div class="tyre-layout-delete-confirm-card__body">
                <div class="tyre-layout-delete-confirm-row">
                    <span class="tyre-layout-delete-confirm-label">
                        Name
                    </span>

                    <strong>
                        {{ tyreLayout?.name ?? '—' }}
                    </strong>
                </div>

                <div class="tyre-layout-delete-confirm-row">
                    <span class="tyre-layout-delete-confirm-label">
                        Code
                    </span>

                    <strong>
                        {{ tyreLayout?.code ?? '—' }}
                    </strong>
                </div>

                <div class="tyre-layout-delete-confirm-row">
                    <span class="tyre-layout-delete-confirm-label">
                        Layout Type
                    </span>

                    <strong>
                        {{
                            tyreLayout?.layout_type === 'legacy_dual_rear'
                                ? 'Legacy Dual Rear'
                                : 'Standard'
                        }}
                    </strong>
                </div>

                <div class="tyre-layout-delete-confirm-row">
                    <span class="tyre-layout-delete-confirm-label">
                        Position Count
                    </span>

                    <strong>
                        {{ positionCount }}
                    </strong>
                </div>

                <div class="tyre-layout-delete-confirm-row">
                    <span class="tyre-layout-delete-confirm-label">
                        Status
                    </span>

                    <strong>
                        {{ tyreLayout?.is_active ? 'Active' : 'Inactive' }}
                    </strong>
                </div>
            </div>
        </div>

        <!-- Deletion Reason -->
        <div class="tyre-layout-delete-confirm-reason">
            <label
                for="tyreLayoutDeleteReason"
                class="form-label"
            >
                Reason for deletion
                <span class="text-danger">*</span>
            </label>

            <textarea
                id="tyreLayoutDeleteReason"
                v-model.trim="deleteReason"
                class="form-control"
                rows="3"
                placeholder="Enter the reason for deleting this tyre layout..."
                :disabled="deleting"
            ></textarea>

            <div
                v-if="showReasonError"
                class="text-danger small mt-1"
            >
                Please enter a reason for deletion.
            </div>
        </div>
    </AppConfirmModal>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import AppConfirmModal from '../../components/ui/AppConfirmModal.vue';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },

    tyreLayout: {
        type: Object,
        default: null,
    },

    deleting: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits([
    'cancel',
    'confirm',
]);

const deleteReason = ref('');
const attemptedConfirm = ref(false);

const positionCount = computed(() => {
    if (!props.tyreLayout) {
        return '—';
    }

    if (props.tyreLayout.layout_type === 'legacy_dual_rear') {
        return 7;
    }

    const steer = (props.tyreLayout.steer_axles ?? 0) * 2;
    const dual = (props.tyreLayout.dual_axles?.length ?? 0) * 4;
    const single = (props.tyreLayout.single_axles?.length ?? 0) * 2;
    const spare = props.tyreLayout.spare_count ?? 0;

    return steer + dual + single + spare;
});

const showReasonError = computed(() =>
    attemptedConfirm.value && !deleteReason.value.trim(),
);

function handleConfirm() {
    attemptedConfirm.value = true;

    if (!deleteReason.value.trim()) {
        return;
    }

    emit('confirm', {
        deleted_reason: deleteReason.value.trim(),
    });
}

function handleCancel() {
    if (props.deleting) {
        return;
    }

    emit('cancel');
}

watch(
    () => props.show,
    (isOpen) => {
        if (isOpen) {
            deleteReason.value = '';
            attemptedConfirm.value = false;
        }
    },
);
</script>

<style scoped>
.tyre-layout-delete-confirm-card {
    border: 1px solid #e2e8f0;
    border-radius: 0.85rem;
    overflow: hidden;
    background: #fff;
}

.tyre-layout-delete-confirm-card__header {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.75rem 0.9rem;
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
    font-size: 0.9rem;
    font-weight: 700;
    color: #1e293b;
}

.tyre-layout-delete-confirm-card__header i {
    color: #64748b;
}

.tyre-layout-delete-confirm-card__body {
    padding: 0;
}

.tyre-layout-delete-confirm-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 0.7rem 0.9rem;
    border-bottom: 1px solid #e2e8f0;
}

.tyre-layout-delete-confirm-row:last-child {
    border-bottom: 0;
}

.tyre-layout-delete-confirm-label {
    color: #64748b;
    font-size: 0.82rem;
}

.tyre-layout-delete-confirm-row strong {
    color: #1e293b;
    font-size: 0.85rem;
    text-align: right;
}

/* Deletion reason */

.tyre-layout-delete-reason {
    margin-top: 1rem;
}

.tyre-layout-delete-reason__label {
    display: block;
    margin-bottom: 0.45rem;
    font-size: 0.85rem;
    font-weight: 600;
    color: #334155;
}

.tyre-layout-delete-reason textarea {
    resize: vertical;
}

.tyre-layout-delete-reason__error {
    margin-top: 0.4rem;
    font-size: 0.8rem;
    color: #dc2626;
}

.tyre-layout-delete-confirm-reason {
    margin-top: 1rem;
}

.tyre-layout-delete-confirm-reason .form-label {
    font-size: 0.85rem;
    font-weight: 600;
    color: #334155;
}

.tyre-layout-delete-confirm-reason textarea {
    resize: vertical;
    min-height: 90px;
}
</style>