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

        <AppIndexFiltersCard :active="filtersActive" @apply="loadReport(1)" @clear="clearFilters">
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
import AppSelect from '../../components/ui/AppSelect.vue';
import VehicleSelect from '../../components/ui/VehicleSelect.vue';
import AppIndexPaginationFooter from '../../components/ui/AppIndexPaginationFooter.vue';
import { formatDate, toDateInputValue } from '../../utils/datetime';
import { thisMonthDateRange } from '../../utils/dateRangePresets';
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
            toast.error('Could not load attendance report.');
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
