<template>
    <div class="container mt-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <h3 class="mb-0">Trip Reports</h3>
            <AppButton
                v-if="canExport"
                icon="bi bi-file-earmark-excel"
                label="Export Excel"
                outline
                @click="exportExcel"
            />
        </div>

        <div v-if="needsCompanySelection" class="alert alert-warning">
            Select a company from
            <RouterLink :to="{ name: 'companies.index' }" class="alert-link">Company List</RouterLink>
            and click <strong>Enter</strong> to view trip reports.
        </div>

        <div v-if="flash.error" class="alert alert-danger">{{ flash.error }}</div>

        <div class="card mb-3">
            <div class="card-body row g-3">
                <div class="col-md-4">
                    <label class="form-label">Report Type</label>
                    <select v-model="reportType" class="form-select">
                        <option v-for="t in reportTypes" :key="t.value" :value="t.value">{{ t.label }}</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">From Date</label>
                    <input v-model="filters.from_date" type="date" class="form-control" />
                </div>
                <div class="col-md-2">
                    <label class="form-label">To Date</label>
                    <input v-model="filters.to_date" type="date" class="form-control" />
                </div>
                <div class="col-md-2">
                    <label class="form-label">Vehicle</label>
                    <select v-model="filters.vehicle_id" class="form-select">
                        <option value="">All</option>
                        <option v-for="v in vehicles" :key="v.id" :value="v.id">{{ v.registration_number }}</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="button" class="btn btn-primary w-100" @click="loadReport">Apply</button>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title mb-0">{{ currentReportLabel }}</h3>
            </div>
            <div class="card-body table-responsive">
                <div v-if="loading" class="text-center py-4 text-muted">Loading report…</div>
                <table v-else-if="columns.length" class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th v-for="col in columns" :key="col">{{ formatColumn(col) }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="rows.length === 0">
                            <td :colspan="columns.length" class="text-center text-muted">No data for selected filters.</td>
                        </tr>
                        <tr v-for="(row, index) in rows" :key="index">
                            <td v-for="col in columns" :key="col">{{ formatCell(row[col]) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { listAllVehicles } from '../../api/vehicles';
import { thisMonthDateRange } from '../../api/trips';
import { TRIP_REPORT_TYPES, exportTripReport, fetchTripReport } from '../../api/tripReports';
import AppButton from '../../components/ui/AppButton.vue';
import { usePermissions } from '../../composables/usePermissions';
import { useRequireCompanyContext } from '../../composables/useRequireCompanyContext';

const { needsCompanySelection } = useRequireCompanyContext();
const { canInCompany } = usePermissions();
const canExport = computed(() => canInCompany('trip_reports.export'));

const reportTypes = TRIP_REPORT_TYPES;
const reportType = ref('profitability');
const vehicles = ref([]);
const rows = ref([]);
const loading = ref(false);
const flash = reactive({ error: '' });

const filters = reactive({
    ...thisMonthDateRange(),
    vehicle_id: '',
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
        rows.value = await fetchTripReport(reportType.value, buildFilterParams());
    } catch (error) {
        flash.error = error?.response?.data?.message ?? 'Failed to load trip report.';
        rows.value = [];
    } finally {
        loading.value = false;
    }
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
