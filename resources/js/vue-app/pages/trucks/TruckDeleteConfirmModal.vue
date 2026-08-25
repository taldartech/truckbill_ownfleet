<template>
    <AppConfirmModal :show="show" title="Delete Truck?"
        :message="`Are you sure you want to delete ${vehicle?.registration_number ?? 'this truck'}? This action cannot be undone.`"
        icon="bi bi-trash3" icon-tone="danger" :loading="deleting" cancel-label="Cancel" confirm-label="Yes, Delete"
        loading-label="Deleting…" confirm-variant="danger" confirm-icon="bi bi-trash3" @cancel="handleCancel"
@confirm="handleConfirm">
        <div class="truck-delete-confirm-card">
            <div class="truck-delete-confirm-card__header">
                <i class="bi bi-truck"></i>
                <span>Truck Details</span>
            </div>

            <div class="truck-delete-confirm-card__body">
                <div class="truck-delete-confirm-row">
                    <span class="truck-delete-confirm-label">
                        Registration
                    </span>

                    <strong>
                        {{ vehicle?.registration_number ?? '—' }}
                    </strong>
                </div>

                <div class="truck-delete-confirm-row">
                    <span class="truck-delete-confirm-label">
                        Truck Type
                    </span>

                    <strong>
                        {{
                            vehicle?.vehicle_type_label
                            ?? vehicle?.vehicle_type
                            ?? '—'
                        }}
                    </strong>
                </div>

                <div class="truck-delete-confirm-row">
                    <span class="truck-delete-confirm-label">
                        Make
                    </span>

                    <strong>
                        {{ vehicle?.make ?? '—' }}
                    </strong>
                </div>

                <div class="truck-delete-confirm-row">
                    <span class="truck-delete-confirm-label">
                        Model
                    </span>

                    <strong>
                        {{ vehicle?.model ?? '—' }}
                    </strong>
                </div>

                <div class="truck-delete-confirm-row">
                    <span class="truck-delete-confirm-label">
                        Driver
                    </span>

                    <strong>
                        {{
                            vehicle?.current_primary_assignment?.driver?.name
                            ?? 'Not assigned'
                        }}
                    </strong>
                </div>

                <div class="truck-delete-confirm-row">
                    <span class="truck-delete-confirm-label">
                        Meter Reading
                    </span>

                    <strong>
                        {{ vehicle?.odometer_km ?? 0 }} KM
                    </strong>
                </div>
            </div>
        </div>

        <!-- Deletion Reason -->
        <div class="truck-delete-confirm-reason">
            <label for="truckDeleteReason" class="form-label">
                Reason for deletion
                <span class="text-danger">*</span>
            </label>

            <textarea id="truckDeleteReason" v-model.trim="deleteReason" class="form-control" rows="3"
                placeholder="Enter the reason for deleting this truck..." :disabled="deleting"></textarea>

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

    vehicle: {
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
.truck-delete-confirm-card {
    border: 1px solid #e2e8f0;
    border-radius: 0.85rem;
    overflow: hidden;
    background: #fff;
}

.truck-delete-confirm-card__header {
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

.truck-delete-confirm-card__header i {
    color: #64748b;
}

.truck-delete-confirm-card__body {
    padding: 0;
}

.truck-delete-confirm-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 0.7rem 0.9rem;
    border-bottom: 1px solid #e2e8f0;
}

.truck-delete-confirm-row:last-child {
    border-bottom: 0;
}

.truck-delete-confirm-label {
    color: #64748b;
    font-size: 0.82rem;
}

.truck-delete-confirm-row strong {
    color: #1e293b;
    font-size: 0.85rem;
    text-align: right;
}

/* Deletion reason */

.truck-delete-reason {
    margin-top: 1rem;
}

.truck-delete-reason__label {
    display: block;
    margin-bottom: 0.45rem;
    font-size: 0.85rem;
    font-weight: 600;
    color: #334155;
}

.truck-delete-reason textarea {
    resize: vertical;
}

.truck-delete-reason__error {
    margin-top: 0.4rem;
    font-size: 0.8rem;
    color: #dc2626;
}


.truck-delete-confirm-reason {
    margin-top: 1rem;
}

.truck-delete-confirm-reason .form-label {
    font-size: 0.85rem;
    font-weight: 600;
    color: #334155;
}

.truck-delete-confirm-reason textarea {
    resize: vertical;
    min-height: 90px;
}
</style>