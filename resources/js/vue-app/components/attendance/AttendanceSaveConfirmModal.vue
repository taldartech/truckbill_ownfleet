<template>
    <div
        v-if="show && summary"
        class="modal fade show d-block attendance-save-confirm-modal"
        tabindex="-1"
        style="position: fixed; inset: 0; z-index: 1060; background: rgba(15, 23, 42, 0.55)"
        @click.self="$emit('cancel')"
    >
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content attendance-save-confirm-modal__content border-0 shadow-lg">
                <button
                    type="button"
                    class="btn-close attendance-save-confirm-modal__close"
                    aria-label="Close"
                    @click="$emit('cancel')"
                ></button>

                <div class="modal-body p-4 pt-5">
                    <div class="text-center mb-3">
                        <div
                            class="attendance-save-confirm-modal__icon"
                            :class="summary.isDelete ? 'attendance-save-confirm-modal__icon--danger' : ''"
                        >
                            <i class="bi" :class="summary.isDelete ? 'bi-trash' : 'bi-question-circle'"></i>
                        </div>
                        <h5 class="attendance-save-confirm-modal__title mb-1">{{ summary.title }}</h5>
                        <p class="text-muted small mb-0">{{ summary.message }}</p>
                    </div>

                    <div
                        v-if="summary.hasLock"
                        class="attendance-save-confirm-banner mb-3"
                        :class="summary.isDelete ? 'attendance-save-confirm-banner--warning' : ''"
                    >
                        <i class="bi" :class="summary.isDelete ? 'bi-unlock' : 'bi-lock'"></i>
                        <span>
                            {{
                                summary.isDelete
                                    ? 'Exception will be removed and day returns to default Present (P).'
                                    : 'Present marks are locked after save and count as admin-confirmed.'
                            }}
                        </span>
                    </div>

                    <div class="attendance-save-confirm-card">
                        <div class="attendance-save-confirm-card__header">
                            <i class="bi bi-calendar3"></i>
                            <span>{{ summary.dateLabel }}</span>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0 attendance-save-confirm-table">
                                <thead>
                                    <tr>
                                        <th>Truck</th>
                                        <th>Driver</th>
                                        <th>Change</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(row, index) in summary.rows" :key="`${row.truck}-${index}`">
                                        <td>
                                            <div class="fw-semibold">{{ row.truck }}</div>
                                            <div class="text-muted small">{{ row.actionLabel }}</div>
                                        </td>
                                        <td class="text-muted small">{{ row.driver }}</td>
                                        <td>
                                            <div
                                                class="attendance-save-confirm-change"
                                                :class="{ 'attendance-save-confirm-change--delete': row.isDelete }"
                                            >
                                                <div v-if="row.changedFields.includes('Status')" class="mb-1">
                                                    <span class="text-muted small">Status:</span>
                                                    <span class="ms-1">{{ row.previousStatusLabel }}</span>
                                                    <i class="bi bi-arrow-right mx-1 text-muted"></i>
                                                    <strong>{{ row.statusLabel }}</strong>
                                                </div>
                                                <div
                                                    v-if="
                                                        row.changedFields.includes('Substitute')
                                                        || (row.substitute && row.action !== 'lock_present')
                                                    "
                                                    class="mb-1"
                                                >
                                                    <span class="text-muted small">Substitute:</span>
                                                    <template v-if="row.previousSubstitute">
                                                        <span class="ms-1">{{ row.previousSubstitute }}</span>
                                                        <i class="bi bi-arrow-right mx-1 text-muted"></i>
                                                    </template>
                                                    <strong>{{ row.substitute ?? '—' }}</strong>
                                                </div>
                                                <div
                                                    v-if="row.changedFields.includes('Remarks') || row.remarks"
                                                    class="mb-0"
                                                >
                                                    <span class="text-muted small">Remarks:</span>
                                                    <template v-if="row.previousRemarks">
                                                        <span class="ms-1">{{ row.previousRemarks }}</span>
                                                        <i class="bi bi-arrow-right mx-1 text-muted"></i>
                                                    </template>
                                                    <strong>{{ row.remarks ?? '—' }}</strong>
                                                </div>
                                                <div v-if="row.locksData && !row.isDelete" class="mt-1">
                                                    <span class="badge bg-success-subtle text-success border border-success-subtle">
                                                        Locks Present
                                                    </span>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="modal-footer border-0 justify-content-center gap-2 pb-4 pt-0">
                    <button type="button" class="btn btn-outline-secondary px-4" @click="$emit('cancel')">
                        <i class="bi bi-x-lg me-1"></i>
                        Cancel
                    </button>
                    <button
                        type="button"
                        class="btn px-4 fw-semibold"
                        :class="summary.isDelete ? 'btn-danger' : 'btn-success'"
                        :disabled="saving"
                        @click="$emit('confirm')"
                    >
                        <span
                            v-if="saving"
                            class="spinner-border spinner-border-sm me-1"
                            role="status"
                            aria-hidden="true"
                        ></span>
                        <i v-else class="bi me-1" :class="summary.isDelete ? 'bi-trash' : 'bi-check-lg'"></i>
                        {{ summary.confirmLabel }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
defineProps({
    show: { type: Boolean, default: false },
    summary: { type: Object, default: null },
    saving: { type: Boolean, default: false },
});

defineEmits(['cancel', 'confirm']);
</script>

<style scoped>
.attendance-save-confirm-modal__content {
    border-radius: 1rem;
    overflow: hidden;
}

.attendance-save-confirm-modal__close {
    position: absolute;
    top: 1rem;
    right: 1rem;
    z-index: 2;
}

.attendance-save-confirm-modal__icon {
    width: 4rem;
    height: 4rem;
    margin: 0 auto 0.75rem;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(45, 140, 255, 0.12);
    color: var(--brand-accent-blue, #2d8cff);
    font-size: 2rem;
    line-height: 1;
}

.attendance-save-confirm-modal__icon--danger {
    background: rgba(220, 53, 69, 0.12);
    color: #dc3545;
}

.attendance-save-confirm-modal__title {
    font-weight: 700;
    color: #1e293b;
}

.attendance-save-confirm-banner {
    display: flex;
    align-items: flex-start;
    gap: 0.5rem;
    padding: 0.65rem 0.85rem;
    border-radius: 0.75rem;
    background: rgba(25, 135, 84, 0.08);
    color: #146c43;
    font-size: 0.9rem;
    font-weight: 600;
}

.attendance-save-confirm-banner--warning {
    background: rgba(255, 193, 7, 0.12);
    color: #856404;
}

.attendance-save-confirm-card {
    border: 1px solid #e2e8f0;
    border-radius: 0.85rem;
    overflow: hidden;
}

.attendance-save-confirm-card__header {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1rem;
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
    font-weight: 700;
    color: #334155;
}

.attendance-save-confirm-table th {
    font-size: 0.8rem;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    color: #64748b;
    background: #fff;
}

.attendance-save-confirm-change {
    font-size: 0.9rem;
}

.attendance-save-confirm-change--delete strong {
    color: #146c43;
}
</style>
