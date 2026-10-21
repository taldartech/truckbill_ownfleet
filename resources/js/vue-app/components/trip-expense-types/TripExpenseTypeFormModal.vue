<template>
    <AppModal
        :show="show"
        :loading="saving"
        :form-error="formError"
        as-form
        @close="close"
        @submit="submit"
    >
        <template #title>
            <i class="bi bi-receipt"></i>
            {{ isEdit ? 'Edit Trip Expense Type' : 'Add Trip Expense Type' }}
        </template>

        <div class="mb-3">
            <label class="form-label">Expense Type Name *</label>
            <input
                v-model="form.name"
                type="text"
                class="form-control"
                :class="{ 'is-invalid': fieldErrors.name }"
                placeholder="e.g. Loading Charges"
                required
            />
            <div v-if="fieldErrors.name" class="invalid-feedback d-block">
                {{ fieldErrors.name[0] }}
            </div>
            <div class="form-text">Available when recording expenses for trips.</div>
        </div>

        <div v-if="isEdit" class="mb-3">
            <div class="form-check form-switch">
                <input
                    id="tripExpenseType_isActive"
                    v-model="form.is_active"
                    type="checkbox"
                    class="form-check-input"
                />
                <label class="form-check-label" for="tripExpenseType_isActive">
                    {{ form.is_active ? 'Active' : 'Inactive (Deactivated)' }}
                </label>
            </div>
            <div class="form-text text-muted">
                Inactive expense types cannot be selected on new expense entries.
            </div>
        </div>

        <template #footer>
            <button type="button" class="btn btn-secondary" :disabled="saving" @click="close">Cancel</button>
            <AppButton type="submit" label="Save" :loading="saving" />
        </template>
    </AppModal>
</template>

<script setup>
import { computed, reactive, watch } from 'vue';
import AppModal from '../ui/AppModal.vue';
import AppButton from '../ui/AppButton.vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    saving: { type: Boolean, default: false },
    expenseType: { type: Object, default: null },
    fieldErrors: { type: Object, default: () => ({}) },
    formError: { type: String, default: '' },
});

const emit = defineEmits(['close', 'submit']);

const isEdit = computed(() => props.expenseType !== null);

const form = reactive({
    name: '',
    is_active: true,
});

function resetForm() {
    form.name = '';
    form.is_active = true;
}

function fillForm(expenseType) {
    if (!expenseType) {
        resetForm();
        return;
    }
    form.name = expenseType.name ?? '';
    form.is_active = expenseType.is_active ?? true;
}

watch(
    () => props.show,
    (visible) => {
        if (visible) {
            fillForm(props.expenseType);
        }
    }
);

function close() {
    emit('close');
}

function submit() {
    const payload = {
        name: form.name.trim(),
    };

    if (isEdit.value) {
        payload.is_active = Boolean(form.is_active);
    }

    emit('submit', { payload });
}
</script>
