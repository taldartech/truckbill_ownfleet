<template>
    <div class="container mt-4 app-index-page">
        <AppIndexPageHeader :title="pageTitle">
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
            and click <strong>Enter</strong> to view the monthly fleet report.
        </div>

        <div v-if="flash.error" class="alert alert-danger">{{ flash.error }}</div>

        <AppIndexFiltersCard :active="filtersActive" @apply="loadReport" @clear="clearFilters">
            <div>
                <label class="form-label">Month</label>
                <input v-model="filters.month" type="month" class="form-control" />
            </div>
        </AppIndexFiltersCard>

        <div class="card app-index-list-card">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                <h3 class="card-title mb-0">Truck-wise Summary</h3>
                <span v-if="companyName" class="text-muted small">{{ companyName }}</span>
            </div>
            <div class="card-body">
                <div v-if="loading" class="text-center py-4 text-muted">Loading report…</div>
                <div v-else class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>S.No</th>
                                <th>Truck No.</th>
                                <th>Driver</th>
                                <th class="text-end">Trips</th>
                                <th class="text-end">K.M.</th>
                                <th class="text-end">Diesel (L)</th>
                                <th class="text-end">Average</th>
                                <th class="text-end">Total Exp.</th>
                                <th class="text-end">Tyres Used</th>
                                <th class="text-end">Tyre Exp. (₹)</th>
                                <th class="text-end">Saving</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="rows.length === 0">
                                <td colspan="11" class="text-center text-muted py-4">
                                    No trucks found for this company.
                                </td>
                            </tr>
                            <tr v-for="row in rows" :key="row.s_no">
                                <td>{{ row.s_no }}</td>
                                <td>{{ row.registration_number }}</td>
                                <td>{{ row.driver_name || '—' }}</td>
                                <td class="text-end">{{ formatNumber(row.trip_count, 0) }}</td>
                                <td class="text-end">{{ formatNumber(row.total_km) }}</td>
                                <td class="text-end">{{ formatNumber(row.diesel_liters) }}</td>
                                <td class="text-end">{{ formatNumber(row.average_km_per_liter) }}</td>
                                <td class="text-end">{{ formatNumber(row.total_expense) }}</td>
                                <td class="text-end">{{ formatNumber(row.tyres_used, 0) }}</td>
                                <td class="text-end">{{ formatNumber(row.tyre_expense) }}</td>
                                <td class="text-end">{{ formatNumber(row.saving) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div v-if="!loading && summary" class="card app-index-list-card mt-3">
            <div class="card-header">
                <h3 class="card-title mb-0">Month Summary</h3>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <tbody>
                            <tr>
                                <th scope="row" class="w-25">Total Bill</th>
                                <td class="text-end">{{ formatNumber(summary.total_bill) }}</td>
                                <th scope="row" class="w-25">Total Saving</th>
                                <td class="text-end">{{ formatNumber(summary.total_saving) }}</td>
                            </tr>
                            <tr>
                                <th scope="row">RTO Entry</th>
                                <td class="text-end">{{ formatNumber(summary.rto_entry) }}</td>
                                <th scope="row">Tyre Bill</th>
                                <td class="text-end">{{ formatNumber(summary.tyre_bill) }}</td>
                            </tr>
                            <tr>
                                <th scope="row">Challan RTO</th>
                                <td class="text-end">{{ formatNumber(summary.challan_rto) }}</td>
                                <th scope="row">Total Tyres Used</th>
                                <td class="text-end">{{ formatNumber(summary.total_tyres_used, 0) }}</td>
                            </tr>
                            <tr>
                                <th scope="row">Net Saving</th>
                                <td class="text-end" colspan="3">{{ formatNumber(summary.net_saving) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { RouterLink } from 'vue-router';
import {
    currentMonthValue,
    exportFleetMonthlyReport,
    fetchFleetMonthlyReport,
} from '../../api/fleetMonthlyReport';
import AppButton from '../../components/ui/AppButton.vue';
import AppIndexFiltersCard from '../../components/ui/AppIndexFiltersCard.vue';
import AppIndexPageHeader from '../../components/ui/AppIndexPageHeader.vue';
import { usePermissions } from '../../composables/usePermissions';
import { useRequireCompanyContext } from '../../composables/useRequireCompanyContext';
import { formatMonthYear } from '../../utils/datetime';

const { needsCompanySelection } = useRequireCompanyContext();
const { canInCompany } = usePermissions();
const canExport = computed(() => canInCompany('fleet_monthly_report.export'));

const rows = ref([]);
const summary = ref(null);
const companyName = ref('');
const loading = ref(false);
const flash = reactive({ error: '' });

function defaultFilters() {
    return {
        month: currentMonthValue(),
    };
}

const filters = reactive(defaultFilters());

const filtersActive = computed(() => filters.month !== defaultFilters().month);

const pageTitle = computed(() => {
    if (!filters.month) {
        return 'Monthly Fleet Report';
    }

    const [year, month] = filters.month.split('-');

    return `Monthly Fleet Report — ${formatMonthYear(`${year}-${month}`)}`;
});

function formatNumber(value, maximumFractionDigits = 2) {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    return Number(value).toLocaleString('en-IN', { maximumFractionDigits });
}

async function loadReport() {
    if (needsCompanySelection.value || !filters.month) {
        return;
    }

    loading.value = true;
    flash.error = '';

    try {
        const data = await fetchFleetMonthlyReport(filters.month);
        rows.value = data.rows ?? [];
        summary.value = data.summary ?? null;
        companyName.value = data.company_name ?? '';
    } catch (error) {
        flash.error = error?.response?.data?.message ?? 'Failed to load monthly fleet report.';
        rows.value = [];
        summary.value = null;
        companyName.value = '';
    } finally {
        loading.value = false;
    }
}

function clearFilters() {
    Object.assign(filters, defaultFilters());
    loadReport();
}

function exportExcel() {
    if (!filters.month) {
        return;
    }

    exportFleetMonthlyReport(filters.month);
}

onMounted(() => {
    loadReport();
});
</script>
