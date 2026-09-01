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
            <i class="bi bi-box-seam"></i>
            {{ isEdit ? 'Edit Goods Type' : 'Add Goods Type' }}
        </template>

        <div class="mb-3">
            <label class="form-label">Goods Type Name *</label>
            <input
                v-model="form.name"
                type="text"
                class="form-control"
                :class="{ 'is-invalid': fieldErrors.name }"
                placeholder="e.g. Cement"
                required
                maxlength="100"
            />
            <div v-if="fieldErrors.name" class="invalid-feedback d-block">
                {{ fieldErrors.name[0] }}
            </div>
            <div class="form-text">Shared across all branches in this company.</div>
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
    goodsType: { type: Object, default: null },
    fieldErrors: { type: Object, default: () => ({}) },
    formError: { type: String, default: '' },
});

const emit = defineEmits(['close', 'submit']);

const isEdit = computed(() => props.goodsType !== null);

const form = reactive({
    name: '',
});

function resetForm() {
    form.name = '';
}

function fillForm(goodsType) {
    if (!goodsType) {
        resetForm();
        return;
    }
    form.name = goodsType.name ?? '';
}

watch(
    () => props.show,
    (visible) => {
        if (visible) {
            fillForm(props.goodsType);
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

    const fields = [
        {
            label: 'Name',
            value: payload.name,
            previousValue: props.goodsType?.name,
        },
    ];

    emit('submit', { payload, fields });
}
</script>
