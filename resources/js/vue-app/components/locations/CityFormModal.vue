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
                        {{ isEdit ? 'Edit City' : 'Add City' }}
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
                            <label :for="`${modalId}_state`" class="form-label">State *</label>
                            <SearchableSelect
                                :input-id="`${modalId}_state`"
                                v-model="form.state_id"
                                :items="stateOptions"
                                placeholder="Select state"
                                search-placeholder="Search state…"
                                :invalid="Boolean(fieldErrors.state_id)"
                                :error-message="fieldErrors.state_id?.[0]"
                            />
                        </div>
                        <div class="mb-0">
                            <label :for="`${modalId}_name`" class="form-label">City Name *</label>
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
                            <i class="bi bi-check-circle"></i>
                            {{ saving ? (isEdit ? 'Updating...' : 'Saving...') : isEdit ? 'Update City' : 'Add City' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import SearchableSelect from '../ui/SearchableSelect.vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    mode: { type: String, default: 'create' },
    city: { type: Object, default: null },
    stateOptions: { type: Array, default: () => [] },
    initialStateId: { type: [String, Number], default: '' },
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
        state_id: '',
    };
}

function fillForm(city, defaultStateId = '') {
    form.value = {
        name: city?.name ?? '',
        state_id: city?.state_id ?? defaultStateId ?? '',
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
            fillForm(props.city, props.initialStateId);
            bootstrapModal.show();
        } else {
            bootstrapModal.hide();
        }
    },
);

watch(
    () => [props.city, props.initialStateId],
    () => {
        if (props.show) {
            fillForm(props.city, props.initialStateId);
        }
    },
);

function close() {
    emit('close');
}

function submit() {
    const name = form.value.name.trim();

    if (!name || !form.value.state_id) {
        return;
    }

    emit('submit', {
        name,
        state_id: form.value.state_id,
    });
}
</script>
