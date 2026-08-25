<template>
    <AppConfirmModal
        :show="show"
        title="Delete Driver?"
        :message="`Are you sure you want to delete ${driver?.name ?? 'this driver'}? This action cannot be undone.`"
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
        <div class="driver-delete-confirm-card">
            <div class="driver-delete-confirm-card__header">
                <i class="bi bi-person"></i>
                <span>Driver Details</span>
            </div>

            <div class="driver-delete-confirm-card__body">
                <div class="driver-delete-confirm-row">
                    <span class="driver-delete-confirm-label">
                        Name
                    </span>

                    <strong>
                        {{ driver?.name ?? '—' }}
                    </strong>
                </div>

                <div class="driver-delete-confirm-row">
                    <span class="driver-delete-confirm-label">
                        Mobile
                    </span>

                    <strong>
                        {{ driver?.phone ?? '—' }}
                    </strong>
                </div>

                <div class="driver-delete-confirm-row">
                    <span class="driver-delete-confirm-label">
                        License Number
                    </span>

                    <strong>
                        {{ driver?.license_number ?? '—' }}
                    </strong>
                </div>

                <div class="driver-delete-confirm-row">
                    <span class="driver-delete-confirm-label">
                        Associated Truck
                    </span>

                    <strong>
                        {{
                            driver?.current_primary_assignment?.vehicle
                                ?.registration_number ?? 'Not assigned'
                        }}
                    </strong>
                </div>

                <div class="driver-delete-confirm-row">
                    <span class="driver-delete-confirm-label">
                        Status
                    </span>

                    <strong>
                        {{ driver?.is_active ? 'Active' : 'Inactive' }}
                    </strong>
                </div>
            </div>
        </div>

        <div class="driver-delete-confirm-reason">
            <label for="driverDeleteReason" class="form-label">
                Reason for deletion
                <span class="text-danger">*</span>
            </label>

            <textarea
                id="driverDeleteReason"
                v-model.trim="deleteReason"
                class="form-control"
                rows="3"
                placeholder="Enter the reason for deleting this driver..."
                :disabled="deleting"
            ></textarea>

            <div v-if="showReasonError" class="text-danger small mt-1">
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

    driver: {
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
.driver-delete-confirm-card {
    border: 1px solid #e2e8f0;
    border-radius: 0.85rem;
    overflow: hidden;
    background: #fff;
}

.driver-delete-confirm-card__header {
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

.driver-delete-confirm-card__header i {
    color: #64748b;
}

.driver-delete-confirm-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 0.7rem 0.9rem;
    border-bottom: 1px solid #e2e8f0;
}

.driver-delete-confirm-row:last-child {
    border-bottom: 0;
}

.driver-delete-confirm-label {
    color: #64748b;
    font-size: 0.82rem;
}

.driver-delete-confirm-row strong {
    color: #1e293b;
    font-size: 0.85rem;
    text-align: right;
}

.driver-delete-confirm-reason {
    margin-top: 1rem;
}

.driver-delete-confirm-reason .form-label {
    font-size: 0.85rem;
    font-weight: 600;
    color: #334155;
}

.driver-delete-confirm-reason textarea {
    resize: vertical;
    min-height: 90px;
}
</style>