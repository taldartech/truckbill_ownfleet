<template>
    <div :class="inline ? 'doc-form-inline' : 'doc-form-grid'">
        <div v-if="!hideType" class="doc-form-field">
            <label v-if="!inline" class="form-label small mb-1">Document Type</label>
            <div class="doc-type-picker">
                <SearchableSelect
                    v-model="localForm.document_type"
                    :items="documentTypes"
                    placeholder="Select type"
                    search-placeholder="Search document type…"
                    attach-dropdown-to-body
                    :invalid="hasFieldError(fieldErrors, 'document_type')"
                    :error-message="fieldErrorMessage(fieldErrors, 'document_type')"
                    :disabled="documentTypeDisabled"
                />
                <button
                    v-if="showTypeQuickAdd"
                    type="button"
                    class="btn btn-outline-success btn-sm doc-type-picker__add"
                    title="Quick add document type"
                    @click="$emit('quick-add-type')"
                >
                    <i class="bi bi-plus-lg"></i>
                </button>
            </div>
        </div>
        <div class="doc-form-field">
            <label v-if="!inline" class="form-label small mb-1">Issue Date</label>
            <AppDateInput
                v-model="localForm.issue_date"
                size="sm"
                :invalid="hasFieldError(fieldErrors, 'issue_date')"
                required
            />
            <div v-if="fieldErrorMessage(fieldErrors, 'issue_date')" class="invalid-feedback d-block">
                {{ fieldErrorMessage(fieldErrors, 'issue_date') }}
            </div>
        </div>
        <div class="doc-form-field">
            <label v-if="!inline" class="form-label small mb-1">Expiry Date</label>
            <AppDateInput
                v-model="localForm.expiry_date"
                size="sm"
                :invalid="hasFieldError(fieldErrors, 'expiry_date')"
                :min="localForm.issue_date || undefined"
                required
            />
            <div v-if="fieldErrorMessage(fieldErrors, 'expiry_date')" class="invalid-feedback d-block">
                {{ fieldErrorMessage(fieldErrors, 'expiry_date') }}
            </div>
        </div>
        <div class="doc-form-field">
            <label v-if="!inline" class="form-label small mb-1">Reminder Days</label>
            <input
                v-model="localForm.reminder_days_before"
                v-no-scroll-change
                type="number"
                min="1"
                max="90"
                class="form-control form-control-sm"
            />
            <div v-if="fieldErrorMessage(fieldErrors, 'reminder_days_before')" class="invalid-feedback d-block">
                {{ fieldErrorMessage(fieldErrors, 'reminder_days_before') }}
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { fieldErrorMessage, hasFieldError } from '../../utils/formErrors';
import SearchableSelect from '../ui/SearchableSelect.vue';

const props = defineProps({
    modelValue: { type: Object, required: true },
    documentTypes: { type: Array, default: () => [] },
    fieldErrors: { type: Object, default: () => ({}) },
    documentTypeDisabled: { type: Boolean, default: false },
    inline: { type: Boolean, default: false },
    hideType: { type: Boolean, default: false },
    showTypeQuickAdd: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue', 'quick-add-type']);

const localForm = computed({
    get: () => props.modelValue,
    set: (value) => emit('update:modelValue', value),
});
</script>

<style scoped>
.doc-form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 0.75rem;
}

.doc-form-inline {
    display: contents;
}

.doc-type-picker {
    display: flex;
    align-items: flex-start;
    gap: 0.35rem;
}

.doc-type-picker :deep(.searchable-select) {
    flex: 1;
    min-width: 0;
}

.doc-type-picker__add {
    height: 31px;
    width: 31px;
    padding: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
</style>
