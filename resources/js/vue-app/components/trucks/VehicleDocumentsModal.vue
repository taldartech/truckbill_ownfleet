<template>
    <div
        :id="modalId"
        ref="modalElement"
        class="modal fade"
        tabindex="-1"
        :aria-labelledby="`${modalId}Label`"
        aria-hidden="true"
    >
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 :id="`${modalId}Label`" class="modal-title">
                        <i class="bi bi-file-earmark-text"></i>
                        Documents — {{ vehicleLabel }}
                    </h5>
                    <button type="button" class="btn-close btn-close-white" aria-label="Close" @click="close"></button>
                </div>
                <div class="modal-body">
                    <div v-if="canManage" class="card mb-3">
                        <div class="card-header">{{ editingDocument ? 'Edit Document' : 'Add Document' }}</div>
                        <div class="card-body">
                            <div v-if="formError" class="alert alert-danger py-2">{{ formError }}</div>
                            <form class="row g-3" @submit.prevent="saveDocument">
                                <div class="col-md-4">
                                    <label class="form-label">Document Type *</label>
                                    <select
                                        v-model="docForm.document_type"
                                        class="form-select"
                                        :class="{ 'is-invalid': hasFieldError(fieldErrors, 'document_type') }"
                                        required
                                    >
                                        <option value="">Select</option>
                                        <option
                                            v-for="type in documentTypes"
                                            :key="type.value"
                                            :value="type.value"
                                        >
                                            {{ type.label }}
                                        </option>
                                    </select>
                                    <div
                                        v-if="fieldErrorMessage(fieldErrors, 'document_type')"
                                        class="invalid-feedback d-block"
                                    >
                                        {{ fieldErrorMessage(fieldErrors, 'document_type') }}
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Issue Date</label>
                                    <input
                                        v-model="docForm.issue_date"
                                        type="date"
                                        class="form-control"
                                        :class="{ 'is-invalid': hasFieldError(fieldErrors, 'issue_date') }"
                                    />
                                    <div
                                        v-if="fieldErrorMessage(fieldErrors, 'issue_date')"
                                        class="invalid-feedback d-block"
                                    >
                                        {{ fieldErrorMessage(fieldErrors, 'issue_date') }}
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Expiry Date</label>
                                    <input
                                        v-model="docForm.expiry_date"
                                        type="date"
                                        class="form-control"
                                        :class="{ 'is-invalid': hasFieldError(fieldErrors, 'expiry_date') }"
                                    />
                                    <div
                                        v-if="fieldErrorMessage(fieldErrors, 'expiry_date')"
                                        class="invalid-feedback d-block"
                                    >
                                        {{ fieldErrorMessage(fieldErrors, 'expiry_date') }}
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Upload File</label>
                                    <input
                                        type="file"
                                        class="form-control"
                                        :class="{ 'is-invalid': hasFieldError(fieldErrors, 'file') }"
                                        accept=".pdf,.jpg,.jpeg,.png,.webp"
                                        @change="onFileSelected"
                                    />
                                    <div
                                        v-if="fieldErrorMessage(fieldErrors, 'file')"
                                        class="invalid-feedback d-block"
                                    >
                                        {{ fieldErrorMessage(fieldErrors, 'file') }}
                                    </div>
                                    <small class="text-muted d-block mt-1">
                                        Allowed: PDF/JPG/PNG/WEBP. Max size: 10 MB.
                                    </small>
                                    <p v-if="editingDocument?.original_filename" class="small mb-0 mt-1">
                                        Current:
                                        <a
                                            v-if="editingDocument.download_url"
                                            :href="editingDocument.download_url"
                                            target="_blank"
                                            rel="noopener"
                                        >
                                            {{ editingDocument.original_filename }}
                                        </a>
                                        <span v-else>{{ editingDocument.original_filename }}</span>
                                    </p>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Reminder (days)</label>
                                    <input
                                        v-model="docForm.reminder_days_before"
                                        v-no-scroll-change
                                        type="number"
                                        min="1"
                                        max="90"
                                        class="form-control"
                                    />
                                </div>
                                <div class="col-md-3 d-flex align-items-end gap-2">
                                    <button type="submit" class="btn btn-primary" :disabled="saving">
                                        {{ saving ? 'Saving...' : editingDocument ? 'Update' : 'Add' }}
                                    </button>
                                    <button
                                        v-if="editingDocument"
                                        type="button"
                                        class="btn btn-outline-secondary"
                                        @click="resetDocForm"
                                    >
                                        Cancel
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div v-if="loading" class="text-muted text-center py-3">Loading documents…</div>
                    <div v-else class="table-responsive">
                        <table class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th>Type</th>
                                    <th>Issue</th>
                                    <th>Expiry</th>
                                    <th>Status</th>
                                    <th>File</th>
                                    <th v-if="canManage" style="width: 120px">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="documents.length === 0">
                                    <td :colspan="canManage ? 6 : 5" class="text-center text-muted">
                                        No documents added yet.
                                    </td>
                                </tr>
                                <tr v-for="doc in documents" :key="doc.id">
                                    <td>{{ doc.document_type_label ?? doc.document_type }}</td>
                                    <td>{{ doc.issue_date ?? '-' }}</td>
                                    <td>{{ doc.expiry_date ?? '-' }}</td>
                                    <td>
                                        <span
                                            class="badge"
                                            :class="doc.is_expired ? 'bg-danger' : 'bg-success'"
                                        >
                                            {{ doc.is_expired ? 'Expired' : 'Valid' }}
                                        </span>
                                    </td>
                                    <td>
                                        <div v-if="doc.download_url" class="d-flex align-items-center gap-2 flex-wrap">
                                            <span class="small text-muted">{{ doc.original_filename ?? 'Document file' }}</span>
                                            <a
                                                v-if="doc.preview_url"
                                                :href="doc.preview_url"
                                                target="_blank"
                                                rel="noopener"
                                                class="btn btn-sm btn-outline-primary"
                                            >
                                                View
                                            </a>
                                            <a
                                                :href="doc.download_url"
                                                class="btn btn-sm btn-outline-secondary"
                                                download
                                            >
                                                Download
                                            </a>
                                        </div>
                                        <span v-else class="text-muted">—</span>
                                    </td>
                                    <td v-if="canManage">
                                        <AppTableActions>
                                            <AppCrudActionButton action="edit" @click="editDocument(doc)" />
                                            <AppCrudActionButton action="delete" @click="removeDocument(doc)" />
                                        </AppTableActions>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" @click="close">Close</button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, watch } from 'vue';
import {
    createVehicleDocument,
    deleteVehicleDocument,
    listVehicleDocuments,
    updateVehicleDocument,
    vehicleDocumentTypes,
} from '../../api/vehicleDocuments';
import { useApiFormErrors } from '../../composables/useApiFormErrors';
import { fieldErrorMessage, hasFieldError } from '../../utils/formErrors';
import AppCrudActionButton from '../ui/AppCrudActionButton.vue';
import AppTableActions from '../ui/AppTableActions.vue';

const { fieldErrors, formError, clearErrors, applyApiError } = useApiFormErrors();

const props = defineProps({
    show: { type: Boolean, default: false },
    vehicle: { type: Object, default: null },
    canManage: { type: Boolean, default: false },
    modalId: { type: String, required: true },
    initialDocumentId: { type: String, default: null },
});

const emit = defineEmits(['close', 'saved']);

const modalElement = ref(null);
let bootstrapModal = null;

const documents = ref([]);
const loading = ref(false);
const saving = ref(false);
const editingDocument = ref(null);
const documentTypes = vehicleDocumentTypes;
const vehicleLabel = ref('');

const docForm = ref(emptyDocForm());
const selectedFile = ref(null);
const maxFileBytes = 10 * 1024 * 1024;

function emptyDocForm() {
    return {
        document_type: '',
        issue_date: '',
        expiry_date: '',
        reminder_days_before: 7,
    };
}

function resetDocForm() {
    editingDocument.value = null;
    selectedFile.value = null;
    clearErrors();
    docForm.value = emptyDocForm();
}

function onFileSelected(event) {
    const [file] = event.target.files ?? [];

    if (file && file.size > maxFileBytes) {
        formError.value = 'File size is too large. Please select a file up to 10 MB.';
        event.target.value = '';
        selectedFile.value = null;

        return;
    }

    formError.value = '';

    selectedFile.value = file ?? null;
}

async function loadDocuments() {
    if (!props.vehicle?.id) {
        return;
    }

    loading.value = true;

    try {
        documents.value = await listVehicleDocuments(props.vehicle.id);
        openInitialDocumentIfNeeded();
    } catch (error) {
        documents.value = [];
        showRequestError(error, 'Unable to load documents right now.');
    } finally {
        loading.value = false;
    }
}

function openInitialDocumentIfNeeded() {
    if (!props.initialDocumentId) {
        return;
    }

    const document = documents.value.find((item) => item.id === props.initialDocumentId);

    if (document) {
        editDocument(document);
    }
}

watch(
    () => props.show,
    async (visible) => {
        if (!modalElement.value || typeof window.bootstrap === 'undefined') {
            return;
        }

        if (!bootstrapModal) {
            bootstrapModal = new window.bootstrap.Modal(modalElement.value, { backdrop: false });
        }

        if (visible) {
            vehicleLabel.value = props.vehicle?.registration_number ?? '';
            resetDocForm();
            await loadDocuments();
            bootstrapModal.show();
        } else {
            bootstrapModal.hide();
        }
    },
);

function close() {
    emit('close');
}

function editDocument(doc) {
    editingDocument.value = doc;
    selectedFile.value = null;
    clearErrors();
    docForm.value = {
        document_type: doc.document_type,
        issue_date: doc.issue_date ?? '',
        expiry_date: doc.expiry_date ?? '',
        reminder_days_before: doc.reminder_days_before ?? 7,
    };
}

async function saveDocument() {
    if (!props.vehicle?.id) {
        return;
    }

    saving.value = true;
    clearErrors();

    const payload = {
        vehicle_id: props.vehicle.id,
        document_type: docForm.value.document_type,
        issue_date: docForm.value.issue_date || null,
        expiry_date: docForm.value.expiry_date || null,
        reminder_days_before: Number(docForm.value.reminder_days_before) || 7,
    };

    try {
        if (editingDocument.value?.id) {
            await updateVehicleDocument(editingDocument.value.id, payload, selectedFile.value);
        } else {
            if (!selectedFile.value) {
                formError.value = 'Please choose a file to upload.';
                saving.value = false;

                return;
            }

            await createVehicleDocument(payload, selectedFile.value);
        }

        resetDocForm();
        await loadDocuments();
        emit('saved');
    } catch (error) {
        if (!applyApiError(error)) {
            formError.value = 'Unable to save the document. Please try again.';
        }
    } finally {
        saving.value = false;
    }
}

async function removeDocument(doc) {
    if (!window.confirm(`Delete ${doc.document_type_label ?? doc.document_type}?`)) {
        return;
    }

    try {
        await deleteVehicleDocument(doc.id);
        await loadDocuments();
        emit('saved');
    } catch (error) {
        showRequestError(error, 'Unable to delete the document.');
    }
}

function showRequestError(error, fallbackMessage) {
    const status = error?.response?.status;
    const message = error?.response?.data?.message;
    const validationErrors = error?.response?.data?.errors;
    const firstValidationMessage = validationErrors
        ? Object.values(validationErrors).flat().find(Boolean)
        : null;

    if (status === 413) {
        window.alert('File is too large for upload. Please choose a smaller file (max 10 MB).');

        return;
    }

    window.alert(firstValidationMessage || message || fallbackMessage);
}
</script>
