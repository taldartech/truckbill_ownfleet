<template>
    <div class="dashboard-panel">
        <div class="dashboard-panel__head">
            <div class="dashboard-panel__headline">
                <h6 class="dashboard-panel__title">
                    <i class="fas fa-shield-halved me-2 text-warning"></i>
                    Compliance Alerts
                </h6>
                <p v-if="periodLabel" class="dashboard-panel__date-hint">Date: {{ periodLabel }}</p>
            </div>
            <AppButton
                v-if="showViewAll"
                size="sm"
                outline
                variant="primary"
                label="View All"
                icon="fas fa-list-ul"
                @click="$emit('view-all')"
            />
        </div>

        <div v-if="loading" class="dashboard-panel__empty text-muted">Loading alerts…</div>
        <template v-else>
            <div class="compliance-summary-grid">
                <div
                    v-for="item in summaryItems"
                    :key="item.key"
                    class="compliance-summary-card"
                    :class="`compliance-summary-card--${item.tone}`"
                >
                    <span class="compliance-summary-card__value">{{ item.value }}</span>
                    <span class="compliance-summary-card__label">{{ item.label }}</span>
                </div>
            </div>

            <div v-if="isEmpty" class="dashboard-panel__empty text-muted">
                No compliance alerts right now.
            </div>

            <div v-else class="dashboard-panel__body">
                <div v-if="documents.length" class="compliance-section">
                    <div class="dashboard-panel__subtitle">Vehicle Documents</div>
                    <div class="table-responsive compliance-table">
                        <table class="table table-sm table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Truck</th>
                                    <th>Document</th>
                                    <th>Expiry</th>
                                    <th>Status</th>
                                    <th v-if="canEditDocuments" class="text-end" style="width: 3.5rem">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template v-for="document in documents" :key="`doc-${document.id}`">
                                    <tr :class="['dashboard-panel__row', rowBorderClass(document)]">
                                        <td class="fw-medium">{{ document.vehicle_registration ?? '—' }}</td>
                                        <td>{{ document.document_type_label }}</td>
                                        <td class="text-muted">{{ formatDate(document.expiry_date) }}</td>
                                        <td>
                                            <DocumentStatusBadge :document="document" soft />
                                        </td>
                                        <td v-if="canEditDocuments" class="text-end">
                                            <div class="compliance-actions">
                                                <AppButton
                                                    v-if="canRenewDocument(document)"
                                                    size="sm"
                                                    variant="warning"
                                                    icon="fas fa-rotate-right"
                                                    title="Renew Now"
                                                    @click.stop="startRenew(document)"
                                                />
                                            </div>
                                        </td>
                                    </tr>
                                    <tr
                                        v-if="renewingDocumentId === document.id"
                                        class="dashboard-panel__renew-row"
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

                <div v-if="licenses.length" class="compliance-section">
                    <div class="dashboard-panel__section-head">
                        <div class="dashboard-panel__subtitle mb-0">Driver Licenses</div>
                        <AppButton
                            size="sm"
                            outline
                            variant="primary"
                            label="Open Drivers"
                            icon="fas fa-arrow-up-right-from-square"
                            :to="{ name: 'drivers.index', query: { license_alerts: '1' } }"
                        />
                    </div>
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
                                <template v-for="license in licenses" :key="`lic-${license.driver_id}`">
                                    <tr
                                        :class="[
                                            'dashboard-panel__row',
                                            rowBorderClass(license),
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
                                                    v-if="canUpdateLicense(license)"
                                                    size="sm"
                                                    variant="primary"
                                                    outline
                                                    icon="fas fa-calendar-days"
                                                    title="Update Expiry"
                                                    @click.stop="startExpiryUpdate(license)"
                                                />
                                            </div>
                                        </td>
                                    </tr>
                                    <tr
                                        v-if="updatingLicenseDriverId === license.driver_id"
                                        class="dashboard-panel__renew-row"
                                    >
                                        <td :colspan="licenseTableColspan">
                                            <LicenseExpiryInlineForm
                                                :license="license"
                                                @saved="handleExpirySaved"
                                                @cancel="cancelExpiryUpdate"
                                            />
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div v-if="ewayBills.length" class="compliance-section">
                    <div class="dashboard-panel__section-head">
                        <div class="dashboard-panel__subtitle mb-0">
                            E-Way Bill Alerts
                        </div>
                        <AppButton
                            size="sm"
                            outline
                            variant="primary"
                            label="Open Trips"
                            icon="fas fa-arrow-up-right-from-square"
                            :to="{ name: 'trips.index' }"
                        />
                    </div>
                    <div class="table-responsive compliance-table">
                        <table class="table table-sm table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Vehicle / Trip</th>
                                    <th>E-Way Bill</th>
                                    <th>Expiry</th>
                                    <th>Status</th>
                                    <th class="text-end" style="width: 3.5rem">
                                        Action
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="ewayBill in ewayBills"
                                    :key="`eway-${ewayBill.id}`"
                                    class="dashboard-panel__row"
                                    :class="ewayBill.status === 'expired'
                                        ? 'dashboard-panel__row--danger'
                                        : 'dashboard-panel__row--warning'"
                                >
                                    <td>
                                        <div class="fw-medium">
                                            {{ ewayBill.vehicle_registration ?? '—' }}
                                        </div>
                                    </td>
                                
                                    <td class="fw-medium">
                                        {{ ewayBill.ewaybill_no }}
                                    </td>
                                
                                    <td class="text-muted">
                                        {{ formatDate(ewayBill.expiry_date) }}
                                    </td>
                                
                                    <td>
                                        <span
                                            v-if="ewayBill.status === 'expired'"
                                            class="badge bg-danger"
                                        >
                                            Expired
                                        </span>
                                    
                                        <span
                                            v-else
                                            class="badge bg-warning text-dark"
                                        >
                                            Expiring
                                        </span>
                                    </td>
                                
                                    <td class="text-end">
                                        <AppButton
                                            size="sm"
                                            variant="primary"
                                            outline
                                            icon="fas fa-arrow-up-right-from-square"
                                            title="Open Trip"
                                            :to="{
                                                name: 'trips.index',
                                                query: {trip_id: ewayBill.trip_id},
                                                }"
                                            />
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div v-if="dueServices.length" class="compliance-section">
                    <div class="dashboard-panel__section-head">
                        <div class="dashboard-panel__subtitle mb-0">Due Services</div>
                        <AppButton
                            size="sm"
                            outline
                            variant="primary"
                            label="Open Due Services"
                            icon="fas fa-arrow-up-right-from-square"
                            :to="{ name: 'maintenance.due' }"
                        />
                    </div>
                    <div class="table-responsive compliance-table">
                        <table class="table table-sm table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Truck</th>
                                    <th>Service Type</th>
                                    <th>Next Due Date</th>
                                    <th>Overdue</th>
                                    <th class="text-end" style="width: 3.5rem">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="service in dueServices"
                                    :key="`due-${service.id}`"
                                    class="dashboard-panel__row"
                                >
                                    <td class="fw-medium">{{ service.vehicle_registration ?? '—' }}</td>
                                    <td>{{ service.service_type_label ?? service.service_type ?? '—' }}</td>
                                    <td>
                                        <span
                                            v-if="service.next_service_due_date"
                                            class="badge"
                                            :class="isDateOverdue(service) ? 'bg-danger' : 'bg-light text-dark border'"
                                        >
                                            {{ formatDate(service.next_service_due_date) }}
                                        </span>
                                        <span v-else class="text-muted">—</span>
                                    </td>
                                    <td>
                                        <span class="badge bg-danger">{{ service.overdue_reason_label ?? '—' }}</span>
                                    </td>
                                    <td class="text-end">
                                        <div class="compliance-actions">
                                            <AppButton
                                                size="sm"
                                                variant="primary"
                                                outline
                                                icon="fas fa-wrench"
                                                title="Open Due Services"
                                                :to="{ name: 'maintenance.due' }"
                                            />
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </template>
    </div>
</template>

<script setup>
import { computed, ref } from 'vue';
import { canRenewDocument, canUpdateLicense, complianceAlertWindowLabel, rowBorderClass } from '../../utils/documentStatus';
import { isMaintenanceDateOverdue } from '../../utils/maintenanceDueStatus';
import { formatDate } from '../../utils/datetime';
import LicenseExpiryInlineForm from '../drivers/LicenseExpiryInlineForm.vue';
import DocumentStatusBadge from '../trucks/DocumentStatusBadge.vue';
import DocumentRenewInlineForm from '../trucks/DocumentRenewInlineForm.vue';
import AppButton from '../ui/AppButton.vue';
import './complianceAlerts.css';

const props = defineProps({
    documents: {
        type: Array,
        default: () => [],
    },
    ewayBills: {
    type: Array,
    default: () => [],
    },
    licenses: {
        type: Array,
        default: () => [],
    },
    dueServices: {
        type: Array,
        default: () => [],
    },
    summary: {
        type: Object,
        default: () => ({
            total: 0,
            expired: 0,
            expiring: 0,
            licenses: 0,
            dueServices: 0,
        }),
    },
    loading: {
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

const emit = defineEmits(['view-all', 'saved']);

const renewingDocumentId = ref(null);
const updatingLicenseDriverId = ref(null);

const periodLabel = computed(() => complianceAlertWindowLabel());

const isEmpty = computed(
    () =>
        props.documents.length === 0
        && props.licenses.length === 0
        && props.dueServices.length === 0
        && props.ewayBills.length === 0,
);
const documentTableColspan = computed(() => (props.canEditDocuments ? 5 : 4));
const licenseTableColspan = computed(() => (props.canManageLicenses ? 5 : 4));

const showViewAll = computed(() => {
    if (props.loading) {
        return false;
    }

    return props.documents.length > 0 || props.licenses.length > 0;
});

const summaryItems = computed(() => [
    {
        key: 'total',
        label: 'Total Alerts',
        value: Number(props.summary?.total ?? 0),
        tone: 'neutral',
    },
    {
        key: 'expired',
        label: 'Expired',
        value: Number(props.summary?.expired ?? 0),
        tone: 'danger',
    },
    {
        key: 'expiring',
        label: 'Expiring',
        value: Number(props.summary?.expiring ?? 0),
        tone: 'warning',
    },
    {
        key: 'licenses',
        label: 'Licenses',
        value: Number(props.summary?.licenses ?? 0),
        tone: 'info',
    },
    {
        key: 'ewayBills',
        label: 'E-Way Bills',
        value: Number(props.summary?.ewayBills ?? 0),
        tone: 'info',
    },
    {
        key: 'dueServices',
        label: 'Due Services',
        value: Number(props.summary?.dueServices ?? 0),
        tone: 'warning',
    },
]);

function isDateOverdue(service) {
    return isMaintenanceDateOverdue(service);
}

function startRenew(document) {
    renewingDocumentId.value = document.id;
    updatingLicenseDriverId.value = null;
}

function cancelRenew() {
    renewingDocumentId.value = null;
}

function handleRenewSaved(payload) {
    renewingDocumentId.value = null;
    emit('saved', payload);
}

function startExpiryUpdate(license) {
    updatingLicenseDriverId.value = license.driver_id;
    renewingDocumentId.value = null;
}

function cancelExpiryUpdate() {
    updatingLicenseDriverId.value = null;
}

function handleExpirySaved(payload) {
    updatingLicenseDriverId.value = null;
    emit('saved', payload);
}
</script>

<style scoped>
.dashboard-panel {
    display: flex;
    flex-direction: column;
    padding: 1.25rem;
    border: 1px solid rgba(226, 232, 240, 0.8);
    border-radius: 1.25rem;
    background: linear-gradient(185deg, #ffffff 0%, #f8fafc 100%);
    box-shadow: 0 4px 18px rgba(15, 23, 42, 0.02);
}

.dashboard-panel__head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 0.75rem;
    margin-bottom: 1rem;
    flex-shrink: 0;
}

.dashboard-panel__headline {
    min-width: 0;
}

.dashboard-panel__title {
    margin: 0;
    font-size: 0.92rem;
    font-weight: 700;
    color: #0f172a;
}

.dashboard-panel__date-hint {
    margin: 0.2rem 0 0;
    font-size: 0.72rem;
    color: #94a3b8;
}

.compliance-summary-grid {
    display: grid;
    grid-template-columns: repeat(6, minmax(0, 1fr));
    gap: 0.75rem;
    margin-bottom: 1.1rem;
}

.compliance-summary-card {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
    padding: 0.85rem 1rem;
    border-radius: 0.85rem;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    min-width: 0;
}

.compliance-summary-card__value {
    font-size: 1.45rem;
    line-height: 1.1;
    font-weight: 800;
    color: #0f172a;
}

.compliance-summary-card__label {
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: none;
    letter-spacing: normal;
    color: #64748b;
}

.compliance-summary-card--danger {
    border-color: rgba(248, 113, 113, 0.35);
    background: linear-gradient(180deg, #fff 0%, #fff5f5 100%);
}

.compliance-summary-card--danger .compliance-summary-card__value {
    color: #dc2626;
}

.compliance-summary-card--warning {
    border-color: rgba(251, 191, 36, 0.35);
    background: linear-gradient(180deg, #fff 0%, #fffbeb 100%);
}

.compliance-summary-card--warning .compliance-summary-card__value {
    color: #d97706;
}

.compliance-summary-card--info {
    border-color: rgba(96, 165, 250, 0.35);
    background: linear-gradient(180deg, #fff 0%, #eff6ff 100%);
}

.compliance-summary-card--info .compliance-summary-card__value {
    color: #2563eb;
}

.dashboard-panel__body {
    display: flex;
    flex-direction: column;
    gap: 1.1rem;
    min-width: 0;
}

.compliance-section {
    min-width: 0;
}

.dashboard-panel__subtitle {
    margin-bottom: 0.45rem;
    font-size: 0.78rem;
    font-weight: 600;
    color: var(--brand-text-muted, #64748b);
    text-transform: none;
    letter-spacing: normal;
}

.dashboard-panel__section-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    margin-bottom: 0.45rem;
}

.dashboard-panel__empty {
    padding: 0.75rem 0;
    font-size: 0.88rem;
}

.dashboard-panel__row--disabled {
    cursor: default;
}

.dashboard-panel__row--disabled:hover,
.dashboard-panel__row--disabled:focus {
    background: transparent;
}

.dashboard-panel__renew-row > td {
    background: rgba(248, 250, 252, 0.9);
    border-top: none;
}

@media (max-width: 767.98px) {
    .compliance-summary-grid {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
}

@media (max-width: 479.98px) {
    .compliance-summary-grid {
        grid-template-columns: 1fr;
    }
}

.dashboard-panel__row--danger {
    border-left: 3px solid #dc2626;
    background: rgba(254, 242, 242, 0.55);
}

.dashboard-panel__row--warning {
    border-left: 3px solid #d97706;
    background: rgba(255, 251, 235, 0.55);
}

.dashboard-panel__row--danger:hover {
    background: rgba(254, 226, 226, 0.75);
}

.dashboard-panel__row--warning:hover {
    background: rgba(254, 243, 199, 0.75);
}
</style>
