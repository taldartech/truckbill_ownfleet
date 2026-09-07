<template>
    <div class="container mt-4 app-index-page app-index-page--standard">
        <AppIndexListCard :title="pageTitle" :loading="loading" loading-text="Loading alerts…">
            <AppIndexCollapsibleFilters panel-id="alerts-filters-panel" :loading="loading" @search="applyFilters"
                @reset="resetFilters">
                <div class="filter-search-col">
                    <label class="form-label" for="alerts_search"> Search </label>

                    <AppIndexSearchInput v-model="search" input-id="alerts_search" :placeholder="searchPlaceholder"
                        max-width="100%" size="md" :disabled="loading" @keydown.enter="applyFilters" />
                </div>

                <div class="filter-select-col">
                    <label class="form-label" for="alerts_severity"> Severity </label>

                    <select id="alerts_severity" v-model="severityFilter" class="form-select" :disabled="loading">
                        <option value="all">All Severities</option>

                        <option value="critical">Critical</option>

                        <option value="warning">Warning</option>
                    </select>
                </div>
            </AppIndexCollapsibleFilters>

            <div v-if="loadError" class="alert alert-danger m-3">
                {{ loadError }}
            </div>

            <div v-else class="table-responsive">
                <table class="table table-striped table-hover align-middle mb-0 app-index-table">
                    <thead class="table-light">
                        <tr>
                            <th>
                                {{ primaryColumnLabel }}
                            </th>
                            <th v-if="alertType === 'eway-bills'">Trip Number</th>
                            <th>Reference</th>
                            <th>Due / Expiry Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr v-if="filteredAlerts.length === 0" class="app-index-empty-row">
                            <td :colspan="alertType === 'eway-bills' ? 5 : 4" class="text-center text-muted py-4">
                                No alerts found for selected filters.
                            </td>
                        </tr>

                        <tr v-for="alert in filteredAlerts" :key="alert.id" class="alerts-table-row" :class="{
                            'alerts-table-row--expired': alert.status === 'expired' || alert.status === 'due',

                            'alerts-table-row--upcoming': alert.status === 'upcoming',
                        }">
                            <td>
                                <div class="alert-title-cell">
                                    <span class="alert-category-icon" :class="`alert-category-icon--${alert.category}`">
                                        <i :class="categoryIcon(alert.category)"></i>
                                    </span>

                                    <strong>
                                        {{ primaryTitle(alert) }}
                                    </strong>
                                </div>
                            </td>

                            <td v-if="alertType === 'eway-bills'">
                                <strong>
                                    {{ alert.trip_number || '—' }}
                                </strong>
                            </td>

                            <td>
                                <strong>
                                    {{ alert.reference || '—' }}
                                </strong>
                            </td>

                            <td>
                                <strong>
                                    {{ formatDate(alert.due_date) }}
                                </strong>
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
        </AppIndexListCard>
    </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';

import { useRoute } from 'vue-router';

import AppIndexCollapsibleFilters from '../../components/ui/AppIndexCollapsibleFilters.vue';

import AppIndexListCard from '../../components/ui/AppIndexListCard.vue';

import AppIndexSearchInput from '../../components/ui/AppIndexSearchInput.vue';

import { fetchComplianceAlerts } from '../../api/dashboard';

import { formatDate } from '../../utils/datetime.js';

const route = useRoute();

const loading = ref(true);

const loadError = ref('');

const alerts = ref([]);

const severityFilter = ref('all');

const search = ref('');

const alertType = computed(() => {
    const type = route.query.type;

    const allowedTypes = ['documents', 'licenses', 'due-services', 'eway-bills'];

    return allowedTypes.includes(type) ? type : 'documents';
});

const typeConfig = computed(() => {
    const configs = {
        documents: {
            title: 'Vehicle Document Alerts',

            category: 'vehicle_documents',

            searchPlaceholder: 'Search vehicle or document type...',

            primaryColumnLabel: 'Document',
        },

        licenses: {
            title: 'Driver License Alerts',

            category: 'drivers',

            searchPlaceholder: 'Search driver or license number...',

            primaryColumnLabel: 'Driver',
        },

        'due-services': {
            title: 'Due Service Alerts',

            category: 'maintenance',

            searchPlaceholder: 'Search vehicle or service type...',

            primaryColumnLabel: 'Service',
        },

        'eway-bills': {
            title: 'E-Way Bill Alerts',

            category: 'ewaybill',

            searchPlaceholder: 'Search vehicle, trip or e-way bill...',

            primaryColumnLabel: 'E-Way Bill',
        },
    };

    return configs[alertType.value];
});

const pageTitle = computed(() => typeConfig.value.title);

const searchPlaceholder = computed(() => typeConfig.value.searchPlaceholder);

const primaryColumnLabel = computed(() => typeConfig.value.primaryColumnLabel);

const typeAlerts = computed(() => {
    return alerts.value.filter((alert) => {
        return alert.category === typeConfig.value.category;
    });
});

const filteredAlerts = computed(() => {
    const searchTerm = search.value.trim().toLowerCase();

    return typeAlerts.value.filter((alert) => {
        const severityMatches =
            severityFilter.value === 'all' || alert.severity === severityFilter.value;

        const haystack = [
            alert.title,
            alert.reference,
            alert.category_label,
            alert.ewaybill_no,
            alert.license_number,
            alert.source?.document_type_label,
            alert.source?.driver_name,
            alert.source?.service_type_label,
            alert.source?.vehicle_registration,
        ]
            .filter(Boolean)
            .join(' ')
            .toLowerCase();

        const searchMatches = !searchTerm || haystack.includes(searchTerm);

        return severityMatches && searchMatches;
    });
});

async function loadAlerts() {
    loading.value = true;

    loadError.value = '';

    try {
        const data = await fetchComplianceAlerts();

        alerts.value = data.alerts ?? [];
    } catch {
        alerts.value = [];

        loadError.value = 'Could not load alerts.';
    } finally {
        loading.value = false;
    }
}

function applyFilters() {
    // Filtering is already reactive through computed properties.
}

function resetFilters() {
    severityFilter.value = 'all';

    search.value = '';
}

function primaryTitle(alert) {
    switch (alertType.value) {
        case 'documents':
            return alert.title || 'Vehicle Document';

        case 'licenses':
            return alert.reference || 'Driver';

        case 'due-services':
            return alert.title || 'Vehicle Service';

        case 'eway-bills':
            return alert.ewaybill_no || alert.title || 'E-Way Bill';

        default:
            return alert.title || 'Alert';
    }
}

function categoryIcon(category) {
    const icons = {
        ewaybill: 'fas fa-file-invoice',

        vehicle_documents: 'fas fa-id-card',

        drivers: 'fas fa-user-tie',

        maintenance: 'fas fa-wrench',
    };

    return icons[category] ?? 'fas fa-bell';
}

function capitalize(value) {
    if (!value) {
        return '';
    }

    return value.charAt(0).toUpperCase() + value.slice(1).replace(/_/g, ' ');
}

watch(
    () => route.query.type,
    () => {
        resetFilters();
    },
);

onMounted(loadAlerts);
</script>

<style scoped>
.alert-title-cell {
    display: flex;
    align-items: center;
    gap: 0.65rem;
}

.alert-category-icon {
    display: grid;
    place-items: center;
    width: 2rem;
    height: 2rem;
    flex-shrink: 0;
    border-radius: 0.5rem;
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

.status-badge {
    display: inline-flex;
    align-items: center;
    padding: 0.25rem 0.6rem;
    border-radius: 999px;
    font-size: 0.75rem;
    font-weight: 700;
    white-space: nowrap;
}

.status-badge--expired,
.status-badge--due {
    background: #fef2f2;
    color: #dc2626;
}

.status-badge--expiring,
.status-badge--upcoming {
    background: #fffbeb;
    color: #d97706;
}

/*
 * Keep expired rows light red,
 * while preserving the Trips page table styling.
 */
.alerts-table-row--expired>td {
    background-color: #fef2f2 !important;
}

.alerts-table-row--expired:hover>td {
    background-color: #fee2e2 !important;
}

/*
 * Match the compact Trips table sizing.
 */
.app-index-list-card :deep(.table) {
    font-size: 0.8125rem;
}

.app-index-list-card :deep(.table th),
.app-index-list-card :deep(.table td) {
    padding-top: 0.55rem;
    padding-bottom: 0.55rem;
}
</style>
