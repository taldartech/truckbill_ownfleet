<template>
    <div v-if="loading" class="py-5 text-center text-muted">Loading dashboard…</div>

    <div v-else-if="loadError" class="alert alert-danger">{{ loadError }}</div>

    <template v-else>
        <RoleDashboardPlaceholder
            v-if="showRolePlaceholder"
            :role="currentRole"
            :user="user"
        />

        <template v-else>
            <div class="dashboard-page">
                <header class="dashboard-page__hero">
                    <div class="dashboard-page__hero-content">
                        <div class="dashboard-page__live-badge">
                            <span class="live-badge-dot"></span>
                            <span class="live-badge-text">System Live</span>
                        </div>
                        <h2 class="dashboard-page__title">
                            {{ greetingMessage }}
                        </h2>
                        <p class="dashboard-page__subtitle mb-0">
                            <span v-if="user?.role === 'super_admin'">
                                <i class="fas fa-building text-brand-primary me-1"></i>
                                Company: <strong>{{ user?.tenant_name ?? user?.company?.name }}</strong>
                            </span>
                            <span v-else>Overview of trips, fleet, tyres, attendance, and compliance.</span>
                        </p>
                    </div>
                    <div class="dashboard-page__hero-actions">
                        <div v-if="quickActions.length" class="dashboard-page__quick-actions">
                            <RouterLink
                                v-for="action in quickActions"
                                :key="action.label"
                                :to="action.to"
                                class="btn btn-sm btn-primary dashboard-page__quick-action"
                            >
                                <i :class="[action.icon, 'me-1']"></i>
                                {{ action.label }}
                            </RouterLink>
                        </div>
                        <DashboardDateRangePicker
                            v-if="showPeriodFilter"
                            v-model:from-date="periodFromDate"
                            v-model:to-date="periodToDate"
                            label=""
                            @change="loadDashboard"
                        />
                        <div v-if="!showPeriodFilter" class="dashboard-page__today">
                            <i class="fas fa-calendar-day me-2"></i>
                            {{ todayLabel }}
                        </div>
                    </div>
                </header>

            <div v-if="showCompanyRequiredNotice" class="alert alert-warning">
                Enter a company from
                <RouterLink :to="{ name: 'companies.index' }" class="alert-link">Company List</RouterLink>
                to see fleet statistics.
            </div>

            <template v-else>
                <div class="dashboard-page__body">
                    <div v-if="canViewTrips" class="dashboard-page__analytics">
                        <DashboardAnalyticsChart
                            v-if="tripStats && fleetStats && tyreStats"
                            :trip-stats="tripStats"
                            :fleet-stats="fleetStats"
                            :tyre-stats="tyreStats"
                        />
                    </div>

                    <div v-if="showComplianceSection" class="dashboard-page__compliance-row">
                        <div class="dashboard-page__compliance-summary">
                            <DashboardGroupedStatCard
                                title="Compliance"
                                :total="complianceTotalCount"
                                total-label="Alerts"
                                icon="fas fa-shield-halved"
                                tone="warning"
                                hint="Documents and licenses needing attention. Click to view and update."
                                clickable
                                :items="complianceBreakdownItems"
                                @click="openComplianceModal"
                            />
                        </div>

                        <div class="dashboard-page__compliance-alerts">
                            <DashboardComplianceAlertsPanel
                                :documents="compliancePreview.documents"
                                :licenses="compliancePreview.licenses"
                                :total-count="complianceTotalCount"
                                :loading="complianceLoading"
                                :can-manage-licenses="canManageDrivers"
                                @view-all="openComplianceModal"
                                @edit-document="openDocumentFromAlert"
                                @edit-license="openDriverFromAlert"
                            />
                        </div>
                    </div>
                </div>
            </template>
            </div>
        </template>

        <DashboardComplianceModal
            :show="showComplianceModal"
            :can-manage-licenses="canManageDrivers"
            @close="closeComplianceModal"
            @edit-document="openDocumentFromAlert"
            @edit-license="openDriverFromAlert"
        />

        <VehicleDocumentsModal
            modal-id="dashboardVehicleDocumentsModal"
            :show="showDocumentsModal"
            :vehicle="documentsVehicle"
            :initial-document-id="initialDocumentId"
            :can-manage="canManageDocuments"
            @close="closeDocumentsModal"
            @saved="onComplianceItemSaved"
        />

        <DriverFormModal
            modal-id="dashboardDriverFormModal"
            :show="showDriverModal"
            mode="edit"
            :driver="editingDriver"
            :saving="driverSaving"
            :field-errors="driverFieldErrors"
            :form-error="driverFormError"
            @close="closeDriverModal"
            @submit="handleDriverUpdate"
        />
    </template>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { RouterLink } from 'vue-router';
import api from '../api';
import { fetchComplianceAlerts } from '../api/dashboard';
import { fetchDriver, updateDriver } from '../api/drivers';
import DashboardAnalyticsChart from '../components/dashboard/DashboardAnalyticsChart.vue';
import DashboardComplianceAlertsPanel from '../components/dashboard/DashboardComplianceAlertsPanel.vue';
import DashboardGroupedStatCard from '../components/dashboard/DashboardGroupedStatCard.vue';
import DashboardComplianceModal from '../components/dashboard/DashboardComplianceModal.vue';
import DashboardDateRangePicker from '../components/dashboard/DashboardDateRangePicker.vue';
import RoleDashboardPlaceholder from '../components/dashboard/RoleDashboardPlaceholder.vue';
import DriverFormModal from '../components/drivers/DriverFormModal.vue';
import VehicleDocumentsModal from '../components/trucks/VehicleDocumentsModal.vue';
import { useApiFormErrors } from '../composables/useApiFormErrors';
import { usePermissions } from '../composables/usePermissions';
import { defaultDashboardTripRange } from '../utils/dateRangePresets';
import { formatDate } from '../utils/datetime';

const { user, canInCompany } = usePermissions();

const greetingMessage = computed(() => {
    const hour = new Date().getHours();
    const name = user.value?.name ? `, ${user.value.name}` : '';
    if (hour < 12) return `Good Morning${name}! ☀️`;
    if (hour < 17) return `Good Afternoon${name}! 🌤️`;
    return `Good Evening${name}! 🌙`;
});

const loading = ref(true);
const loadError = ref('');
const fleetStats = ref(null);
const tripStats = ref(null);
const tyreStats = ref(null);
const requiresCompany = ref(false);
const complianceLoading = ref(false);
const compliancePreview = ref({ documents: [], licenses: [] });

const defaultPeriodRange = defaultDashboardTripRange();
const periodFromDate = ref(defaultPeriodRange.from_date);
const periodToDate = ref(defaultPeriodRange.to_date);

const currentRole = computed(() => user.value?.role ?? 'user');
const showRolePlaceholder = computed(() => !canInCompany('dashboard.view'));
const canViewTrips = computed(() => canInCompany('trips.view'));
const canViewTyres = computed(() => canInCompany('tyres.view'));
const showPeriodFilter = computed(() => canViewTrips.value || canViewTyres.value);
const canViewAttendance = computed(() => canInCompany('attendances.view'));
const canManageDocuments = computed(() => canInCompany('vehicle_documents.manage'));
const canManageDrivers = computed(() => canInCompany('drivers.manage'));
const canManageTrips = computed(() => canInCompany('trips.manage'));
const canViewComplianceAlerts = computed(
    () => canInCompany('vehicle_documents.view') || canInCompany('drivers.view'),
);

const quickActions = computed(() => {
    const actions = [];

    if (canManageTrips.value) {
        actions.push({
            label: 'New Trip',
            icon: 'fas fa-plus',
            to: { name: 'trips.create' },
        });
    }

    if (canViewAttendance.value) {
        actions.push({
            label: 'Attendance',
            icon: 'fas fa-calendar-check',
            to: { name: 'attendance.index' },
        });
    }

    return actions;
});

const showComplianceModal = ref(false);
const showDocumentsModal = ref(false);
const showDriverModal = ref(false);
const documentsVehicle = ref(null);
const initialDocumentId = ref(null);
const editingDriver = ref(null);
const driverSaving = ref(false);

const {
    fieldErrors: driverFieldErrors,
    formError: driverFormError,
    clearErrors: clearDriverErrors,
    applyApiError: applyDriverApiError,
} = useApiFormErrors();

const userHasCompanyContext = computed(() => {
    if (user.value?.requires_company_selection) {
        return false;
    }

    const tenantId = user.value?.effective_tenant_id ?? user.value?.company_id;

    return tenantId != null;
});

const showCompanyRequiredNotice = computed(
    () => requiresCompany.value && !userHasCompanyContext.value,
);

const todayLabel = formatDate(new Date());

const complianceBreakdownItems = computed(() => {
    const stats = fleetStats.value ?? {};
    const items = [];

    if (stats.expired_documents_count != null) {
        items.push({
            key: 'expired_docs',
            label: 'Expired',
            value: stats.expired_documents_count,
        });
    }

    if (stats.expiring_documents_count != null) {
        items.push({
            key: 'expiring_docs',
            label: 'Expiring',
            value: stats.expiring_documents_count,
        });
    }

    if (stats.license_expiring_count != null) {
        items.push({
            key: 'expiring_licenses',
            label: 'Licenses',
            value: stats.license_expiring_count,
        });
    }

    return items;
});

const complianceTotalCount = computed(() =>
    complianceBreakdownItems.value.reduce((sum, item) => sum + Number(item.value ?? 0), 0),
);

const showComplianceSection = computed(() => complianceBreakdownItems.value.length > 0);

function openComplianceModal() {
    showComplianceModal.value = true;
}

function closeComplianceModal() {
    showComplianceModal.value = false;
}

function openDocumentFromAlert(document) {
    showComplianceModal.value = false;
    documentsVehicle.value = {
        id: document.vehicle_id,
        registration_number: document.vehicle_registration,
    };
    initialDocumentId.value = document.id;
    showDocumentsModal.value = true;
}

function closeDocumentsModal() {
    showDocumentsModal.value = false;
    documentsVehicle.value = null;
    initialDocumentId.value = null;
}

async function openDriverFromAlert(license) {
    if (!canManageDrivers.value) {
        return;
    }

    showComplianceModal.value = false;

    try {
        editingDriver.value = await fetchDriver(license.driver_id);
        clearDriverErrors();
        showDriverModal.value = true;
    } catch {
        loadError.value = 'Could not open driver for editing.';
    }
}

function closeDriverModal() {
    showDriverModal.value = false;
    editingDriver.value = null;
    clearDriverErrors();
}

async function handleDriverUpdate(payload) {
    if (!editingDriver.value?.id) {
        return;
    }

    driverSaving.value = true;
    clearDriverErrors();

    try {
        await updateDriver(editingDriver.value.id, payload);
        closeDriverModal();
        await loadDashboard();
    } catch (error) {
        applyDriverApiError(error);
    } finally {
        driverSaving.value = false;
    }
}

async function onComplianceItemSaved() {
    await loadDashboard();
}

async function loadCompliancePreview() {
    if (!canViewComplianceAlerts.value || !userHasCompanyContext.value) {
        compliancePreview.value = { documents: [], licenses: [] };

        return;
    }

    complianceLoading.value = true;

    try {
        const alerts = await fetchComplianceAlerts();

        if (alerts.requiresCompany) {
            compliancePreview.value = { documents: [], licenses: [] };

            return;
        }

        const documents = [...alerts.documents].sort((left, right) => {
            if (left.status === right.status) {
                return String(left.expiry_date).localeCompare(String(right.expiry_date));
            }

            return left.status === 'expired' ? -1 : 1;
        });

        const licenses = [...alerts.licenses].sort((left, right) => {
            if (left.status === right.status) {
                return String(left.license_expiry_date).localeCompare(String(right.license_expiry_date));
            }

            return left.status === 'expired' ? -1 : 1;
        });

        compliancePreview.value = {
            documents: documents.slice(0, 5),
            licenses: licenses.slice(0, 5),
        };
    } catch {
        compliancePreview.value = { documents: [], licenses: [] };
    } finally {
        complianceLoading.value = false;
    }
}

async function loadDashboard() {
    if (showRolePlaceholder.value) {
        loading.value = false;

        return;
    }

    loading.value = true;
    loadError.value = '';

    try {
        const params = {};

        if (showPeriodFilter.value) {
            params.from_date = periodFromDate.value;
            params.to_date = periodToDate.value;
        }

        const { data } = await api.get('/app/dashboard/stats', { params });
        requiresCompany.value =
            Boolean(data.requires_company) && !userHasCompanyContext.value;
        fleetStats.value = data.fleet ?? null;
        tripStats.value = data.trips ?? null;
        tyreStats.value = data.tyres ?? null;

        await loadCompliancePreview();
    } catch {
        loadError.value = 'Could not load dashboard data.';
    } finally {
        loading.value = false;
    }
}

onMounted(loadDashboard);

watch(
    () => user.value?.effective_tenant_id ?? user.value?.company_id,
    () => {
        loadDashboard();
    },
);
</script>

<style scoped>
.dashboard-page {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}

.dashboard-page__hero {
    position: relative;
    z-index: 10;
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 1.5rem 1.75rem;
    border: 1px solid rgba(226, 232, 240, 0.8);
    border-radius: 1.25rem;
    background: linear-gradient(135deg, rgba(47, 179, 111, 0.1) 0%, rgba(255, 255, 255, 0.9) 60%);
    box-shadow: 0 10px 30px rgba(15, 23, 42, 0.03);
    backdrop-filter: blur(8px);
}

.dashboard-page__hero-content {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
}

.dashboard-page__live-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.25rem 0.65rem;
    border-radius: 999px;
    background: rgba(47, 179, 111, 0.12);
    border: 1px solid rgba(47, 179, 111, 0.2);
    margin-bottom: 0.55rem;
}

.live-badge-dot {
    width: 0.45rem;
    height: 0.45rem;
    border-radius: 50%;
    background: #2fb36f;
    display: inline-block;
    box-shadow: 0 0 0 0 rgba(47, 179, 111, 0.7);
    animation: pulseDot 2s infinite;
}

@keyframes pulseDot {
    0% {
        transform: scale(0.95);
        box-shadow: 0 0 0 0 rgba(47, 179, 111, 0.7);
    }
    70% {
        transform: scale(1);
        box-shadow: 0 0 0 6px rgba(47, 179, 111, 0);
    }
    100% {
        transform: scale(0.95);
        box-shadow: 0 0 0 0 rgba(47, 179, 111, 0);
    }
}

.live-badge-text {
    font-size: 0.65rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #23965c;
}

.dashboard-page__title {
    margin: 0 0 0.35rem;
    font-size: 1.6rem;
    font-weight: 800;
    color: #0f172a;
    letter-spacing: -0.02em;
}

.dashboard-page__subtitle {
    color: var(--brand-text-muted, #64748b);
    font-size: 0.92rem;
}

.dashboard-page__hero-actions {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: flex-end;
    gap: 0.75rem;
}

.dashboard-page__quick-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.dashboard-page__quick-action {
    border-radius: 999px;
    font-weight: 600;
    padding: 0.55rem 1.1rem;
    transition: transform 0.2s cubic-bezier(0.34, 1.56, 0.64, 1), box-shadow 0.2s ease;
}

.dashboard-page__quick-action:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(47, 179, 111, 0.2);
}

.dashboard-page__panels {
    margin-top: 1rem;
}

.dashboard-page__today {
    display: inline-flex;
    align-items: center;
    padding: 0.55rem 0.9rem;
    border-radius: 999px;
    background: var(--brand-active-bg, #e8f5ee);
    color: var(--brand-primary-dark, #23965c);
    font-size: 0.85rem;
    font-weight: 600;
}

.dashboard-page__body {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}

.dashboard-page__analytics {
    min-width: 0;
}

.dashboard-page__analytics :deep(.analytics-charts) {
    margin: 0;
}

.dashboard-page__compliance-row {
    display: grid;
    grid-template-columns: minmax(17rem, 24rem) minmax(0, 1fr);
    gap: 1.25rem;
    align-items: stretch;
}

.dashboard-page__compliance-summary {
    display: flex;
    flex-direction: column;
    min-width: 0;
    min-height: 100%;
}

.dashboard-page__compliance-summary :deep(.dashboard-stat-card) {
    flex: 1;
}

.dashboard-page__compliance-alerts {
    display: flex;
    flex-direction: column;
    min-width: 0;
    min-height: 100%;
}

.dashboard-page__compliance-alerts :deep(.dashboard-panel) {
    flex: 1;
    height: 100%;
}

@media (max-width: 1199.98px) {
    .dashboard-page__compliance-row {
        grid-template-columns: minmax(15rem, 20rem) minmax(0, 1fr);
    }
}

@media (max-width: 991.98px) {
    .dashboard-page__compliance-row {
        grid-template-columns: 1fr;
    }
}
</style>
