<template>
    <div class="container mt-4">
        <h3 class="mb-3">Tyre Reports</h3>

        <div v-if="needsCompanySelection" class="alert alert-warning">
            Select a company and click <strong>Enter</strong> to view tyre reports.
        </div>

        <div class="card mb-3">
            <div class="card-body row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label">Report</label>
                    <select v-model="reportType" class="form-select">
                        <option v-for="r in reportTypes" :key="r.value" :value="r.value">{{ r.label }}</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">From</label>
                    <input v-model="filters.from" type="date" class="form-control" />
                </div>
                <div class="col-md-2">
                    <label class="form-label">To</label>
                    <input v-model="filters.to" type="date" class="form-control" />
                </div>
                <div class="col-md-2">
                    <button type="button" class="btn btn-primary w-100" @click="loadReport">Apply</button>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">{{ currentLabel }}</div>
            <div class="card-body table-responsive">
                <div v-if="loading" class="text-muted text-center py-4">Loading…</div>
                <table v-else class="table table-bordered table-striped table-sm">
                    <thead v-if="columns.length">
                        <tr>
                            <th v-for="col in columns" :key="col">{{ col }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="rows.length === 0">
                            <td class="text-center text-muted">No data.</td>
                        </tr>
                        <tr v-for="(row, i) in rows" :key="i">
                            <td v-for="col in columns" :key="col">{{ row[col] ?? '—' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import {
    fetchTyreCostPerKmReport,
    fetchTyreExpenseSummary,
    fetchTyreLifeReport,
    fetchTyreReplacementsReport,
    fetchTyreRetreadHistoryReport,
    fetchTyreVehicleWiseReport,
} from '../../api/tyreReports';
import { useRequireCompanyContext } from '../../composables/useRequireCompanyContext';

const { needsCompanySelection } = useRequireCompanyContext();

const reportTypes = [
    { value: 'life', label: 'Tyre Life Report', loader: fetchTyreLifeReport },
    { value: 'cost', label: 'Cost per KM', loader: fetchTyreCostPerKmReport },
    { value: 'replacements', label: 'Replacement Report', loader: fetchTyreReplacementsReport },
    { value: 'retread', label: 'Retread History', loader: fetchTyreRetreadHistoryReport },
    { value: 'vehicle', label: 'Vehicle-wise Usage', loader: fetchTyreVehicleWiseReport },
    { value: 'expenses', label: 'Expense Summary', loader: fetchTyreExpenseSummary },
];

const reportType = ref('life');
const filters = reactive({ from: '', to: '' });
const rows = ref([]);
const loading = ref(false);

const currentLabel = computed(() => reportTypes.find((r) => r.value === reportType.value)?.label ?? 'Report');

const columns = computed(() => {
    if (rows.value.length === 0) {
        return [];
    }
    return Object.keys(rows.value[0]);
});

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

onMounted(() => loadReport());
</script>
