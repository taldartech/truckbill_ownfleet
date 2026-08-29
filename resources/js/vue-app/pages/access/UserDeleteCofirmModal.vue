<template>
    <AppConfirmModal
        :show="show"
        :title="`Delete ${userTypeLabel}?`"
        :message="`Are you sure you want to delete ${user?.name ?? 'this user'}? This action cannot be undone.`"
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
        <div class="user-delete-confirm-card">
            <div class="user-delete-confirm-card__header">
                <i class="bi bi-person"></i>
                <span>{{ userTypeLabel }} Details</span>
            </div>

            <div class="user-delete-confirm-card__body">
                <div class="user-delete-confirm-row">
                    <span class="user-delete-confirm-label">
                        Name
                    </span>

                    <strong>
                        {{ user?.name ?? '—' }}
                    </strong>
                </div>

                <div class="user-delete-confirm-row">
                    <span class="user-delete-confirm-label">
                        Email
                    </span>

                    <strong>
                        {{ user?.email ?? '—' }}
                    </strong>
                </div>

                <div class="user-delete-confirm-row">
                    <span class="user-delete-confirm-label">
                        Role
                    </span>

                    <strong>
                        {{ user?.role_label ?? '—' }}
                    </strong>
                </div>

                <div class="user-delete-confirm-row">
                    <span class="user-delete-confirm-label">
                        Company
                    </span>

                    <strong>
                        {{ user?.tenant_name ?? '—' }}
                    </strong>
                </div>

                <div class="user-delete-confirm-row">
                    <span class="user-delete-confirm-label">
                        Branch
                    </span>

                    <strong>
                        {{ user?.branch_name ?? '—' }}
                    </strong>
                </div>
            </div>
        </div>

        <!-- Deletion Reason -->
        <div class="user-delete-confirm-reason">
            <label
                for="userDeleteReason"
                class="form-label"
            >
                Reason for deletion
                <span class="text-danger">*</span>
            </label>

            <textarea
                id="userDeleteReason"
                v-model.trim="deleteReason"
                class="form-control"
                rows="3"
                placeholder="Enter the reason for deleting this user..."
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

    user: {
        type: Object,
        default: null,
    },

    deleting: {
        type: Boolean,
        default: false,
    },

    userTypeLabel: {
        type: String,
        default: 'User',
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
.user-delete-confirm-card {
    border: 1px solid #e2e8f0;
    border-radius: 0.85rem;
    overflow: hidden;
    background: #fff;
}

.user-delete-confirm-card__header {
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

.user-delete-confirm-card__header i {
    color: #64748b;
}

.user-delete-confirm-card__body {
    padding: 0;
}

.user-delete-confirm-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 0.7rem 0.9rem;
    border-bottom: 1px solid #e2e8f0;
}

.user-delete-confirm-row:last-child {
    border-bottom: 0;
}

.user-delete-confirm-label {
    color: #64748b;
    font-size: 0.82rem;
}

.user-delete-confirm-row strong {
    color: #1e293b;
    font-size: 0.85rem;
    text-align: right;
}

.user-delete-confirm-reason {
    margin-top: 1rem;
}

.user-delete-confirm-reason .form-label {
    font-size: 0.85rem;
    font-weight: 600;
    color: #334155;
}

.user-delete-confirm-reason textarea {
    resize: vertical;
    min-height: 90px;
}
</style>