<template>
    <div
        :id="modalId"
        ref="modalElement"
        class="modal fade"
        tabindex="-1"
        :aria-labelledby="`${modalId}Label`"
        aria-hidden="true"
    >
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 :id="`${modalId}Label`" class="modal-title">
                        <i :class="isEdit ? 'bi bi-pencil-square' : 'bi bi-plus-circle'"></i>
                        {{ isEdit ? 'Edit State' : 'Add State' }}
                    </h5>
                    <button type="button" class="btn-close btn-close-white" aria-label="Close" @click="close"></button>
                </div>
                <form novalidate @submit.prevent="submit">
                    <div class="modal-body">
                        <div v-if="formError" class="alert alert-danger py-2">{{ formError }}</div>
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
                                :class="{ 'is-invalid': fieldErrors.name }"
                                required
                            />
                            <div v-if="fieldErrors.name" class="invalid-feedback d-block">
                                {{ fieldErrors.name[0] }}
                            </div>
                        </div>
                        <div class="mb-0">
                            <label :for="`${modalId}_code`" class="form-label">State Code</label>
                            <input
                                :id="`${modalId}_code`"
                                v-model="form.code"
                                type="text"
                                class="form-control"
                                :class="{ 'is-invalid': fieldErrors.code }"
                                maxlength="10"
                                placeholder="e.g. RJ"
                            />
                            <small class="form-text text-muted">Optional</small>
                            <div v-if="fieldErrors.code" class="invalid-feedback d-block">
                                {{ fieldErrors.code[0] }}
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" @click="close">Cancel</button>
                        <button type="submit" class="btn btn-success" :disabled="saving">
                            <i class="bi bi-check-circle"></i>
                            {{ saving ? (isEdit ? 'Updating...' : 'Saving...') : isEdit ? 'Update State' : 'Add State' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';

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

const modalElement = ref(null);
let bootstrapModal = null;

const isEdit = computed(() => props.mode === 'edit');

const form = ref(emptyForm());

function emptyForm() {
    return {
        name: '',
        code: '',
    };
}

function fillForm(state) {
    form.value = {
        name: state?.name ?? '',
        code: state?.code ?? '',
    };
}

watch(
    () => props.show,
    (visible) => {
        if (!modalElement.value || typeof window.bootstrap === 'undefined') {
            return;
        }

        if (!bootstrapModal) {
            bootstrapModal = new window.bootstrap.Modal(modalElement.value, { backdrop: false });
        }

        if (visible) {
            fillForm(props.state);
            bootstrapModal.show();
        } else {
            bootstrapModal.hide();
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

function submit() {
    const name = form.value.name.trim();

    if (!name) {
        return;
    }

    emit('submit', {
        name,
        code: form.value.code.trim() || null,
    });
}
</script>
