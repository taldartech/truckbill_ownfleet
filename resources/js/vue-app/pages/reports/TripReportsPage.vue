<template>
    <div class="container mt-4 app-index-page report-index-page">
        <div v-if="needsCompanySelection" class="alert alert-warning">
            Select a company from
            <RouterLink :to="{ name: 'companies.index' }" class="alert-link">Company List</RouterLink>
            and click <strong>Enter</strong> to view trip reports.
        </div>

        <AppIndexListCard title="Trip Reports">
            <template v-if="canExport" #toolbar>
                <button
                    type="button"
                    class="btn btn-brand-secondary btn-sm report-header-btn report-export-btn"
                    title="Export Excel"
                    aria-label="Export Excel"
                    @click="exportExcel"
                >
                    <svg width="15" height="15" viewBox="0 0 16 16" fill="currentColor" class="report-export-btn__icon me-1 flex-shrink-0" aria-hidden="true">
                        <path d="M5.884 6.68a.5.5 0 1 0-.768.64L7.349 10l-2.233 2.68a.5.5 0 0 0 .768.64L8 10.781l2.116 2.54a.5.5 0 0 0 .768-.641L8.651 10l2.233-2.68a.5.5 0 0 0-.768-.64L8 9.219l-2.116-2.54z"/>
                        <path d="M14 14V4.5L9.5 0H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2zM9.5 3A1.5 1.5 0 0 0 11 4.5h2V14a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1h5.5v2z"/>
                    </svg>
                    <span class="report-export-btn__text">Export Excel</span>
                </button>
            </template>

            <template #filters>
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
                    <div v-if="reportType === 'fuel-performance'">
                        <label class="form-label">Breakdown By</label>
                        <AppSelect
                            v-model="filters.dimension"
                            :options="fuelDimensionOptions"
                            placeholder="Select breakdown"
                        />
                    </div>
                    <div v-if="reportType === 'expense-summary' || (reportType === 'fuel-performance' && filters.dimension !== 'driver')">
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
                    class="px-3 py-3 border-bottom trip-profitability-summary"
                >
                    <div class="row g-2 g-sm-3 small">
                        <div class="col-6 col-sm-6 col-lg-3">
                            <div class="trip-kpi-card">
                                <div class="text-muted trip-kpi-label">Total Freight</div>
                                <div class="fw-semibold trip-kpi-val">{{ formatMoney(financialSummary.total_freight_amount) }}</div>
                            </div>
                        </div>
                        <div class="col-6 col-sm-6 col-lg-3">
                            <div class="trip-kpi-card">
                                <div class="text-muted trip-kpi-label">Trip Expenses</div>
                                <div class="fw-semibold trip-kpi-val">{{ formatMoney(financialSummary.total_expense_amount) }}</div>
                            </div>
                        </div>
                        <div class="col-6 col-sm-6 col-lg-3">
                            <div class="trip-kpi-card">
                                <div class="text-muted trip-kpi-label">Monthly Expenses</div>
                                <div class="fw-semibold trip-kpi-val">{{ formatMoney(financialSummary.monthly_expenses) }}</div>
                            </div>
                        </div>
                        <div class="col-6 col-sm-6 col-lg-3">
                            <div class="trip-kpi-card">
                                <div class="text-muted trip-kpi-label">Profit</div>
                                <div class="fw-semibold trip-kpi-val" :class="Number(financialSummary.profit_amount) >= 0 ? 'text-success' : 'text-danger'">
                                    {{ formatMoney(financialSummary.profit_amount) }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Fuel Performance Summary -->
                <div
                    v-if="!loading && reportType === 'fuel-performance' && reportSummary"
                    class="px-3 py-3 border-bottom bg-light-subtle trip-fuel-summary"
                >
                    <div class="row g-2 g-sm-3 small text-center text-sm-start">
                        <div class="col-6 col-md-4 col-lg-2">
                            <div class="trip-kpi-card">
                                <div class="text-muted small trip-kpi-label">Total Distance</div>
                                <div class="fw-bold fs-6 trip-kpi-val">{{ formatDistance(reportSummary.total_distance_km) }}</div>
                            </div>
                        </div>
                        <div class="col-6 col-md-4 col-lg-2">
                            <div class="trip-kpi-card">
                                <div class="text-muted small trip-kpi-label">Fuel Consumed</div>
                                <div class="fw-bold fs-6 text-primary trip-kpi-val">{{ formatLiters(reportSummary.total_fuel_liters) }}</div>
                            </div>
                        </div>
                        <div class="col-6 col-md-4 col-lg-2">
                            <div class="trip-kpi-card">
                                <div class="text-muted small trip-kpi-label">Total Fuel Cost</div>
                                <div class="fw-bold fs-6 text-dark trip-kpi-val">{{ formatMoney(reportSummary.total_fuel_cost) }}</div>
                            </div>
                        </div>
                        <div class="col-6 col-md-4 col-lg-2">
                            <div class="trip-kpi-card">
                                <div class="text-muted small trip-kpi-label">Fleet Avg Mileage</div>
                                <div class="fw-bold fs-6 text-success trip-kpi-val">{{ formatMileageVal(reportSummary.average_mileage) }}</div>
                            </div>
                        </div>
                        <div class="col-6 col-md-4 col-lg-2">
                            <div class="trip-kpi-card">
                                <div class="text-muted small trip-kpi-label">Fuel Cost / KM</div>
                                <div class="fw-bold fs-6 text-dark trip-kpi-val">₹{{ Number(reportSummary.cost_per_km || 0).toFixed(2) }}</div>
                            </div>
                        </div>
                        <div class="col-6 col-md-4 col-lg-2">
                            <div class="trip-kpi-card">
                                <div class="text-muted small trip-kpi-label">Fuel Variance</div>
                                <div class="fw-bold fs-6 trip-kpi-val" :class="Number(reportSummary.fuel_variance_liters || 0) > 0 ? 'text-danger' : 'text-success'">
                                    {{ Number(reportSummary.fuel_variance_liters || 0) > 0 ? '+' : '' }}{{ reportSummary.fuel_variance_liters }} L
                                    <span v-if="reportSummary.fuel_variance_pct !== undefined">({{ reportSummary.fuel_variance_pct }}%)</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </template>

            <div v-if="loading" class="text-center py-4 text-muted">Loading report…</div>

            <!-- Expense Summary Report (Itemized expense entries with trip details) -->
            <table v-else-if="reportType === 'expense-summary' && rows.length" class="table table-hover align-middle mb-0 report-table">
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
import { expenseTypeLabel, thisMonthDateRange } from '../../api/trips';
import { TRIP_REPORT_TYPES, exportTripReport, fetchTripReport } from '../../api/tripReports';
import { fetchAllTripExpenseTypes } from '../../api/tripExpenseTypes';
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
const activeExpenseTypes = ref([]);
const loading = ref(false);
const { pagination, syncFromMeta, reset: resetPagination } = useListPagination(10);

function defaultFilters() {
    return {
        ...thisMonthDateRange(),
        vehicle_id: '',
        driver_id: '',
        expense_type: '',
        dimension: 'vehicle',
    };
}

const filters = reactive(defaultFilters());

const fuelDimensionOptions = [
    { value: 'vehicle', label: 'Truck-wise (Vehicle)' },
    { value: 'trip', label: 'Trip-wise' },
    { value: 'driver', label: 'Driver-wise' },
    { value: 'route', label: 'Route-wise' },
];

const driverOptions = computed(() => [
    { value: '', label: 'All drivers' },
    ...drivers.value.map((d) => ({ value: d.id, label: d.name })),
]);

const expenseTypeOptions = computed(() => [
    { value: '', label: 'All expense types' },
    ...activeExpenseTypes.value.map((t) => ({
        value: t.value,
        label: t.is_active ? t.label : `${t.label} (Inactive)`
    })),
]);

const currentReportLabel = computed(
    () => reportTypes.find((t) => t.value === reportType.value)?.label ?? 'Trip Report',
);

const columns = computed(() => {
    if (rows.value.length === 0) {
        return [];
    }

    return Object.keys(rows.value[0]).filter(
        (col) => !['trip_id', 'vehicle_id', 'driver_id'].includes(col),
    );
});

const COLUMN_LABELS = {
    s_no: 'S.No',
    vehicle: 'Truck',
    trip_count: 'Trip Count',
    entry_count: 'Entry Count',
    trip_number: 'Trip #',
    date: 'Date',
    driver: 'Driver',
    customer_name: 'Customer',
    start_at: 'Date',
    total_freight_amount: 'Total Freight',
    total_expense_amount: 'Trip Expenses',
    profit_amount: 'Profit',
    registration_number: 'Truck',
    driver_name: 'Driver',
    distance_km: 'Distance (KM)',
    total_distance_km: 'Total Distance (KM)',
    actual_fuel_liters: 'Actual Fuel (L)',
    expected_fuel_liters: 'Expected Fuel (L)',
    fuel_variance_liters: 'Variance (L)',
    fuel_variance_pct: 'Variance (%)',
    average_mileage: 'Avg Mileage (KM/L)',
    actual_mileage: 'Mileage (KM/L)',
    expected_mileage: 'Exp. Mileage (KM/L)',
    target_mileage: 'Target Mileage (KM/L)',
    total_fuel_cost: 'Fuel Cost',
    fuel_cost: 'Fuel Cost',
    cost_per_km: 'Cost / KM',
    route: 'Route',
    status: 'Status',
};

function formatDistance(val) {
    return `${Number(val ?? 0).toLocaleString('en-IN', { maximumFractionDigits: 2 })} km`;
}

function formatLiters(val) {
    return `${Number(val ?? 0).toLocaleString('en-IN', { maximumFractionDigits: 2 })} L`;
}

function formatMileageVal(val) {
    return val ? `${Number(val).toFixed(2)} KM/L` : '—';
}

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

    if (value === null || value === undefined || value === '') {
        return '—';
    }

    if (
        columnKey === 'total_fuel_cost' ||
        columnKey === 'fuel_cost' ||
        columnKey === 'total_freight_amount' ||
        columnKey === 'total_expense_amount' ||
        columnKey === 'profit_amount'
    ) {
        return formatMoney(value);
    }

    if (columnKey === 'cost_per_km') {
        return `₹${Number(value).toFixed(2)}`;
    }

    if (
        columnKey === 'average_mileage' ||
        columnKey === 'actual_mileage' ||
        columnKey === 'expected_mileage' ||
        columnKey === 'target_mileage'
    ) {
        return `${Number(value).toFixed(2)} KM/L`;
    }

    if (
        columnKey === 'actual_fuel_liters' ||
        columnKey === 'expected_fuel_liters' ||
        columnKey === 'fuel_variance_liters'
    ) {
        return `${Number(value).toFixed(2)} L`;
    }

    if (columnKey === 'fuel_variance_pct') {
        const num = Number(value);

        return `${num > 0 ? '+' : ''}${num.toFixed(2)}%`;
    }

    if (columnKey === 'distance_km' || columnKey === 'total_distance_km') {
        return formatDistance(value);
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
    if ((reportType.value === 'expense-summary' || reportType.value === 'fuel-performance') && filters.driver_id) {
        params.driver_id = filters.driver_id;
    }
    if (reportType.value === 'expense-summary' && filters.expense_type) {
        params.expense_type = filters.expense_type;
    }
    if (reportType.value === 'fuel-performance' && filters.dimension) {
        params.dimension = filters.dimension;
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
    if (reportType.value !== 'expense-summary' && reportType.value !== 'fuel-performance') {
        filters.driver_id = '';
        filters.expense_type = '';
    }
    resetPagination();
    loadReport(1);
});

watch(
    () => filters.dimension,
    () => {
        if (reportType.value === 'fuel-performance') {
            resetPagination();
            loadReport(1);
        }
    },
);

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
    const [fetchedVehicles, fetchedDrivers, expRes] = await Promise.all([
        listAllVehicles(),
        listAllDrivers(),
        fetchAllTripExpenseTypes(),
    ]);
    vehicles.value = fetchedVehicles;
    drivers.value = fetchedDrivers;
    activeExpenseTypes.value = expRes ?? [];
    await loadReport(1);
});

useOperationalDataInvalidation('trip-reports', () => {
    if (!needsCompanySelection.value) {
        loadReport(pagination.currentPage);
    }
});
</script>

<style scoped>
.report-export-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
}

.report-export-btn__icon {
    font-size: 0.95rem;
    line-height: 1;
}

@media (min-width: 768px) {
    .trip-kpi-card {
        background: transparent;
        border: none;
        padding: 0;
        box-shadow: none;
    }
}

@media (max-width: 767.98px) {
    :deep(.card-header) {
        display: flex !important;
        flex-direction: row !important;
        flex-wrap: nowrap !important;
        align-items: center !important;
        justify-content: space-between !important;
        padding: 0.45rem 0.65rem !important;
        gap: 0.4rem !important;
    }

    :deep(.card-header .card-title) {
        font-size: 0.85rem !important;
        font-weight: 600 !important;
        line-height: 1.25 !important;
        margin-bottom: 0 !important;
        white-space: nowrap !important;
        overflow: hidden !important;
        text-overflow: ellipsis !important;
        flex: 1 1 auto !important;
        min-width: 0 !important;
        color: #ffffff !important;
    }

    :deep(.card-header .app-index-card-toolbar) {
        margin-left: auto !important;
        flex-shrink: 0 !important;
        display: flex !important;
        align-items: center !important;
    }

    .report-export-btn {
        width: auto !important;
        height: auto !important;
        min-height: 26px !important;
        padding: 0.2rem 0.55rem !important;
        border-radius: 6px !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        gap: 0.25rem !important;
        white-space: nowrap !important;
        flex-shrink: 0 !important;
        font-size: 0.72rem !important;
        font-weight: 600 !important;
        line-height: 1.2 !important;
        background-color: #ffffff !important;
        border: 1px solid #ffffff !important;
        color: var(--brand-primary, #2fb36f) !important;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1) !important;
    }

    .report-export-btn__icon {
        font-size: 0.78rem !important;
        color: var(--brand-primary, #2fb36f) !important;
        line-height: 1 !important;
        display: inline-block !important;
        margin: 0 !important;
    }

    .report-export-btn__text {
        display: inline !important;
        font-size: 0.72rem !important;
        font-weight: 600 !important;
        color: var(--brand-primary, #2fb36f) !important;
        line-height: 1.2 !important;
        white-space: nowrap !important;
    }

    .trip-profitability-summary,
    .trip-fuel-summary {
        padding: 0.5rem 0.75rem !important;
        background-color: #f8fafc;
    }

    .trip-kpi-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 0.45rem 0.65rem;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);
    }

    .trip-kpi-label {
        font-size: 0.72rem !important;
        line-height: 1.2;
        color: #64748b !important;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        margin-bottom: 2px;
    }

    .trip-kpi-val {
        font-size: 0.85rem !important;
        line-height: 1.25;
        font-weight: 700 !important;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .report-table {
        font-size: 0.72rem !important;
        width: max-content !important;
        min-width: 100% !important;
    }

    .report-table th,
    .report-table td {
        font-size: 0.72rem !important;
        padding: 0.35rem 0.5rem !important;
        white-space: nowrap !important;
        vertical-align: middle !important;
        line-height: 1.3 !important;
    }

    .report-results-meta {
        padding: 0.5rem 0.75rem !important;
        font-size: 0.8rem !important;
    }

    .report-results-meta__title {
        font-size: 0.85rem !important;
        font-weight: 600;
    }
}

@media (max-width: 380px) {
    :deep(.card-header) {
        padding: 0.4rem 0.5rem !important;
        gap: 0.3rem !important;
    }

    :deep(.card-header .card-title) {
        font-size: 0.78rem !important;
    }

    .report-export-btn {
        min-height: 24px !important;
        padding: 0.15rem 0.45rem !important;
        font-size: 0.65rem !important;
        border-radius: 5px !important;
        gap: 0.2rem !important;
    }

    .report-export-btn__icon {
        font-size: 0.7rem !important;
    }

    .report-export-btn__text {
        font-size: 0.65rem !important;
    }

    .trip-kpi-label {
        font-size: 0.68rem !important;
    }

    .trip-kpi-val {
        font-size: 0.78rem !important;
    }

    .report-table {
        font-size: 0.68rem !important;
    }

    .report-table th,
    .report-table td {
        font-size: 0.68rem !important;
        padding: 0.3rem 0.4rem !important;
    }
}
</style>
