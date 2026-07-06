<template>
    <div class="dashboard-panel">
        <div class="dashboard-panel__head">
            <h6 class="dashboard-panel__title">
                <i class="fas fa-shield-halved me-2 text-warning"></i>
                Compliance Alerts
            </h6>
            <AppButton
                v-if="showViewAll"
                size="sm"
                outline
                variant="primary"
                label="View all"
                icon="fas fa-list-ul"
                @click="$emit('view-all')"
            />
        </div>

        <div v-if="loading" class="dashboard-panel__empty text-muted">Loading alerts…</div>
        <div v-else-if="isEmpty" class="dashboard-panel__empty text-muted">No compliance alerts right now.</div>

        <div v-else class="dashboard-panel__body">
            <div v-if="documents.length" class="mb-3">
                <div class="dashboard-panel__subtitle">Vehicle documents</div>
                <div class="table-responsive compliance-table">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Truck</th>
                                <th>Document</th>
                                <th>Expiry</th>
                                <th>Status</th>
                                <th v-if="canManageDocuments" class="text-end" style="width: 3.5rem">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="document in documents"
                                :key="`doc-${document.id}`"
                                :class="['dashboard-panel__row', rowBorderClass(document)]"
                                tabindex="0"
                                role="button"
                                @click="canManageDocuments && $emit('edit-document', document)"
                                @keydown.enter.prevent="canManageDocuments && $emit('edit-document', document)"
                            >
                                <td class="fw-medium">{{ document.vehicle_registration ?? '—' }}</td>
                                <td>{{ document.document_type_label }}</td>
                                <td class="text-muted">{{ formatDate(document.expiry_date) }}</td>
                                <td>
                                    <DocumentStatusBadge :document="document" soft />
                                </td>
                                <td v-if="canManageDocuments" class="text-end">
                                    <div class="compliance-actions">
                                        <AppButton
                                            v-if="canRenewDocument(document)"
                                            size="sm"
                                            variant="primary"
                                            icon="fas fa-rotate-right"
                                            title="Renew Now"
                                            @click.stop="$emit('renew-document', document)"
                                        />
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div v-if="licenses.length">
                <div class="dashboard-panel__subtitle">Driver licenses</div>
                <div class="table-responsive compliance-table">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Driver</th>
                                <th>License</th>
                                <th>Expiry</th>
                                <th>Status</th>
                                <th v-if="canManageLicenses" class="text-end" style="width: 3.5rem">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="license in licenses"
                                :key="`lic-${license.driver_id}`"
                                :class="[
                                    'dashboard-panel__row',
                                    rowBorderClass(license),
                                    { 'dashboard-panel__row--disabled': !canManageLicenses },
                                ]"
                                :tabindex="canManageLicenses ? 0 : -1"
                                :role="canManageLicenses ? 'button' : undefined"
                                @click="canManageLicenses && $emit('edit-license', license)"
                                @keydown.enter.prevent="canManageLicenses && $emit('edit-license', license)"
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
                                            v-if="canUpdateLicense(license)"
                                            size="sm"
                                            variant="primary"
                                            outline
                                            icon="fas fa-id-card"
                                            title="Update license"
                                            @click.stop="$emit('edit-license', license)"
                                        />
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { canRenewDocument, canUpdateLicense, rowBorderClass } from '../../utils/documentStatus';
import { formatDate } from '../../utils/datetime';
import DocumentStatusBadge from '../trucks/DocumentStatusBadge.vue';
import AppButton from '../ui/AppButton.vue';
import './complianceAlerts.css';

const props = defineProps({
    documents: {
        type: Array,
        default: () => [],
    },
    licenses: {
        type: Array,
        default: () => [],
    },
    totalCount: {
        type: Number,
        default: 0,
    },
    loading: {
        type: Boolean,
        default: false,
    },
    canManageLicenses: {
        type: Boolean,
        default: false,
    },
    canManageDocuments: {
        type: Boolean,
        default: false,
    },
});

defineEmits(['view-all', 'edit-document', 'renew-document', 'edit-license']);

const isEmpty = computed(() => props.documents.length === 0 && props.licenses.length === 0);

const showViewAll = computed(() => !props.loading && props.totalCount > 0);
</script>

<style scoped>
.dashboard-panel {
    display: flex;
    flex-direction: column;
    height: 100%;
    padding: 1.25rem;
    border: 1px solid rgba(226, 232, 240, 0.8);
    border-radius: 1.25rem;
    background: linear-gradient(185deg, #ffffff 0%, #f8fafc 100%);
    box-shadow: 0 4px 18px rgba(15, 23, 42, 0.02);
}

.dashboard-panel__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    margin-bottom: 0.85rem;
    flex-shrink: 0;
}

.dashboard-panel__title {
    margin: 0;
    font-size: 0.92rem;
    font-weight: 700;
    color: #0f172a;
}

.dashboard-panel__body {
    flex: 1;
    min-height: 0;
    overflow-y: auto;
}

.dashboard-panel__subtitle {
    margin-bottom: 0.45rem;
    font-size: 0.78rem;
    font-weight: 600;
    color: var(--brand-text-muted, #64748b);
    text-transform: uppercase;
    letter-spacing: 0.04em;
}

.dashboard-panel__empty {
    padding: 0.75rem 0;
    font-size: 0.88rem;
}

.dashboard-panel__row {
    cursor: pointer;
}

.dashboard-panel__row:hover,
.dashboard-panel__row:focus {
    background: rgba(47, 179, 111, 0.04);
    outline: none;
}

.dashboard-panel__row--disabled {
    cursor: default;
}

.dashboard-panel__row--disabled:hover,
.dashboard-panel__row--disabled:focus {
    background: transparent;
}
</style>
