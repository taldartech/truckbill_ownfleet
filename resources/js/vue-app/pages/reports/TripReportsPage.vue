<template>
    <div class="container mt-4 app-index-page report-index-page">
        <div v-if="needsCompanySelection" class="alert alert-warning">
            Select a company from
            <RouterLink :to="{ name: 'companies.index' }" class="alert-link">Company List</RouterLink>
            and click <strong>Enter</strong> to view trip reports.
        </div>

        <AppIndexListCard title="Trip Reports">
            <template v-if="canExport" #toolbar>
                <button type="button" class="btn btn-brand-secondary btn-sm report-header-btn" @click="exportExcel">
                    Export Excel
                </button>
            </template>

            <AppReportFiltersPanel :loading="loading" @search="loadReport(1)" @reset="clearFilters">
                <div>
                    <label class="form-label">Report Type</label>
                    <AppSelect
                        v-model="reportType"
                        :options="reportTypes"
                        placeholder="Select report"
                        search-placeholder="Search report…"
                    />
                </div>
                <div>
                    <label class="form-label">From Date</label>
                    <AppDateInput v-model="filters.from_date" />
                </div>
                <div>
                    <label class="form-label">To Date</label>
                    <AppDateInput v-model="filters.to_date" />
                </div>
                <div>
                    <label class="form-label" for="tripReports_vehicle">Truck</label>
                    <VehicleSelect
                        input-id="tripReports_vehicle"
                        v-model="filters.vehicle_id"
                        :vehicles="vehicles"
                        placeholder="All trucks"
                    />
                </div>
            </AppReportFiltersPanel>

            <div
                v-if="!loading && tripCount !== null"
                class="report-results-meta d-flex justify-content-between align-items-center flex-wrap gap-2 px-3 py-2 border-bottom"
            >
                <span class="report-results-meta__title">{{ currentReportLabel }}</span>
                <span class="badge bg-secondary">
                    {{ tripCount }} {{ tripCount === 1 ? 'trip' : 'trips' }}
                </span>
            </div>

            <div v-if="loading" class="text-center py-4 text-muted">Loading report…</div>
            <table v-else-if="columns.length" class="table table-striped table-hover align-middle mb-0 report-table">
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
                        <td v-for="col in columns" :key="col">{{ formatCell(col, row[col]) }}</td>
                    </tr>
                </tbody>
            </table>
            <div v-else class="text-center text-muted py-4">No data for selected filters.</div>

            <template #footer>
                <AppIndexPartloadPagination
                    :pagination="pagination"
                    :loading="loading"
                    item-label="records"
                    aria-label="Trip reports pagination"
                    @page-change="loadReport"
                />
            </template>
        </AppIndexListCard>
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { RouterLink, useRoute } from 'vue-router';
import { listAllVehicles } from '../../api/vehicles';
import { expenseTypeLabel, thisMonthDateRange } from '../../api/trips';
import { TRIP_REPORT_TYPES, exportTripReport, fetchTripReport } from '../../api/tripReports';
import { withReportPagination } from '../../api/reportPagination';
import AppIndexListCard from '../../components/ui/AppIndexListCard.vue';
import AppReportFiltersPanel from '../../components/ui/AppReportFiltersPanel.vue';
import AppSelect from '../../components/ui/AppSelect.vue';
import AppIndexPartloadPagination from '../../components/ui/AppIndexPartloadPagination.vue';
import VehicleSelect from '../../components/ui/VehicleSelect.vue';
import { formatTableCell } from '../../utils/datetime';
import { useListPagination } from '../../composables/useListPagination';
import { usePermissions } from '../../composables/usePermissions';
import { useOperationalDataInvalidation } from '../../composables/useOperationalDataInvalidation';
import { useRequireCompanyContext } from '../../composables/useRequireCompanyContext';
import { useAppToast } from '../../composables/useAppToast';

const { needsCompanySelection } = useRequireCompanyContext();
const route = useRoute();
const toast = useAppToast();
const { canInCompany } = usePermissions();
const canExport = computed(() => canInCompany('trip_reports.export'));

const reportTypes = TRIP_REPORT_TYPES;
const requestedReportType = String(route.query.type ?? '');
const reportType = ref(
    reportTypes.some((type) => type.value === requestedReportType)
        ? requestedReportType
        : 'profitability',
);
const vehicles = ref([]);
const rows = ref([]);
const tripCount = ref(null);
const loading = ref(false);
const { pagination, syncFromMeta, reset: resetPagination } = useListPagination(10);

function defaultFilters() {
    return {
        ...thisMonthDateRange(),
        vehicle_id: '',
    };
}

const filters = reactive(defaultFilters());

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

function formatCell(columnKey, value) {
    if (columnKey === 'expense_type') {
        return expenseTypeLabel(value);
    }

    return formatTableCell(columnKey, value);
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

async function loadReport(page = 1) {
    loading.value = true;

    try {
        const result = await fetchTripReport(
            reportType.value,
            withReportPagination(buildFilterParams(), page, pagination.perPage),
        );
        rows.value = result.rows;
        tripCount.value = result.summary?.trip_count ?? result.meta?.total ?? result.rows.length;
        syncFromMeta(result.meta, result.rows.length);
    } catch (error) {
        toast.error(error?.response?.data?.message ?? 'Failed to load trip report.');
        rows.value = [];
        tripCount.value = null;
        resetPagination();
    } finally {
        loading.value = false;
    }
}

function clearFilters() {
    Object.assign(filters, defaultFilters());
    loadReport(1);
}

function exportExcel() {
    exportTripReport(reportType.value, buildFilterParams());
}

watch(reportType, () => {
    resetPagination();
    loadReport(1);
});

watch(
    () => route.query.type,
    (value) => {
        const requestedType = String(value ?? '');

        if (
            requestedType !== reportType.value
            && reportTypes.some((type) => type.value === requestedType)
        ) {
            reportType.value = requestedType;
        }
    },
);

onMounted(async () => {
    vehicles.value = await listAllVehicles();
    await loadReport(1);
});

useOperationalDataInvalidation('trip-reports', () => {
    if (!needsCompanySelection.value) {
        loadReport(pagination.currentPage);
    }
});
</script>
