<template>
    <div class="container mt-4 app-index-page">
        <AppIndexPageHeader title="Tyre Reports" />

        <div v-if="needsCompanySelection" class="alert alert-warning">
            Select a company and click <strong>Enter</strong> to view tyre reports.
        </div>

        <AppIndexFiltersCard :active="filtersActive" @apply="loadReport" @clear="clearFilters">
            <div>
                <label class="form-label">Report</label>
                <select v-model="reportType" class="form-select">
                    <option v-for="r in reportTypes" :key="r.value" :value="r.value">{{ r.label }}</option>
                </select>
            </div>
            <div>
                <label class="form-label">From</label>
                <input v-model="filters.from" type="date" class="form-control" />
            </div>
            <div>
                <label class="form-label">To</label>
                <input v-model="filters.to" type="date" class="form-control" />
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
                                <td :colspan="columns.length || 1" class="text-center text-muted py-4">No data.</td>
                            </tr>
                            <tr v-for="(row, i) in rows" :key="i">
                                <td v-for="col in columns" :key="col">{{ formatCell(row, col, i) }}</td>
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
import {
    fetchTyreCostPerKmReport,
    fetchTyreExpenseSummary,
    fetchTyreLifeReport,
    fetchTyreReplacementsReport,
    fetchTyreRetreadHistoryReport,
    fetchTyreVehicleWiseReport,
} from '../../api/tyreReports';
import AppIndexFiltersCard from '../../components/ui/AppIndexFiltersCard.vue';
import AppIndexPageHeader from '../../components/ui/AppIndexPageHeader.vue';
import { useRequireCompanyContext } from '../../composables/useRequireCompanyContext';

const { needsCompanySelection } = useRequireCompanyContext();

const reportTypes = [
    { value: 'life', label: 'Tyre Life Report', loader: fetchTyreLifeReport },
    { value: 'cost', label: 'Cost per KM', loader: fetchTyreCostPerKmReport },
    { value: 'replacements', label: 'Replacement Report', loader: fetchTyreReplacementsReport },
    { value: 'retread', label: 'Retread History', loader: fetchTyreRetreadHistoryReport },
    { value: 'vehicle', label: 'Truck-wise Usage', loader: fetchTyreVehicleWiseReport },
    { value: 'expenses', label: 'Expense Summary', loader: fetchTyreExpenseSummary },
];

const reportType = ref('life');
const filters = reactive({ from: '', to: '' });
const rows = ref([]);
const loading = ref(false);

const filtersActive = computed(() => filters.from !== '' || filters.to !== '');

const currentLabel = computed(() => reportTypes.find((r) => r.value === reportType.value)?.label ?? 'Report');

const COLUMN_LABELS = {
    s_no: 'S.No',
    vehicle: 'Truck',
    vehicle_id: 'Truck ID',
};

const HIDDEN_COLUMNS = ['tyre_id'];

const columns = computed(() => {
    if (rows.value.length === 0) {
        return [];
    }

    const keys = Object.keys(rows.value[0]).filter((key) => !HIDDEN_COLUMNS.includes(key));

    return ['s_no', ...keys];
});

function formatColumn(key) {
    if (COLUMN_LABELS[key]) {
        return COLUMN_LABELS[key];
    }

    return key.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
}

function formatCell(row, col, rowIndex) {
    if (col === 's_no') {
        return rowIndex + 1;
    }

    return row[col] ?? '—';
}

async function loadReport() {
    if (needsCompanySelection.value) {
        return;
    }

    loading.value = true;

    try {
        const def = reportTypes.find((r) => r.value === reportType.value);
        const params = {};

        if (filters.from) {
            params.from = filters.from;
        }

        if (filters.to) {
            params.to = filters.to;
        }

        const data = await def.loader(params);

        if (reportType.value === 'expenses') {
            rows.value = Object.entries(data).map(([expense_type, total]) => ({ expense_type, total }));
        } else {
            rows.value = data;
        }
    } finally {
        loading.value = false;
    }
}

function clearFilters() {
    filters.from = '';
    filters.to = '';
    loadReport();
}

watch(reportType, () => {
    loadReport();
});

onMounted(() => loadReport());
</script>
