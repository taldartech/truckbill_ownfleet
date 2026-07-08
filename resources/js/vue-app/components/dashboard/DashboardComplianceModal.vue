<template>
    <AppModal
        :show="show"
        :loading="loading"
        :header-gradient="false"
        content-class="compliance-modal"
        @close="close"
    >
        <template #title>
            <i class="fas fa-shield-halved me-2"></i>
            Compliance Alerts
        </template>

        <div v-if="loading" class="text-center py-4 text-muted">Loading alerts…</div>
        <div v-else-if="loadError" class="alert alert-danger mb-0">{{ loadError }}</div>
        <div v-else-if="isEmpty" class="text-center py-4 text-muted">
            No compliance alerts right now.
        </div>
        <template v-else>
            <div v-if="documents.length" class="mb-4">
                <h6 class="fw-bold mb-1">Vehicle Documents</h6>
                <p class="text-muted small mb-3">
                    Most urgent items appear first. Renew from here.
                </p>
                <div class="table-responsive compliance-table">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Truck</th>
                                <th>Document</th>
                                <th>Expiry</th>
                                
                                <th>Status</th>
                                <th v-if="canEditDocuments" class="text-end compliance-actions-col">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template v-for="document in documents" :key="document.id">
                                <tr :class="['compliance-alert-row', rowBorderClass(document)]">
                                    <td class="fw-medium">{{ document.vehicle_registration ?? '—' }}</td>
                                    <td>{{ document.document_type_label }}</td>
                                    <td class="text-muted">{{ formatDate(document.expiry_date) }}</td>
                                    <td>
                                        <DocumentStatusBadge :document="document" soft />
                                    </td>
                                    <td v-if="canEditDocuments" class="text-end">
                                        <div class="compliance-actions">
                                            <AppButton
                                                v-if="canEditDocuments && canRenewDocument(document)"
                                                size="sm"
                                                variant="primary"
                                                icon="fas fa-rotate-right"
                                                title="Renew"
                                                @click="startRenew(document)"
                                            />
                                        </div>
                                    </td>
                                </tr>
                                <tr
                                    v-if="renewingDocumentId === document.id"
                                    class="compliance-renew-row"
                                >
                                    <td :colspan="documentTableColspan">
                                        <DocumentRenewInlineForm
                                            :document="document"
                                            @saved="handleRenewSaved"
                                            @cancel="cancelRenew"
                                        />
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>

            <div v-if="licenses.length">
                <h6 class="fw-bold mb-1">Driver Licenses</h6>
                <p class="text-muted small mb-3">
                    Update license expiry for drivers needing attention.
                </p>
                <div class="table-responsive compliance-table">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Driver</th>
                                <th>License #</th>
                                <th>Expiry</th>
                                <th>Status</th>
                                <th v-if="canManageLicenses" class="text-end compliance-actions-col">
                                    Action
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="license in licenses"
                                :key="license.driver_id"
                                :class="[
                                    'compliance-alert-row',
                                    rowBorderClass(license),
                                    { 'compliance-alert-row--disabled': !canManageLicenses },
                                ]"
                            >
                                <td class="fw-medium">{{ license.driver_name }}</td>
                                <td>{{ license.license_number ?? '—' }}</td>
                                <td class="text-muted">{{ formatDate(license.license_expiry_date) }}</td>
                                <td>
                                    <DocumentStatusBadge :document="license" soft />
                                </td>
                                <td v-if="canManageLicenses" class="text-end">
                                    <div class="compliance-actions">
                                        <AppButton
                                            size="sm"
                                            variant="secondary"
                                            outline
                                            icon="fas fa-eye"
                                            title="View"
                                            :disabled="!canManageLicenses"
                                            @click="selectLicense(license)"
                                        />
                                        <AppButton
                                            v-if="canUpdateLicense(license)"
                                            size="sm"
                                            variant="primary"
                                            icon="fas fa-id-card"
                                            title="Update license"
                                            @click="selectLicense(license)"
                                        />
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </template>

        <template #footer>
            <button type="button" class="btn btn-secondary" :disabled="loading" @click="close">Close</button>
        </template>
    </AppModal>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import AppModal from '../ui/AppModal.vue';
import { fetchComplianceAlerts } from '../../api/dashboard';
import {
    canRenewDocument,
    canUpdateLicense,
    rowBorderClass,
    sortDocumentsByUrgency,
} from '../../utils/documentStatus';
import { formatDate } from '../../utils/datetime';
import DocumentStatusBadge from '../trucks/DocumentStatusBadge.vue';
import DocumentRenewInlineForm from '../trucks/DocumentRenewInlineForm.vue';
import AppButton from '../ui/AppButton.vue';
import './complianceAlerts.css';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    canManageLicenses: {
        type: Boolean,
        default: false,
    },
    canEditDocuments: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['close', 'edit-license', 'saved']);

const loading = ref(false);
const loadError = ref('');
const documents = ref([]);
const licenses = ref([]);
const renewingDocumentId = ref(null);

const isEmpty = computed(() => documents.value.length === 0 && licenses.value.length === 0);
const documentTableColspan = computed(() => (props.canEditDocuments ? 5 : 4));

watch(
    () => props.show,
    async (visible) => {
        if (visible) {
            await loadAlerts();
        } else {
            renewingDocumentId.value = null;
        }
    },
);

async function loadAlerts() {
    loading.value = true;
    loadError.value = '';

    try {
        const data = await fetchComplianceAlerts();
        documents.value = sortDocumentsByUrgency(data.documents ?? []);
        licenses.value = sortDocumentsByUrgency(data.licenses ?? []);
    } catch {
        documents.value = [];
        licenses.value = [];
        loadError.value = 'Could not load compliance alerts.';
    } finally {
        loading.value = false;
    }
}

function close() {
    renewingDocumentId.value = null;
    emit('close');
}

function startRenew(document) {
    renewingDocumentId.value = document.id;
}

function cancelRenew() {
    renewingDocumentId.value = null;
}

async function handleRenewSaved(payload) {
    renewingDocumentId.value = null;
    await loadAlerts();
    emit('saved', payload);
}

function selectLicense(license) {
    if (!props.canManageLicenses) {
        return;
    }

    emit('edit-license', license);
}

defineExpose({
    refreshAlerts: loadAlerts,
});
</script>

<style scoped>
.compliance-alert-row:hover {
    background-color: rgba(47, 179, 111, 0.04);
}

.compliance-alert-row--disabled {
    cursor: default;
}

.compliance-alert-row--disabled:hover {
    background-color: transparent;
}

.compliance-renew-row > td {
    background: rgba(248, 250, 252, 0.9);
    border-top: none;
}
</style>
