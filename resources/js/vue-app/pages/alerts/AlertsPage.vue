<template>
    <div class="alerts-page">
        <header class="alerts-page__hero">
            <div>
                <div class="alerts-page__eyebrow">
                    <i class="fas fa-bell me-2"></i>
                    Fleet Monitoring
                </div>

                <h2 class="alerts-page__title">
                    Active Alerts
                </h2>

                <p class="alerts-page__subtitle mb-0">
                    Monitor compliance, vehicle, driver, maintenance and e-way bill alerts.
                </p>
            </div>

            <DashboardDateRangePicker v-model:from-date="fromDate" v-model:to-date="toDate" label=""
                :preset-keys="dashboardDatePresetKeys" :max-months="MAX_LIST_DATE_RANGE_MONTHS"
                @change="applyDateFilter" />
        </header>

        <div class="alerts-page__summary">
            <div class="alert-stat alert-stat--total">
                <span class="alert-stat__icon">
                    <i class="fas fa-bell"></i>
                </span>

                <div>
                    <span class="alert-stat__label">Total</span>
                    <strong>{{ filteredAlerts.length }}</strong>
                </div>
            </div>

            <div class="alert-stat alert-stat--critical">
                <span class="alert-stat__icon">
                    <i class="fas fa-circle-exclamation"></i>
                </span>

                <div>
                    <span class="alert-stat__label">Critical</span>
                    <strong>{{ criticalCount }}</strong>
                </div>
            </div>

            <div class="alert-stat alert-stat--warning">
                <span class="alert-stat__icon">
                    <i class="fas fa-triangle-exclamation"></i>
                </span>

                <div>
                    <span class="alert-stat__label">Warning</span>
                    <strong>{{ warningCount }}</strong>
                </div>
            </div>

            <div class="alert-stat alert-stat--info">
                <span class="alert-stat__icon">
                    <i class="fas fa-circle-info"></i>
                </span>

                <div>
                    <span class="alert-stat__label">Info</span>
                    <strong>{{ infoCount }}</strong>
                </div>
            </div>
        </div>

        <section class="alerts-page__filters">
            <div class="alerts-filter-group">
                <label>Category</label>

                <select v-model="categoryFilter" class="form-select">
                    <option value="all">All Categories</option>
                    <option value="ewaybill">E-Way Bills</option>
                    <option value="vehicle_documents">
                        Vehicle Documents
                    </option>
                    <option value="drivers">Drivers</option>
                    <option value="maintenance">Maintenance</option>
                    <option value="fuel">Fuel & Mileage</option>
                </select>
            </div>

            <div class="alerts-filter-group">
                <label>Severity</label>

                <select v-model="severityFilter" class="form-select">
                    <option value="all">All Severities</option>
                    <option value="critical">Critical</option>
                    <option value="warning">Warning</option>
                    <option value="info">Info</option>
                </select>
            </div>

            <div class="alerts-filter-group alerts-filter-group--search">
                <label>Search</label>

                <div class="input-group">
                    <span class="input-group-text">
                        <i class="fas fa-search"></i>
                    </span>

                    <input v-model="search" type="search" class="form-control"
                        placeholder="Search vehicle, driver, trip..." />
                </div>
            </div>

            <div class="alerts-filter-actions">
                <button type="button" class="btn btn-light" @click="resetFilters">
                    <i class="fas fa-rotate-left me-1"></i>
                    Reset
                </button>
            </div>
        </section>

        <div v-if="loading" class="alerts-page__state text-center text-muted">
            Loading alerts…
        </div>

        <div v-else-if="loadError" class="alert alert-danger">
            {{ loadError }}
        </div>

        <div v-else-if="filteredAlerts.length === 0" class="alerts-page__empty">
            <i class="fas fa-shield-halved"></i>

            <h5>No active alerts</h5>

            <p class="mb-0">
                There are no alerts matching your current filters.
            </p>
        </div>

        <div v-else class="alerts-table-card">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Alert</th>
                            <th>Category</th>
                            <th>Severity</th>
                            <th>Reference</th>
                            <th>Due / Expiry Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr v-for="alert in filteredAlerts" :key="alert.id" class="alerts-table-row">
                            <td>
                                <div class="alert-title-cell">
                                    <span class="alert-category-icon" :class="`alert-category-icon--${alert.category}`">
                                        <i :class="categoryIcon(alert.category)"></i>
                                    </span>

                                    <div>
                                        <strong>
                                            {{ alert.title }}
                                        </strong>

                                        <div v-if="alert.ewaybill_no" class="text-muted small">
                                            E-Way Bill: {{ alert.ewaybill_no }}
                                        </div>

                                        <div v-if="alert.license_number" class="text-muted small">
                                            License: {{ alert.license_number }}
                                        </div>

                                        <div v-if="alert.description" class="text-muted small">
                                            {{ alert.description }}
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <td>
                                {{ alert.category_label }}
                            </td>

                            <td>
                                <span class="severity-badge" :class="`severity-badge--${alert.severity}`">
                                    <i :class="severityIcon(alert.severity)"></i>

                                    {{ capitalize(alert.severity) }}
                                </span>
                            </td>

                            <td>
                                <strong>
                                    {{ alert.reference || '—' }}
                                </strong>

                                <div v-if="alert.reference_type" class="text-muted small">
                                    {{ referenceLabel(alert.reference_type) }}
                                </div>

                                <div v-if="alert.trip_id" class="mt-1">
                                    <RouterLink
                                        :to="{ name: 'trips.index', query: { trip_id: alert.trip_id } }"
                                        class="btn btn-sm btn-link p-0 text-primary"
                                        style="font-size: 0.75rem;"
                                    >
                                        <i class="fas fa-arrow-up-right-from-square me-1"></i>Trip Details
                                    </RouterLink>
                                </div>
                            </td>

                            <td>
                                <strong>
                                    {{ formatDate(alert.due_date) }}
                                </strong>

                                <div v-if="dateDescription(alert)" class="text-muted small">
                                    {{ dateDescription(alert) }}
                                </div>
                            </td>

                            <td>
                                <span class="status-badge" :class="`status-badge--${alert.status}`">
                                    {{ capitalize(alert.status || 'active') }}
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { RouterLink } from 'vue-router';

import { fetchComplianceAlerts } from '../../api/dashboard';

import DashboardDateRangePicker
    from '../../components/dashboard/DashboardDateRangePicker.vue';

import {
    defaultDashboardTripRange,
    DASHBOARD_DATE_PRESET_KEYS,
    MAX_LIST_DATE_RANGE_MONTHS,
} from '../../utils/dateRangePresets.js';

import { formatDate } from '../../utils/datetime.js';

const loading = ref(true);
const loadError = ref('');

const alerts = ref([]);

const categoryFilter = ref('all');
const severityFilter = ref('all');
const search = ref('');

const defaultRange = defaultDashboardTripRange();

const fromDate = ref(defaultRange.from_date);
const toDate = ref(defaultRange.to_date);

const dashboardDatePresetKeys = DASHBOARD_DATE_PRESET_KEYS;

const filteredAlerts = computed(() => {
    const searchTerm = search.value.trim().toLowerCase();

    return alerts.value.filter((alert) => {
        const categoryMatches =
            categoryFilter.value === 'all'
            || alert.category === categoryFilter.value;

        const severityMatches =
            severityFilter.value === 'all'
            || alert.severity === severityFilter.value;

        const haystack = [
            alert.title,
            alert.reference,
            alert.category_label,
            alert.ewaybill_no,
            alert.license_number,
            alert.description,
        ]
            .filter(Boolean)
            .join(' ')
            .toLowerCase();

        const searchMatches =
            !searchTerm
            || haystack.includes(searchTerm);

        return categoryMatches
            && severityMatches
            && searchMatches;
    });
});

const criticalCount = computed(
    () => alerts.value.filter((alert) => alert.severity === 'critical').length,
);

const warningCount = computed(
    () => alerts.value.filter((alert) => alert.severity === 'warning').length,
);

const infoCount = computed(
    () => alerts.value.filter((alert) => alert.severity === 'info').length,
);

async function loadAlerts() {
    loading.value = true;
    loadError.value = '';

    try {
        const data = await fetchComplianceAlerts({
            from_date: fromDate.value,
            to_date: toDate.value,
        });

        alerts.value = data.alerts ?? [];
    } catch {
        alerts.value = [];
        loadError.value = 'Could not load alerts.';
    } finally {
        loading.value = false;
    }
}

function applyDateFilter() {
    loadAlerts();
}

function resetFilters() {
    categoryFilter.value = 'all';
    severityFilter.value = 'all';
    search.value = '';

    const range = defaultDashboardTripRange();

    fromDate.value = range.from_date;
    toDate.value = range.to_date;
}

function categoryIcon(category) {
    const icons = {
        ewaybill: 'fas fa-file-invoice',
        vehicle_documents: 'fas fa-id-card',
        drivers: 'fas fa-user-tie',
        maintenance: 'fas fa-wrench',
        fuel: 'fas fa-gas-pump',
    };

    return icons[category] ?? 'fas fa-bell';
}

function severityIcon(severity) {
    const icons = {
        critical: 'fas fa-circle-exclamation',
        warning: 'fas fa-triangle-exclamation',
        info: 'fas fa-circle-info',
    };

    return icons[severity] ?? 'fas fa-bell';
}

function referenceLabel(type) {
    const labels = {
        vehicle: 'Vehicle',
        driver: 'Driver',
        trip: 'Trip',
    };

    return labels[type] ?? '';
}

function dateDescription(alert) {
    if (alert.category === 'fuel') {
        return 'Incident date';
    }

    if (alert.days_remaining === null || alert.days_remaining === undefined) {
        return alert.overdue_reason_label ?? '';
    }

    if (alert.days_remaining < 0) {
        return `${Math.abs(alert.days_remaining)} days overdue`;
    }

    if (alert.days_remaining === 0) {
        return 'Due today';
    }

    return `${alert.days_remaining} days remaining`;
}

function capitalize(value) {
    if (!value) {
        return '';
    }

    return value.charAt(0).toUpperCase()
        + value.slice(1).replace(/_/g, ' ');
}

onMounted(loadAlerts);
</script>

<style scoped>
.alerts-page {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}

.alerts-page__hero {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 1rem;

    padding: 1.5rem 1.75rem;

    border: 1px solid rgba(226, 232, 240, 0.8);
    border-radius: 1.25rem;

    background:
        linear-gradient(135deg,
            rgba(47, 179, 111, 0.1) 0%,
            rgba(255, 255, 255, 0.9) 60%);
}

.alerts-page__eyebrow {
    color: var(--brand-primary-dark, #23965c);
    font-size: 0.8rem;
    font-weight: 700;
}

.alerts-page__title {
    margin: 0.35rem 0;
    font-size: 1.6rem;
    font-weight: 800;
    color: #0f172a;
}

.alerts-page__subtitle {
    color: #64748b;
}

.alerts-page__summary {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 1rem;
}

.alert-stat {
    display: flex;
    align-items: center;
    gap: 0.9rem;

    padding: 1.1rem;

    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 1rem;
}

.alert-stat__icon {
    display: grid;
    place-items: center;

    width: 2.75rem;
    height: 2.75rem;

    border-radius: 0.8rem;
}

.alert-stat__label {
    display: block;
    color: #64748b;
    font-size: 0.78rem;
}

.alert-stat strong {
    font-size: 1.35rem;
}

.alert-stat--total .alert-stat__icon {
    background: #eff6ff;
}

.alert-stat--critical .alert-stat__icon {
    background: #fef2f2;
}

.alert-stat--warning .alert-stat__icon {
    background: #fffbeb;
}

.alert-stat--info .alert-stat__icon {
    background: #eff6ff;
}

.alerts-page__filters {
    display: flex;
    align-items: end;
    flex-wrap: wrap;
    gap: 1rem;

    padding: 1.25rem;

    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 1rem;
}

.alerts-filter-group {
    min-width: 180px;
}

.alerts-filter-group--search {
    flex: 1;
    min-width: 250px;
}

.alerts-filter-group label {
    display: block;
    margin-bottom: 0.4rem;

    font-size: 0.78rem;
    font-weight: 700;
    color: #475569;
}

.alerts-filter-actions {
    display: flex;
    align-items: center;
}

.alerts-table-card {
    overflow: hidden;

    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 1rem;
}

.alerts-table-card th {
    padding: 1rem;
    background: #f8fafc;

    color: #64748b;
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
}

.alerts-table-card td {
    padding: 1rem;
}

.alerts-table-row:hover {
    background: rgba(47, 179, 111, 0.035);
}

.alert-title-cell {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.alert-category-icon {
    display: grid;
    place-items: center;

    width: 2.4rem;
    height: 2.4rem;

    border-radius: 0.7rem;
    background: #f1f5f9;
}

.alert-category-icon--ewaybill {
    background: #eff6ff;
}

.alert-category-icon--vehicle_documents {
    background: #f0fdf4;
}

.alert-category-icon--drivers {
    background: #faf5ff;
}

.alert-category-icon--maintenance {
    background: #fff7ed;
}

.alert-category-icon--fuel {
    background: #fef3c7;
    color: #b45309;
}

.severity-badge,
.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;

    padding: 0.3rem 0.65rem;

    border-radius: 999px;

    font-size: 0.75rem;
    font-weight: 700;
}

.severity-badge--critical {
    background: #fef2f2;
    color: #dc2626;
}

.severity-badge--warning {
    background: #fffbeb;
    color: #d97706;
}

.severity-badge--info {
    background: #eff6ff;
    color: #2563eb;
}

.status-badge--expired {
    background: #fef2f2;
    color: #dc2626;
}

.status-badge--expiring,
.status-badge--due {
    background: #fffbeb;
    color: #d97706;
}

.alerts-page__state,
.alerts-page__empty {
    padding: 4rem 1rem;
}

.alerts-page__empty {
    text-align: center;

    background: #fff;
    border: 1px dashed #cbd5e1;
    border-radius: 1rem;

    color: #64748b;
}

.alerts-page__empty i {
    display: block;

    margin-bottom: 1rem;

    font-size: 2.5rem;

    color: var(--brand-primary, #2fb36f);
}

@media (max-width: 991px) {
    .alerts-page__summary {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 575px) {
    .alerts-page__summary {
        grid-template-columns: 1fr;
    }

    .alerts-page__hero {
        padding: 1.25rem;
    }

    .alerts-page__filters {
        align-items: stretch;
    }

    .alerts-filter-group,
    .alerts-filter-group--search {
        width: 100%;
        min-width: 100%;
    }
}
</style>