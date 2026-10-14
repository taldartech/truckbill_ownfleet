<template>
    <div v-if="loading" class="py-5 text-center text-muted">Loading dashboard…</div>

    <div v-else-if="loadError" class="alert alert-danger">{{ loadError }}</div>

    <template v-else>
        <GroupDashboard v-if="showGroupDashboard" :user="user" />

        <RoleDashboardPlaceholder
            v-else-if="showRolePlaceholder"
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
                            <span v-else>Overview of trips, fleet, tyres, and compliance.</span>
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
                            :preset-keys="dashboardDatePresetKeys"
                            :max-months="MAX_LIST_DATE_RANGE_MONTHS"
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
                    <!-- Top KPI Cards: Total Fleet, On Trip, Available, Idle, Maintenance & Utilization, Revenue, Cost, Profit, Outstanding -->
                    <DashboardTopKpis
                        :kpi-stats="kpiStats"
                        :fleet-status-stats="fleetStatusStats"
                        :trip-stats="tripStats"
                        :fleet-stats="fleetStats"
                        :can-view-trips="canViewTrips"
                        :can-view-fleet="canViewFleet"
                        @select-tab="onSelectAnalyticsTab"
                    />

                    <div v-if="canViewTrips || canViewTyres || canViewFleet" class="dashboard-page__analytics">
                        <DashboardAnalyticsChart
                            ref="analyticsChartRef"
                            v-if="(canViewTrips && tripStats) || (canViewTyres && tyreStats) || fleetStatusStats"
                            :trip-stats="tripStats"
                            :tyre-stats="tyreStats"
                            :fleet-status-stats="fleetStatusStats"
                            :period-from-date="periodFromDate"
                            :period-to-date="periodToDate"
                            :can-view-trips="canViewTrips"
                            :can-view-tyres="canViewTyres"
                            :can-view-fleet="canViewFleet"
                        />
                    </div>

                    <div v-if="showComplianceSection" class="dashboard-page__compliance">
                        <DashboardComplianceAlertsPanel
                            :documents="compliancePreview.documents"
                            :licenses="compliancePreview.licenses"
                            :due-services="compliancePreview.dueServices"
                            :eway-bills="compliancePreview.ewayBills"
                            :fuel-alerts="compliancePreview.fuelAlerts"
                            :summary="complianceSummary"
                            :loading="complianceLoading"
                            :can-manage-licenses="canManageDrivers"
                            :can-edit-documents="canEditDocuments"
                            @view-all="openComplianceModal"
                            @saved="onComplianceItemSaved"
                        />
                    </div>
                </div>
            </template>
            </div>
        </template>

        <DashboardComplianceModal
            ref="complianceModalRef"
            :show="showComplianceModal"
            :can-manage-licenses="canManageDrivers"
            :can-edit-documents="canEditDocuments"
            @close="closeComplianceModal"
            @saved="onComplianceItemSaved"
        />

        <VehicleDocumentsModal
            modal-id="dashboardVehicleDocumentsModal"
            context="dashboard"
            :show="showDocumentsModal"
            :vehicle="documentsVehicle"
            :initial-document-id="initialDocumentId"
            :initial-action="initialDocumentAction"
            :can-create="canCreateDocuments"
            :can-edit="canEditDocuments"
            :can-delete="canDeleteDocuments"
            @close="closeDocumentsModal"
            @saved="onComplianceItemSaved"
        />
    </template>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { RouterLink } from 'vue-router';
import api from '../api';
import { fetchComplianceAlerts } from '../api/dashboard';
import DashboardAnalyticsChart from '../components/dashboard/DashboardAnalyticsChart.vue';
import DashboardComplianceAlertsPanel from '../components/dashboard/DashboardComplianceAlertsPanel.vue';
import DashboardComplianceModal from '../components/dashboard/DashboardComplianceModal.vue';
import DashboardDateRangePicker from '../components/dashboard/DashboardDateRangePicker.vue';
import DashboardTopKpis from '../components/dashboard/DashboardTopKpis.vue';
import RoleDashboardPlaceholder from '../components/dashboard/RoleDashboardPlaceholder.vue';
import GroupDashboard from '../components/dashboard/GroupDashboard.vue';
import VehicleDocumentsModal from '../components/trucks/VehicleDocumentsModal.vue';
import { useOperationalDataInvalidation } from '../composables/useOperationalDataInvalidation';
import { usePermissions } from '../composables/usePermissions';
import { defaultDashboardTripRange, DASHBOARD_DATE_PRESET_KEYS, MAX_LIST_DATE_RANGE_MONTHS } from '../utils/dateRangePresets';
import { sortDocumentsByUrgency } from '../utils/documentStatus';
import { formatDate } from '../utils/datetime';

const complianceModalRef = ref(null);
const analyticsChartRef = ref(null);
const kpiStats = ref(null);

const { user, canInCompany, canCreateInCompany, canEditInCompany, canDeleteRecords } = usePermissions();

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
const fleetStatusStats = ref(null);
const tripStats = ref(null);
const tyreStats = ref(null);
const requiresCompany = ref(false);
const complianceLoading = ref(false);
const compliancePreview = ref({ documents: [], licenses: [], dueServices: [], ewayBills: [], fuelAlerts: [] });
const complianceSummary = ref({
    total: 0,
    expired: 0,
    expiring: 0,
    licenses: 0,
    dueServices: 0,
    fuelAlerts: 0,
});

const defaultPeriodRange = defaultDashboardTripRange();
const periodFromDate = ref(defaultPeriodRange.from_date);
const periodToDate = ref(defaultPeriodRange.to_date);
const dashboardDatePresetKeys = DASHBOARD_DATE_PRESET_KEYS;

const currentRole = computed(() => user.value?.role ?? 'user');
const showRolePlaceholder = computed(() => !canInCompany('dashboard.view'));
const showGroupDashboard = computed(
    () => user.value?.role === 'super_admin' && user.value?.requires_company_selection === true,
);
const canViewTrips = computed(() => canInCompany('trips.view'));
const canViewTyres = computed(() => canInCompany('tyres.view'));
const canViewFleet = computed(
    () => canInCompany('vehicles.view') || canInCompany('trips.view') || canInCompany('dashboard.view'),
);
const showPeriodFilter = computed(
    () => canViewTrips.value || canViewTyres.value || canViewFleet.value,
);
const canCreateDocuments = computed(() => canCreateInCompany('vehicle_documents'));
const canEditDocuments = computed(() => canEditInCompany('vehicle_documents'));
const canDeleteDocuments = computed(
    () => canDeleteRecords.value && canInCompany('vehicle_documents.manage'),
);
const canManageDrivers = computed(() => canInCompany('drivers.create'));
const canManageTrips = computed(() => canInCompany('trips.create'));
const canViewComplianceAlerts = computed(
    () =>
        canInCompany('vehicle_documents.view')
        || canInCompany('drivers.view')
        || canInCompany('maintenance.view')
        || canInCompany('alerts.view')
        || canInCompany('trips.view'),
);

const quickActions = computed(() => {
    const actions = [];

    if (canManageTrips.value) {
        actions.push({
            label: 'New Trip',
            icon: 'fas fa-plus',
            to: { name: 'trips.index', query: { create: '1' } },
        });
    }

    return actions;
});

const showComplianceModal = ref(false);
const showDocumentsModal = ref(false);
const documentsVehicle = ref(null);
const initialDocumentId = ref(null);
const initialDocumentAction = ref(null);

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

function emptyComplianceSummary() {
    return {
        total: 0,
        expired: 0,
        expiring: 0,
        licenses: 0,
        dueServices: 0,
        fuelAlerts: 0,
    };
}

function emptyCompliancePreview() {
    return { documents: [], licenses: [], dueServices: [], ewayBills: [], fuelAlerts: [] };
}

function buildComplianceSummary(documents, licenses, dueServices, ewayBills, fuelAlerts = []) {
    const documentAndLicenseItems = [...documents, ...licenses];
    const expiredEwayBills = ewayBills.filter(
        (item) => item.status === 'expired',
    ).length;
    const criticalFuelAlerts = fuelAlerts.filter(
        (item) => item.severity === 'critical',
    ).length;
    const expired = documentAndLicenseItems.filter(
        (item) => item.status === 'expired' || item.status_tier === 'expired',
    ).length;

    return {
        total: documentAndLicenseItems.length + dueServices.length + ewayBills.length + fuelAlerts.length,
        expired: expired + expiredEwayBills + criticalFuelAlerts,
        expiring: documentAndLicenseItems.length - expired + ewayBills.filter(item => item.status === 'expiring').length,
        licenses: licenses.length,
        dueServices: dueServices.length,
        ewayBills: ewayBills.length,
        fuelAlerts: fuelAlerts.length,
    };
}

function applyComplianceAlerts(alerts) {
    const documents = sortDocumentsByUrgency(alerts.documents ?? []);
    const licenses = sortDocumentsByUrgency(alerts.licenses ?? []);
    const dueServices = alerts.dueServices ?? [];
    const ewayBills = alerts.ewayBills ?? [];
    const fuelAlerts = alerts.fuelAlerts ?? alerts.fuel_alerts ?? [];

    complianceSummary.value = buildComplianceSummary(documents, licenses, dueServices, ewayBills, fuelAlerts);
    compliancePreview.value = {
        documents: documents.slice(0, 5),
        licenses: licenses.slice(0, 5),
        dueServices: dueServices.slice(0, 5),
        ewayBills: ewayBills.slice(0, 5),
        fuelAlerts: fuelAlerts.slice(0, 5),
    };
}

const showComplianceSection = computed(
    () => canViewComplianceAlerts.value && userHasCompanyContext.value,
);

function openComplianceModal() {
    showComplianceModal.value = true;
}

function closeComplianceModal() {
    showComplianceModal.value = false;
}

function closeDocumentsModal() {
    showDocumentsModal.value = false;
    documentsVehicle.value = null;
    initialDocumentId.value = null;
    initialDocumentAction.value = null;
}

function onSelectAnalyticsTab(tabId) {
    if (analyticsChartRef.value?.setActiveTab) {
        analyticsChartRef.value.setActiveTab(tabId);
        analyticsChartRef.value.$el?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
}

async function onComplianceItemSaved() {
    await refreshComplianceData();
}

async function refreshComplianceData() {
    if (!canViewComplianceAlerts.value || !userHasCompanyContext.value) {
        return;
    }

    complianceLoading.value = true;

    try {
        const params = {};

        if (showPeriodFilter.value) {
            params.from_date = periodFromDate.value;
            params.to_date = periodToDate.value;
        }

        const [statsResponse, alerts] = await Promise.all([
            api.get('/app/dashboard/stats', { params }),
            fetchComplianceAlerts(),
        ]);

        if (!alerts.requiresCompany) {
            fleetStats.value = statsResponse.data.fleet ?? fleetStats.value;
            fleetStatusStats.value =
                statsResponse.data.fleet_status ??
                statsResponse.data.fleet?.fleet_status ??
                fleetStatusStats.value;
            kpiStats.value = statsResponse.data.kpis ?? kpiStats.value;
            applyComplianceAlerts(alerts);
        }

        if (showComplianceModal.value && complianceModalRef.value?.refreshAlerts) {
            await complianceModalRef.value.refreshAlerts();
        }
    } catch {
        // Keep existing data on refresh failure.
    } finally {
        complianceLoading.value = false;
    }
}

async function loadDashboard() {
    if (showRolePlaceholder.value || showGroupDashboard.value) {
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

        const shouldLoadCompliance =
            canViewComplianceAlerts.value && userHasCompanyContext.value;

        if (shouldLoadCompliance) {
            complianceLoading.value = true;
        }

        const [statsResult, alertsResult] = await Promise.all([
            api.get('/app/dashboard/stats', { params }),
            shouldLoadCompliance
                ? fetchComplianceAlerts().catch(() => null)
                : Promise.resolve(null),
        ]);

        const { data } = statsResult;
        requiresCompany.value =
            Boolean(data.requires_company) && !userHasCompanyContext.value;
        fleetStats.value = data.fleet ?? null;
        fleetStatusStats.value =
            data.fleet_status ?? data.fleet?.fleet_status ?? null;
        tripStats.value = data.trips ?? null;
        tyreStats.value = data.tyres ?? null;
        kpiStats.value = data.kpis ?? null;

        if (!shouldLoadCompliance) {
            compliancePreview.value = emptyCompliancePreview();
            complianceSummary.value = emptyComplianceSummary();
        } else if (alertsResult == null || alertsResult.requiresCompany) {
            compliancePreview.value = emptyCompliancePreview();
            complianceSummary.value = emptyComplianceSummary();
        } else {
            applyComplianceAlerts(alertsResult);
        }
    } catch (error) {
        const validationMessage =
            error?.response?.data?.errors?.from_date?.[0] ??
            error?.response?.data?.errors?.to_date?.[0];

        loadError.value = validationMessage ?? 'Could not load dashboard data.';
    } finally {
        complianceLoading.value = false;
        loading.value = false;
    }
}

onMounted(loadDashboard);

useOperationalDataInvalidation('dashboard', () => {
    if (!showRolePlaceholder.value) {
        loadDashboard();
    }
});

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
    text-transform: none;
    letter-spacing: normal;
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

.dashboard-page__compliance {
    min-width: 0;
}

.dashboard-page__compliance :deep(.dashboard-panel) {
    height: auto;
}
</style>
