<template>
    <div class="document-renew-inline">
        <h6 class="small fw-bold mb-2">
            Renew — {{ document.document_type_label ?? document.document_type }}
        </h6>
        <DocumentFormFields
            v-model="docForm"
            :document-types="documentTypeOptions"
            :field-errors="fieldErrors"
            :document-type-disabled="true"
            :hide-type="true"
        />
        <div v-if="formError" class="text-danger small mt-1">{{ formError }}</div>
        <div class="d-flex gap-2 mt-2">
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
    </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { updateVehicleDocument } from '../../api/vehicleDocuments';
import { useApiFormErrors } from '../../composables/useApiFormErrors';
import { useAppToast } from '../../composables/useAppToast';
import { defaultRenewFormValues } from '../../utils/documentStatus';
import DocumentFormFields from './DocumentFormFields.vue';

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

const documentTypeOptions = computed(() => [
    {
        id: props.document.document_type,
        label: props.document.document_type_label ?? props.document.document_type,
    },
]);

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
    padding: 0.5rem 0.25rem;
}
</style>
