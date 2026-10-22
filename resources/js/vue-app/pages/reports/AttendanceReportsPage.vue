<template>
    <div class="container mt-4 app-index-page report-index-page">
        <div v-if="needsCompanySelection" class="alert alert-warning">
            Select a company from
            <RouterLink :to="{ name: 'companies.index' }" class="alert-link">Company List</RouterLink>
            and click <strong>Enter</strong> to view attendance reports.
        </div>

        <AppIndexListCard title="Attendance Reports">
            <template v-if="canExport" #toolbar>
                <button
                    type="button"
                    class="btn btn-brand-secondary btn-sm report-header-btn report-export-btn"
                    title="Export CSV"
                    aria-label="Export CSV"
                    @click="exportCsv"
                >
                    <svg width="15" height="15" viewBox="0 0 16 16" fill="currentColor" class="report-export-btn__icon me-1 flex-shrink-0" aria-hidden="true">
                        <path d="M5.884 6.68a.5.5 0 1 0-.768.64L7.349 10l-2.233 2.68a.5.5 0 0 0 .768.64L8 10.781l2.116 2.54a.5.5 0 0 0 .768-.641L8.651 10l2.233-2.68a.5.5 0 0 0-.768-.64L8 9.219l-2.116-2.54z"/>
                        <path d="M14 14V4.5L9.5 0H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2zM9.5 3A1.5 1.5 0 0 0 11 4.5h2V14a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1h5.5v2z"/>
                    </svg>
                    <span class="report-export-btn__text">Export CSV</span>
                </button>
            </template>

            <AppReportFiltersPanel :loading="loading" @search="loadReport(1)" @reset="clearFilters">
                <div>
                    <label class="form-label" for="from_date">From Date</label>
                    <AppDateInput input-id="from_date" v-model="filters.from_date" />
                </div>
                <div>
                    <label class="form-label" for="to_date">To Date</label>
                    <AppDateInput input-id="to_date" v-model="filters.to_date" />
                </div>
                <div>
                    <label class="form-label" for="vehicle_id">Truck</label>
                    <VehicleSelect
                        input-id="vehicle_id"
                        v-model="filters.vehicle_id"
                        :vehicles="vehicles"
                        placeholder="All trucks"
                    />
                </div>
                <div>
                    <label class="form-label" for="driver_id">Driver</label>
                    <AppSelect
                        input-id="driver_id"
                        v-model="filters.driver_id"
                        :options="driverSelectOptions"
                        placeholder="All drivers"
                        search-placeholder="Search driver…"
                    />
                </div>
                <div>
                    <label class="form-label" for="status">Status</label>
                    <AppSelect
                        input-id="status"
                        v-model="filters.status"
                        :options="attendanceStatusOptions"
                        placeholder="All"
                        search-placeholder="Search status…"
                    />
                </div>
            </AppReportFiltersPanel>

            <div v-if="loading" class="text-center py-4 text-muted">Loading report…</div>
            <table v-else class="table table-striped table-hover align-middle mb-0 report-table">
                <thead class="table-light">
                    <tr>
                        <th>Date</th>
                        <th>Truck</th>
                        <th>Primary Driver</th>
                        <th>Temp. Driver</th>
                        <th>Status</th>
                        <th>Wage</th>
                        <th>Payment</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="rows.length === 0">
                        <td colspan="7" class="text-center text-muted py-4">No records for selected filters.</td>
                    </tr>
                    <tr v-for="row in rows" :key="row.id">
                        <td>{{ formatDate(row.attendance_date) }}</td>
                        <td>{{ row.vehicle?.registration_number ?? '—' }}</td>
                        <td>{{ row.primary_driver?.name ?? '—' }}</td>
                        <td>{{ row.temporary_driver?.name ?? '—' }}</td>
                        <td>
                            <span
                                class="badge"
                                :class="row.status === 'PRESENT' ? 'bg-success' : 'bg-danger'"
                            >
                                {{ row.status }}
                            </span>
                        </td>
                        <td>{{ row.wage_amount ?? '—' }}</td>
                        <td>{{ row.wage_payment_status ?? '—' }}</td>
                    </tr>
                </tbody>
            </table>

            <template #footer>
                <AppIndexPartloadPagination
                    :pagination="pagination"
                    :loading="loading"
                    item-label="records"
                    aria-label="Attendance reports pagination"
                    @page-change="loadReport"
                />
            </template>
        </AppIndexListCard>
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { RouterLink } from 'vue-router';
import { listAllDrivers } from '../../api/drivers';
import { attendanceReportExportUrl, fetchAttendanceReport } from '../../api/reports';
import { listAllVehicles } from '../../api/vehicles';
import AppIndexListCard from '../../components/ui/AppIndexListCard.vue';
import AppReportFiltersPanel from '../../components/ui/AppReportFiltersPanel.vue';
import AppSelect from '../../components/ui/AppSelect.vue';
import VehicleSelect from '../../components/ui/VehicleSelect.vue';
import AppIndexPartloadPagination from '../../components/ui/AppIndexPartloadPagination.vue';
import { formatDate, toDateInputValue } from '../../utils/datetime';
import { thisMonthDateRange } from '../../utils/dateRangePresets';
import { useListPagination } from '../../composables/useListPagination';
import { usePermissions } from '../../composables/usePermissions';
import { useRequireCompanyContext } from '../../composables/useRequireCompanyContext';
import { useAppToast } from '../../composables/useAppToast';

const { canInCompany } = usePermissions();
const { needsCompanySelection, guardCompanyContext, handleCompanyContextError } =
    useRequireCompanyContext();
const toast = useAppToast();

const canExport = computed(() => canInCompany('reports.export'));

const vehicles = ref([]);
const drivers = ref([]);
const attendanceStatusOptions = [
    { value: 'PRESENT', label: 'Present' },
    { value: 'ABSENT', label: 'Absent' },
];
const driverSelectOptions = computed(() =>
    drivers.value.map((driver) => ({
        id: driver.id,
        label: driver.name,
        is_active: driver.is_active,
    })),
);
const rows = ref([]);
const loading = ref(false);
const { pagination, syncFromMeta, reset: resetPagination } = useListPagination(10);

function defaultFilters() {
    const { from_date } = thisMonthDateRange();

    return {
        from_date,
        to_date: toDateInputValue(),
        vehicle_id: '',
        driver_id: '',
        status: '',
    };
}

const filters = reactive(defaultFilters());

function buildFilterParams() {
    const params = {};

    if (filters.from_date) {
        params.from_date = filters.from_date;
    }

    if (filters.to_date) {
        params.to_date = filters.to_date;
    }

    if (filters.vehicle_id) {
        params.vehicle_id = filters.vehicle_id;
    }

    if (filters.driver_id) {
        params.driver_id = filters.driver_id;
    }

    if (filters.status) {
        params.status = filters.status;
    }

    return params;
}

function buildParams(page = 1) {
    return {
        ...buildFilterParams(),
        page,
        per_page: pagination.perPage,
    };
}

async function loadReport(page = 1) {
    if (needsCompanySelection.value) {
        return;
    }

    loading.value = true;

    try {
        const data = await fetchAttendanceReport(buildParams(page));
        rows.value = data.data ?? [];
        syncFromMeta(data.meta, rows.value.length);
    } catch (error) {
        if (!handleCompanyContextError(error, 'Could not load attendance report.')) {
            toast.error('Could not load attendance report.');
        }

        rows.value = [];
        resetPagination();
    } finally {
        loading.value = false;
    }
}

function clearFilters() {
    Object.assign(filters, defaultFilters());
    loadReport(1);
}

function exportCsv() {
    window.location.href = attendanceReportExportUrl(buildFilterParams());
}

onMounted(async () => {
    const ok = await guardCompanyContext();

    if (ok !== true) {
        return;
    }

    try {
        [vehicles.value, drivers.value] = await Promise.all([listAllVehicles(), listAllDrivers()]);
    } catch {
        vehicles.value = [];
        drivers.value = [];
    }

    await loadReport();
});
</script>
