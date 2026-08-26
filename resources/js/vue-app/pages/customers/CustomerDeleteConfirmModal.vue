<template>
    <AppConfirmModal
        :show="show"
        title="Delete Customer?"
        :message="`Are you sure you want to delete ${customer?.name ?? 'this customer'}? This action cannot be undone.`"
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
        <div class="customer-delete-confirm-card">
            <div class="customer-delete-confirm-card__header">
                <i class="bi bi-person"></i>
                <span>Customer Details</span>
            </div>

            <div class="customer-delete-confirm-card__body">
                <div class="customer-delete-confirm-row">
                    <span class="customer-delete-confirm-label">
                        Name
                    </span>

                    <strong>
                        {{ customer?.name ?? '—' }}
                    </strong>
                </div>

                <div class="customer-delete-confirm-row">
                    <span class="customer-delete-confirm-label">
                        Contact
                    </span>

                    <strong>
                        {{ customer?.contact_number ?? '—' }}
                    </strong>
                </div>

                <div class="customer-delete-confirm-row">
                    <span class="customer-delete-confirm-label">
                        GST Number
                    </span>

                    <strong>
                        {{ customer?.gst_number ?? '—' }}
                    </strong>
                </div>

                <div class="customer-delete-confirm-row">
                    <span class="customer-delete-confirm-label">
                        PAN Number
                    </span>

                    <strong>
                        {{ customer?.pan_number ?? '—' }}
                    </strong>
                </div>

                <div class="customer-delete-confirm-row">
                    <span class="customer-delete-confirm-label">
                        City
                    </span>

                    <strong>
                        {{ customer?.city_name ?? customer?.city?.name ?? '—' }}
                    </strong>
                </div>

                <div class="customer-delete-confirm-row">
                    <span class="customer-delete-confirm-label">
                        State
                    </span>

                    <strong>
                        {{ customer?.state_name ?? customer?.state?.name ?? '—' }}
                    </strong>
                </div>

                <div class="customer-delete-confirm-row">
                    <span class="customer-delete-confirm-label">
                        Customer Type
                    </span>

                    <strong>
                        {{ customer?.is_broker ? 'Broker' : 'Customer' }}
                    </strong>
                </div>
            </div>
        </div>

        <div class="customer-delete-confirm-reason">
            <label
                for="customerDeleteReason"
                class="form-label"
            >
                Reason for deletion
                <span class="text-danger">*</span>
            </label>

            <textarea
                id="customerDeleteReason"
                v-model.trim="deleteReason"
                class="form-control"
                rows="3"
                placeholder="Enter the reason for deleting this customer..."
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

    customer: {
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
.customer-delete-confirm-card {
    border: 1px solid #e2e8f0;
    border-radius: 0.85rem;
    overflow: hidden;
    background: #fff;
}

.customer-delete-confirm-card__header {
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

.customer-delete-confirm-card__header i {
    color: #64748b;
}

.customer-delete-confirm-card__body {
    padding: 0;
}

.customer-delete-confirm-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 0.7rem 0.9rem;
    border-bottom: 1px solid #e2e8f0;
}

.customer-delete-confirm-row:last-child {
    border-bottom: 0;
}

.customer-delete-confirm-label {
    color: #64748b;
    font-size: 0.82rem;
}

.customer-delete-confirm-row strong {
    color: #1e293b;
    font-size: 0.85rem;
    text-align: right;
}

.customer-delete-confirm-reason {
    margin-top: 1rem;
}

.customer-delete-confirm-reason .form-label {
    font-size: 0.85rem;
    font-weight: 600;
    color: #334155;
}

.customer-delete-confirm-reason textarea {
    resize: vertical;
    min-height: 90px;
}
</style>