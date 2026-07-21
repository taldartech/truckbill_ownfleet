<template>
    <AppModal
        :show="show"
        :loading="saving"
        :form-error="displayFormError"
        as-form
        @close="close"
        @submit="submit"
    >
        <template #title>
            <i :class="isEdit ? 'bi bi-pencil-square' : 'bi bi-plus-circle'"></i>
            {{ isEdit ? 'Edit State' : 'Add State' }}
        </template>

        <div class="mb-3">
            <label :for="`${modalId}_country`" class="form-label">Country</label>
            <input
                :id="`${modalId}_country`"
                type="text"
                class="form-control"
                value="India"
                disabled
            />
        </div>
        <div class="mb-3">
            <label :for="`${modalId}_name`" class="form-label">State Name *</label>
            <input
                :id="`${modalId}_name`"
                v-model="form.name"
                type="text"
                class="form-control"
                :class="{ 'is-invalid': displayFieldErrors.name }"
                required
            />
            <div v-if="displayFieldErrors.name" class="invalid-feedback d-block">
                {{ displayFieldErrors.name[0] }}
            </div>
        </div>
        <div class="mb-0">
            <label :for="`${modalId}_code`" class="form-label">State Code</label>
            <input
                :id="`${modalId}_code`"
                v-model="form.code"
                type="text"
                class="form-control"
                :class="{ 'is-invalid': displayFieldErrors.code }"
                maxlength="10"
                placeholder="e.g. RJ"
            />
            <small class="form-text text-muted">Optional</small>
            <div v-if="displayFieldErrors.code" class="invalid-feedback d-block">
                {{ displayFieldErrors.code[0] }}
            </div>
        </div>

        <template #footer>
            <button type="button" class="btn btn-secondary" @click="close">Cancel</button>
            <button type="submit" class="btn" :class="isEdit ? 'btn-warning' : 'btn-success'" :disabled="saving">
                <i class="bi bi-check-circle"></i>
                {{ saving ? (isEdit ? 'Updating...' : 'Saving...') : isEdit ? 'Update State' : 'Add State' }}
            </button>
        </template>
    </AppModal>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import AppModal from '../ui/AppModal.vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    mode: { type: String, default: 'create' },
    state: { type: Object, default: null },
    saving: { type: Boolean, default: false },
    fieldErrors: { type: Object, default: () => ({}) },
    formError: { type: String, default: '' },
    modalId: { type: String, required: true },
});

const emit = defineEmits(['close', 'submit']);

const isEdit = computed(() => props.mode === 'edit');

const clientErrors = ref({});
const clientFormError = ref('');
const displayFieldErrors = computed(() => ({
    ...clientErrors.value,
    ...props.fieldErrors,
}));
const displayFormError = computed(() => props.formError || clientFormError.value);

const form = ref(emptyForm());

function emptyForm() {
    return {
        name: '',
        code: '',
    };
}

function clearClientErrors() {
    clientErrors.value = {};
    clientFormError.value = '';
}

function fillForm(state) {
    clearClientErrors();
    form.value = {
        name: state?.name ?? '',
        code: state?.code ?? '',
    };
}

watch(
    () => form.value.name,
    () => {
        if (clientErrors.value.name) {
            const next = { ...clientErrors.value };
            delete next.name;
            clientErrors.value = next;
        }
    },
);

watch(
    () => props.fieldErrors,
    (errors) => {
        if (Object.keys(errors).length > 0) {
            clearClientErrors();
        }
    },
    { deep: true },
);

watch(
    () => props.show,
    (visible) => {
        if (visible) {
            fillForm(props.state);
        }
    },
);

watch(
    () => props.state,
    (state) => {
        if (props.show) {
            fillForm(state);
        }
    },
);

function close() {
    emit('close');
}

function validateClientForm() {
    clearClientErrors();

    const name = form.value.name.trim();

    if (!name) {
        clientErrors.value = { name: ['State name is required.'] };
        clientFormError.value = 'Please fix the highlighted fields.';

        return false;
    }

    return true;
}

function submit() {
    if (!validateClientForm()) {
        return;
    }

    emit('submit', {
        name: form.value.name.trim(),
        code: form.value.code.trim() || null,
    });
}
</script>
