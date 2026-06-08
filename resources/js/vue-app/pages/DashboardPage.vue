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
                    <div>
                        <h2 class="dashboard-page__title">
                            <i class="fas fa-grid-2 text-brand-primary me-2"></i>
                            Fleet Dashboard
                        </h2>
                        <p class="dashboard-page__subtitle mb-0">
                            <span v-if="user?.role === 'super_admin'">
                                Company:
                                <strong>{{ user?.tenant_name ?? user?.company?.name }}</strong>
                            </span>
                            <span v-else>Quick overview of trips, fleet, tyres, attendance and compliance.</span>
                        </p>
                    </div>
                    <div class="dashboard-page__today">
                        <i class="fas fa-calendar-day me-2"></i>
                        {{ todayLabel }}
                    </div>
                </header>

            <div v-if="showCompanyRequiredNotice" class="alert alert-warning">
                Enter a company from
                <RouterLink :to="{ name: 'companies.index' }" class="alert-link">Company List</RouterLink>
                to see fleet statistics.
            </div>

            <template v-else>
                <section v-if="canViewTrips" class="dashboard-page__section">
                    <div class="dashboard-page__section-head">
                        <div>
                            <h5 class="dashboard-page__section-title">
                                <i class="fas fa-route me-2"></i>Trips
                            </h5>
                            <p class="dashboard-page__section-copy">Trips starting in the selected period.</p>
                        </div>
                        <DashboardDateRangePicker
                            v-model:from-date="tripFromDate"
                            v-model:to-date="tripToDate"
                            @change="loadDashboard"
                        />
                    </div>

                    <div class="dashboard-page__cards dashboard-page__cards--single">
                        <DashboardGroupedStatCard
                            title="Trips"
                            :total="tripStats?.total_count ?? 0"
                            icon="fas fa-route"
                            tone="primary"
                            hint="Click total or a status to open Trip Management."
                            :to="tripsIndexRoute()"
                            :items="tripBreakdownItems"
                        />
                    </div>
                </section>

                <section class="dashboard-page__section">
                    <div class="dashboard-page__section-head dashboard-page__section-head--compact">
                        <div>
                            <h5 class="dashboard-page__section-title">
                                <i class="fas fa-truck me-2"></i>Fleet &amp; Attendance
                            </h5>
                            <p class="dashboard-page__section-copy">Live snapshot for today.</p>
                        </div>
                    </div>

                    <div class="dashboard-page__cards">
                        <DashboardGroupedStatCard
                            title="Trucks"
                            :total="fleetStats?.vehicles_count ?? 0"
                            icon="fas fa-truck"
                            tone="primary"
                            hint="Click total or a status to open Truck Management."
                            :to="{ name: 'trucks.index' }"
                            :items="truckBreakdownItems"
                        />
                        <DashboardGroupedStatCard
                            title="Drivers"
                            :total="driverTotalCount"
                            icon="fas fa-user-tie"
                            tone="info"
                            hint="Click to open Drivers Management."
                            :to="{ name: 'drivers.index' }"
                            :items="driverBreakdownItems"
                        />

                        <DashboardGroupedStatCard
                            v-if="canViewAttendance"
                            class="dashboard-page__card-wide"
                            title="Today's Attendance"
                            :total="fleetStats?.assigned_trucks_today_count ?? 0"
                            total-label="Assigned"
                            icon="fas fa-calendar-check"
                            tone="success"
                            hint="Click to open today's attendance."
                            :to="{ name: 'attendance.index' }"
                            :items="attendanceBreakdownItems"
                        />

                        <DashboardGroupedStatCard
                            v-if="showComplianceSection"
                            title="Compliance"
                            :total="complianceTotalCount"
                            total-label="Alerts"
                            icon="fas fa-shield-halved"
                            tone="warning"
                            hint="Click to view documents and update expiry dates."
                            :items="complianceBreakdownItems"
                            clickable
                            @click="openComplianceModal"
                        />
                    </div>
                </section>

                <section v-if="canViewTyres" class="dashboard-page__section">
                    <div class="dashboard-page__section-head dashboard-page__section-head--compact">
                        <div>
                            <h5 class="dashboard-page__section-title">
                                <i class="fas fa-circle-dot me-2"></i>Tyre Management
                            </h5>
                            <p class="dashboard-page__section-copy">Live snapshot of your tyre inventory.</p>
                        </div>
                    </div>

                    <div class="dashboard-page__cards dashboard-page__cards--single">
                        <DashboardGroupedStatCard
                            title="Tyres"
                            :total="tyreStats?.total_count ?? 0"
                            icon="fas fa-circle-dot"
                            tone="info"
                            hint="Click total or a status to open Tyre Management."
                            :to="{ name: 'tyres.index' }"
                            :items="tyreBreakdownItems"
                        />
                    </div>
                </section>
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
import { fetchDriver, updateDriver } from '../api/drivers';
import DashboardComplianceModal from '../components/dashboard/DashboardComplianceModal.vue';
import DashboardDateRangePicker from '../components/dashboard/DashboardDateRangePicker.vue';
import DashboardGroupedStatCard from '../components/dashboard/DashboardGroupedStatCard.vue';
import RoleDashboardPlaceholder from '../components/dashboard/RoleDashboardPlaceholder.vue';
import DriverFormModal from '../components/drivers/DriverFormModal.vue';
import VehicleDocumentsModal from '../components/trucks/VehicleDocumentsModal.vue';
import { useApiFormErrors } from '../composables/useApiFormErrors';
import { usePermissions } from '../composables/usePermissions';
import { defaultDashboardTripRange } from '../utils/dateRangePresets';

const { user, canInCompany } = usePermissions();

const loading = ref(true);
const loadError = ref('');
const fleetStats = ref(null);
const tripStats = ref(null);
const tyreStats = ref(null);
const requiresCompany = ref(false);

const defaultTripRange = defaultDashboardTripRange();
const tripFromDate = ref(defaultTripRange.from_date);
const tripToDate = ref(defaultTripRange.to_date);

const currentRole = computed(() => user.value?.role ?? 'user');
const showRolePlaceholder = computed(() => !canInCompany('dashboard.view'));
const canViewTrips = computed(() => canInCompany('trips.view'));
const canViewTyres = computed(() => canInCompany('tyres.view'));
const canViewAttendance = computed(() => canInCompany('attendances.view'));
const canManageDocuments = computed(() => canInCompany('vehicle_documents.manage'));
const canManageDrivers = computed(() => canInCompany('drivers.manage'));

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

const todayLabel = new Date().toLocaleDateString('en-GB', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
});

const driverTotalCount = computed(() => {
    const stats = fleetStats.value ?? {};

    return Number(stats.drivers_count ?? 0) + Number(stats.inactive_drivers_count ?? 0);
});

const truckBreakdownItems = computed(() => {
    const stats = fleetStats.value ?? {};

    return [
        {
            key: 'active',
            label: 'Active',
            value: stats.active_vehicles_count,
            to: { name: 'trucks.index', query: { status: 'active' } },
        },
        {
            key: 'inactive',
            label: 'Inactive',
            value: stats.inactive_vehicles_count,
            to: { name: 'trucks.index', query: { status: 'inactive' } },
        },
    ];
});

const driverBreakdownItems = computed(() => {
    const stats = fleetStats.value ?? {};

    return [
        {
            key: 'active',
            label: 'Active',
            value: stats.drivers_count,
        },
        {
            key: 'inactive',
            label: 'Inactive',
            value: stats.inactive_drivers_count,
        },
    ];
});

const attendanceBreakdownItems = computed(() => {
    const stats = fleetStats.value ?? {};

    return [
        { key: 'present', label: 'Present', value: stats.present_today_count },
        { key: 'absent', label: 'Absent', value: stats.absent_today_count },
        { key: 'substitute', label: 'Substitute', value: stats.absent_substitute_today_count },
        { key: 'no_work', label: 'No Work', value: stats.no_work_day_today_count },
        { key: 'exceptions', label: 'Exceptions', value: stats.exceptions_today_count },
    ];
});

function tripsIndexRoute(extraQuery = {}) {
    return {
        name: 'trips.index',
        query: {
            from_date: tripFromDate.value,
            to_date: tripToDate.value,
            ...extraQuery,
        },
    };
}

const tripBreakdownItems = computed(() => {
    const stats = tripStats.value ?? {};

    return [
        {
            key: 'created',
            label: 'Created',
            value: stats.created_count,
            to: tripsIndexRoute({ status: 'CREATED' }),
        },
        {
            key: 'in_progress',
            label: 'Running',
            value: stats.in_progress_count,
            to: tripsIndexRoute({ status: 'IN_PROGRESS' }),
        },
        {
            key: 'completed',
            label: 'Done',
            value: stats.completed_count,
            to: tripsIndexRoute({ status: 'COMPLETED' }),
        },
        {
            key: 'cancelled',
            label: 'Cancelled',
            value: stats.cancelled_count,
            to: tripsIndexRoute({ status: 'CANCELLED' }),
        },
    ];
});

const tyreBreakdownItems = computed(() => {
    const stats = tyreStats.value ?? {};

    return [
        {
            key: 'in_stock',
            label: 'In Stock',
            value: stats.in_stock_count,
            to: { name: 'tyres.index', query: { status: 'IN_STOCK' } },
        },
        {
            key: 'installed',
            label: 'Installed',
            value: stats.installed_count,
            to: { name: 'tyres.index', query: { status: 'INSTALLED' } },
        },
        {
            key: 'damaged',
            label: 'Damaged',
            value: stats.damaged_count,
            to: { name: 'tyres.index', query: { status: 'DAMAGED' } },
        },
        {
            key: 'scrapped',
            label: 'Scrapped',
            value: stats.scrapped_count,
            to: { name: 'tyres.index', query: { status: 'SCRAPPED' } },
        },
    ];
});

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

async function loadDashboard() {
    if (showRolePlaceholder.value) {
        loading.value = false;

        return;
    }

    loading.value = true;
    loadError.value = '';

    try {
        const params = {};

        if (canViewTrips.value) {
            params.from_date = tripFromDate.value;
            params.to_date = tripToDate.value;
        }

        const { data } = await api.get('/app/dashboard/stats', { params });
        requiresCompany.value =
            Boolean(data.requires_company) && !userHasCompanyContext.value;
        fleetStats.value = data.fleet ?? null;
        tripStats.value = data.trips ?? null;
        tyreStats.value = data.tyres ?? null;
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
    display: flex;
    flex-wrap: wrap;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1rem;
    padding: 1.15rem 1.25rem;
    border: 1px solid var(--brand-border, #e2e8f0);
    border-radius: 1rem;
    background: linear-gradient(135deg, rgba(47, 179, 111, 0.08) 0%, #ffffff 55%);
}

.dashboard-page__title {
    margin: 0 0 0.35rem;
    font-size: 1.45rem;
    font-weight: 700;
    color: #0f172a;
}

.dashboard-page__subtitle {
    color: var(--brand-text-muted, #64748b);
    font-size: 0.92rem;
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

.dashboard-page__section {
    padding: 1rem 1.1rem 1.15rem;
    border: 1px solid var(--brand-border, #e2e8f0);
    border-radius: 1rem;
    background: #fff;
}

.dashboard-page__section-head {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-end;
    justify-content: space-between;
    gap: 1rem;
    margin-bottom: 1rem;
}

.dashboard-page__section-head--compact {
    margin-bottom: 0.85rem;
}

.dashboard-page__section-title {
    margin: 0 0 0.2rem;
    font-size: 1.05rem;
    font-weight: 700;
    color: #0f172a;
}

.dashboard-page__section-copy {
    margin: 0;
    font-size: 0.82rem;
    color: var(--brand-text-muted, #64748b);
}

.dashboard-page__cards {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 1rem;
}

.dashboard-page__cards--single {
    grid-template-columns: minmax(0, 28rem);
}

.dashboard-page__card-wide {
    grid-column: 1 / -1;
}

@media (max-width: 991.98px) {
    .dashboard-page__cards {
        grid-template-columns: 1fr;
    }

    .dashboard-page__cards--single {
        grid-template-columns: 1fr;
    }
}
</style>
