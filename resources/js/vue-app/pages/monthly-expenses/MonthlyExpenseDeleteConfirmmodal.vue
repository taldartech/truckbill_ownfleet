<template>
    <AppConfirmModal
        :show="show"
        title="Delete Monthly Expense?"
        :message="`Are you sure you want to delete ${expense?.particulars ?? 'this monthly expense'}? This action cannot be undone.`"
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
        <div class="monthly-expense-delete-confirm-card">
            <div class="monthly-expense-delete-confirm-card__header">
                <i class="bi bi-wallet2"></i>
                <span>Expense Details</span>
            </div>

            <div class="monthly-expense-delete-confirm-card__body">
                <div class="monthly-expense-delete-confirm-row">
                    <span class="monthly-expense-delete-confirm-label">
                        Month
                    </span>

                    <strong>
                        {{ formatMonth(expense?.month) }}
                    </strong>
                </div>

                <div class="monthly-expense-delete-confirm-row">
                    <span class="monthly-expense-delete-confirm-label">
                        Particulars
                    </span>

                    <strong>
                        {{ expense?.particulars ?? '—' }}
                    </strong>
                </div>

                <div class="monthly-expense-delete-confirm-row">
                    <span class="monthly-expense-delete-confirm-label">
                        Amount
                    </span>

                    <strong>
                        {{ formatAmount(expense?.amount) }}
                    </strong>
                </div>
            </div>
        </div>

        <div class="monthly-expense-delete-confirm-reason">
            <label
                for="monthlyExpenseDeleteReason"
                class="form-label"
            >
                Reason for deletion
                <span class="text-danger">*</span>
            </label>

            <textarea
                id="monthlyExpenseDeleteReason"
                v-model.trim="deleteReason"
                class="form-control"
                rows="3"
                placeholder="Enter the reason for deleting this monthly expense..."
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

    expense: {
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

function formatAmount(amount) {
    return new Intl.NumberFormat('en-IN', {
        style: 'currency',
        currency: 'INR',
        maximumFractionDigits: 2,
    }).format(Number(amount ?? 0));
}

function formatMonth(month) {
    if (!month || !/^\d{4}-\d{2}$/.test(month)) {
        return month || '—';
    }

    const [year, monthPart] = month.split('-');

    const date = new Date(
        Number(year),
        Number(monthPart) - 1,
        1,
    );

    return date.toLocaleDateString(
        'en-IN',
        {
            month: 'short',
            year: 'numeric',
        },
    );
}

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
.monthly-expense-delete-confirm-card {
    border: 1px solid #e2e8f0;
    border-radius: 0.85rem;
    overflow: hidden;
    background: #fff;
}

.monthly-expense-delete-confirm-card__header {
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

.monthly-expense-delete-confirm-card__header i {
    color: #64748b;
}

.monthly-expense-delete-confirm-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 0.7rem 0.9rem;
    border-bottom: 1px solid #e2e8f0;
}

.monthly-expense-delete-confirm-row:last-child {
    border-bottom: 0;
}

.monthly-expense-delete-confirm-label {
    color: #64748b;
    font-size: 0.82rem;
}

.monthly-expense-delete-confirm-row strong {
    color: #1e293b;
    font-size: 0.85rem;
    text-align: right;
}

.monthly-expense-delete-confirm-reason {
    margin-top: 1rem;
}

.monthly-expense-delete-confirm-reason .form-label {
    font-size: 0.85rem;
    font-weight: 600;
    color: #334155;
}

.monthly-expense-delete-confirm-reason textarea {
    resize: vertical;
    min-height: 90px;
}
</style>