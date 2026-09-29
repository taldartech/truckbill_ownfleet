<template>
    <div class="container mt-4 app-index-page report-index-page">
        <div v-if="needsCompanySelection" class="alert alert-warning">
            Select a company from
            <RouterLink :to="{ name: 'companies.index' }" class="alert-link">Company List</RouterLink>
            and click <strong>Enter</strong> to view the vehicle utilization & idle report.
        </div>

        <AppIndexListCard title="Vehicle Utilization & Idle Report">
            <template v-if="canExport" #toolbar>
                <button
                    type="button"
                    class="btn btn-brand-secondary btn-sm report-header-btn"
                    :disabled="loading"
                    @click="exportExcel"
                >
                    <i class="bi bi-file-earmark-excel me-1" aria-hidden="true"></i>
                    Export Excel
                </button>
            </template>

            <!-- Filters -->
            <AppReportFiltersPanel :loading="loading" @search="loadReport(1)" @reset="clearFilters">
                <div>
                    <label class="form-label">From Date</label>
                    <AppDateInput v-model="filters.from_date" />
                </div>
                <div>
                    <label class="form-label">To Date</label>
                    <AppDateInput v-model="filters.to_date" />
                </div>
                <div>
                    <label class="form-label" for="vehicleUtilization_vehicle">Truck</label>
                    <VehicleSelect
                        input-id="vehicleUtilization_vehicle"
                        v-model="filters.vehicle_id"
                        :vehicles="vehicles"
                        placeholder="All trucks"
                    />
                </div>
                <div>
                    <label class="form-label">Status</label>
                    <AppSelect
                        v-model="filters.status"
                        :options="statusOptions"
                        placeholder="All Statuses"
                    />
                </div>
                <div>
                    <label class="form-label">Idle Threshold</label>
                    <AppSelect
                        v-model="filters.min_idle_days"
                        :options="idleThresholdOptions"
                        placeholder="All Idle Days"
                    />
                </div>
                <div>
                    <label class="form-label">Search</label>
                    <input
                        v-model="filters.search"
                        type="search"
                        class="form-control"
                        placeholder="Search truck, driver, route…"
                        @keydown.enter.prevent="loadReport(1)"
                    />
                </div>
            </AppReportFiltersPanel>

            <!-- Results Meta Bar -->
            <div
                class="report-results-meta d-flex justify-content-between align-items-center flex-wrap gap-2 px-3 py-2 border-bottom"
            >
                <span class="report-results-meta__title">
                    <i class="bi bi-truck me-1 text-primary"></i> Fleet Utilization & Business Overview
                </span>
                <div class="d-flex align-items-center gap-2">
                    <span v-if="periodLabel" class="badge bg-light text-dark border">
                        <i class="bi bi-calendar3 me-1 text-muted"></i>{{ periodLabel }}
                    </span>
                    <span v-if="companyName" class="text-muted small fw-medium">{{ companyName }}</span>
                </div>
            </div>

            <!-- Summary KPI Cards -->
            <div v-if="summary" class="px-3 py-3 border-bottom bg-light bg-opacity-25">
                <div class="row g-2 text-center">
                    <div class="col-6 col-sm-4 col-md-3 col-xl">
                        <div class="p-2 border rounded bg-white shadow-sm h-100">
                            <div class="text-muted small">Total Fleet</div>
                            <div class="fs-5 fw-bold text-dark">{{ summary.total_fleet ?? 0 }}</div>
                        </div>
                    </div>
                    <div class="col-6 col-sm-4 col-md-3 col-xl">
                        <div class="p-2 border rounded bg-white shadow-sm h-100">
                            <div class="text-muted small">On Trip</div>
                            <div class="fs-5 fw-bold text-primary">{{ summary.on_trip_count ?? 0 }}</div>
                        </div>
                    </div>
                    <div class="col-6 col-sm-4 col-md-3 col-xl">
                        <div class="p-2 border rounded bg-white shadow-sm h-100">
                            <div class="text-muted small">Available</div>
                            <div class="fs-5 fw-bold text-success">{{ summary.available_count ?? 0 }}</div>
                        </div>
                    </div>
                    <div class="col-6 col-sm-4 col-md-3 col-xl">
                        <div class="p-2 border rounded bg-white shadow-sm h-100">
                            <div class="text-muted small">Idle (7+ Days)</div>
                            <div class="fs-5 fw-bold text-warning">{{ summary.idle_7plus_count ?? 0 }}</div>
                        </div>
                    </div>
                    <div class="col-6 col-sm-4 col-md-3 col-xl">
                        <div class="p-2 border rounded bg-white shadow-sm h-100">
                            <div class="text-muted small">Fleet Utilization</div>
                            <div class="fs-5 fw-bold text-info">{{ summary.overall_utilization_rate ?? 0 }}%</div>
                        </div>
                    </div>
                    <div class="col-6 col-sm-4 col-md-3 col-xl">
                        <div class="p-2 border rounded bg-white shadow-sm h-100">
                            <div class="text-muted small">Revenue</div>
                            <div class="fs-5 fw-bold text-success">{{ formatMoney(summary.total_revenue) }}</div>
                        </div>
                    </div>
                    <div class="col-6 col-sm-4 col-md-3 col-xl">
                        <div class="p-2 border rounded bg-white shadow-sm h-100">
                            <div class="text-muted small">Trip Expenses</div>
                            <div class="fs-5 fw-bold text-danger">{{ formatMoney(summary.total_expenses) }}</div>
                        </div>
                    </div>
                    <div class="col-6 col-sm-4 col-md-3 col-xl">
                        <div class="p-2 border rounded bg-white shadow-sm h-100">
                            <div class="text-muted small">Net Profit</div>
                            <div
                                class="fs-5 fw-bold"
                                :class="(summary.total_profit ?? 0) >= 0 ? 'text-success' : 'text-danger'"
                            >
                                {{ formatMoney(summary.total_profit) }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Loading State -->
            <div v-if="loading" class="text-center py-5 text-muted">
                <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
                Loading utilization report…
            </div>

            <!-- Report Table -->
            <div v-else class="table-responsive">
                <table class="table table-striped table-hover align-middle mb-0 report-table">
                    <thead class="table-light">
                        <tr>
                            <th class="text-center" style="width: 50px;">S.No</th>
                            <th>Truck No.</th>
                            <th>Driver</th>
                            <th>Status</th>
                            <th>Current / Last Location</th>
                            <th>Last Trip</th>
                            <th>Last Trip End</th>
                            <th>Last Route</th>
                            <th>Idle Since</th>
                            <th class="text-center">Idle Days</th>
                            <th class="text-end">Trips</th>
                            <th class="text-end">Running Days</th>
                            <th class="text-end">Utilization</th>
                            <th class="text-end">Revenue (₹)</th>
                            <th class="text-end">Expenses (₹)</th>
                            <th class="text-end">Net Profit (₹)</th>
                            <th class="text-end">Total KM</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="rows.length === 0">
                            <td colspan="17" class="text-center text-muted py-5">
                                <i class="bi bi-inbox fs-3 d-block mb-2 text-secondary"></i>
                                No vehicle utilization records found matching the selected filters.
                            </td>
                        </tr>
                        <tr v-for="row in rows" :key="row.vehicle_id">
                            <td class="text-center text-muted small">{{ row.s_no }}</td>
                            <td>
                                <RouterLink
                                    :to="{ name: 'trucks.show', params: { vehicleId: row.vehicle_id } }"
                                    class="fw-semibold text-decoration-none text-brand-primary"
                                >
                                    {{ row.registration_number }}
                                </RouterLink>
                                <span v-if="row.vehicle_type" class="text-muted d-block small">
                                    {{ row.vehicle_type }}
                                </span>
                            </td>
                            <td>{{ row.driver_name || '—' }}</td>
                            <td>
                                <span class="badge" :class="statusBadgeClass(row)">
                                    {{ row.status_label }}
                                </span>
                            </td>
                            <td>
                                <span>{{ row.current_location }}</span>
                            </td>
                            <td>
                                <RouterLink
                                    v-if="row.last_trip_id"
                                    :to="{ name: 'trips.show', params: { id: row.last_trip_id } }"
                                    class="text-decoration-none fw-medium"
                                >
                                    {{ row.last_trip_number }}
                                </RouterLink>
                                <span v-else class="text-muted">{{ row.last_trip_number }}</span>
                            </td>
                            <td class="small">{{ row.last_trip_end }}</td>
                            <td class="small">{{ row.last_trip_route }}</td>
                            <td class="small">{{ row.idle_since }}</td>
                            <td class="text-center">
                                <span
                                    v-if="row.status === 'on_trip'"
                                    class="badge bg-light text-primary border"
                                >
                                    0 d
                                </span>
                                <span
                                    v-else
                                    class="badge"
                                    :class="idleDaysBadgeClass(row.idle_days)"
                                >
                                    {{ row.idle_days }} d
                                </span>
                            </td>
                            <td class="text-end fw-medium">{{ formatNumber(row.trips_count, 0) }}</td>
                            <td class="text-end">{{ formatNumber(row.running_days, 0) }}</td>
                            <td class="text-end">
                                <span class="fw-semibold" :class="utilizationColorClass(row.utilization_rate)">
                                    {{ row.utilization_rate }}%
                                </span>
                            </td>
                            <td class="text-end">{{ formatNumber(row.revenue) }}</td>
                            <td class="text-end text-danger">{{ formatNumber(row.expenses) }}</td>
                            <td
                                class="text-end fw-semibold"
                                :class="row.profit >= 0 ? 'text-success' : 'text-danger'"
                            >
                                {{ formatNumber(row.profit) }}
                            </td>
                            <td class="text-end">{{ formatNumber(row.total_km, 1) }}</td>
                        </tr>
                    </tbody>
                    <tfoot v-if="rows.length > 0 && summary" class="table-light fw-semibold">
                        <tr>
                            <td colspan="10" class="text-end text-uppercase small text-muted">
                                Overall Fleet Totals (Period: {{ summary.total_available_days ?? 0 }} Avail Days):
                            </td>
                            <td class="text-end">{{ formatNumber(summary.total_trips, 0) }}</td>
                            <td class="text-end">{{ formatNumber(summary.total_running_days, 0) }}</td>
                            <td class="text-end text-info">{{ summary.overall_utilization_rate ?? 0 }}%</td>
                            <td class="text-end">{{ formatNumber(summary.total_revenue) }}</td>
                            <td class="text-end text-danger">{{ formatNumber(summary.total_expenses) }}</td>
                            <td
                                class="text-end"
                                :class="(summary.total_profit ?? 0) >= 0 ? 'text-success' : 'text-danger'"
                            >
                                {{ formatNumber(summary.total_profit) }}
                            </td>
                            <td class="text-end">{{ formatNumber(summary.total_km, 1) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <!-- Pagination Footer -->
            <template #footer>
                <AppIndexPartloadPagination
                    :pagination="pagination"
                    :loading="loading"
                    item-label="trucks"
                    aria-label="Vehicle utilization report pagination"
                    @page-change="loadReport"
                />
            </template>
        </AppIndexListCard>
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { RouterLink } from 'vue-router';
import { listAllVehicles } from '../../api/vehicles';
import {
    exportVehicleUtilizationReport,
    fetchVehicleUtilizationReport,
} from '../../api/vehicleUtilizationReport';
import { thisMonthDateRange } from '../../utils/dateRangePresets';
import { formatDate } from '../../utils/datetime';
import AppIndexListCard from '../../components/ui/AppIndexListCard.vue';
import AppReportFiltersPanel from '../../components/ui/AppReportFiltersPanel.vue';
import AppIndexPartloadPagination from '../../components/ui/AppIndexPartloadPagination.vue';
import AppDateInput from '../../components/ui/AppDateInput.vue';
import AppSelect from '../../components/ui/AppSelect.vue';
import VehicleSelect from '../../components/ui/VehicleSelect.vue';
import { useListPagination } from '../../composables/useListPagination';
import { usePermissions } from '../../composables/usePermissions';
import { useRequireCompanyContext } from '../../composables/useRequireCompanyContext';
import { useAppToast } from '../../composables/useAppToast';

const { needsCompanySelection } = useRequireCompanyContext();
const toast = useAppToast();
const { canInCompany } = usePermissions();

const canExport = computed(() => canInCompany('trip_reports.export'));

const rows = ref([]);
const summary = ref(null);
const companyName = ref('');
const period = ref({});
const vehicles = ref([]);
const loading = ref(false);

const { pagination, syncFromMeta, reset: resetPagination } = useListPagination(25);

const statusOptions = [
    { value: 'all', label: 'All Statuses' },
    { value: 'on_trip', label: 'On Trip' },
    { value: 'available', label: 'Available' },
    { value: 'idle', label: 'Idle (4+ Days)' },
    { value: 'maintenance', label: 'Maintenance Due' },
    { value: 'inactive', label: 'Inactive' },
];

const idleThresholdOptions = [
    { value: '', label: 'All Idle Days' },
    { value: '4', label: 'Attention (4+ Days)' },
    { value: '7', label: 'Idle (7+ Days)' },
    { value: '15', label: 'Critical Idle (15+ Days)' },
];

function defaultFilters() {
    const range = thisMonthDateRange(new Date(), { clampToToday: true });
    return {
        from_date: range.from_date,
        to_date: range.to_date,
        vehicle_id: '',
        status: 'all',
        min_idle_days: '',
        search: '',
    };
}

const filters = reactive(defaultFilters());

const periodLabel = computed(() => {
    if (!filters.from_date || !filters.to_date) {
        return '';
    }
    return `${formatDate(filters.from_date)} – ${formatDate(filters.to_date)}`;
});

function formatNumber(value, decimals = 2) {
    if (value === null || value === undefined || value === '') {
        return '—';
    }
    const num = Number(value);
    if (Number.isNaN(num)) {
        return '—';
    }
    return num.toLocaleString('en-IN', {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals,
    });
}

function formatMoney(amount) {
    const n = Number(amount ?? 0);
    return `₹${n.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

function statusBadgeClass(row) {
    switch (row.status_tier) {
        case 'running':
            return 'bg-primary';
        case 'normal':
            return 'bg-success';
        case 'attention':
            return 'bg-warning text-dark';
        case 'prolonged':
            return 'bg-orange text-white';
        case 'critical':
            return 'bg-danger';
        case 'maintenance':
            return 'bg-warning text-dark border border-warning';
        case 'inactive':
            return 'bg-secondary';
        default:
            return 'bg-secondary';
    }
}

function idleDaysBadgeClass(days) {
    if (days <= 3) {
        return 'bg-success-subtle text-success border border-success-subtle';
    }
    if (days <= 7) {
        return 'bg-warning-subtle text-warning-emphasis border border-warning-subtle';
    }
    if (days <= 15) {
        return 'bg-orange-subtle text-orange border';
    }
    return 'bg-danger text-white';
}

function utilizationColorClass(rate) {
    if (rate >= 70) {
        return 'text-success';
    }
    if (rate >= 40) {
        return 'text-primary';
    }
    if (rate > 0) {
        return 'text-warning';
    }
    return 'text-muted';
}

async function loadVehicles() {
    try {
        vehicles.value = await listAllVehicles();
    } catch {
        vehicles.value = [];
    }
}

async function loadReport(page = 1) {
    loading.value = true;
    try {
        const payload = await fetchVehicleUtilizationReport(
            {
                from_date: filters.from_date || undefined,
                to_date: filters.to_date || undefined,
                vehicle_id: filters.vehicle_id || undefined,
                status: filters.status && filters.status !== 'all' ? filters.status : undefined,
                min_idle_days: filters.min_idle_days || undefined,
                search: filters.search ? filters.search.trim() : undefined,
            },
            {
                page,
                per_page: pagination.perPage,
            }
        );

        rows.value = payload.rows ?? [];
        summary.value = payload.summary ?? {};
        companyName.value = payload.company_name ?? '';
        period.value = payload.period ?? {};

        if (payload.meta) {
            syncFromMeta(payload.meta);
        }
    } catch (err) {
        toast.error(err?.response?.data?.message || 'Failed to load vehicle utilization report.');
        rows.value = [];
        summary.value = null;
    } finally {
        loading.value = false;
    }
}

function clearFilters() {
    Object.assign(filters, defaultFilters());
    resetPagination();
    loadReport(1);
}

function exportExcel() {
    try {
        exportVehicleUtilizationReport({
            from_date: filters.from_date || undefined,
            to_date: filters.to_date || undefined,
            vehicle_id: filters.vehicle_id || undefined,
            status: filters.status && filters.status !== 'all' ? filters.status : undefined,
            min_idle_days: filters.min_idle_days || undefined,
            search: filters.search ? filters.search.trim() : undefined,
        });
    } catch (err) {
        toast.error('Failed to initiate Excel export.');
    }
}

onMounted(() => {
    loadVehicles();
    loadReport(1);
});
</script>

<style scoped>
.bg-orange {
    background-color: #f97316 !important;
}
.bg-orange-subtle {
    background-color: #ffedd5 !important;
}
.text-orange {
    color: #c2410c !important;
}
.report-table th,
.report-table td {
    white-space: nowrap;
    font-size: 0.84rem;
}
</style>
