<template>
    <div class="license-expiry-inline">
        <div class="license-expiry-inline__row">
            <span class="license-expiry-inline__title">
                Update expiry — {{ license.driver_name ?? 'Driver' }}
            </span>
            <label :for="inputId" class="license-expiry-inline__label">License Expiry</label>
            <div class="license-expiry-inline__field">
                <AppDateInput
                    :input-id="inputId"
                    v-model="expiryDate"
                    size="sm"
                    :invalid="!!fieldErrors.license_expiry_date"
                />
            </div>
            <button
                type="button"
                class="btn btn-sm btn-success"
                :disabled="saving"
                @click="save"
            >
                Save
            </button>
            <button
                type="button"
                class="btn btn-sm btn-outline-secondary"
                :disabled="saving"
                @click="$emit('cancel')"
            >
                Cancel
            </button>
        </div>
        <div v-if="fieldErrors.license_expiry_date" class="invalid-feedback d-block">
            {{ fieldErrors.license_expiry_date[0] }}
        </div>
        <div v-if="formError" class="text-danger small mt-1">{{ formError }}</div>
    </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { updateDriver } from '../../api/drivers';
import { useApiFormErrors } from '../../composables/useApiFormErrors';
import { useAppToast } from '../../composables/useAppToast';
import AppDateInput from '../ui/AppDateInput.vue';

const props = defineProps({
    license: {
        type: Object,
        required: true,
    },
});

const emit = defineEmits(['saved', 'cancel']);

const { fieldErrors, formError, clearErrors, applyApiError } = useApiFormErrors();
const toast = useAppToast();

const saving = ref(false);
const expiryDate = ref(props.license?.license_expiry_date ?? '');

const inputId = computed(
    () => `license-expiry-inline-${props.license?.driver_id ?? 'new'}`,
);

watch(
    () => props.license,
    (license) => {
        expiryDate.value = license?.license_expiry_date ?? '';
        clearErrors();
    },
    { immediate: true },
);

async function save() {
    if (!props.license?.driver_id) {
        return;
    }

    if (!expiryDate.value) {
        formError.value = 'License expiry date is required.';

        return;
    }

    saving.value = true;
    clearErrors();

    try {
        await updateDriver(props.license.driver_id, {
            license_expiry_date: expiryDate.value,
        });
        toast.success('License expiry updated.');
        emit('saved', {
            driver_id: props.license.driver_id,
            license_expiry_date: expiryDate.value,
        });
    } catch (error) {
        applyApiError(error);
    } finally {
        saving.value = false;
    }
}
</script>

<style scoped>
.license-expiry-inline__row {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.65rem 0.75rem;
}

.license-expiry-inline__title {
    font-size: 0.82rem;
    font-weight: 700;
    color: #0f172a;
    white-space: nowrap;
}

.license-expiry-inline__label {
    margin: 0;
    font-size: 0.78rem;
    font-weight: 600;
    color: #64748b;
    white-space: nowrap;
}

.license-expiry-inline__field {
    width: 10.5rem;
    max-width: 100%;
}
</style>
