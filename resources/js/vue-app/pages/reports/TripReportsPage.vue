<template>
    <div class="container mt-4 app-index-page">
        <AppIndexPageHeader title="Trip Reports">
            <template v-if="canExport" #actions>
                <AppButton
                    icon="bi bi-file-earmark-excel"
                    label="Export Excel"
                    outline
                    @click="exportExcel"
                />
            </template>
        </AppIndexPageHeader>

        <div v-if="needsCompanySelection" class="alert alert-warning">
            Select a company from
            <RouterLink :to="{ name: 'companies.index' }" class="alert-link">Company List</RouterLink>
            and click <strong>Enter</strong> to view trip reports.
        </div>

        <div v-if="flash.error" class="alert alert-danger">{{ flash.error }}</div>

        <AppIndexFiltersCard :active="filtersActive" @apply="loadReport" @clear="clearFilters">
            <div>
                <label class="form-label">Report Type</label>
                <select v-model="reportType" class="form-select">
                    <option v-for="t in reportTypes" :key="t.value" :value="t.value">{{ t.label }}</option>
                </select>
            </div>
            <div>
                <label class="form-label">From Date</label>
                <input v-model="filters.from_date" type="date" class="form-control" />
            </div>
            <div>
                <label class="form-label">To Date</label>
                <input v-model="filters.to_date" type="date" class="form-control" />
            </div>
            <div>
                <label class="form-label">Truck</label>
                <select v-model="filters.vehicle_id" class="form-select">
                    <option value="">All</option>
                    <option v-for="v in vehicles" :key="v.id" :value="v.id">{{ v.registration_number }}</option>
                </select>
            </div>
        </AppIndexFiltersCard>

        <div class="card app-index-list-card">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h3 class="card-title mb-0">{{ currentReportLabel }}</h3>
                <span v-if="!loading && tripCount !== null" class="badge bg-secondary">
                    {{ tripCount }} {{ tripCount === 1 ? 'trip' : 'trips' }}
                </span>
            </div>
            <div class="card-body">
                <div v-if="loading" class="text-center py-4 text-muted">Loading report…</div>
                <div v-else-if="columns.length" class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th v-for="col in columns" :key="col">{{ formatColumn(col) }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="rows.length === 0">
                                <td :colspan="columns.length" class="text-center text-muted py-4">
                                    No data for selected filters.
                                </td>
                            </tr>
                            <tr v-for="(row, index) in rows" :key="index">
                                <td v-for="col in columns" :key="col">{{ formatCell(row[col]) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-else-if="!loading" class="text-center text-muted py-4">No data for selected filters.</div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { RouterLink } from 'vue-router';
import { listAllVehicles } from '../../api/vehicles';
import { thisMonthDateRange } from '../../api/trips';
import { TRIP_REPORT_TYPES, exportTripReport, fetchTripReport } from '../../api/tripReports';
import AppButton from '../../components/ui/AppButton.vue';
import AppIndexFiltersCard from '../../components/ui/AppIndexFiltersCard.vue';
import AppIndexPageHeader from '../../components/ui/AppIndexPageHeader.vue';
import { usePermissions } from '../../composables/usePermissions';
import { useRequireCompanyContext } from '../../composables/useRequireCompanyContext';

const { needsCompanySelection } = useRequireCompanyContext();
const { canInCompany } = usePermissions();
const canExport = computed(() => canInCompany('trip_reports.export'));

const reportTypes = TRIP_REPORT_TYPES;
const reportType = ref('profitability');
const vehicles = ref([]);
const rows = ref([]);
const tripCount = ref(null);
const loading = ref(false);
const flash = reactive({ error: '' });

function defaultFilters() {
    return {
        ...thisMonthDateRange(),
        vehicle_id: '',
    };
}

const filters = reactive(defaultFilters());

const filtersActive = computed(() => {
    const defaults = defaultFilters();

    return (
        filters.vehicle_id !== defaults.vehicle_id ||
        filters.from_date !== defaults.from_date ||
        filters.to_date !== defaults.to_date
    );
});

const currentReportLabel = computed(
    () => reportTypes.find((t) => t.value === reportType.value)?.label ?? 'Trip Report',
);

const columns = computed(() => {
    if (rows.value.length === 0) {
        return [];
    }

    return Object.keys(rows.value[0]);
});

const COLUMN_LABELS = {
    s_no: 'S.No',
    vehicle: 'Truck',
    trip_count: 'Trip Count',
    entry_count: 'Entry Count',
};

function formatColumn(key) {
    if (COLUMN_LABELS[key]) {
        return COLUMN_LABELS[key];
    }

    return key.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
}

function formatCell(value) {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    if (typeof value === 'number') {
        return value.toLocaleString('en-IN', { maximumFractionDigits: 2 });
    }

    return value;
}

function buildFilterParams() {
    const params = {
        from_date: filters.from_date,
        to_date: filters.to_date,
    };

    if (filters.vehicle_id) {
        params.vehicle_id = filters.vehicle_id;
    }

    return params;
}

async function loadReport() {
    loading.value = true;
    flash.error = '';

    try {
        const result = await fetchTripReport(reportType.value, buildFilterParams());
        rows.value = result.rows;
        tripCount.value = result.summary?.trip_count ?? result.rows.length;
    } catch (error) {
        flash.error = error?.response?.data?.message ?? 'Failed to load trip report.';
        rows.value = [];
        tripCount.value = null;
    } finally {
        loading.value = false;
    }
}

function clearFilters() {
    Object.assign(filters, defaultFilters());
    loadReport();
}

function exportExcel() {
    exportTripReport(reportType.value, buildFilterParams());
}

watch(reportType, () => {
    loadReport();
});

onMounted(async () => {
    vehicles.value = await listAllVehicles();
    await loadReport();
});
</script>
