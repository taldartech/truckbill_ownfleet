<template>
    <AppConfirmModal
        :show="state.show"
        :title="state.title"
        :message="state.message"
        icon="bi bi-trash3"
        icon-tone="danger"
        :loading="state.loading"
        cancel-label="Cancel"
        :confirm-label="state.confirmLabel"
        loading-label="Deleting…"
        confirm-variant="danger"
        confirm-icon="bi bi-trash3"
        @cancel="cancelDelete"
        @confirm="confirmDelete"
    >
        <!-- Optional Details Summary Card -->
        <div v-if="filteredDetails.length" class="app-confirm-card mb-3">
            <div class="app-confirm-card__header">
                <i class="bi bi-card-list"></i>
                <span>{{ state.detailsTitle || 'Record Details' }}</span>
            </div>
            <div class="app-confirm-card__body">
                <div
                    v-for="(item, index) in filteredDetails"
                    :key="index"
                    class="app-confirm-row"
                >
                    <span class="app-confirm-row__label">{{ item.label }}</span>
                    <strong class="app-confirm-row__value">{{ item.value ?? '—' }}</strong>
                </div>
            </div>
        </div>

        <!-- Optional Warning Notice -->
        <div v-if="state.warningNotice" class="alert alert-warning py-2 px-3 small mb-3">
            <i class="bi bi-exclamation-triangle me-1"></i>
            {{ state.warningNotice }}
        </div>

        <!-- Mandatory Remark Textarea -->
        <div class="text-start">
            <label for="appDeleteRemark" class="form-label fw-semibold">
                Remark <span class="text-danger">*</span>
            </label>
            <textarea
                id="appDeleteRemark"
                v-model="state.remark"
                class="form-control"
                :class="{ 'is-invalid': Boolean(state.errorMessage) }"
                rows="3"
                placeholder="Enter remark or reason for deletion..."
                :disabled="state.loading"
                @input="onRemarkInput"
            ></textarea>
            <div v-if="state.errorMessage" class="invalid-feedback d-block">
                {{ state.errorMessage }}
            </div>
        </div>
    </AppConfirmModal>
</template>

<script setup>
import { computed } from 'vue';
import AppConfirmModal from './AppConfirmModal.vue';
import { useDeleteConfirm } from '../../composables/useDeleteConfirm';

const { state, cancelDelete, confirmDelete } = useDeleteConfirm();

const filteredDetails = computed(() => {
    if (!Array.isArray(state.details)) {
        return [];
    }

    return state.details.filter(
        (item) =>
            item &&
            item.label &&
            item.value !== undefined &&
            item.value !== null &&
            item.value !== '',
    );
});

function onRemarkInput() {
    if (state.errorMessage && state.remark && state.remark.trim()) {
        state.errorMessage = '';
    }
}
</script>
