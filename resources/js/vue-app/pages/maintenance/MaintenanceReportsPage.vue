<template>
    <div class="container mt-4 app-index-page">
        <AppIndexPageHeader title="Maintenance Reports" />

        <div v-if="needsCompanySelection" class="alert alert-warning">
            Select a company and click <strong>Enter</strong> to view maintenance reports.
        </div>

        <div v-if="formError" class="alert alert-danger">{{ formError }}</div>

        <AppIndexFiltersCard :active="filtersActive" @apply="loadReport" @clear="clearFilters">
            <div>
                <label class="form-label">Report</label>
                <select v-model="reportType" class="form-select" @change="clearErrors">
                    <option v-for="report in reportTypes" :key="report.value" :value="report.value">
                        {{ report.label }}
                    </option>
                </select>
            </div>
            <div v-if="reportType !== 'due'">
                <label class="form-label">From</label>
                <AppDateInput
                    v-model="filters.from"
                    :invalid="!!fieldErrors.from"
                    @input="clearFieldError('from')"
                />
                <div v-if="fieldErrors.from" class="invalid-feedback d-block">
                    {{ fieldErrors.from[0] }}
                </div>
            </div>
            <div v-if="reportType !== 'due'">
                <label class="form-label">To</label>
                <AppDateInput
                    v-model="filters.to"
                    :invalid="!!fieldErrors.to"
                    @input="clearFieldError('to')"
                />
                <div v-if="fieldErrors.to" class="invalid-feedback d-block">
                    {{ fieldErrors.to[0] }}
                </div>
            </div>
            <div v-if="reportType === 'cost'">
                <label class="form-label">Service Type</label>
                <select v-model="filters.maintenance_service_type_id" class="form-select">
                    <option value="">All types</option>
                    <option v-for="type in serviceTypeOptions" :key="type.value" :value="type.value">
                        {{ type.label }}
                    </option>
                </select>
            </div>
            <div v-if="reportType === 'truck'">
                <label class="form-label" :for="`${pageId}_vehicle`">Truck</label>
                <SearchableSelect
                    :input-id="`${pageId}_vehicle`"
                    v-model="filters.vehicle_id"
                    :items="vehicleSelectOptions"
                    placeholder="All trucks"
                    search-placeholder="Search truck number…"
                    empty-message="No truck found."
                    item-icon-class="bi bi-truck"
                    attach-dropdown-to-body
                />
            </div>
        </AppIndexFiltersCard>

        <div class="card app-index-list-card">
            <div class="card-header">
                <h3 class="card-title mb-0">{{ currentLabel }}</h3>
            </div>
            <div class="card-body">
                <div v-if="loading" class="text-muted text-center py-4">Loading…</div>
                <div v-else class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
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
                                v-for="(row, index) in rows"
                                :key="index"
                                :class="{ 'table-secondary fw-semibold': row.service_type === '_grand_total' }"
                            >
                                <td v-for="col in columns" :key="col">{{ formatCell(row, col, index) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { buildMaintenanceServiceTypeSelectOptions, listMaintenanceServiceTypes } from '../../api/maintenanceServiceTypes';
import {
    fetchMaintenanceCostSummary,
    fetchMaintenanceDueServicesReport,
    fetchMaintenanceTruckWiseReport,
} from '../../api/maintenanceReports';
import { buildVehicleSelectOptions, listAllVehicles } from '../../api/vehicles';
import AppIndexFiltersCard from '../../components/ui/AppIndexFiltersCard.vue';
import AppIndexPageHeader from '../../components/ui/AppIndexPageHeader.vue';
import SearchableSelect from '../../components/ui/SearchableSelect.vue';
import { useApiFormErrors } from '../../composables/useApiFormErrors';
import { useRequireCompanyContext } from '../../composables/useRequireCompanyContext';
import { formatTableCell } from '../../utils/datetime';

const { needsCompanySelection } = useRequireCompanyContext();
const { fieldErrors, formError, clearErrors, clearFieldError, applyApiError } = useApiFormErrors();

const pageId = 'maintenanceReports';
const reportTypes = [
    { value: 'cost', label: 'Maintenance Cost Report', loader: fetchMaintenanceCostSummary },
    { value: 'truck', label: 'Truck-wise Maintenance Cost', loader: fetchMaintenanceTruckWiseReport },
    { value: 'due', label: 'Due Services Report', loader: fetchMaintenanceDueServicesReport },
];

const serviceTypes = ref([]);
const serviceTypeOptions = computed(() => buildMaintenanceServiceTypeSelectOptions(serviceTypes.value));
const reportType = ref('cost');
const filters = reactive({ from: '', to: '', maintenance_service_type_id: '', vehicle_id: '' });
const vehicles = ref([]);
const vehicleSelectOptions = computed(() => buildVehicleSelectOptions(vehicles.value));
const rows = ref([]);
const loading = ref(false);
const reportLoaded = ref(false);

const filtersActive = computed(() =>
    reportType.value === 'due'
        ? false
        : filters.from !== '' || filters.to !== '' || filters.maintenance_service_type_id !== '' || filters.vehicle_id !== '',
);

const currentLabel = computed(() => reportTypes.find((report) => report.value === reportType.value)?.label ?? 'Report');

const emptyTableMessage = computed(() => {
    if (formError.value) {
        return 'Fix the filters above and try again.';
    }

    if (reportType.value === 'due') {
        return reportLoaded.value ? 'No data.' : 'Click search to load due services.';
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
    if (rows.value.length === 0) {
        return [];
    }

    return Object.keys(rows.value[0]).filter((key) => !HIDDEN_COLUMNS.includes(key));
});

function formatColumn(key) {
    return COLUMN_LABELS[key] ?? key.replaceAll('_', ' ').replace(/\b\w/g, (char) => char.toUpperCase());
}

function formatCell(row, col, index) {
    if (col === 's_no') {
        return index + 1;
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

function validateFilters() {
    if (reportType.value === 'due') {
        return true;
    }

    const errors = {};

    if (!filters.from) {
        errors.from = ['Date range is required.'];
    }

    if (!filters.to) {
        errors.to = ['Date range is required.'];
    }

    if (filters.from && filters.to && filters.from > filters.to) {
        errors.to = ['End date must be on or after start date.'];
    }

    if (Object.keys(errors).length > 0) {
        fieldErrors.value = errors;
        formError.value = errors.to?.[0] ?? errors.from?.[0] ?? 'Date range is required.';

        return false;
    }

    return true;
}

async function loadReport() {
    if (needsCompanySelection.value) {
        rows.value = [];
        reportLoaded.value = false;

        return;
    }

    clearErrors();

    if (!validateFilters()) {
        rows.value = [];
        reportLoaded.value = false;

        return;
    }

    const report = reportTypes.find((item) => item.value === reportType.value);

    if (!report) {
        return;
    }

    loading.value = true;

    try {
        const params =
            reportType.value === 'due'
                ? {}
                : {
                      from: filters.from,
                      to: filters.to,
                      ...(filters.maintenance_service_type_id
                          ? { maintenance_service_type_id: filters.maintenance_service_type_id }
                          : {}),
                      ...(filters.vehicle_id ? { vehicle_id: filters.vehicle_id } : {}),
                  };

        rows.value = await report.loader(params);
        reportLoaded.value = true;
    } catch (error) {
        rows.value = [];
        reportLoaded.value = false;
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
    reportLoaded.value = false;
    clearErrors();
}

watch(reportType, () => {
    rows.value = [];
    reportLoaded.value = false;
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
