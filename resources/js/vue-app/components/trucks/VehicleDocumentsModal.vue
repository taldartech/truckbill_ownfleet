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
                    <div v-if="formError" class="alert alert-danger py-2">{{ formError }}</div>

                    <div class="card app-index-list-card vehicle-doc-table-card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h3 class="card-title mb-0">Documents List</h3>
                            <button
                                v-if="canManage && !showDocForm"
                                type="button"
                                class="btn btn-primary btn-sm"
                                title="Add document"
                                @click="openAddForm"
                            >
                                <i class="bi bi-plus-lg"></i>
                            </button>
                        </div>
                        <div class="card-body table-responsive p-0">
                            <div v-if="loading" class="text-muted text-center py-4">Loading documents…</div>
                            <table v-else class="table table-hover align-middle mb-0 vehicle-doc-table">
                                <thead class="table-light">
                                    <tr>
                                        <th>Type</th>
                                        <th style="min-width: 150px">Issue Date</th>
                                        <th style="min-width: 150px">Expiry Date</th>
                                        <th style="width: 110px">Reminder</th>
                                        <th style="width: 90px">Status</th>
                                        <th v-if="canManage" class="app-index-actions-cell" style="width: 120px">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-if="canManage && showDocForm" class="vehicle-doc-form-row">
                                        <td>
                                            <div class="doc-type-picker">
                                                <SearchableSelect
                                                    v-model="docForm.document_type"
                                                    :items="selectableDocumentTypes"
                                                    placeholder="Select type"
                                                    search-placeholder="Search document type…"
                                                    :invalid="hasFieldError(fieldErrors, 'document_type')"
                                                    :error-message="fieldErrorMessage(fieldErrors, 'document_type')"
                                                    :disabled="Boolean(editingDocument)"
                                                />
                                                <button
                                                    v-if="canManage"
                                                    type="button"
                                                    class="btn btn-outline-success btn-sm doc-type-picker__add"
                                                    title="Quick add document type"
                                                    @click="showTypeQuickAdd = true"
                                                >
                                                    <i class="bi bi-plus-lg"></i>
                                                </button>
                                            </div>
                                        </td>
                                        <td>
                                            <input
                                                v-model="docForm.issue_date"
                                                type="date"
                                                class="form-control form-control-sm"
                                                :class="{ 'is-invalid': hasFieldError(fieldErrors, 'issue_date') }"
                                                required
                                            />
                                            <div
                                                v-if="fieldErrorMessage(fieldErrors, 'issue_date')"
                                                class="invalid-feedback d-block"
                                            >
                                                {{ fieldErrorMessage(fieldErrors, 'issue_date') }}
                                            </div>
                                        </td>
                                        <td>
                                            <input
                                                v-model="docForm.expiry_date"
                                                type="date"
                                                class="form-control form-control-sm"
                                                :class="{ 'is-invalid': hasFieldError(fieldErrors, 'expiry_date') }"
                                                :min="docForm.issue_date || undefined"
                                                required
                                            />
                                            <div
                                                v-if="fieldErrorMessage(fieldErrors, 'expiry_date')"
                                                class="invalid-feedback d-block"
                                            >
                                                {{ fieldErrorMessage(fieldErrors, 'expiry_date') }}
                                            </div>
                                        </td>
                                        <td>
                                            <input
                                                v-model="docForm.reminder_days_before"
                                                v-no-scroll-change
                                                type="number"
                                                min="1"
                                                max="90"
                                                class="form-control form-control-sm"
                                            />
                                        </td>
                                        <td>
                                            <span class="badge text-bg-light border">New</span>
                                        </td>
                                        <td class="app-index-actions-cell">
                                            <div class="d-flex gap-1">
                                                <button
                                                    type="button"
                                                    class="btn btn-sm btn-success"
                                                    title="Save"
                                                    :disabled="saving"
                                                    @click="saveDocument"
                                                >
                                                    <i class="bi bi-check-lg"></i>
                                                </button>
                                                <button
                                                    type="button"
                                                    class="btn btn-sm btn-outline-secondary"
                                                    title="Cancel"
                                                    :disabled="saving"
                                                    @click="resetDocForm"
                                                >
                                                    <i class="bi bi-x-lg"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>

                                    <tr v-if="!loading && documents.length === 0 && !showDocForm">
                                        <td :colspan="canManage ? 6 : 5" class="text-center text-muted py-4">
                                            No documents added yet.
                                            <button
                                                v-if="canManage"
                                                type="button"
                                                class="btn btn-link btn-sm"
                                                @click="openAddForm"
                                            >
                                                Add document
                                            </button>
                                        </td>
                                    </tr>

                                    <tr
                                        v-for="doc in documents"
                                        v-show="!(showDocForm && editingDocument?.id === doc.id)"
                                        :key="doc.id"
                                        :class="{ 'vehicle-doc-row--expired': doc.is_expired }"
                                    >
                                        <td>
                                            <div class="fw-medium">{{ doc.document_type_label ?? doc.document_type }}</div>
                                            <small class="text-muted">{{ doc.document_type }}</small>
                                        </td>
                                        <td>{{ formatDate(doc.issue_date) }}</td>
                                        <td>{{ formatDate(doc.expiry_date) }}</td>
                                        <td>{{ doc.reminder_days_before ?? 7 }} days</td>
                                        <td>
                                            <span
                                                class="badge"
                                                :class="doc.is_expired ? 'bg-danger' : 'bg-success'"
                                            >
                                                {{ doc.is_expired ? 'Expired' : 'Valid' }}
                                            </span>
                                        </td>
                                        <td v-if="canManage" class="app-index-actions-cell">
                                            <AppTableActions>
                                                <AppCrudActionButton
                                                    action="edit"
                                                    title="Edit document"
                                                    @click="editDocument(doc)"
                                                />
                                                <AppCrudActionButton
                                                    action="delete"
                                                    title="Delete document"
                                                    @click="removeDocument(doc)"
                                                />
                                            </AppTableActions>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" @click="close">Close</button>
                </div>
            </div>
        </div>

        <DocumentTypeQuickAddModal
            :show="showTypeQuickAdd"
            @close="showTypeQuickAdd = false"
            @created="onDocumentTypeCreated"
        />
    </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { listAllDocumentTypes } from '../../api/documentTypes';
import {
    createVehicleDocument,
    deleteVehicleDocument,
    listVehicleDocuments,
    updateVehicleDocument,
} from '../../api/vehicleDocuments';
import { useApiFormErrors } from '../../composables/useApiFormErrors';
import { fieldErrorMessage, hasFieldError } from '../../utils/formErrors';
import AppCrudActionButton from '../ui/AppCrudActionButton.vue';
import AppTableActions from '../ui/AppTableActions.vue';
import SearchableSelect from '../ui/SearchableSelect.vue';
import DocumentTypeQuickAddModal from './DocumentTypeQuickAddModal.vue';

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
const showDocForm = ref(false);
const showTypeQuickAdd = ref(false);
const editingDocument = ref(null);
const documentTypes = ref([]);
const vehicleLabel = ref('');

const documentTypeOptions = computed(() =>
    documentTypes.value.map((type) => ({
        id: type.code,
        label: type.name,
    })),
);

const selectableDocumentTypes = computed(() => {
    if (editingDocument.value) {
        return documentTypeOptions.value;
    }

    const usedTypes = new Set(documents.value.map((doc) => doc.document_type));

    return documentTypeOptions.value.filter((type) => !usedTypes.has(type.id));
});

const docForm = ref(emptyDocForm());

function emptyDocForm() {
    return {
        document_type: '',
        issue_date: '',
        expiry_date: '',
        reminder_days_before: 7,
    };
}

function formatDate(value) {
    if (!value) {
        return '—';
    }

    const [year, month, day] = String(value).split('-');

    if (!year || !month || !day) {
        return value;
    }

    return `${month}/${day}/${year}`;
}

function openAddForm() {
    editingDocument.value = null;
    clearErrors();
    docForm.value = emptyDocForm();
    showDocForm.value = true;
}

function resetDocForm() {
    editingDocument.value = null;
    showDocForm.value = false;
    clearErrors();
    docForm.value = emptyDocForm();
}

async function loadDocumentTypes() {
    try {
        documentTypes.value = await listAllDocumentTypes();
    } catch {
        documentTypes.value = [];
    }
}

function onDocumentTypeCreated(documentType) {
    if (!documentType?.code) {
        return;
    }

    documentTypes.value = [...documentTypes.value, documentType].sort((a, b) =>
        String(a.name).localeCompare(String(b.name)),
    );
    docForm.value.document_type = documentType.code;
}

async function loadDocuments() {
    if (!props.vehicle?.id) {
        return;
    }

    loading.value = true;

    try {
        await loadDocumentTypes();
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
    showDocForm.value = true;
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

    if (!docForm.value.document_type) {
        formError.value = 'Document type is required.';

        return;
    }

    if (!docForm.value.issue_date || !docForm.value.expiry_date) {
        formError.value = 'Issue date and expiry date are required.';

        return;
    }

    saving.value = true;
    clearErrors();

    const payload = {
        vehicle_id: props.vehicle.id,
        document_type: docForm.value.document_type,
        issue_date: docForm.value.issue_date,
        expiry_date: docForm.value.expiry_date,
        reminder_days_before: Number(docForm.value.reminder_days_before) || 7,
    };

    try {
        if (editingDocument.value?.id) {
            await updateVehicleDocument(editingDocument.value.id, payload);
        } else {
            await createVehicleDocument(payload);
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
        if (editingDocument.value?.id === doc.id) {
            resetDocForm();
        }
        await loadDocuments();
        emit('saved');
    } catch (error) {
        showRequestError(error, 'Unable to delete the document.');
    }
}

function showRequestError(error, fallbackMessage) {
    const message = error?.response?.data?.message;
    const validationErrors = error?.response?.data?.errors;
    const firstValidationMessage = validationErrors
        ? Object.values(validationErrors).flat().find(Boolean)
        : null;

    window.alert(firstValidationMessage || message || fallbackMessage);
}
</script>

<style scoped>
.vehicle-doc-table-card > .card-body {
    padding: 0;
}

.vehicle-doc-form-row > td {
    background: rgba(13, 110, 253, 0.06);
    vertical-align: middle;
}

.vehicle-doc-row--expired > td {
    background: rgba(220, 53, 69, 0.04);
}

.doc-type-picker {
    display: flex;
    align-items: flex-start;
    gap: 0.35rem;
}

.doc-type-picker :deep(.searchable-select) {
    flex: 1;
    min-width: 0;
}

.doc-type-picker__add {
    height: 31px;
    width: 31px;
    padding: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
</style>
