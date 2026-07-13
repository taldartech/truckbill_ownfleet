<template>
    <div class="container-fluid px-3 px-lg-4 mt-4 maintenance-detail-page pb-4">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
            <div>
                <RouterLink :to="listReturnTo" class="text-decoration-none small">
                    <i class="bi bi-arrow-left"></i> {{ listReturnLabel }}
                </RouterLink>
                <div class="d-flex flex-wrap align-items-center gap-2 mt-1">
                    <h3 class="mb-0">{{ pageTitle }}</h3>
                    <span v-if="record" class="badge" :class="dueStatusMeta.badge">{{ dueStatusMeta.label }}</span>
                </div>
                <p v-if="record" class="text-muted small mb-0">
                    Service on {{ formatDate(record.service_date) }}
                    <span v-if="record.service_provider"> · {{ record.service_provider }}</span>
                </p>
            </div>
            <div v-if="record" class="d-flex flex-wrap gap-2">
                <AppButton
                    v-if="canEdit"
                    variant="primary"
                    outline
                    size="sm"
                    icon="bi bi-pencil"
                    label="Edit"
                    :to="{ name: 'maintenance.edit', params: { id: record.id } }"
                />
                <AppButton
                    v-if="canDelete"
                    variant="danger"
                    outline
                    size="sm"
                    icon="bi bi-trash"
                    label="Delete"
                    @click="openDeleteConfirm"
                />
            </div>
        </div>

        <div v-if="needsCompanySelection" class="alert alert-warning">
            Select a company and click <strong>Enter</strong> to view maintenance details.
        </div>

        <div v-if="loading" class="text-center py-5 text-muted">Loading maintenance details…</div>

        <div v-else-if="record" class="card shadow-sm">
            <div class="card-body maintenance-detail-grid small">
                <div class="maintenance-detail-item">
                    <span class="text-muted">Truck</span>
                    <div class="fw-semibold">
                        <RouterLink
                            v-if="record.vehicle?.id"
                            :to="{ name: 'trucks.show', params: { vehicleId: record.vehicle.id } }"
                            class="text-decoration-none"
                        >
                            {{ record.vehicle.registration_number }}
                        </RouterLink>
                        <span v-else>—</span>
                    </div>
                </div>
                <div class="maintenance-detail-item">
                    <span class="text-muted">Service Date</span>
                    <div>{{ formatDate(record.service_date) }}</div>
                </div>
                <div class="maintenance-detail-item">
                    <span class="text-muted">Service Type</span>
                    <div class="fw-semibold">{{ record.service_type_label ?? record.service_type ?? '—' }}</div>
                </div>
                <div class="maintenance-detail-item">
                    <span class="text-muted">Service Provider</span>
                    <div>{{ record.service_provider || '—' }}</div>
                </div>
                <div class="maintenance-detail-item">
                    <span class="text-muted">Meter Reading</span>
                    <div>{{ formatOdometer(record.odometer_km) }} km</div>
                </div>
                <div class="maintenance-detail-item">
                    <span class="text-muted">Current Truck KM</span>
                    <div>{{ formatOdometer(record.vehicle?.odometer_km) }} km</div>
                </div>
                <div class="maintenance-detail-item">
                    <span class="text-muted">Cost</span>
                    <div class="fw-semibold text-primary">{{ formatAmount(record.cost_amount) }}</div>
                </div>
                <div class="maintenance-detail-item">
                    <span class="text-muted">Next Due KM</span>
                    <div>{{ record.next_service_due_km ? `${formatOdometer(record.next_service_due_km)} km` : '—' }}</div>
                </div>
                <div class="maintenance-detail-item">
                    <span class="text-muted">Next Due Date</span>
                    <div>{{ record.next_service_due_date ? formatDate(record.next_service_due_date) : '—' }}</div>
                </div>
                <div class="maintenance-detail-item">
                    <span class="text-muted">Schedule Status</span>
                    <div>
                        <span class="badge" :class="dueStatusMeta.badge">{{ dueStatusMeta.label }}</span>
                    </div>
                </div>

                <div v-if="record.remarks?.trim()" class="maintenance-detail-item maintenance-detail-item--full">
                    <span class="text-muted">Remarks</span>
                    <div style="white-space: pre-wrap">{{ record.remarks }}</div>
                </div>
            </div>

            <div class="card-footer bg-light small text-muted d-flex flex-wrap gap-x-4 gap-y-1">
                <span>Created by {{ record.created_by_name ?? '—' }} · {{ formatDateTime(record.created_at) }}</span>
                <span v-if="record.updated_at && record.updated_at !== record.created_at">
                    Updated by {{ record.updated_by_name ?? '—' }} · {{ formatDateTime(record.updated_at) }}
                </span>
            </div>
        </div>

        <MaintenanceActionConfirmModal
            :show="showDeleteConfirm"
            :summary="deleteConfirmSummary"
            :deleting="deleting"
            @cancel="closeDeleteConfirm"
            @confirm="confirmDelete"
        />
    </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { deleteVehicleMaintenance, fetchVehicleMaintenance } from '../../api/vehicleMaintenances';
import MaintenanceActionConfirmModal from '../../components/maintenance/MaintenanceActionConfirmModal.vue';
import AppButton from '../../components/ui/AppButton.vue';
import { useAppToast } from '../../composables/useAppToast';
import { useMaintenanceListReturn } from '../../composables/useMaintenanceListReturn';
import { usePermissions } from '../../composables/usePermissions';
import { useRequireCompanyContext } from '../../composables/useRequireCompanyContext';
import { formatDate, formatDateTime } from '../../utils/datetime';
import { friendlyApiErrorMessage } from '../../utils/formErrors';
import { maintenanceDueStatusMeta, resolveMaintenanceDueStatus } from '../../utils/maintenanceDueStatus';
import { buildMaintenanceDeleteConfirmViewModel } from '../../utils/maintenanceSaveConfirm';

const route = useRoute();
const router = useRouter();
const toast = useAppToast();
const { needsCompanySelection } = useRequireCompanyContext();
const { canInCompany } = usePermissions();
const { listReturnTo, listReturnLabel, listReturnWithFlash } = useMaintenanceListReturn();

const canEdit = computed(() => canInCompany('maintenance.edit'));
const canDelete = computed(() => canInCompany('maintenance.delete'));

const record = ref(null);
const loading = ref(true);
const deleting = ref(false);
const showDeleteConfirm = ref(false);
const deleteConfirmSummary = ref(null);

const pageTitle = computed(() => {
    if (!record.value) {
        return 'Maintenance Record';
    }

    const truck = record.value.vehicle?.registration_number;
    const type = record.value.service_type_label ?? record.value.service_type;

    if (truck && type) {
        return `${truck} · ${type}`;
    }

    return truck ?? type ?? 'Maintenance Record';
});

const dueStatusMeta = computed(() => {
    const status = record.value?.due_status ?? resolveMaintenanceDueStatus(record.value);

    return maintenanceDueStatusMeta(status);
});

function formatOdometer(value) {
    if (value === null || value === undefined || value === '') {
        return '0';
    }

    return Number(value).toLocaleString('en-IN');
}

function formatAmount(amount) {
    return new Intl.NumberFormat('en-IN', {
        style: 'currency',
        currency: 'INR',
        maximumFractionDigits: 2,
    }).format(Number(amount ?? 0));
}

function showRouteFlash() {
    if (route.query.updated === '1') {
        toast.success('Maintenance record updated.');
    }
}

function openDeleteConfirm() {
    deleteConfirmSummary.value = buildMaintenanceDeleteConfirmViewModel(record.value);
    showDeleteConfirm.value = true;
}

function closeDeleteConfirm() {
    showDeleteConfirm.value = false;
    deleteConfirmSummary.value = null;
}

async function confirmDelete() {
    if (!record.value?.id) {
        return;
    }

    deleting.value = true;

    try {
        await deleteVehicleMaintenance(record.value.id);
        toast.success('Maintenance record deleted.');
        await router.push(listReturnWithFlash({ deleted: '1' }));
    } catch (error) {
        toast.error(friendlyApiErrorMessage(error, 'Unable to delete maintenance record.'));
        closeDeleteConfirm();
    } finally {
        deleting.value = false;
    }
}

async function loadRecord() {
    loading.value = true;

    try {
        record.value = await fetchVehicleMaintenance(route.params.id);
    } catch {
        toast.error('Could not load maintenance record.');
        await router.replace(listReturnTo.value);
    } finally {
        loading.value = false;
    }
}

onMounted(async () => {
    showRouteFlash();

    if (!needsCompanySelection.value) {
        await loadRecord();
    } else {
        loading.value = false;
    }
});
</script>

<style scoped>
.maintenance-detail-grid {
    display: grid;
    grid-template-columns: repeat(5, minmax(0, 1fr));
    gap: 1rem 1.25rem;
}

.maintenance-detail-item {
    min-width: 0;
}

.maintenance-detail-item--full {
    grid-column: 1 / -1;
    padding-top: 0.25rem;
    border-top: 1px solid #e9ecef;
}

@media (max-width: 1200px) {
    .maintenance-detail-grid {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
}

@media (max-width: 768px) {
    .maintenance-detail-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 480px) {
    .maintenance-detail-grid {
        grid-template-columns: minmax(0, 1fr);
    }
}
</style>
