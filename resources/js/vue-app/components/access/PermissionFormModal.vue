<template>
    <div
        :id="modalId"
        ref="modalElement"
        class="modal fade"
        tabindex="-1"
        :aria-labelledby="`${modalId}Label`"
        aria-hidden="true"
    >
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 :id="`${modalId}Label`" class="modal-title">
                        <i :class="isEdit ? 'bi bi-pencil-square' : 'bi bi-shield-plus'"></i>
                        {{ isEdit ? 'Edit Permission' : 'Add Permission' }}
                    </h5>
                    <button type="button" class="btn-close btn-close-white" aria-label="Close" @click="close"></button>
                </div>
                <form novalidate @submit.prevent="submit">
                    <div class="modal-body">
                        <div v-if="formError" class="alert alert-danger py-2">{{ formError }}</div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label :for="`${modalId}_slug`" class="form-label">Slug *</label>
                                <input
                                    :id="`${modalId}_slug`"
                                    v-model="form.slug"
                                    type="text"
                                    class="form-control"
                                    :class="{ 'is-invalid': fieldErrors.slug }"
                                    placeholder="module.action"
                                    required
                                />
                                <div v-if="fieldErrors.slug" class="invalid-feedback d-block">
                                    {{ fieldErrors.slug[0] }}
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label :for="`${modalId}_group`" class="form-label">Group *</label>
                                <input
                                    :id="`${modalId}_group`"
                                    v-model="form.group"
                                    type="text"
                                    class="form-control"
                                    :class="{ 'is-invalid': fieldErrors.group }"
                                    placeholder="access"
                                    required
                                />
                                <div v-if="fieldErrors.group" class="invalid-feedback d-block">
                                    {{ fieldErrors.group[0] }}
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label :for="`${modalId}_name`" class="form-label">Name *</label>
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
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" @click="close">Cancel</button>
                        <button type="submit" class="btn btn-success" :disabled="saving">
                            <span v-if="saving" class="spinner-border spinner-border-sm me-1"></span>
                            {{ isEdit ? 'Update' : 'Create' }}
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
    modalId: { type: String, default: 'permissionFormModal' },
    show: { type: Boolean, default: false },
    mode: { type: String, default: 'create' },
    permission: { type: Object, default: null },
    saving: { type: Boolean, default: false },
    fieldErrors: { type: Object, default: () => ({}) },
    formError: { type: String, default: '' },
});

const emit = defineEmits(['close', 'submit']);

const modalElement = ref(null);
let modalInstance = null;

const isEdit = computed(() => props.mode === 'edit');

const form = ref({
    slug: '',
    name: '',
    group: '',
});

function resetForm() {
    if (props.permission) {
        form.value = {
            slug: props.permission.slug ?? '',
            name: props.permission.name ?? '',
            group: props.permission.group ?? '',
        };
    } else {
        form.value = { slug: '', name: '', group: '' };
    }
}

function close() {
    emit('close');
}

function submit() {
    emit('submit', { ...form.value });
}

watch(
    () => props.show,
    async (visible) => {
        if (!modalElement.value) {
            return;
        }

        const { Modal } = await import('bootstrap');

        if (!modalInstance) {
            modalInstance = new Modal(modalElement.value, { backdrop: 'static' });
            modalElement.value.addEventListener('hidden.bs.modal', () => emit('close'));
        }

        if (visible) {
            resetForm();
            modalInstance.show();
        } else {
            modalInstance.hide();
        }
    },
);

watch(
    () => props.permission,
    () => {
        if (props.show) {
            resetForm();
        }
    },
);
</script>
