<template>
    <AppModal
        :show="show"
        :loading="saving"
        :form-error="formError"
        as-form
        @close="$emit('close')"
        @submit="submit"
    >
        <template #title>
            <i class="bi bi-box-seam"></i> Quick Add Goods Type
        </template>

        <label class="form-label">Goods Type Name *</label>
        <input
            v-model="name"
            type="text"
            class="form-control"
            :class="{ 'is-invalid': fieldErrors.name }"
            placeholder="e.g. Cement"
            required
        />
        <div v-if="fieldErrors.name" class="invalid-feedback d-block">
            {{ fieldErrors.name[0] }}
        </div>
        <div class="form-text">Code auto-generated. Shared across all branches in this company.</div>

        <template #footer>
            <button type="button" class="btn btn-secondary" :disabled="saving" @click="$emit('close')">Cancel</button>
            <AppButton type="submit" label="Save Type" :loading="saving" />
        </template>
    </AppModal>
</template>

<script setup>
import { ref, watch } from 'vue';
import { createGoodsType } from '../../api/goodsTypes';
import { useApiFormErrors } from '../../composables/useApiFormErrors';
import AppButton from '../ui/AppButton.vue';
import AppModal from '../ui/AppModal.vue';

const props = defineProps({
    show: { type: Boolean, default: false },
});

const emit = defineEmits(['close', 'created']);

const { fieldErrors, formError, clearErrors, applyApiError } = useApiFormErrors();
const name = ref('');
const saving = ref(false);

watch(
    () => props.show,
    (visible) => {
        if (visible) {
            name.value = '';
            clearErrors();
        }
    },
);

async function submit() {
    clearErrors();
    saving.value = true;

    try {
        const goodsType = await createGoodsType({
            name: name.value.trim(),
        });
        emit('created', goodsType);
        emit('close');
    } catch (error) {
        applyApiError(error, 'Failed to add goods type.');
    } finally {
        saving.value = false;
    }
}
</script>
