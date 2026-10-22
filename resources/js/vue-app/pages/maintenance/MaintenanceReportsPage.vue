<template>
    <div class="container mt-4 app-index-page report-index-page">
        <div v-if="needsCompanySelection" class="alert alert-warning">
            Select a company and click <strong>Enter</strong> to view maintenance reports.
        </div>

        <div v-if="formError" class="alert alert-danger">{{ formError }}</div>

        <AppIndexListCard title="Maintenance Reports">
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

            <AppReportFiltersPanel :loading="loading" @search="loadReport(1)" @reset="clearFilters">
                <div>
                    <label class="form-label">Report</label>
                    <AppSelect
                        v-model="reportType"
                        :options="reportTypes"
                        placeholder="Select report"
                        search-placeholder="Search report…"
                        @change="clearErrors"
                    />
                </div>
                <div>
                    <label class="form-label">From</label>
                    <AppDateInput
                        v-model="filters.from"
                        :max="todayMax"
                        :invalid="!!fieldErrors.from"
                        @input="onFromChange"
                    />
                    <div v-if="fieldErrors.from" class="invalid-feedback d-block">
                        {{ fieldErrors.from[0] }}
                    </div>
                </div>
                <div>
                    <label class="form-label">To</label>
                    <AppDateInput
                        v-model="filters.to"
                        :min="filters.from || undefined"
                        :max="filtersToMax"
                        :invalid="!!fieldErrors.to"
                        @input="clearFieldError('to')"
                    />
                    <div v-if="fieldErrors.to" class="invalid-feedback d-block">
                        {{ fieldErrors.to[0] }}
                    </div>
                  
                </div>
                <div v-if="reportType === 'cost'">
                    <label class="form-label" :for="`${pageId}_service_type`">Service Type</label>
                    <MaintenanceServiceTypeSelect
                        :input-id="`${pageId}_service_type`"
                        v-model="filters.maintenance_service_type_id"
                        :service-types="serviceTypes"
                        placeholder="All types"
                    />
                </div>
                <div v-if="reportType === 'truck'">
                    <label class="form-label" :for="`${pageId}_vehicle`">Truck</label>
                    <VehicleSelect
                        :input-id="`${pageId}_vehicle`"
                        v-model="filters.vehicle_id"
                        :vehicles="vehicles"
                        placeholder="All trucks"
                    />
                </div>
            </AppReportFiltersPanel>

            <div class="report-results-meta px-3 py-2 border-bottom">
                <span class="report-results-meta__title">{{ currentLabel }}</span>
            </div>

            <div v-if="loading" class="text-muted text-center py-4">Loading…</div>
            <table v-else class="table table-striped table-hover align-middle mb-0 report-table">
                <thead v-if="columns.length" class="table-light">
                    <tr>
                        <th v-for="col in columns" :key="col">{{ formatColumn(col) }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="rows.length === 0">
                        <td :colspan="columns.length || 1" class="text-center text-muted py-4">
                            {{ emptyTableMessage }}
                        </td>
                    </tr>
                    <tr
                        v-for="(row, index) in displayRows"
                        :key="index"
                        :class="{ 'table-secondary fw-semibold': row.service_type === '_grand_total' }"
                    >
                        <td v-for="col in columns" :key="col">{{ formatCell(row, col, index) }}</td>
                    </tr>
                </tbody>
            </table>

            <template #footer>
                <AppIndexPartloadPagination
                    :pagination="pagination"
                    :loading="loading"
                    item-label="records"
                    aria-label="Maintenance reports pagination"
                    @page-change="loadReport"
                />
            </template>
        </AppIndexListCard>
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { listMaintenanceServiceTypes } from '../../api/maintenanceServiceTypes';
import { exportMaintenanceReport, fetchMaintenanceCostSummary, fetchMaintenanceTruckWiseReport } from '../../api/maintenanceReports';
import { withReportPagination } from '../../api/reportPagination';
import { listAllVehicles } from '../../api/vehicles';
import AppIndexListCard from '../../components/ui/AppIndexListCard.vue';
import AppReportFiltersPanel from '../../components/ui/AppReportFiltersPanel.vue';
import AppSelect from '../../components/ui/AppSelect.vue';
import AppIndexPartloadPagination from '../../components/ui/AppIndexPartloadPagination.vue';
import MaintenanceServiceTypeSelect from '../../components/ui/MaintenanceServiceTypeSelect.vue';
import VehicleSelect from '../../components/ui/VehicleSelect.vue';
import { useApiFormErrors } from '../../composables/useApiFormErrors';
import { useListPagination } from '../../composables/useListPagination';
import { usePermissions } from '../../composables/usePermissions';
import { useRequireCompanyContext } from '../../composables/useRequireCompanyContext';
import {
    dateRangeExceedsMonths,
    dateRangeMonthsError,
    FUTURE_DATE_ERROR,
    MAX_LIST_DATE_RANGE_MONTHS,
    maxDateRangeEnd,
    TO_DATE_BEFORE_FROM_ERROR,
} from '../../utils/dateRangePresets';
import { formatTableCell, toDateInputValue } from '../../utils/datetime';

const { needsCompanySelection } = useRequireCompanyContext();
const { canInCompany } = usePermissions();
const canExport = computed(() => canInCompany('maintenance_reports.export'));

const MAX_REPORT_DATE_RANGE_MONTHS = MAX_LIST_DATE_RANGE_MONTHS;
const todayMax = computed(() => toDateInputValue());
const { fieldErrors, formError, clearErrors, clearFieldError, applyApiError } = useApiFormErrors();

const pageId = 'maintenanceReports';
const reportTypes = [
    { value: 'cost', label: 'Maintenance Cost Report', loader: fetchMaintenanceCostSummary },
    { value: 'truck', label: 'Truck-wise Maintenance Cost', loader: fetchMaintenanceTruckWiseReport },
];

const serviceTypes = ref([]);
const reportType = ref('cost');
const filters = reactive({ from: '', to: '', maintenance_service_type_id: '', vehicle_id: '' });
const vehicles = ref([]);
const rows = ref([]);
const grandTotalRow = ref(null);
const loading = ref(false);
const reportLoaded = ref(false);
const { pagination, syncFromMeta, reset: resetPagination } = useListPagination(10);

const filtersToMax = computed(() => {
    if (!filters.from) {
        return todayMax.value;
    }

    const rangeEnd = maxDateRangeEnd(filters.from, MAX_REPORT_DATE_RANGE_MONTHS);

    return rangeEnd > todayMax.value ? todayMax.value : rangeEnd;
});

const currentLabel = computed(() => reportTypes.find((report) => report.value === reportType.value)?.label ?? 'Report');

const emptyTableMessage = computed(() => {
    if (formError.value) {
        return 'Fix the filters above and try again.';
    }

    return reportLoaded.value ? 'No data.' : 'Select date range and click search.';
});

const COLUMN_LABELS = {
    s_no: 'S.No',
    service_type_label: 'Service Type',
    service_count: 'Count',
    total_cost: 'Total Cost',
    truck: 'Truck',
    last_service_date: 'Last Service',
    odometer_km: 'Meter Reading',
    next_service_due_km: 'Next Due KM',
    next_service_due_date: 'Next Due Date',
    overdue_reason: 'Overdue Reason',
};

const HIDDEN_COLUMNS = ['service_type', 'vehicle_id', 'maintenance_service_type_id'];

const columns = computed(() => {
    const source = displayRows.value;

    if (source.length === 0) {
        return [];
    }

    return Object.keys(source[0]).filter((key) => !HIDDEN_COLUMNS.includes(key));
});

const displayRows = computed(() => {
    if (grandTotalRow.value) {
        return [...rows.value, grandTotalRow.value];
    }

    return rows.value;
});

function formatColumn(key) {
    return COLUMN_LABELS[key] ?? key.replaceAll('_', ' ').replace(/\b\w/g, (char) => char.toUpperCase());
}

function formatCell(row, col, index) {
    if (col === 's_no' && row.service_type !== '_grand_total') {
        return serialNumber(index);
    }

    if (col === 'total_cost') {
        return new Intl.NumberFormat('en-IN', {
            style: 'currency',
            currency: 'INR',
            maximumFractionDigits: 2,
        }).format(Number(row[col] ?? 0));
    }

    return formatTableCell(col, row[col]);
}

function serialNumber(rowIndex) {
    if (pagination.from > 0) {
        return pagination.from + rowIndex;
    }

    return rowIndex + 1;
}

function validateFilters() {
    const errors = {};

    if (!filters.from) {
        errors.from = ['Date range is required.'];
    }

    if (!filters.to) {
        errors.to = ['Date range is required.'];
    }

    if (filters.from && filters.to && filters.from > filters.to) {
        errors.to = [TO_DATE_BEFORE_FROM_ERROR];
    }

    if (filters.from && filters.from > todayMax.value) {
        errors.from = [FUTURE_DATE_ERROR];
    }

    if (filters.to && filters.to > todayMax.value) {
        errors.to = [FUTURE_DATE_ERROR];
    }

    if (dateRangeExceedsMonths(filters.from, filters.to, MAX_REPORT_DATE_RANGE_MONTHS)) {
        errors.to = [dateRangeMonthsError(MAX_REPORT_DATE_RANGE_MONTHS)];
    }

    if (Object.keys(errors).length > 0) {
        fieldErrors.value = errors;
        formError.value = errors.to?.[0] ?? errors.from?.[0] ?? 'Date range is required.';

        return false;
    }

    return true;
}

async function loadReport(page = 1) {
    if (needsCompanySelection.value) {
        rows.value = [];
        grandTotalRow.value = null;
        reportLoaded.value = false;
        resetPagination();

        return;
    }

    clearErrors();

    if (!validateFilters()) {
        rows.value = [];
        grandTotalRow.value = null;
        reportLoaded.value = false;
        resetPagination();

        return;
    }

    const report = reportTypes.find((item) => item.value === reportType.value);

    if (!report) {
        return;
    }

    loading.value = true;

    try {
        const result = await report.loader(withReportPagination(buildFilterParams(), page, pagination.perPage));

        rows.value = result.rows;
        grandTotalRow.value = result.grandTotal ?? null;
        syncFromMeta(result.meta, result.rows.length);
        reportLoaded.value = true;
    } catch (error) {
        rows.value = [];
        grandTotalRow.value = null;
        reportLoaded.value = false;
        resetPagination();
        applyApiError(error, 'Unable to load report.');
    } finally {
        loading.value = false;
    }
}

function clearFilters() {
    filters.from = '';
    filters.to = '';
    filters.maintenance_service_type_id = '';
    filters.vehicle_id = '';
    rows.value = [];
    grandTotalRow.value = null;
    reportLoaded.value = false;
    resetPagination();
    clearErrors();
}

function buildFilterParams() {
    return {
        from: filters.from,
        to: filters.to,
        ...(filters.maintenance_service_type_id
            ? { maintenance_service_type_id: filters.maintenance_service_type_id }
            : {}),
        ...(filters.vehicle_id ? { vehicle_id: filters.vehicle_id } : {}),
    };
}

function exportExcel() {
    if (!validateFilters()) {
        return;
    }

    exportMaintenanceReport(reportType.value, buildFilterParams());
}

function onFromChange() {
    clearFieldError('from');

    if (filters.to && filters.to > filtersToMax.value) {
        filters.to = filtersToMax.value;
    }
}

watch(reportType, () => {
    rows.value = [];
    grandTotalRow.value = null;
    reportLoaded.value = false;
    resetPagination();
    clearErrors();
});

onMounted(async () => {
    try {
        const [vehicleList, typeList] = await Promise.all([listAllVehicles(), listMaintenanceServiceTypes()]);

        vehicles.value = vehicleList;
        serviceTypes.value = typeList;
    } catch {
        vehicles.value = [];
        serviceTypes.value = [];
    }
});
</script>
