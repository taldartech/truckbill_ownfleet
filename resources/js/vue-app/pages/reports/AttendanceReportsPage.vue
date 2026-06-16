<template>
    <div class="container mt-4 app-index-page">
        <AppIndexPageHeader title="Attendance Reports">
            <template v-if="canExport" #actions>
                <AppButton icon="bi bi-download" label="Export CSV" outline @click="exportCsv" />
            </template>
        </AppIndexPageHeader>

        <div v-if="needsCompanySelection" class="alert alert-warning">
            Select a company from
            <RouterLink :to="{ name: 'companies.index' }" class="alert-link">Company List</RouterLink>
            and click <strong>Enter</strong> to view attendance reports.
        </div>

        <div v-if="flash.error" class="alert alert-danger">{{ flash.error }}</div>

        <AppIndexFiltersCard :active="filtersActive" @apply="loadReport(1)" @clear="clearFilters">
            <div>
                <label class="form-label" for="from_date">From Date</label>
                <input id="from_date" v-model="filters.from_date" type="date" class="form-control" />
            </div>
            <div>
                <label class="form-label" for="to_date">To Date</label>
                <input id="to_date" v-model="filters.to_date" type="date" class="form-control" />
            </div>
            <div>
                <label class="form-label" for="vehicle_id">Truck</label>
                <select id="vehicle_id" v-model="filters.vehicle_id" class="form-select">
                    <option value="">All trucks</option>
                    <option v-for="vehicle in vehicles" :key="vehicle.id" :value="vehicle.id">
                        {{ vehicle.registration_number }}
                    </option>
                </select>
            </div>
            <div>
                <label class="form-label" for="driver_id">Driver</label>
                <select id="driver_id" v-model="filters.driver_id" class="form-select">
                    <option value="">All drivers</option>
                    <option v-for="driver in drivers" :key="driver.id" :value="driver.id">
                        {{ driver.name }}
                    </option>
                </select>
            </div>
            <div>
                <label class="form-label" for="status">Status</label>
                <select id="status" v-model="filters.status" class="form-select">
                    <option value="">All</option>
                    <option value="PRESENT">Present</option>
                    <option value="ABSENT">Absent</option>
                </select>
            </div>
        </AppIndexFiltersCard>

        <div class="card app-index-list-card">
            <div class="card-header">
                <h3 class="card-title mb-0">Results</h3>
            </div>
            <div class="card-body">
                <div v-if="loading" class="text-center py-4 text-muted">Loading report…</div>
                <div v-else class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
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
                </div>
            </div>
            <AppIndexPaginationFooter
                page-id="attendanceReports"
                :loading="loading"
                :current-page="pagination.current_page"
                :last-page="pagination.last_page"
                :per-page="50"
                :total="pagination.total"
                :from="pagination.from"
                :to="pagination.to"
                item-label="records"
                :per-page-options="[50]"
                @page-change="loadReport"
            />
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { RouterLink } from 'vue-router';
import { listAllDrivers } from '../../api/drivers';
import { attendanceReportExportUrl, fetchAttendanceReport } from '../../api/reports';
import { listAllVehicles } from '../../api/vehicles';
import AppButton from '../../components/ui/AppButton.vue';
import AppIndexFiltersCard from '../../components/ui/AppIndexFiltersCard.vue';
import AppIndexPageHeader from '../../components/ui/AppIndexPageHeader.vue';
import AppIndexPaginationFooter from '../../components/ui/AppIndexPaginationFooter.vue';
import { formatDate } from '../../utils/datetime';
import { usePermissions } from '../../composables/usePermissions';
import { useRequireCompanyContext } from '../../composables/useRequireCompanyContext';

const { canInCompany } = usePermissions();
const { needsCompanySelection, guardCompanyContext, handleCompanyContextError } =
    useRequireCompanyContext();

const canExport = computed(() => canInCompany('reports.export'));

const vehicles = ref([]);
const drivers = ref([]);
const rows = ref([]);
const loading = ref(false);
const flash = reactive({ error: '' });

function defaultFilters() {
    const now = new Date();
    const start = new Date(now.getFullYear(), now.getMonth(), 1);

    return {
        from_date: start.toISOString().slice(0, 10),
        to_date: now.toISOString().slice(0, 10),
        vehicle_id: '',
        driver_id: '',
        status: '',
    };
}

const filters = reactive(defaultFilters());

const filtersActive = computed(() => {
    const defaults = defaultFilters();

    return (
        filters.from_date !== defaults.from_date ||
        filters.to_date !== defaults.to_date ||
        filters.vehicle_id !== '' ||
        filters.driver_id !== '' ||
        filters.status !== ''
    );
});

const pagination = reactive({
    current_page: 1,
    last_page: 1,
    from: 0,
    to: 0,
    total: 0,
});

function buildParams(page = 1) {
    const params = { page, per_page: 50 };

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

async function loadReport(page = 1) {
    if (needsCompanySelection.value) {
        return;
    }

    loading.value = true;
    flash.error = '';

    try {
        const data = await fetchAttendanceReport(buildParams(page));
        rows.value = data.data ?? [];
        pagination.current_page = data.meta?.current_page ?? 1;
        pagination.last_page = data.meta?.last_page ?? 1;
        pagination.from = data.meta?.from ?? 0;
        pagination.to = data.meta?.to ?? 0;
        pagination.total = data.meta?.total ?? 0;
    } catch (error) {
        if (!handleCompanyContextError(error, 'Could not load attendance report.')) {
            flash.error = 'Could not load attendance report.';
        }

        rows.value = [];
    } finally {
        loading.value = false;
    }
}

function clearFilters() {
    Object.assign(filters, defaultFilters());
    loadReport(1);
}

function exportCsv() {
    window.location.href = attendanceReportExportUrl(buildParams());
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
