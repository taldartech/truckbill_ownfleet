<template>
    <div
        id="dashboardComplianceModal"
        ref="modalElement"
        class="modal fade"
        tabindex="-1"
        aria-labelledby="dashboardComplianceModalLabel"
        aria-hidden="true"
    >
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header bg-warning-subtle">
                    <h5 id="dashboardComplianceModalLabel" class="modal-title fw-bold">
                        <i class="fas fa-shield-halved text-warning me-2"></i>
                        Compliance Alerts
                    </h5>
                    <button type="button" class="btn-close" aria-label="Close" @click="close"></button>
                </div>
                <div class="modal-body">
                    <div v-if="loading" class="text-center py-4 text-muted">Loading alerts…</div>
                    <div v-else-if="loadError" class="alert alert-danger mb-0">{{ loadError }}</div>
                    <div v-else-if="isEmpty" class="text-center py-4 text-muted">
                        No compliance alerts right now.
                    </div>
                    <template v-else>
                        <div v-if="documents.length" class="mb-4">
                            <h6 class="fw-bold mb-2">Vehicle Documents</h6>
                            <p class="text-muted small mb-3">
                                Click a row to open that truck's documents and update the expiry.
                            </p>
                            <div class="table-responsive">
                                <table class="table table-sm table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Truck</th>
                                            <th>Document</th>
                                            <th>Expiry</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr
                                            v-for="document in documents"
                                            :key="document.id"
                                            class="compliance-alert-row"
                                            tabindex="0"
                                            role="button"
                                            @click="selectDocument(document)"
                                            @keydown.enter.prevent="selectDocument(document)"
                                        >
                                            <td class="fw-medium">{{ document.vehicle_registration ?? '—' }}</td>
                                            <td>{{ document.document_type_label }}</td>
                                            <td>{{ formatDate(document.expiry_date) }}</td>
                                            <td>
                                                <span :class="['badge rounded-pill', statusBadgeClass(document.status)]">
                                                    {{ statusLabel(document) }}
                                                </span>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div v-if="licenses.length">
                            <h6 class="fw-bold mb-2">Driver Licenses</h6>
                            <p class="text-muted small mb-3">
                                Click a row to edit the driver's license expiry.
                            </p>
                            <div class="table-responsive">
                                <table class="table table-sm table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Driver</th>
                                            <th>License #</th>
                                            <th>Expiry</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr
                                            v-for="license in licenses"
                                            :key="license.driver_id"
                                            :class="[
                                                'compliance-alert-row',
                                                { 'compliance-alert-row--disabled': !canManageLicenses },
                                            ]"
                                            :tabindex="canManageLicenses ? 0 : -1"
                                            :role="canManageLicenses ? 'button' : undefined"
                                            @click="selectLicense(license)"
                                            @keydown.enter.prevent="selectLicense(license)"
                                        >
                                            <td class="fw-medium">{{ license.driver_name }}</td>
                                            <td>{{ license.license_number ?? '—' }}</td>
                                            <td>{{ formatDate(license.license_expiry_date) }}</td>
                                            <td>
                                                <span :class="['badge rounded-pill', statusBadgeClass(license.status)]">
                                                    {{ statusLabel(license) }}
                                                </span>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </template>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" @click="close">Close</button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { useBootstrapModal } from '../../composables/useBootstrapModal';
import { fetchComplianceAlerts } from '../../api/dashboard';
import { formatDate } from '../../utils/datetime';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    canManageLicenses: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['close', 'edit-document', 'edit-license']);

const modalElement = ref(null);
const { show: showBootstrapModal, hide: hideBootstrapModal } = useBootstrapModal(modalElement, {
    onHidden: () => emit('close'),
});

const loading = ref(false);
const loadError = ref('');
const documents = ref([]);
const licenses = ref([]);

const isEmpty = computed(() => documents.value.length === 0 && licenses.value.length === 0);

watch(
    () => props.show,
    async (visible) => {
        if (!modalElement.value) {
            return;
        }

        if (visible) {
            await loadAlerts();
            showBootstrapModal();
        } else {
            hideBootstrapModal();
        }
    },
);

async function loadAlerts() {
    loading.value = true;
    loadError.value = '';

    try {
        const data = await fetchComplianceAlerts();
        documents.value = data.documents;
        licenses.value = data.licenses;
    } catch {
        documents.value = [];
        licenses.value = [];
        loadError.value = 'Could not load compliance alerts.';
    } finally {
        loading.value = false;
    }
}

function close() {
    emit('close');
}

function selectDocument(document) {
    emit('edit-document', document);
}

function selectLicense(license) {
    if (!props.canManageLicenses) {
        return;
    }

    emit('edit-license', license);
}

function statusBadgeClass(status) {
    return status === 'expired' ? 'bg-danger' : 'bg-warning text-dark';
}

function statusLabel(item) {
    const days = Number(item.days_remaining ?? 0);

    if (item.status === 'expired') {
        const daysAgo = Math.abs(days);

        return daysAgo === 0 ? 'Expired today' : `Expired ${daysAgo}d ago`;
    }

    if (days === 0) {
        return 'Expires today';
    }

    return `Expires in ${days}d`;
}

</script>

<style scoped>
.compliance-alert-row {
    cursor: pointer;
}

.compliance-alert-row:hover,
.compliance-alert-row:focus {
    background-color: rgba(47, 179, 111, 0.08);
}

.compliance-alert-row--disabled {
    cursor: default;
}

.compliance-alert-row--disabled:hover {
    background-color: transparent;
}
</style>
