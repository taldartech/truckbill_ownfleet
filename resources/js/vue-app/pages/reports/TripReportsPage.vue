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

        <AppIndexFiltersCard :active="filtersActive" @apply="loadReport(1)" @clear="clearFilters">
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
                                <td v-for="col in columns" :key="col">{{ formatCell(col, row[col]) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-else-if="!loading" class="text-center text-muted py-4">No data for selected filters.</div>
            </div>
            <AppIndexPaginationFooter
                page-id="tripReports"
                :loading="loading"
                :current-page="pagination.currentPage"
                :last-page="pagination.lastPage"
                :per-page="pagination.perPage"
                :total="pagination.total"
                :from="pagination.from"
                :to="pagination.to"
                item-label="records"
                @page-change="loadReport"
                @update:per-page="onPerPageUpdate"
            />
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { RouterLink } from 'vue-router';
import { listAllVehicles } from '../../api/vehicles';
import { expenseTypeLabel, thisMonthDateRange } from '../../api/trips';
import { TRIP_REPORT_TYPES, exportTripReport, fetchTripReport } from '../../api/tripReports';
import { withReportPagination } from '../../api/reportPagination';
import AppButton from '../../components/ui/AppButton.vue';
import AppIndexFiltersCard from '../../components/ui/AppIndexFiltersCard.vue';
import AppSelect from '../../components/ui/AppSelect.vue';
import AppIndexPageHeader from '../../components/ui/AppIndexPageHeader.vue';
import AppIndexPaginationFooter from '../../components/ui/AppIndexPaginationFooter.vue';
import VehicleSelect from '../../components/ui/VehicleSelect.vue';
import { formatTableCell } from '../../utils/datetime';
import { useListPagination } from '../../composables/useListPagination';
import { usePermissions } from '../../composables/usePermissions';
import { useOperationalDataInvalidation } from '../../composables/useOperationalDataInvalidation';
import { useRequireCompanyContext } from '../../composables/useRequireCompanyContext';
import { useAppToast } from '../../composables/useAppToast';

const { needsCompanySelection } = useRequireCompanyContext();
const toast = useAppToast();
const { canInCompany } = usePermissions();
const canExport = computed(() => canInCompany('trip_reports.export'));

const reportTypes = TRIP_REPORT_TYPES;
const reportType = ref('profitability');
const vehicles = ref([]);
const rows = ref([]);
const tripCount = ref(null);
const loading = ref(false);
const { pagination, syncFromMeta, reset: resetPagination } = useListPagination();

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

function onPerPageUpdate(perPage) {
    pagination.perPage = perPage;
    loadReport(1);
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
