<template>
    <AppModal
        :show="show"
        size="xl"
        :loading="saving"
        :form-error="formError"
        @close="close"
    >
        <template #title>
            <div>
                <span>
                    <i class="bi bi-file-earmark-text"></i>
                    <template v-if="isDashboardContext">Compliance Documents</template>
                    <template v-else>Documents — {{ vehicleLabel }}</template>
                </span>
                <div v-if="isDashboardContext" class="small text-white-50 mt-1">
                    {{ vehicleLabel }}
                </div>
            </div>
        </template>

        <div
                        v-if="isDashboardContext && showAddPanel && canCreate"
                        class="card mb-3 border-primary-subtle"
                    >
                        <div class="card-header d-flex justify-content-between align-items-center py-2">
                            <h6 class="mb-0">Add Document</h6>
                            <button type="button" class="btn-close btn-sm" @click="closeAddPanel"></button>
                        </div>
                        <div class="card-body">
                            <DocumentFormFields
                                v-model="docForm"
                                :document-types="addDocumentTypeOptions"
                                :field-errors="fieldErrors"
                                :document-type-disabled="false"
                                :show-type-quick-add="canCreate"
                                @quick-add-type="showTypeQuickAdd = true"
                            />
                            <div class="d-flex gap-2 mt-3">
                                <button
                                    type="button"
                                    class="btn btn-sm btn-success"
                                    :disabled="saving"
                                    @click="saveDocument('add')"
                                >
                                    Save
                                </button>
                                <button
                                    type="button"
                                    class="btn btn-sm btn-outline-secondary"
                                    :disabled="saving"
                                    @click="closeAddPanel"
                                >
                                    Cancel
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="card app-index-list-card vehicle-doc-table-card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h3 class="card-title mb-0">
                                {{ isDashboardContext ? 'Documents' : 'Documents List' }}
                            </h3>
                            <div
                                v-if="canCreate && showAddButton"
                                class="app-index-card-toolbar d-flex flex-wrap gap-2 align-items-center"
                            >
                                <AppIndexHeaderButton
                                    icon="plus"
                                    aria-label="Add document"
                                    @click="openAddForm"
                                >
                                    Add Document
                                </AppIndexHeaderButton>
                            </div>
                        </div>
                        <div ref="tableScrollRef" class="card-body table-responsive p-0" :class="{ 'compliance-table': isDashboardContext }">
                            <div v-if="loading" class="text-muted text-center py-4">Loading documents…</div>
                            <table v-else class="table table-hover align-middle mb-0 vehicle-doc-table">
                                <thead class="table-light">
                                    <tr>
                                        <th>{{ isDashboardContext ? 'Document' : 'Type' }}</th>
                                        <th v-if="!isDashboardContext" style="min-width: 150px">Issue Date</th>
                                        <th style="min-width: 150px">Expiry Date</th>
                                        <th v-if="isDashboardContext" style="width: 100px">Days Left</th>
                                        <th v-if="!isDashboardContext" style="width: 110px">Reminder</th>
                                        <th style="width: 120px">Status</th>
                                        <th v-if="showActionsColumn" class="app-index-actions-cell" style="width: 140px">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-if="!isDashboardContext && canCreate && showDocForm && !editingDocument"
                                        class="vehicle-doc-form-row"
                                    >
                                        <td>
                                            <div class="doc-type-picker">
                                                <SearchableSelect
                                                    v-model="docForm.document_type"
                                                    :items="selectableDocumentTypes"
                                                    placeholder="Select type"
                                                    search-placeholder="Search document type…"
                                                    attach-dropdown-to-body
                                                    :invalid="hasFieldError(fieldErrors, 'document_type')"
                                                    :error-message="fieldErrorMessage(fieldErrors, 'document_type')"
                                                    :disabled="Boolean(editingDocument)"
                                                />
                                                <button
                                                    v-if="canCreate"
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
                                            <AppDateInput
                                                v-model="docForm.issue_date"
                                                size="sm"
                                                :invalid="hasFieldError(fieldErrors, 'issue_date')"
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
                                            <AppDateInput
                                                v-model="docForm.expiry_date"
                                                size="sm"
                                                :invalid="hasFieldError(fieldErrors, 'expiry_date')"
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
                                                    @click="saveDocument('add')"
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

                                    <tr v-if="!loading && sortedDocuments.length === 0 && !showDocForm && !showAddPanel">
                                        <td :colspan="tableColspan" class="text-center text-muted py-4">
                                            No documents added yet.
                                            <button
                                                v-if="canCreate"
                                                type="button"
                                                class="btn btn-link btn-sm"
                                                @click="openAddForm"
                                            >
                                                Add document
                                            </button>
                                        </td>
                                    </tr>

                                    <template v-for="doc in sortedDocuments" :key="doc.id">
                                        <tr :class="rowBorderClass(doc)">
                                            <td>
                                                <div class="fw-medium">{{ doc.document_type_label ?? doc.document_type }}</div>
                                            </td>
                                            <td v-if="!isDashboardContext">{{ formatDate(doc.issue_date) }}</td>
                                            <td>{{ formatDate(doc.expiry_date) }}</td>
                                            <td v-if="isDashboardContext">{{ formatDaysLeft(doc) }}</td>
                                            <td v-if="!isDashboardContext">{{ doc.reminder_days_before ?? 7 }} days</td>
                                            <td>
                                                <DocumentStatusBadge :document="doc" :soft="isDashboardContext" />
                                            </td>
                                            <td v-if="showActionsColumn" class="app-index-actions-cell">
                                                <template v-if="isDashboardContext">
                                                    <div class="compliance-actions">
                                                        <AppButton
                                                            v-if="canEdit && canRenewDocument(doc)"
                                                            size="sm"
                                                            variant="primary"
                                                            icon="fas fa-rotate-right"
                                                            title="Renew"
                                                            @click="renewDocument(doc)"
                                                        />
                                                    </div>
                                                </template>
                                                <AppTableActions v-else-if="canEdit || canDelete">
                                                    <AppCrudActionButton
                                                        v-if="canEdit"
                                                        action="edit"
                                                        title="Edit document"
                                                        @click="editDocument(doc)"
                                                    />
                                                    <AppCrudActionButton
                                                        v-if="canDelete"
                                                        action="delete"
                                                        title="Delete document"
                                                        @click="removeDocument(doc)"
                                                    />
                                                </AppTableActions>
                                            </td>
                                        </tr>

                                        <tr
                                            v-if="!isDashboardContext && editingDocument?.id === doc.id"
                                            class="vehicle-doc-form-row"
                                        >
                                            <td>
                                                <div class="fw-medium">{{ doc.document_type_label ?? doc.document_type }}</div>
                                            </td>
                                            <td>
                                                <AppDateInput
                                                    v-model="docForm.issue_date"
                                                    size="sm"
                                                    :invalid="hasFieldError(fieldErrors, 'issue_date')"
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
                                                <AppDateInput
                                                    v-model="docForm.expiry_date"
                                                    size="sm"
                                                    :invalid="hasFieldError(fieldErrors, 'expiry_date')"
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
                                                <DocumentStatusBadge :document="doc" />
                                            </td>
                                            <td v-if="showActionsColumn" class="app-index-actions-cell">
                                                <div class="d-flex gap-1">
                                                    <button
                                                        type="button"
                                                        class="btn btn-sm btn-success"
                                                        title="Save"
                                                        :disabled="saving"
                                                        @click="saveDocument('update')"
                                                    >
                                                        <i class="bi bi-check-lg"></i>
                                                    </button>
                                                    <button
                                                        type="button"
                                                        class="btn btn-sm btn-outline-secondary"
                                                        title="Cancel"
                                                        :disabled="saving"
                                                        @click="cancelEdit"
                                                    >
                                                        <i class="bi bi-x-lg"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>

                                        <tr
                                            v-if="isDashboardContext && renewingDocument?.id === doc.id"
                                            class="vehicle-doc-form-row"
                                        >
                                            <td :colspan="tableColspan">
                                                <div class="p-2">
                                                    <h6 class="small fw-bold mb-2">
                                                        Renew — {{ doc.document_type_label ?? doc.document_type }}
                                                    </h6>
                                                    <DocumentFormFields
                                                        v-model="docForm"
                                                        :document-types="[{ id: doc.document_type, label: doc.document_type_label ?? doc.document_type }]"
                                                        :field-errors="fieldErrors"
                                                        :document-type-disabled="true"
                                                        :hide-type="true"
                                                    />
                                                    <div class="d-flex gap-2 mt-2">
                                                        <button
                                                            type="button"
                                                            class="btn btn-sm btn-success"
                                                            :disabled="saving"
                                                            @click="saveDocument('renew')"
                                                        >
                                                            Save
                                                        </button>
                                                        <button
                                                            type="button"
                                                            class="btn btn-sm btn-outline-secondary"
                                                            :disabled="saving"
                                                            @click="cancelRenew"
                                                        >
                                                            Cancel
                                                        </button>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </div>

        <template #footer>
            <button type="button" class="btn btn-secondary" :disabled="saving" @click="close">Close</button>
        </template>
    </AppModal>

    <DocumentTypeQuickAddModal
        :show="showTypeQuickAdd"
        @close="showTypeQuickAdd = false"
        @created="onDocumentTypeCreated"
    />
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
import { useAppToast } from '../../composables/useAppToast';
import {
    canRenewDocument,
    defaultRenewFormValues,
    formatDaysLeft,
    rowBorderClass,
    sortDocumentsByUrgency,
} from '../../utils/documentStatus';
import { formatDate } from '../../utils/datetime';
import { fieldErrorMessage, hasFieldError } from '../../utils/formErrors';
import AppCrudActionButton from '../ui/AppCrudActionButton.vue';
import AppButton from '../ui/AppButton.vue';
import AppIndexHeaderButton from '../ui/AppIndexHeaderButton.vue';
import AppModal from '../ui/AppModal.vue';
import AppTableActions from '../ui/AppTableActions.vue';
import SearchableSelect from '../ui/SearchableSelect.vue';
import '../dashboard/complianceAlerts.css';
import DocumentFormFields from './DocumentFormFields.vue';
import DocumentStatusBadge from './DocumentStatusBadge.vue';
import DocumentTypeQuickAddModal from './DocumentTypeQuickAddModal.vue';

const { fieldErrors, formError, clearErrors, applyApiError } = useApiFormErrors();
const toast = useAppToast();

const props = defineProps({
    show: { type: Boolean, default: false },
    vehicle: { type: Object, default: null },
    canCreate: { type: Boolean, default: false },
    canEdit: { type: Boolean, default: false },
    canDelete: { type: Boolean, default: false },
    modalId: { type: String, required: true },
    initialDocumentId: { type: String, default: null },
    initialAction: { type: String, default: null },
    context: { type: String, default: 'truck' },
});

const emit = defineEmits(['close', 'saved']);

const tableScrollRef = ref(null);

const documents = ref([]);
const loading = ref(false);
const saving = ref(false);
const showDocForm = ref(false);
const showAddPanel = ref(false);
const showTypeQuickAdd = ref(false);
const editingDocument = ref(null);
const renewingDocument = ref(null);
const documentTypes = ref([]);
const vehicleLabel = ref('');
const initialDocumentHandled = ref(false);

const isDashboardContext = computed(() => props.context === 'dashboard');

const sortedDocuments = computed(() => sortDocumentsByUrgency(documents.value));

const showActionsColumn = computed(() => props.canEdit || props.canDelete);

const showAddButton = computed(() => {
    if (isDashboardContext.value) {
        return !showAddPanel.value && !renewingDocument.value && !editingDocument.value;
    }

    return !showDocForm.value;
});

const tableColspan = computed(() => {
    if (isDashboardContext.value) {
        return showActionsColumn.value ? 5 : 4;
    }

    return showActionsColumn.value ? 6 : 5;
});

const documentTypeOptions = computed(() =>
    documentTypes.value.map((type) => ({
        id: type.code,
        label: type.name,
    })),
);

const usedDocumentTypes = computed(() => new Set(documents.value.map((doc) => doc.document_type)));

const selectableDocumentTypes = computed(() => {
    if (editingDocument.value) {
        return documentTypeOptions.value;
    }

    return documentTypeOptions.value.filter((type) => !usedDocumentTypes.value.has(type.id));
});

const addDocumentTypeOptions = computed(() =>
    documentTypeOptions.value.map((type) => ({
        ...type,
        disabled: usedDocumentTypes.value.has(type.id),
    })),
);

const docForm = ref(emptyDocForm());

function emptyDocForm() {
    return {
        document_type: '',
        issue_date: '',
        expiry_date: '',
        reminder_days_before: 7,
    };
}

function openAddForm() {
    if (isDashboardContext.value) {
        editingDocument.value = null;
        renewingDocument.value = null;
        clearErrors();
        docForm.value = emptyDocForm();
        showAddPanel.value = true;

        return;
    }

    editingDocument.value = null;
    clearErrors();
    docForm.value = emptyDocForm();
    showDocForm.value = true;
}

function closeAddPanel() {
    showAddPanel.value = false;
    clearErrors();
    docForm.value = emptyDocForm();
}

function resetDocForm() {
    editingDocument.value = null;
    showDocForm.value = false;
    showAddPanel.value = false;
    renewingDocument.value = null;
    clearErrors();
    docForm.value = emptyDocForm();
}

function cancelEdit() {
    editingDocument.value = null;
    clearErrors();
    docForm.value = emptyDocForm();
}

function cancelRenew() {
    renewingDocument.value = null;
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

function getScrollTop() {
    return tableScrollRef.value?.scrollTop ?? 0;
}

function setScrollTop(value) {
    if (tableScrollRef.value) {
        tableScrollRef.value.scrollTop = value;
    }
}

async function loadDocuments({ preserveInitialAction = false } = {}) {
    if (!props.vehicle?.id) {
        return;
    }

    const scrollTop = getScrollTop();
    loading.value = true;

    try {
        await loadDocumentTypes();
        documents.value = await listVehicleDocuments(props.vehicle.id);

        if (preserveInitialAction) {
            openInitialDocumentIfNeeded();
        }

        setScrollTop(scrollTop);
    } catch (error) {
        documents.value = [];
        showRequestError(error, 'Unable to load documents right now.');
    } finally {
        loading.value = false;
    }
}

function openInitialDocumentIfNeeded() {
    if (!props.initialDocumentId || initialDocumentHandled.value) {
        return;
    }

    const document = documents.value.find((item) => item.id === props.initialDocumentId);

    if (!document) {
        return;
    }

    initialDocumentHandled.value = true;

    if (isDashboardContext.value) {
        if (props.initialAction === 'renew' && canRenewDocument(document)) {
            renewDocument(document);
        }

        return;
    }

    if (props.canEdit) {
        editDocument(document);
    }
}

watch(
    () => props.show,
    async (visible) => {
        if (visible) {
            vehicleLabel.value = props.vehicle?.registration_number ?? '';
            initialDocumentHandled.value = false;
            resetDocForm();
            await loadDocuments({ preserveInitialAction: true });
        }
    },
);

function close() {
    emit('close');
}

function renewDocument(doc) {
    renewingDocument.value = doc;
    editingDocument.value = null;
    showAddPanel.value = false;
    clearErrors();
    docForm.value = defaultRenewFormValues(doc);
}

function editDocument(doc) {
    editingDocument.value = doc;
    renewingDocument.value = null;
    showAddPanel.value = false;
    showDocForm.value = false;
    clearErrors();
    docForm.value = {
        document_type: doc.document_type,
        issue_date: doc.issue_date ?? '',
        expiry_date: doc.expiry_date ?? '',
        reminder_days_before: doc.reminder_days_before ?? 7,
    };
}

function validateDocForm() {
    if (!docForm.value.document_type && !renewingDocument.value) {
        formError.value = 'Document type is required.';

        return false;
    }

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

async function saveDocument(action = 'update') {
    if (!props.vehicle?.id || !validateDocForm()) {
        return;
    }

    saving.value = true;
    clearErrors();

    const payload = {
        vehicle_id: props.vehicle.id,
        document_type: renewingDocument.value?.document_type ?? docForm.value.document_type,
        issue_date: docForm.value.issue_date,
        expiry_date: docForm.value.expiry_date,
        reminder_days_before: Number(docForm.value.reminder_days_before) || 7,
    };

    try {
        let savedDocument = null;
        let savedAction = action;

        if (renewingDocument.value?.id) {
            savedDocument = await updateVehicleDocument(renewingDocument.value.id, payload);
            savedAction = 'renew';
        } else if (editingDocument.value?.id) {
            savedDocument = await updateVehicleDocument(editingDocument.value.id, payload);
            savedAction = 'update';
        } else {
            savedDocument = await createVehicleDocument(payload);
            savedAction = 'add';
        }

        const toastMessages = {
            renew: 'Document renewed successfully.',
            add: 'Document added successfully.',
            update: 'Document updated successfully.',
        };

        toast.success(toastMessages[savedAction] ?? 'Document saved successfully.');

        resetDocForm();
        await loadDocuments();

        emit('saved', {
            action: savedAction,
            document: savedDocument,
            vehicleId: props.vehicle.id,
        });
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

        toast.success('Document deleted successfully.');
        await loadDocuments();

        emit('saved', {
            action: 'delete',
            document: doc,
            vehicleId: props.vehicle.id,
        });
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

    toast.error(firstValidationMessage || message || fallbackMessage);
}
</script>

<style scoped>
.vehicle-doc-table-card > .card-body {
    padding: 0;
    max-height: 420px;
    overflow-y: auto;
}

.vehicle-doc-form-row > td {
    background: rgba(13, 110, 253, 0.06);
    vertical-align: middle;
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
