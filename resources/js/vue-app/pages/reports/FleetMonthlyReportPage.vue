<template>
    <div class="container mt-4 app-index-page report-index-page">
        <div v-if="needsCompanySelection" class="alert alert-warning">
            Select a company from
            <RouterLink :to="{ name: 'companies.index' }" class="alert-link">Company List</RouterLink>
            and click <strong>Enter</strong> to view the monthly fleet report.
        </div>

        <AppIndexListCard :title="pageTitle">
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
                        <label class="form-label">Month</label>
                        <input v-model="filters.month" type="month" class="form-control" />
                    </div>
                </AppReportFiltersPanel>

                <div
                    class="report-results-meta d-flex justify-content-between align-items-center flex-wrap gap-2 px-3 py-2 border-bottom"
                >
                    <span class="report-results-meta__title">Truck-wise Summary</span>
                    <span v-if="companyName" class="text-muted small">{{ companyName }}</span>
                </div>
            </template>

            <div v-if="loading" class="text-center py-4 text-muted">Loading report…</div>
            <table v-else class="table table-striped table-hover align-middle mb-0 report-table">
                <thead class="table-light">
                    <tr>
                        <th>S.No</th>
                        <th>Truck No.</th>
                        <th>Driver</th>
                        <th class="text-end">Trips</th>
                        <th class="text-end">K.M.</th>
                        <th class="text-end">Diesel (L)</th>
                        <th class="text-end">Average</th>
                        <th class="text-end">Total Freight</th>
                        <th class="text-end">Total Exp.</th>
                        <th class="text-end">Tyres Used</th>
                        <th class="text-end">Tyre Exp. (₹)</th>
                        <th class="text-end">Saving</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="rows.length === 0">
                        <td colspan="12" class="text-center text-muted py-4">
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
                        <td class="text-end">{{ formatNumber(row.total_freight) }}</td>
                        <td class="text-end">{{ formatNumber(row.total_expense) }}</td>
                        <td class="text-end">{{ formatNumber(row.tyres_used, 0) }}</td>
                        <td class="text-end">{{ formatNumber(row.tyre_expense) }}</td>
                        <td class="text-end">{{ formatNumber(row.saving) }}</td>
                    </tr>
                </tbody>
            </table>

            <template #footer>
                <AppIndexPartloadPagination
                    :pagination="pagination"
                    :loading="loading"
                    item-label="trucks"
                    aria-label="Fleet monthly report pagination"
                    @page-change="loadReport"
                />
            </template>
        </AppIndexListCard>

        <div v-if="!loading && summary" class="card app-index-list-card mt-3">
            <div class="card-header">
                <h3 class="card-title mb-0">Month Summary</h3>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0 fleet-month-summary-table">
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
                            <tr
                                v-for="(item, index) in monthlyExpenseItems"
                                :key="`${item.particulars}-${index}`"
                            >
                                <th scope="row">{{ item.particulars }}</th>
                                <td class="text-end">{{ formatNumber(item.amount) }}</td>
                                <td colspan="2"></td>
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
import AppIndexListCard from '../../components/ui/AppIndexListCard.vue';
import AppReportFiltersPanel from '../../components/ui/AppReportFiltersPanel.vue';
import AppIndexPartloadPagination from '../../components/ui/AppIndexPartloadPagination.vue';
import { useListPagination } from '../../composables/useListPagination';
import { usePermissions } from '../../composables/usePermissions';
import { useRequireCompanyContext } from '../../composables/useRequireCompanyContext';
import { useAppToast } from '../../composables/useAppToast';
import { formatMonthYear } from '../../utils/datetime';

const { needsCompanySelection } = useRequireCompanyContext();
const toast = useAppToast();
const { canInCompany } = usePermissions();
const canExport = computed(() => canInCompany('fleet_monthly_report.export'));

const rows = ref([]);
const summary = ref(null);
const companyName = ref('');
const loading = ref(false);
const { pagination, syncFromMeta, reset: resetPagination } = useListPagination(10);

const monthlyExpenseItems = computed(() => summary.value?.monthly_expense_items ?? []);

function defaultFilters() {
    return {
        month: currentMonthValue(),
    };
}

const filters = reactive(defaultFilters());

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

async function loadReport(page = 1) {
    if (needsCompanySelection.value || !filters.month) {
        return;
    }

    loading.value = true;

    try {
        const data = await fetchFleetMonthlyReport(filters.month, {
            page,
            per_page: pagination.perPage,
        });
        rows.value = data.rows ?? [];
        summary.value = data.summary ?? null;
        companyName.value = data.company_name ?? '';
        syncFromMeta(data.meta, rows.value.length);
    } catch (error) {
        toast.error(error?.response?.data?.message ?? 'Failed to load monthly fleet report.');
        rows.value = [];
        summary.value = null;
        companyName.value = '';
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
    if (!filters.month) {
        return;
    }

    exportFleetMonthlyReport(filters.month);
}

onMounted(() => {
    loadReport(1);
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

    .fleet-month-summary-table {
        font-size: 0.72rem !important;
        width: max-content !important;
        min-width: 100% !important;
    }

    .fleet-month-summary-table th,
    .fleet-month-summary-table td {
        font-size: 0.72rem !important;
        padding: 0.35rem 0.5rem !important;
        white-space: nowrap !important;
        vertical-align: middle !important;
        line-height: 1.3 !important;
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

    .report-table th,
    .report-table td,
    .fleet-month-summary-table th,
    .fleet-month-summary-table td {
        font-size: 0.68rem !important;
        padding: 0.3rem 0.4rem !important;
    }
}
</style>
