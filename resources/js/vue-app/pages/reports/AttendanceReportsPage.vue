<template>
    <div class="container mt-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <h3 class="mb-0">Attendance Reports</h3>
            <AppButton
                v-if="canExport"
                icon="bi bi-download"
                label="Export CSV"
                outline
                @click="exportCsv"
            />
        </div>

        <div v-if="needsCompanySelection" class="alert alert-warning">
            Select a company from
            <RouterLink :to="{ name: 'companies.index' }" class="alert-link">Company List</RouterLink>
            and click <strong>Enter</strong> to view attendance reports.
        </div>

        <div v-if="flash.error" class="alert alert-danger">{{ flash.error }}</div>

        <div class="card mb-3">
            <div class="card-body row g-3">
                <div class="col-md-3">
                    <label class="form-label" for="from_date">From Date</label>
                    <input id="from_date" v-model="filters.from_date" type="date" class="form-control" />
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="to_date">To Date</label>
                    <input id="to_date" v-model="filters.to_date" type="date" class="form-control" />
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="vehicle_id">Vehicle</label>
                    <select id="vehicle_id" v-model="filters.vehicle_id" class="form-select">
                        <option value="">All vehicles</option>
                        <option v-for="vehicle in vehicles" :key="vehicle.id" :value="vehicle.id">
                            {{ vehicle.registration_number }}
                        </option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="driver_id">Driver</label>
                    <select id="driver_id" v-model="filters.driver_id" class="form-select">
                        <option value="">All drivers</option>
                        <option v-for="driver in drivers" :key="driver.id" :value="driver.id">
                            {{ driver.name }}
                        </option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="status">Status</label>
                    <select id="status" v-model="filters.status" class="form-select">
                        <option value="">All</option>
                        <option value="PRESENT">Present</option>
                        <option value="ABSENT">Absent</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="button" class="btn btn-primary" @click="loadReport(1)">Apply Filters</button>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="card-title mb-0">Results</h3>
                <span v-if="pagination.total" class="text-muted small">
                    {{ pagination.from }}–{{ pagination.to }} of {{ pagination.total }}
                </span>
            </div>
            <div class="card-body table-responsive">
                <div v-if="loading" class="text-center py-4 text-muted">Loading report…</div>
                <table v-else class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Vehicle</th>
                            <th>Primary Driver</th>
                            <th>Temp. Driver</th>
                            <th>Status</th>
                            <th>Wage</th>
                            <th>Payment</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="rows.length === 0">
                            <td colspan="7" class="text-center text-muted">No records for selected filters.</td>
                        </tr>
                        <tr v-for="row in rows" :key="row.id">
                            <td>{{ row.attendance_date }}</td>
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
                <nav v-if="pagination.last_page > 1" class="mt-3">
                    <ul class="pagination pagination-sm mb-0">
                        <li class="page-item" :class="{ disabled: pagination.current_page <= 1 }">
                            <button type="button" class="page-link" @click="loadReport(pagination.current_page - 1)">
                                Previous
                            </button>
                        </li>
                        <li class="page-item disabled">
                            <span class="page-link">
                                Page {{ pagination.current_page }} / {{ pagination.last_page }}
                            </span>
                        </li>
                        <li
                            class="page-item"
                            :class="{ disabled: pagination.current_page >= pagination.last_page }"
                        >
                            <button type="button" class="page-link" @click="loadReport(pagination.current_page + 1)">
                                Next
                            </button>
                        </li>
                    </ul>
                </nav>
            </div>
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

const filters = reactive({
    from_date: '',
    to_date: '',
    vehicle_id: '',
    driver_id: '',
    status: '',
});

const pagination = reactive({
    current_page: 1,
    last_page: 1,
    from: 0,
    to: 0,
    total: 0,
});

function defaultDateRange() {
    const now = new Date();
    const start = new Date(now.getFullYear(), now.getMonth(), 1);

    filters.from_date = start.toISOString().slice(0, 10);
    filters.to_date = now.toISOString().slice(0, 10);
}

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
    if (needsCompanySelection()) {
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

function exportCsv() {
    window.location.href = attendanceReportExportUrl(buildParams());
}

onMounted(async () => {
    defaultDateRange();

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
