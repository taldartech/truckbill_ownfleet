<template>
    <AppConfirmModal
        :show="show"
        title="Delete Goods Type?"
        :message="`Are you sure you want to delete ${goodsType?.name ?? 'this goods type'}? This action cannot be undone.`"
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
        <div class="goods-type-delete-confirm-card">
            <div class="goods-type-delete-confirm-card__header">
                <i class="bi bi-box-seam"></i>

                <span>Goods Type Details</span>
            </div>

            <div class="goods-type-delete-confirm-card__body">
                <div class="goods-type-delete-confirm-row">
                    <span class="goods-type-delete-confirm-label">
                        Name
                    </span>

                    <strong>
                        {{ goodsType?.name ?? '—' }}
                    </strong>
                </div>

                <div class="goods-type-delete-confirm-row">
                    <span class="goods-type-delete-confirm-label">
                        Code
                    </span>

                    <strong>
                        {{ goodsType?.code ?? '—' }}
                    </strong>
                </div>

                <div class="goods-type-delete-confirm-row">
                    <span class="goods-type-delete-confirm-label">
                        Status
                    </span>

                    <strong>
                        {{ goodsType?.is_active ? 'Active' : 'Inactive' }}
                    </strong>
                </div>
            </div>
        </div>

        <div class="goods-type-delete-confirm-reason">
            <label
                for="goodsTypeDeleteReason"
                class="form-label"
            >
                Reason for deletion

                <span class="text-danger">*</span>
            </label>

            <textarea
                id="goodsTypeDeleteReason"
                v-model.trim="deleteReason"
                class="form-control"
                rows="3"
                placeholder="Enter the reason for deleting this goods type..."
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

    goodsType: {
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
.goods-type-delete-confirm-card {
    border: 1px solid #e2e8f0;
    border-radius: 0.85rem;
    overflow: hidden;
    background: #fff;
}

.goods-type-delete-confirm-card__header {
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

.goods-type-delete-confirm-card__header i {
    color: #64748b;
}

.goods-type-delete-confirm-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 0.7rem 0.9rem;
    border-bottom: 1px solid #e2e8f0;
}

.goods-type-delete-confirm-row:last-child {
    border-bottom: 0;
}

.goods-type-delete-confirm-label {
    color: #64748b;
    font-size: 0.82rem;
}

.goods-type-delete-confirm-row strong {
    color: #1e293b;
    font-size: 0.85rem;
    text-align: right;
}

.goods-type-delete-confirm-reason {
    margin-top: 1rem;
}

.goods-type-delete-confirm-reason .form-label {
    font-size: 0.85rem;
    font-weight: 600;
    color: #334155;
}

.goods-type-delete-confirm-reason textarea {
    resize: vertical;
    min-height: 90px;
}
</style>