<template>
    <div class="document-renew-inline">
        <div class="document-renew-inline__row">
            <span class="document-renew-inline__title">
                Renew — {{ document.document_type_label ?? document.document_type }}
            </span>

            <div class="document-renew-inline__field">
                <label class="document-renew-inline__label" :for="`${fieldId}_issue`">Issue Date</label>
                <AppDateInput
                    :input-id="`${fieldId}_issue`"
                    v-model="docForm.issue_date"
                    size="sm"
                    :invalid="!!fieldErrors.issue_date"
                    required
                />
            </div>

            <div class="document-renew-inline__field">
                <label class="document-renew-inline__label" :for="`${fieldId}_expiry`">Expiry Date</label>
                <AppDateInput
                    :input-id="`${fieldId}_expiry`"
                    v-model="docForm.expiry_date"
                    size="sm"
                    :invalid="!!fieldErrors.expiry_date"
                    :min="docForm.issue_date || undefined"
                    required
                />
            </div>

            <div class="document-renew-inline__field document-renew-inline__field--narrow">
                <label class="document-renew-inline__label" :for="`${fieldId}_reminder`">Reminder Days</label>
                <input
                    :id="`${fieldId}_reminder`"
                    v-model="docForm.reminder_days_before"
                    v-no-scroll-change
                    type="number"
                    min="1"
                    max="90"
                    class="form-control form-control-sm"
                    :class="{ 'is-invalid': !!fieldErrors.reminder_days_before }"
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

        <div v-if="fieldErrors.issue_date" class="invalid-feedback d-block">
            {{ fieldErrors.issue_date[0] }}
        </div>
        <div v-if="fieldErrors.expiry_date" class="invalid-feedback d-block">
            {{ fieldErrors.expiry_date[0] }}
        </div>
        <div v-if="fieldErrors.reminder_days_before" class="invalid-feedback d-block">
            {{ fieldErrors.reminder_days_before[0] }}
        </div>
        <div v-if="formError" class="text-danger small mt-1">{{ formError }}</div>
    </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { updateVehicleDocument } from '../../api/vehicleDocuments';
import { useApiFormErrors } from '../../composables/useApiFormErrors';
import { useAppToast } from '../../composables/useAppToast';
import { defaultRenewFormValues } from '../../utils/documentStatus';
import AppDateInput from '../ui/AppDateInput.vue';

const props = defineProps({
    document: {
        type: Object,
        required: true,
    },
});

const emit = defineEmits(['saved', 'cancel']);

const { fieldErrors, formError, clearErrors, applyApiError } = useApiFormErrors();
const toast = useAppToast();

const saving = ref(false);
const docForm = ref(defaultRenewFormValues(props.document));

const fieldId = computed(
    () => `document-renew-inline-${props.document?.id ?? 'new'}`,
);

watch(
    () => props.document,
    (document) => {
        docForm.value = defaultRenewFormValues(document);
        clearErrors();
    },
    { immediate: true },
);

function validateDocForm() {
    if (!docForm.value.issue_date || !docForm.value.expiry_date) {
        formError.value = 'Issue date and expiry date are required.';

        return false;
    }

    const issue = new Date(docForm.value.issue_date);
    const expiry = new Date(docForm.value.expiry_date);

    if (expiry <= issue) {
        formError.value = 'Expiry date must be after the issue date.';

        return false;
    }

    const validityDays = Math.round((expiry - issue) / (1000 * 60 * 60 * 24));
    const reminderDays = Number(docForm.value.reminder_days_before) || 7;

    if (reminderDays > validityDays) {
        formError.value = 'Reminder days cannot exceed the document validity period.';

        return false;
    }

    return true;
}

async function save() {
    if (!validateDocForm()) {
        return;
    }

    saving.value = true;
    clearErrors();

    const payload = {
        vehicle_id: props.document.vehicle_id,
        document_type: props.document.document_type,
        issue_date: docForm.value.issue_date,
        expiry_date: docForm.value.expiry_date,
        reminder_days_before: Number(docForm.value.reminder_days_before) || 7,
    };

    try {
        const savedDocument = await updateVehicleDocument(props.document.id, payload);

        toast.success('Document renewed successfully.');
        emit('saved', {
            action: 'renew',
            document: savedDocument,
            vehicleId: props.document.vehicle_id,
        });
    } catch (error) {
        if (!applyApiError(error)) {
            formError.value = 'Unable to renew the document. Please try again.';
        }
    } finally {
        saving.value = false;
    }
}
</script>

<style scoped>
.document-renew-inline {
    padding: 0.35rem 0.25rem;
}

.document-renew-inline__row {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-end;
    gap: 0.65rem 0.75rem;
}

.document-renew-inline__title {
    font-size: 0.82rem;
    font-weight: 700;
    color: #0f172a;
    white-space: nowrap;
    align-self: center;
}

.document-renew-inline__field {
    width: 10.5rem;
    max-width: 100%;
}

.document-renew-inline__field--narrow {
    width: 6.5rem;
}

.document-renew-inline__label {
    display: block;
    margin: 0 0 0.2rem;
    font-size: 0.72rem;
    font-weight: 600;
    color: #64748b;
    white-space: nowrap;
}
</style>
