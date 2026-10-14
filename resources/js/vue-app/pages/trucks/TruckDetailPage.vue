<template>
    <div class="container mt-4 truck-detail-page">

        <div v-if="needsCompanySelection" class="alert alert-warning">
            Select a company and click <strong>Enter</strong> to view truck details.
        </div>

        <div v-if="loading" class="text-center py-5 text-muted">Loading truck details…</div>

        <template v-else-if="vehicle">
            <div class="row g-3">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <strong>Truck Information ({{ vehicle?.registration_number }})</strong>
                            <div class="app-index-table-actions ms-auto" style="margin-left: auto !important; display: inline-flex !important; gap: 0.375rem !important;">
                                 <RouterLink
                                    :to="{ name: 'trucks.index' }"
                                    class="btn btn-sm btn-outline-secondary btn-icon app-index-action-btn"
                                    title="Back to Trucks"
                                    style="background-color: #fff; border-color: #cbd5e1; color: #475569;"
                                >
                                    <i class="fas fa-arrow-left" style="color: #475569 !important;" aria-hidden="true"></i>
                                </RouterLink>
                                <AppCrudActionButton
                                    v-if="canViewAssignments"
                                    action="driver"
                                    @click="openAssignment"
                                />
                                <AppCrudActionButton
                                    v-if="canViewDocuments"
                                    action="documents"
                                    @click="openDocuments"
                                />
                                <AppCrudActionButton
                                    v-if="canViewTyres"
                                    action="tyres"
                                    title="Tyre positions"
                                    :to="{ name: 'vehicles.tyres', params: { vehicleId: vehicle.id } }"
                                />
                                <AppCrudActionButton
                                    v-if="canCreateMaintenance"
                                    action="service"
                                    title="Add service record"
                                    :to="{ name: 'maintenance.create', query: { vehicle_id: vehicle.id } }"
                                />
                                <AppCrudActionButton
                                    v-if="canEdit"
                                    action="edit"
                                    @click="openEdit"
                                />
                                <AppCrudActionButton
                                    v-if="canDelete"
                                    action="delete"
                                    @click="handleDelete"
                                />
                            </div>

                        </div>
                        <div class="card-body row g-3 small">
                            <div class="col-md-4">
                                <span class="text-muted">Registration</span>
                                <div class="fw-semibold">{{ vehicle.registration_number }}</div>
                            </div>
                            <div class="col-md-4">
                                <span class="text-muted">Truck Type</span>
                                <div>{{ vehicle.vehicle_type_label ?? vehicle.vehicle_type ?? '—' }}</div>
                            </div>
                            <div class="col-md-4">
                                <span class="text-muted">Tyre Layout</span>
                                <div>{{ vehicle.tyre_layout_label ?? vehicle.tyre_layout ?? '—' }}</div>
                            </div>
                            <div class="col-md-4">
                                <span class="text-muted">Make</span>
                                <div>{{ vehicle.make || '—' }}</div>
                            </div>
                            <div class="col-md-4">
                                <span class="text-muted">Model</span>
                                <div>{{ vehicle.model || '—' }}</div>
                            </div>
                            <div class="col-md-4">
                                <span class="text-muted">Manufacture Year</span>
                                <div>{{ vehicle.manufacture_year || '—' }}</div>
                            </div>
                            <div class="col-md-4">
                                <span class="text-muted">Truck Running Meter Reading</span>
                                <div class="fw-semibold">{{ formatOdometer(vehicle.odometer_km) }}</div>
                            </div>
                            <div class="col-md-4">
                                <span class="text-muted">Default Mileage (KM/LTR)</span>
                                <div>{{ vehicle.default_mileage_km_per_liter ? formatOdometer(vehicle.default_mileage_km_per_liter) : '—' }}</div>
                            </div>
                            <div class="col-md-4">
                                <span class="text-muted">Max Load (Ton)</span>
                                <div>{{ vehicle.max_load_ton ? formatOdometer(vehicle.max_load_ton) : '—' }}</div>
                            </div>
                            <div class="col-md-4">
                                <span class="text-muted">Fuel Tank Capacity (LTR)</span>
                                <div>{{ vehicle.fuel_tank_capacity_ltr ? formatOdometer(vehicle.fuel_tank_capacity_ltr) : '—' }}</div>
                            </div>
                        </div>
                        <div class="card-footer bg-white border-top-0 d-flex justify-content-between align-items-center pt-0 pb-3 pe-3 ps-3" style="display: flex !important; justify-content: space-between !important; align-items: center !important; width: 100% !important;">
                            <div class="d-flex align-items-center">
                                <span class="text-muted me-2 small">Status:</span>
                                <span
                                    class="badge"
                                    :class="vehicle.is_active ? 'bg-success' : 'bg-secondary'"
                                >
                                    {{ vehicle.is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </div>
                           
                        </div>
                    </div>

                    <!-- Fuel Performance Summary -->
                    <div class="card mt-3">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <strong>Fuel Summary</strong>
                            <span v-if="vehicle?.default_mileage_km_per_liter" class="badge bg-light text-dark border">
                                Target: {{ formatMileage(vehicle.default_mileage_km_per_liter) }}
                            </span>
                        </div>
                        <div class="card-body">
                            <div v-if="fuelLoading && !fuelSummary" class="text-center py-3 text-muted">
                                Loading fuel summary…
                            </div>
                            <div v-else class="row g-3 text-center">
                                <div class="col-6 col-md-3">
                                    <div class="p-3 border rounded bg-light">
                                        <div class="text-muted small text-uppercase">Total Fuel</div>
                                        <div class="fs-5 fw-bold text-primary mt-1">
                                            {{ fuelSummary?.total_fuel_liters ? formatFuelLiters(fuelSummary.total_fuel_liters) : '0 L' }}
                                        </div>
                                    </div>
                                </div>
                                <div class="col-6 col-md-3">
                                    <div class="p-3 border rounded bg-light">
                                        <div class="text-muted small text-uppercase">Fuel Cost</div>
                                        <div class="fs-5 fw-bold text-dark mt-1">
                                            {{ fuelSummary?.total_fuel_cost ? formatFuelCostSummary(fuelSummary.total_fuel_cost) : '₹0' }}
                                        </div>
                                    </div>
                                </div>
                                <div class="col-6 col-md-3">
                                    <div class="p-3 border rounded bg-light">
                                        <div class="text-muted small text-uppercase">Average Mileage</div>
                                        <div class="fs-5 fw-bold mt-1" :class="mileageColorClass">
                                            {{ fuelSummary?.average_mileage ? formatMileage(fuelSummary.average_mileage) : '—' }}
                                        </div>
                                    </div>
                                </div>
                                <div class="col-6 col-md-3">
                                    <div class="p-3 border rounded bg-light">
                                        <div class="text-muted small text-uppercase">Cost / KM</div>
                                        <div class="fs-5 fw-bold text-dark mt-1">
                                            {{ fuelSummary?.cost_per_km ? formatCostPerKm(fuelSummary.cost_per_km) : '—' }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-if="canViewDocuments" class="card mt-3">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <strong>Documents</strong>
                           
                        </div>
                        <div class="card-body p-0">
                            <div v-if="documents.length === 0" class="text-muted text-center py-4">
                                No documents uploaded for this truck.
                            </div>
                            <div v-else class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Type</th>
                                            <th>Issue Date</th>
                                            <th>Expiry Date</th>
                                            <th>Status</th>
                                            <th>File</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="doc in documents" :key="doc.id">
                                            <td>{{ doc.document_type_label ?? doc.document_type }}</td>
                                            <td>{{ formatDate(doc.issue_date) }}</td>
                                            <td>{{ formatDate(doc.expiry_date) }}</td>
                                            <td>
                                                <span
                                                    class="badge"
                                                    :class="doc.is_expired ? 'bg-danger' : 'bg-success'"
                                                >
                                                    {{ doc.is_expired ? 'Expired' : 'Valid' }}
                                                </span>
                                            </td>
                                            <td>
                                                <a
                                                    v-if="doc.download_url"
                                                    :href="doc.download_url"
                                                    class="btn btn-sm btn-outline-secondary"
                                                    target="_blank"
                                                    rel="noopener"
                                                >
                                                    Download
                                                </a>
                                                <span v-else class="text-muted">—</span>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div v-if="canViewAssignments" class="card mt-3">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <strong>Driver Assignment History</strong>
                           
                        </div>
                        <div class="card-body p-0">
                            <div v-if="assignments.length === 0" class="text-muted text-center py-4">
                                No driver assignments recorded for this truck.
                            </div>
                            <div v-else class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Driver</th>
                                            <th>Phone</th>
                                            <th>From</th>
                                            <th>To</th>
                                            <th>Primary</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="assignment in assignments" :key="assignment.id">
                                            <td>{{ assignment.driver?.name ?? '—' }}</td>
                                            <td>{{ assignment.driver?.phone ?? '—' }}</td>
                                            <td>{{ formatDate(assignment.assigned_from) }}</td>
                                            <td>{{ formatDate(assignment.assigned_to) }}</td>
                                            <td>
                                                <span
                                                    v-if="assignment.is_primary"
                                                    class="badge bg-primary"
                                                >
                                                    Primary
                                                </span>
                                                <span v-else class="text-muted">—</span>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div v-if="canViewMaintenance" class="card mt-3">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <strong>Maintenance History</strong>
                            <div class="d-flex gap-2">
                               
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <div v-if="maintenanceLoading" class="text-muted text-center py-4">
                                Loading maintenance history…
                            </div>
                            <div v-else-if="maintenanceRecords.length === 0" class="text-muted text-center py-4">
                                No service records for this truck.
                            </div>
                            <div v-else class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Date</th>
                                            <th>Service Type</th>
                                            <th>Provider</th>
                                            <th class="text-end">Meter Reading</th>
                                            <th class="text-end">Cost</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="record in maintenanceRecords" :key="record.id">
                                            <td>{{ formatDate(record.service_date) }}</td>
                                            <td>{{ record.service_type_label ?? record.service_type }}</td>
                                            <td>{{ record.service_provider || '—' }}</td>
                                            <td class="text-end">{{ formatOdometer(record.odometer_km) }}</td>
                                            <td class="text-end">{{ formatMaintenanceCost(record.cost_amount) }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Fuel Refill History -->
                    <div class="card mt-3">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <strong>Fuel Refill History</strong>
                            <span v-if="fuelPagination.total > 0" class="badge bg-secondary">
                                {{ fuelPagination.total }} {{ fuelPagination.total === 1 ? 'refill' : 'refills' }}
                            </span>
                        </div>
                        <div class="card-body p-0">
                            <div v-if="fuelLoading" class="text-muted text-center py-4">
                                Loading fuel refill history…
                            </div>
                            <div v-else-if="fuelRecords.length === 0" class="text-muted text-center py-4">
                                No fuel refill records logged for this truck.
                            </div>
                            <div v-else class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Date</th>
                                            <th>Trip #</th>
                                            <th>Driver</th>
                                            <th class="text-end">Meter Reading</th>
                                            <th class="text-end">Quantity (L)</th>
                                            <th class="text-end">Rate / L</th>
                                            <th class="text-end">Cost</th>
                                            <th>Notes</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="record in fuelRecords" :key="record.id">
                                            <td>{{ formatDate(record.expense_date) }}</td>
                                            <td>
                                                <RouterLink
                                                    v-if="record.trip_id"
                                                    :to="{ name: 'trips.show', params: { id: record.trip_id } }"
                                                    class="text-decoration-none fw-semibold"
                                                >
                                                    {{ record.trip_number || '—' }}
                                                </RouterLink>
                                                <span v-else>{{ record.trip_number || '—' }}</span>
                                            </td>
                                            <td>{{ record.driver_name || '—' }}</td>
                                            <td class="text-end fw-semibold">
                                                {{ record.fuel_odometer_km ? formatOdometer(record.fuel_odometer_km) + ' km' : '—' }}
                                            </td>
                                            <td class="text-end">{{ record.fuel_liters ? Number(record.fuel_liters).toFixed(2) + ' L' : '—' }}</td>
                                            <td class="text-end">{{ record.fuel_rate ? '₹' + Number(record.fuel_rate).toFixed(2) : '—' }}</td>
                                            <td class="text-end fw-semibold">{{ formatMaintenanceCost(record.amount) }}</td>
                                            <td class="text-muted small">{{ record.notes || '—' }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div v-if="fuelPagination.last_page > 1" class="card-footer bg-white d-flex justify-content-between align-items-center">
                            <span class="text-muted small">
                                Page {{ fuelPagination.current_page }} of {{ fuelPagination.last_page }}
                            </span>
                            <div class="btn-group btn-group-sm">
                                <button
                                    type="button"
                                    class="btn btn-outline-secondary"
                                    :disabled="fuelPagination.current_page <= 1 || fuelLoading"
                                    @click="loadFuelHistory(fuelPagination.current_page - 1)"
                                >
                                    Previous
                                </button>
                                <button
                                    type="button"
                                    class="btn btn-outline-secondary"
                                    :disabled="fuelPagination.current_page >= fuelPagination.last_page || fuelLoading"
                                    @click="loadFuelHistory(fuelPagination.current_page + 1)"
                                >
                                    Next
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header"><strong>Primary Driver</strong></div>
                        <div class="card-body">
                            <template v-if="vehicle.current_primary_assignment?.driver">
                                <div class="fw-semibold">
                                    {{ vehicle.current_primary_assignment.driver.name }}
                                </div>
                                <div v-if="vehicle.current_primary_assignment.driver.phone" class="text-muted small">
                                    {{ vehicle.current_primary_assignment.driver.phone }}
                                </div>
                                <div
                                    v-if="vehicle.current_primary_assignment.assigned_from"
                                    class="small text-muted mt-2"
                                >
                                    Assigned since {{ formatDate(vehicle.current_primary_assignment.assigned_from) }}
                                </div>
                            </template>
                            <div v-else class="text-muted">No primary driver assigned.</div>
                        </div>
                    </div>

                    <!-- Batteries Card -->
                    <div class="card mt-3">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <strong><i class="fas fa-car-battery text-warning me-1"></i> Batteries</strong>
                            <button
                                v-if="canManageBatteries"
                                type="button"
                                class="btn btn-sm btn-outline-success py-0 px-2 ms-auto"
                                style="font-size: 0.75rem;"
                                @click="openTruckBatteryInstall"
                            >
                                <i class="fas fa-plus me-1"></i> Install Battery
                            </button>
                        </div>
                        <div class="card-body">
                            <div v-if="batteriesLoading" class="text-center py-2 text-muted small">
                                <span class="spinner-border spinner-border-sm me-1"></span> Loading batteries…
                            </div>
                            <div v-else-if="vehicleBatteries.length === 0" class="text-center py-3 text-muted">
                                <i class="fas fa-car-battery fs-4 mb-2 text-secondary opacity-50 d-block"></i>
                                <span class="small">No battery assigned to this truck.</span>
                                <div v-if="canManageBatteries" class="mt-2">
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-outline-primary"
                                        @click="openTruckBatteryInstall"
                                    >
                                        <i class="fas fa-plus me-1"></i> Install Battery
                                    </button>
                                </div>
                            </div>
                            <div v-else class="d-flex flex-column gap-3">
                                <div
                                    v-for="inst in vehicleBatteries"
                                    :key="inst.id"
                                    class="p-2 border rounded bg-light"
                                >
                                    <div class="d-flex align-items-center justify-content-between">
                                        <div class="fw-bold text-dark fs-6 d-flex align-items-center gap-1">
                                            <RouterLink
                                                v-if="inst.battery?.id"
                                                :to="{ name: 'batteries.show', params: { id: inst.battery.id } }"
                                                class="text-decoration-none text-dark"
                                            >
                                                {{ inst.battery?.serial_number || 'BAT-' + inst.battery_id }}
                                            </RouterLink>
                                            <span v-else>{{ 'BAT-' + inst.battery_id }}</span>
                                        </div>
                                        <span class="badge bg-light text-dark border">
                                            {{ inst.slot_name || 'Main' }}
                                        </span>
                                    </div>
                                    <div class="text-muted small mt-1">
                                        {{ inst.battery?.brand || 'Battery' }}
                                        <template v-if="inst.battery?.capacity_ah"> | {{ inst.battery.capacity_ah }} Ah</template>
                                        <template v-if="inst.battery?.voltage"> | {{ inst.battery.voltage }}</template>
                                    </div>
                                    <div class="text-muted small mt-1 d-flex justify-content-between">
                                        <span>Installed: {{ formatDate(inst.installed_at) }}</span>
                                        <span>
                                            Stint: {{ Math.round(calculateStintKm(inst)).toLocaleString('en-IN') }} KM
                                        </span>
                                    </div>
                                    <div v-if="canManageBatteries" class="mt-2 pt-2 border-top d-flex gap-2">
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-primary flex-grow-1 py-1"
                                            style="font-size: 0.8rem;"
                                            @click="openTruckBatteryReplace(inst)"
                                        >
                                            <i class="fas fa-arrows-rotate me-1"></i> Replace
                                        </button>
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-outline-warning flex-grow-1 py-1"
                                            style="font-size: 0.8rem;"
                                            @click="openTruckBatteryRemove(inst)"
                                        >
                                            <i class="fas fa-plug-circle-minus me-1"></i> Remove
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card mt-3">
                        <div class="card-header"><strong>Record Info</strong></div>
                        <div class="card-body small">
                            <div class="mb-2">
                                <span class="text-muted">Created</span>
                                <div>{{ formatDateTime(vehicle.created_at) }}</div>
                                <div v-if="vehicle.created_by_name" class="text-muted">
                                    by {{ vehicle.created_by_name }}
                                </div>
                            </div>
                            <div>
                                <span class="text-muted">Last Updated</span>
                                <div>{{ formatDateTime(vehicle.updated_at) }}</div>
                                <div v-if="vehicle.updated_by_name" class="text-muted">
                                    by {{ vehicle.updated_by_name }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </template>

        <div v-else-if="!needsCompanySelection" class="alert alert-danger">
            Truck not found or you do not have access.
        </div>

        <VehicleFormModal
            modal-id="truckDetailFormModal"
            :show="showForm"
            mode="edit"
            :vehicle="vehicle"
            :saving="saving"
            :field-errors="fieldErrors"
            :form-error="vehicleFormError"
            :can-manage-types="canManage"
            @close="closeForm"
            @submit="handleSubmit"
        />

        <VehicleDocumentsModal
            modal-id="truckDetailDocumentsModal"
            :show="showDocuments"
            :vehicle="vehicle"
            :can-create="canCreateDocuments"
            :can-edit="canEditDocuments"
            :can-delete="canDeleteDocuments"
            @close="closeDocuments"
            @saved="loadVehicle"
        />

        <VehicleDriverAssignmentModal
            modal-id="truckDetailAssignmentModal"
            :show="showAssignment"
            :vehicle="vehicle"
            :can-create="canCreateAssignments"
            :can-edit="canEditAssignments"
            @close="closeAssignment"
            @assigned="onAssignmentSaved"
        />

        <BatteryInstallModal
            :show="showBatteryInstallModal"
            :vehicle="vehicle"
            @close="showBatteryInstallModal = false"
            @installed="onBatteryChanged('Battery installed.')"
        />

        <BatteryReplaceModal
            :show="showBatteryReplaceModal"
            :battery="replacingBatteryObj"
            :vehicle="vehicle"
            :current-installation="replacingInstallation"
            @close="showBatteryReplaceModal = false"
            @replaced="onBatteryChanged('Battery replaced.')"
        />

        <BatteryRemoveModal
            :show="showBatteryRemoveModal"
            :installation="replacingInstallation"
            :battery-serial="replacingInstallation?.battery?.serial_number"
            :vehicle="vehicle"
            @close="showBatteryRemoveModal = false"
            @removed="onBatteryChanged('Battery removed and moved to standby.')"
        />
    </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import { listDriverAssignments } from '../../api/driverAssignments';
import { listVehicleMaintenanceHistory } from '../../api/vehicleMaintenances';
import { fetchVehicle, updateVehicle, deleteVehicle, fetchVehicleFuelHistory } from '../../api/vehicles';
import VehicleDocumentsModal from '../../components/trucks/VehicleDocumentsModal.vue';
import VehicleDriverAssignmentModal from '../../components/trucks/VehicleDriverAssignmentModal.vue';
import VehicleFormModal from '../../components/trucks/VehicleFormModal.vue';
import BatteryInstallModal from '../../components/batteries/BatteryInstallModal.vue';
import BatteryReplaceModal from '../../components/batteries/BatteryReplaceModal.vue';
import BatteryRemoveModal from '../../components/batteries/BatteryRemoveModal.vue';
import { getVehicleBatteries } from '../../api/batteries';
import AppButton from '../../components/ui/AppButton.vue';
import AppCrudActionButton from '../../components/ui/AppCrudActionButton.vue';
import { useApiFormErrors } from '../../composables/useApiFormErrors';
import { usePermissions } from '../../composables/usePermissions';
import { useRequireCompanyContext } from '../../composables/useRequireCompanyContext';
import { useAppToast } from '../../composables/useAppToast';
import { useDeleteConfirm } from '../../composables/useDeleteConfirm';
import { formatDate, formatDateTime } from '../../utils/datetime';

const route = useRoute();
const router = useRouter();
const toast = useAppToast();
const { promptDelete } = useDeleteConfirm();
const { canInCompany, canCreateInCompany, canEditInCompany, canDeleteRecords } = usePermissions();
const { needsCompanySelection, guardCompanyContext, handleCompanyContextError } =
    useRequireCompanyContext();

const canManage = computed(() => canInCompany('vehicles.manage'));
const canEdit = computed(() => canEditInCompany('vehicles'));
const canViewTyres = computed(() => canInCompany('tyres.view'));
const canViewAssignments = computed(() => canInCompany('driver_assignments.view'));
const canCreateAssignments = computed(() => canInCompany('driver_assignments.create'));
const canEditAssignments = computed(() => canInCompany('driver_assignments.edit'));
const canManageAssignments = computed(
    () => canCreateAssignments.value || canEditAssignments.value,
);
const canViewDocuments = computed(() => canInCompany('vehicle_documents.view'));
const canCreateDocuments = computed(() => canCreateInCompany('vehicle_documents'));
const canEditDocuments = computed(() => canEditInCompany('vehicle_documents'));
const canDeleteDocuments = computed(
    () => canDeleteRecords.value && canInCompany('vehicle_documents.manage'),
);
const canViewMaintenance = computed(() => canInCompany('maintenance.view'));
const canCreateMaintenance = computed(() => canInCompany('maintenance.create'));
const canDelete = computed(() => canInCompany('vehicles.delete'));
const canManageBatteries = computed(() => canCreateInCompany('batteries'));

const vehicleBatteries = ref([]);
const batteriesLoading = ref(false);
const showBatteryInstallModal = ref(false);
const showBatteryReplaceModal = ref(false);
const showBatteryRemoveModal = ref(false);
const replacingInstallation = ref(null);
const replacingBatteryObj = ref(null);

function calculateStintKm(inst) {
    if (inst.stint_km !== null && inst.stint_km !== undefined) {
        return inst.stint_km;
    }
    const currentOdo = Number(vehicle.value?.odometer_km || 0);
    const installOdo = Number(inst.km_at_installation || 0);
    return Math.max(0, currentOdo - installOdo);
}

async function loadVehicleBatteries() {
    if (!vehicle.value?.id) {
        vehicleBatteries.value = [];
        return;
    }
    batteriesLoading.value = true;
    try {
        const res = await getVehicleBatteries(vehicle.value.id);
        vehicleBatteries.value = res.active_batteries ?? [];
    } catch {
        vehicleBatteries.value = [];
    } finally {
        batteriesLoading.value = false;
    }
}

function openTruckBatteryInstall() {
    showBatteryInstallModal.value = true;
}

function openTruckBatteryReplace(inst) {
    replacingInstallation.value = inst;
    replacingBatteryObj.value = inst.battery
        ? { ...inst.battery, active_installation: inst }
        : { id: inst.battery_id, active_installation: inst };
    showBatteryReplaceModal.value = true;
}

function openTruckBatteryRemove(inst) {
    replacingInstallation.value = inst;
    showBatteryRemoveModal.value = true;
}

async function onBatteryChanged(message) {
    toast.success(message);
    await Promise.all([loadVehicleBatteries(), loadVehicle()]);
}

function handleDelete() {
    if (!vehicle.value?.id) {
        return;
    }

    promptDelete({
        title: 'Delete Truck?',
        itemName: vehicle.value.registration_number,
        details: [
            { label: 'Registration', value: vehicle.value.registration_number },
            { label: 'Truck Type', value: vehicle.value.vehicle_type_label ?? vehicle.value.vehicle_type },
            { label: 'Make', value: vehicle.value.make },
            { label: 'Model', value: vehicle.value.model },
        ],
        onConfirm: async (remark) => {
            await deleteVehicle(vehicle.value.id, { deleted_reason: remark, remark });
            toast.success('Truck deleted.');
            router.push({ name: 'trucks.index' });
        },
    });
}

const vehicle = ref(null);
const assignments = ref([]);
const maintenanceRecords = ref([]);
const loading = ref(true);
const maintenanceLoading = ref(false);
const saving = ref(false);
const showForm = ref(false);
const showDocuments = ref(false);
const showAssignment = ref(false);
const { fieldErrors, formError: vehicleFormError, clearErrors, applyApiError } = useApiFormErrors();

const documents = computed(() => vehicle.value?.documents ?? []);

function formatMakeModel(item) {
    const parts = [item.make, item.model].filter(Boolean);

    return parts.length ? parts.join(' / ') : '—';
}

function formatOdometer(value) {
    if (value === null || value === undefined || value === '') {
        return '0';
    }

    return Number(value).toLocaleString('en-IN', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 2,
    });
}

function formatMaintenanceCost(amount) {
    return new Intl.NumberFormat('en-IN', {
        style: 'currency',
        currency: 'INR',
        maximumFractionDigits: 2,
    }).format(Number(amount ?? 0));
}

function formatFuelLiters(liters) {
    if (liters === null || liters === undefined) {
        return '0 L';
    }

    return `${Number(liters).toLocaleString('en-IN', { minimumFractionDigits: 0, maximumFractionDigits: 2 })} L`;
}

function formatFuelCostSummary(amount) {
    const val = Number(amount ?? 0);
    if (val >= 100000) {
        const inLakhs = (val / 100000).toFixed(2);

        return `₹${inLakhs} L`;
    }

    return new Intl.NumberFormat('en-IN', {
        style: 'currency',
        currency: 'INR',
        maximumFractionDigits: 2,
    }).format(val);
}

function formatMileage(val) {
    if (!val || Number(val) <= 0) {
        return '—';
    }

    return `${Number(val).toFixed(2)} KM/L`;
}

function formatCostPerKm(val) {
    if (!val || Number(val) <= 0) {
        return '—';
    }

    return `₹${Number(val).toFixed(2)}`;
}

const fuelSummary = ref(null);
const fuelRecords = ref([]);
const fuelPagination = ref({ current_page: 1, last_page: 1, total: 0 });
const fuelLoading = ref(false);

const mileageColorClass = computed(() => {
    if (!fuelSummary.value?.average_mileage) {
        return 'text-dark';
    }

    const actual = Number(fuelSummary.value.average_mileage);
    const target = Number(fuelSummary.value.target_mileage ?? vehicle.value?.default_mileage_km_per_liter ?? 0);

    if (target <= 0) {
        return 'text-success';
    }

    if (actual >= target) {
        return 'text-success';
    }

    if (actual >= target * 0.9) {
        return 'text-warning';
    }

    return 'text-danger';
});

async function loadFuelHistory(page = 1) {
    if (!vehicle.value?.id) {
        fuelSummary.value = null;
        fuelRecords.value = [];

        return;
    }

    fuelLoading.value = true;

    try {
        const response = await fetchVehicleFuelHistory(vehicle.value.id, { page, perPage: 10 });
        fuelSummary.value = response.summary ?? null;
        fuelRecords.value = response.data ?? [];
        fuelPagination.value = response.meta ?? { current_page: 1, last_page: 1, total: 0 };
    } catch {
        fuelSummary.value = null;
        fuelRecords.value = [];
    } finally {
        fuelLoading.value = false;
    }
}

async function loadAssignments() {
    if (!vehicle.value?.id || !canViewAssignments.value) {
        assignments.value = [];

        return;
    }

    try {
        const response = await listDriverAssignments({
            vehicleId: vehicle.value.id,
            perPage: 100,
        });
        assignments.value = response.data ?? [];
    } catch {
        assignments.value = [];
    }
}

async function loadMaintenanceHistory() {
    if (!vehicle.value?.id || !canViewMaintenance.value) {
        maintenanceRecords.value = [];

        return;
    }

    maintenanceLoading.value = true;

    try {
        const response = await listVehicleMaintenanceHistory(vehicle.value.id, { perPage: 10 });
        maintenanceRecords.value = response.data ?? [];
    } catch {
        maintenanceRecords.value = [];
    } finally {
        maintenanceLoading.value = false;
    }
}

async function loadVehicle() {
    const vehicleId = route.params.vehicleId;

    if (needsCompanySelection.value || !vehicleId) {
        vehicle.value = null;
        assignments.value = [];
        loading.value = false;

        return;
    }

    loading.value = true;

    try {
        vehicle.value = await fetchVehicle(vehicleId);
        await Promise.all([
            loadAssignments(),
            loadMaintenanceHistory(),
            loadFuelHistory(),
            loadVehicleBatteries(),
        ]);
    } catch (error) {
        vehicle.value = null;
        assignments.value = [];
        maintenanceRecords.value = [];
        fuelSummary.value = null;
        fuelRecords.value = [];

        if (!handleCompanyContextError(error, 'Could not load truck details.')) {
            toast.error('Could not load truck details.');
        }
    } finally {
        loading.value = false;
    }
}

function openEdit() {
    clearErrors();
    showForm.value = true;
}

function closeForm() {
    showForm.value = false;
    clearErrors();
}

function openDocuments() {
    showDocuments.value = true;
}

function closeDocuments() {
    showDocuments.value = false;
}

function openAssignment() {
    showAssignment.value = true;
}

function closeAssignment() {
    showAssignment.value = false;
}

function openMaintenanceCreate() {
    if (!vehicle.value?.id) {
        return;
    }

    router.push({ name: 'maintenance.create', query: { vehicle_id: vehicle.value.id } });
}

async function onAssignmentSaved(action) {
    if (action === 'save' || action === 'remove') {
        closeAssignment();
    }
    
    if (action === 'save') {
        toast.success('Driver assignment saved.');
    } else if (action === 'remove') {
        toast.success('Driver removed successfully.');
    } else if (action === 'delete') {
        toast.success('Assignment history deleted successfully.');
    } else {
        toast.success('Driver assignment saved.');
    }
    await loadVehicle();
}

async function handleSubmit(payload) {
    if (!vehicle.value?.id) {
        return;
    }

    saving.value = true;
    clearErrors();

    try {
        vehicle.value = await updateVehicle(vehicle.value.id, payload);
        toast.success('Truck updated successfully.');
        closeForm();
    } catch (error) {
        if (!applyApiError(error, 'Please check the highlighted fields.')) {
            if (!handleCompanyContextError(error, 'Select a company before saving.')) {
                toast.error('Unable to save truck.');
            }
        }
    } finally {
        saving.value = false;
    }
}

onMounted(async () => {
    const ok = await guardCompanyContext();

    if (ok === true) {
        await loadVehicle();
    } else {
        loading.value = false;
    }
});

watch(() => route.params.vehicleId, () => {
    loadVehicle();
});
</script>
