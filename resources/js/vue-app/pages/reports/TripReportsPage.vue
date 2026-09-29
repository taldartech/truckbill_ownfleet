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
                <div v-if="reportType === 'expense-summary'">
                    <label class="form-label">Driver</label>
                    <AppSelect v-model="filters.driver_id" :options="driverOptions" placeholder="All drivers"
                        search-placeholder="Search driver…" />
                </div>
                <div v-if="reportType === 'expense-summary'">
                    <label class="form-label">Expense Type</label>
                    <AppSelect v-model="filters.expense_type" :options="expenseTypeOptions"
                        placeholder="All expense types" search-placeholder="Search expense type…" />
                </div>
            </AppReportFiltersPanel>

            <div
                v-if="!loading && tripCount !== null"
                class="report-results-meta d-flex justify-content-between align-items-center flex-wrap gap-2 px-3 py-2 border-bottom"
            >
                <span class="report-results-meta__title">{{ currentReportLabel }}</span>
                <div class="d-flex align-items-center gap-2">
                    <span v-if="reportType === 'expense-summary'" class="fw-semibold me-2">
                        Total: {{ formatMoney(totalExpenseAmount) }}
                    </span>
                    <span class="badge bg-secondary">
                        {{ tripCount }} {{ tripCount === 1 ? 'trip' : 'trips' }}
                    </span>
                </div>
            </div>

            <div
                v-if="!loading && reportType === 'profitability' && financialSummary"
                class="px-3 py-3 border-bottom"
            >
                <div class="row g-3 small">
                    <div class="col-sm-6 col-lg-3">
                        <div class="text-muted">Total Freight</div>
                        <div class="fw-semibold">{{ formatMoney(financialSummary.total_freight_amount) }}</div>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <div class="text-muted">Trip Expenses</div>
                        <div class="fw-semibold">{{ formatMoney(financialSummary.total_expense_amount) }}</div>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <div class="text-muted">Monthly Expenses</div>
                        <div class="fw-semibold">{{ formatMoney(financialSummary.monthly_expenses) }}</div>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <div class="text-muted">Profit</div>
                        <div class="fw-semibold">{{ formatMoney(financialSummary.profit_amount) }}</div>
                    </div>
                </div>
            </div>

            <div v-if="loading" class="text-center py-4 text-muted">Loading report…</div>

            <!-- Expense Summary Report (Itemized expense entries with trip details) -->
            <div v-else-if="reportType === 'expense-summary' && rows.length" class="table-responsive">
                <table class="table table-hover align-middle mb-0 report-table">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 50px;">Sr. No.</th>
                            <th>Expense Date</th>
                            <th>Trip Number</th>
                            <th>Truck</th>
                            <th>Driver</th>
                            <th>Expense Type</th>
                            <th class="text-end">Expense Amount</th>
                            <th style="min-width: 250px;">Other</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="(row, index) in flattenedRows"
                            :key="row.expense_id ? `${row.trip_id}-${row.expense_id}` : (row.s_no || index)"
                        >
                            <td>{{ startSerialNumber + index }}</td>
                            <td>{{ row.expense_date ? formatDate(row.expense_date) : '—' }}</td>
                            <td class="fw-semibold text-nowrap">
                                <RouterLink
                                    v-if="row.trip_id"
                                    :to="{ name: 'trips.show', params: { id: row.trip_id } }"
                                    class="text-decoration-none"
                                >
                                    {{ row.trip_number }}
                                </RouterLink>
                                <span v-else>{{ row.trip_number || '—' }}</span>
                            </td>
                            <td>{{ row.vehicle || '—' }}</td>
                            <td class="text-nowrap">{{ row.driver || '—' }}</td>

                            <!-- Expense entry columns -->
                            <td>
                                <span class="text-start">
                                    {{ row.expense_type_label || row.expense_type }}
                                </span>
                                <span v-if="row.other_type" class="text-muted small ms-1">
                                    ({{ row.other_type }})
                                </span>
                            </td>
                            <td class="text-end fw-semibold">{{ formatMoney(row.amount) }}</td>
                            <td class="small text-muted">
                                <span v-if="row.fuel_liters">
                                    {{ row.fuel_liters }} L
                                    <template v-if="row.fuel_rate"> @ ₹{{ row.fuel_rate }}/L</template>
                                    <template v-if="row.fuel_odometer_km"> (Odo: {{ row.fuel_odometer_km }} km)</template>
                                </span>
                                <span v-if="row.notes">
                                    <template v-if="row.fuel_liters"> &bull; </template>
                                    {{ row.notes }}
                                </span>
                                <span v-if="!row.fuel_liters && !row.notes">—</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Generic Table for Other Report Types -->
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
import { listAllDrivers } from '../../api/drivers';
import { EXPENSE_TYPES, expenseTypeLabel, thisMonthDateRange } from '../../api/trips';
import { TRIP_REPORT_TYPES, exportTripReport, fetchTripReport } from '../../api/tripReports';
import { withReportPagination } from '../../api/reportPagination';
import AppIndexListCard from '../../components/ui/AppIndexListCard.vue';
import AppReportFiltersPanel from '../../components/ui/AppReportFiltersPanel.vue';
import AppSelect from '../../components/ui/AppSelect.vue';
import AppDateInput from '../../components/ui/AppDateInput.vue';
import AppIndexPartloadPagination from '../../components/ui/AppIndexPartloadPagination.vue';
import VehicleSelect from '../../components/ui/VehicleSelect.vue';
import { formatDate, formatTableCell } from '../../utils/datetime';
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
const drivers = ref([]);
const rows = ref([]);
const tripCount = ref(null);
const financialSummary = ref(null);
const reportSummary = ref(null);
const loading = ref(false);
const { pagination, syncFromMeta, reset: resetPagination } = useListPagination(10);

function defaultFilters() {
    return {
        ...thisMonthDateRange(),
        vehicle_id: '',
        driver_id: '',
        expense_type: '',
    };
}

const filters = reactive(defaultFilters());

const driverOptions = computed(() => [
    { value: '', label: 'All drivers' },
    ...drivers.value.map((d) => ({ value: d.id, label: d.name })),
]);

const expenseTypeOptions = computed(() => [
    { value: '', label: 'All expense types' },
    ...EXPENSE_TYPES.map((t) => ({ value: t.value, label: t.label })),
]);

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

function formatMoney(value) {
    return new Intl.NumberFormat('en-IN', {
        style: 'currency',
        currency: 'INR',
        maximumFractionDigits: 2,
    }).format(Number(value ?? 0));
}

const startSerialNumber = computed(() => Number(rows.value[0]?.s_no) || 1);

const flattenedRows = computed(() => {
    if (reportType.value !== 'expense-summary') {
        return [];
    }

    return rows.value.slice().sort((a, b) => {
        const tripA = String(a.trip_number ?? '');
        const tripB = String(b.trip_number ?? '');
        const comp = tripA.localeCompare(tripB, undefined, { numeric: true });
        if (comp !== 0) return comp;
        const dateA = a.expense_date || '';
        const dateB = b.expense_date || '';
        return dateB.localeCompare(dateA);
    });
});

const totalExpenseAmount = computed(() => {
    if (reportSummary.value?.total_expense_amount !== undefined) {
        return Number(reportSummary.value.total_expense_amount) || 0;
    }
    return flattenedRows.value.reduce((sum, r) => sum + (Number(r.amount) || 0), 0);
});

function buildFilterParams() {
    const params = {
        from_date: filters.from_date,
        to_date: filters.to_date,
    };

    if (filters.vehicle_id) {
        params.vehicle_id = filters.vehicle_id;
    }
    if (reportType.value === 'expense-summary' && filters.driver_id) {
        params.driver_id = filters.driver_id;
    }
    if (reportType.value === 'expense-summary' && filters.expense_type) {
        params.expense_type = filters.expense_type;
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
        reportSummary.value = result.summary ?? null;
        financialSummary.value =
            reportType.value === 'profitability' && result.summary
                ? {
                      total_freight_amount: result.summary.total_freight_amount ?? 0,
                      total_expense_amount: result.summary.total_expense_amount ?? 0,
                      monthly_expenses: result.summary.monthly_expenses ?? 0,
                      profit_amount: result.summary.profit_amount ?? 0,
                  }
                : null;
        syncFromMeta(result.meta, result.rows.length);
    } catch (error) {
        toast.error(error?.response?.data?.message ?? 'Failed to load trip report.');
        rows.value = [];
        tripCount.value = null;
        reportSummary.value = null;
        financialSummary.value = null;
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
    if (reportType.value !== 'expense-summary') {
        filters.driver_id = '';
        filters.expense_type = '';
    }
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
    const [fetchedVehicles, fetchedDrivers] = await Promise.all([
        listAllVehicles(),
        listAllDrivers(),
    ]);
    vehicles.value = fetchedVehicles;
    drivers.value = fetchedDrivers;
    await loadReport(1);
});

useOperationalDataInvalidation('trip-reports', () => {
    if (!needsCompanySelection.value) {
        loadReport(pagination.currentPage);
    }
});
</script>
