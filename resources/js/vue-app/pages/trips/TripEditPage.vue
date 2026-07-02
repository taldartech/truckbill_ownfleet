<template>
    <div class="container mt-4 pb-5 app-index-page trip-edit-page">
        <div v-if="needsCompanySelection" class="alert alert-warning">
            Select a company and click <strong>Enter</strong> before editing trips.
        </div>

        <div v-else-if="loading" class="text-center py-5 text-muted">Loading trip…</div>

        <template v-else>
            <div v-if="terminalAlert" class="alert alert-secondary mb-3">
                <i class="bi bi-lock me-1"></i>
                {{ terminalAlert }}
            </div>

            <form novalidate @submit.prevent="handleSubmit">
                <TripForm
                    ref="tripFormRef"
                    mode="edit"
                    :show-expenses="false"
                    :form="form"
                    :stations="stations"
                    :brokers="brokers"
                    :customers="customers"
                    :readonly="isFormReadonly"
                    :expense-totals-by-sequence="expenseTotalsBySequence"
                    :can-quick-add-station="canQuickAddStation && canEditTripFields"
                    :can-quick-add-broker="canQuickAddBroker && canEditTripFields"
                    :can-quick-add-customer="canQuickAddCustomer && canEditTripFields"
                    :snapshot-customer-name="snapshotCustomerName"
                    :snapshot-broker-name="snapshotBrokerName"
                    :form-error="tripFormError"
                    :field-errors="fieldErrors"
                    :trip-number-display="form.trip_number"
                    :snapshot-vehicle-label="snapshotVehicleLabel"
                    :snapshot-driver-name="snapshotDriverName"
                    :snapshot-substitute-driver-name="snapshotSubstituteDriverName"
                    :drivers="drivers"
                    :vehicle-odometer-km="vehicleOdometerKm"
                    :vehicle-expected-mileage="vehicleExpectedMileage"
                    :status-locked="isTerminalStatus"
                    @add-stop="handleAddStop"
                    @return-to-start="handleReturnToStart"
                    @remove-route-leg="removeRouteLeg"
                    @start-station-change="onStartStationChange"
                    @stop-change="onStopStationChange"
                    @station-created="onStationCreated"
                    @broker-created="onBrokerCreated"
                    @customer-created="onCustomerCreated"
                    @status-updated="handleStatusUpdated"
                    @clear-field-error="clearFieldError"
                >
                    <template #trip-nav-actions>
                        <RouterLink
                            :to="tripListReturnTo"
                            class="btn btn-sm trip-header-btn trip-header-btn--back"
                        >
                            <i class="bi bi-arrow-left me-1"></i>Back
                        </RouterLink>
                    </template>
                    <template #trip-save-actions>
                        <button
                            v-if="canSave"
                            type="submit"
                            class="btn btn-sm trip-header-btn trip-header-btn--update"
                            :disabled="saving"
                        >
                            <span
                                v-if="saving"
                                class="spinner-border spinner-border-sm me-1"
                                role="status"
                                aria-hidden="true"
                            ></span>
                            <i v-else class="bi bi-floppy-fill me-1"></i>Update
                        </button>
                    </template>
                </TripForm>

                <div class="trip-form-actions card shadow-sm">
                    <div class="card-body d-flex flex-wrap gap-2 justify-content-end">
                        <template v-if="showStatusFooterActions">
                            <AppButton
                                variant="success"
                                size="sm"
                                icon="bi bi-check-circle-fill"
                                label="Completed"
                                @click="handleFooterStatusChange('COMPLETED')"
                            />
                            <AppButton
                                variant="danger"
                                size="sm"
                                icon="bi bi-x-circle-fill"
                                label="Cancelled"
                                @click="handleFooterStatusChange('CANCELLED')"
                            />
                        </template>
                        <AppButton
                            v-if="canManageExpenses"
                            variant="success"
                            outline
                            size="sm"
                            icon="bi bi-receipt"
                            label="Manage Expenses"
                            :to="{ name: 'trips.expenses', params: { id: route.params.id } }"
                        />
                        <AppButton
                            variant="secondary"
                            outline
                            size="sm"
                            icon="bi bi-x-lg"
                            label="Exit"
                            :to="tripListReturnTo"
                        />
                        <AppButton
                            v-if="canSave"
                            type="submit"
                            variant="primary"
                            size="sm"
                            icon="bi bi-floppy-fill"
                            label="Save"
                            :loading="saving"
                            :disabled="saving"
                        />
                    </div>
                </div>
            </form>

            <TripSaveConfirmModal
                :show="showSaveConfirm"
                :summary="saveConfirmSummary"
                :saving="saving"
                @cancel="cancelSaveConfirm"
                @confirm="confirmSave"
            />
        </template>
    </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { listAllBrokers } from '../../api/brokers';
import { listAllCustomers } from '../../api/customers';
import { listAllDrivers } from '../../api/drivers';
import { listAllStations } from '../../api/stations';
import {
    canFullEditTrip,
    canManageTripExpenses,
    expenseTotalsBySequence as computeExpenseTotalsBySequence,
    fetchTrip,
    resolveTripEditAccess,
    updateTrip,
    validateTripPayload,
} from '../../api/trips';
import TripForm from '../../components/trips/TripForm.vue';
import TripSaveConfirmModal from '../../components/trips/TripSaveConfirmModal.vue';
import AppButton from '../../components/ui/AppButton.vue';
import { useApiFormErrors } from '../../composables/useApiFormErrors';
import { useAppToast } from '../../composables/useAppToast';
import { usePermissions } from '../../composables/usePermissions';
import { useRequireCompanyContext } from '../../composables/useRequireCompanyContext';
import { useTripForm } from '../../composables/useTripForm';
import { useTripListReturn } from '../../composables/useTripListReturn';
import { useTripWindowValidation } from '../../composables/useTripWindowValidation';
import { scrollToFirstFormError } from '../../utils/formErrors';
import { buildTripSaveConfirmViewModel } from '../../utils/tripSaveConfirm';

const route = useRoute();
const router = useRouter();
const toast = useAppToast();
const { needsCompanySelection } = useRequireCompanyContext();
const { tripListReturnTo, tripListReturnWithFlash } = useTripListReturn();
const { canInCompany, user } = usePermissions();
const { fieldErrors, formError: tripFormError, clearErrors, clearFieldError, applyApiError } = useApiFormErrors();

const {
    form,
    populateFromTrip,
    addStop,
    addReturnToStart,
    removeRouteLeg,
    buildPayload,
    onStartStationChange,
    onStopStationChange,
    applyLegacyPartyLookups,
} = useTripForm();

const canQuickAddStation = computed(
    () => canInCompany('stations.manage') || canInCompany('trips.manage'),
);
const canQuickAddBroker = computed(
    () => canInCompany('brokers.manage') || canInCompany('trips.manage'),
);
const canQuickAddCustomer = computed(
    () => canInCompany('customers.manage') || canInCompany('trips.manage'),
);

const stations = ref([]);
const brokers = ref([]);
const customers = ref([]);
const drivers = ref([]);
const loading = ref(true);
const saving = ref(false);
const snapshotVehicleLabel = ref('');
const vehicleOdometerKm = ref('');
const vehicleExpectedMileage = ref('');
const snapshotDriverName = ref('');
const snapshotSubstituteDriverName = ref('');
const snapshotCustomerName = ref('');
const snapshotBrokerName = ref('');
const originalStatus = ref('');
const originalConfirmPayload = ref(null);
const savedExpenses = ref([]);
const showSaveConfirm = ref(false);
const saveConfirmSummary = ref(buildTripSaveConfirmViewModel({ route_legs: [], expenses: [] }));
const pendingPayload = ref(null);

const isTerminalStatus = computed(
    () => form.status === 'COMPLETED' || form.status === 'CANCELLED',
);

const canManage = computed(() => canInCompany('trips.manage'));
const canManageExpenses = computed(
    () => canManage.value && canManageTripExpenses(form.status, user.value),
);
const editAccess = computed(() => resolveTripEditAccess(form.status, user.value));
const canSave = computed(() => editAccess.value.fullEdit);
const canEditTripFields = computed(() => editAccess.value.fullEdit);
const isFormReadonly = computed(() => !editAccess.value.fullEdit);
const expenseTotalsBySequence = computed(() => computeExpenseTotalsBySequence(savedExpenses.value));

const terminalAlert = computed(() => {
    if (!isTerminalStatus.value) {
        return '';
    }

    if (editAccess.value.fullEdit) {
        return '';
    }

    const label = form.status === 'COMPLETED' ? 'completed' : 'cancelled';

    return `This trip is ${label} and is shown in read-only mode.`;
});

const tripFormRef = ref(null);

const { applyWindowValidation } = useTripWindowValidation(form, {
    fieldErrors,
    clearFieldError,
    ignoreTripId: computed(() => route.params.id),
});

const showStatusFooterActions = computed(
    () => !isTerminalStatus.value && String(form.status ?? '').toUpperCase() === 'IN_PROGRESS',
);

function handleFooterStatusChange(status) {
    tripFormRef.value?.changeStatus(status);
}

function onStationCreated({ station, legIndex }) {
    stations.value = [station, ...stations.value.filter((s) => s.id !== station.id)];

    if (legIndex !== null && form.route_legs[legIndex]) {
        form.route_legs[legIndex].to_station_id = station.id;
        onStopStationChange(legIndex);
    }
}

function onBrokerCreated(broker) {
    brokers.value = [broker, ...brokers.value.filter((item) => item.id !== broker.id)];
    form.broker_id = broker.id;
}

function onCustomerCreated({ customer, legIndex = null }) {
    customers.value = [customer, ...customers.value.filter((item) => item.id !== customer.id)];

    if (legIndex !== null && form.route_legs[legIndex]) {
        form.route_legs[legIndex].customer_id = customer.id;
    } else {
        form.customer_id = customer.id;
    }
}

function handleAddStop() {
    if (!form.start_station_id) {
        toast.warning('Select a start station first.');
        scrollToFirstFormError();

        return;
    }

    addStop();
}

function handleReturnToStart() {
    if (form.route_legs.length === 0) {
        toast.warning('Add at least one stop first.');

        return;
    }

    addReturnToStart();
    toast.info('Return leg added from start station (empty).');
}

async function loadLookups() {
    const [stationList, brokerList, customerList, driverList] = await Promise.all([
        listAllStations(),
        listAllBrokers(),
        listAllCustomers(),
        listAllDrivers({ activeOnly: true }),
    ]);

    stations.value = stationList;
    brokers.value = brokerList;
    customers.value = customerList;
    drivers.value = driverList;
}

async function loadTrip() {
    loading.value = true;
    clearErrors();

    try {
        const trip = await fetchTrip(route.params.id);

        if (!canFullEditTrip(trip.status, user.value)) {
            const access = resolveTripEditAccess(trip.status, user.value);

            if (access.expensesOnly) {
                await router.replace({ name: 'trips.expenses', params: { id: trip.id } });

                return;
            }

            toast.error('This trip cannot be edited.');
            await router.replace({ name: 'trips.show', params: { id: trip.id } });

            return;
        }

        populateFromTrip(trip);
        savedExpenses.value = trip.expenses ?? [];
        applyLegacyPartyLookups(trip, customers.value, brokers.value);
        form.trip_number = trip.trip_number ?? '';
        originalStatus.value = trip.status ?? 'CREATED';
        snapshotVehicleLabel.value = trip.vehicle?.registration_number ?? '—';
        vehicleOdometerKm.value = trip.vehicle?.odometer_km ?? '';
        vehicleExpectedMileage.value = trip.vehicle?.default_mileage_km_per_liter ?? '';
        snapshotDriverName.value = trip.driver?.name ?? '—';
        snapshotSubstituteDriverName.value = trip.substitute_driver?.name ?? '';
        snapshotCustomerName.value = trip.customer_name ?? '';
        snapshotBrokerName.value = trip.broker_name ?? '';
        refreshOriginalConfirmPayload();
    } catch (error) {
        applyApiError(error, 'Failed to load trip.');
        toast.error(tripFormError.value);
        await router.replace(tripListReturnTo);
    } finally {
        loading.value = false;
    }
}

function refreshOriginalConfirmPayload() {
    originalConfirmPayload.value = JSON.parse(
        JSON.stringify(buildPayload(stations.value, { mode: 'edit', includeExpenses: false })),
    );
}

function substituteDriverLabel() {
    if (snapshotSubstituteDriverName.value) {
        return snapshotSubstituteDriverName.value;
    }

    if (!form.substitute_driver_id) {
        return '—';
    }

    return drivers.value.find((driver) => String(driver.id) === String(form.substitute_driver_id))?.name ?? '—';
}

function buildConfirmSummary() {
    return buildTripSaveConfirmViewModel(form, {
        mode: 'edit',
        stations: stations.value,
        customers: customers.value,
        brokers: brokers.value,
        drivers: drivers.value,
        originalPayload: originalConfirmPayload.value,
        vehicleLabel: snapshotVehicleLabel.value || '—',
        driverLabel: snapshotDriverName.value || '—',
        substituteDriverLabel: substituteDriverLabel(),
        tripNumber: form.trip_number,
    });
}

async function prepareSave() {
    if (!canSave.value) {
        return null;
    }

    clearErrors();

    const payload = buildPayload(stations.value, { mode: 'edit', includeExpenses: false });
    const clientErrors = validateTripPayload(
        {
            ...payload,
            start_meter_reading_km: form.start_meter_reading_km,
            truck_running_meter_km: vehicleOdometerKm.value,
        },
        {
            mode: 'edit',
            previousStatus: originalStatus.value,
            user: user.value,
        },
    );

    if (Object.keys(clientErrors).length > 0) {
        fieldErrors.value = clientErrors;
        tripFormError.value = 'Please fix the errors below before saving.';
        toast.error(tripFormError.value);
        scrollToFirstFormError();

        return null;
    }

    try {
        const windowAvailable = await applyWindowValidation();

        if (!windowAvailable) {
            tripFormError.value = 'This truck already has another open trip in the selected date range.';
            toast.error(tripFormError.value);
            scrollToFirstFormError();

            return null;
        }
    } catch (error) {
        if (!applyApiError(error, 'Could not verify trip dates for this truck.')) {
            tripFormError.value = error?.response?.data?.message ?? 'Could not verify trip dates for this truck.';
        }

        toast.error(tripFormError.value);
        scrollToFirstFormError();

        return null;
    }

    return payload;
}

async function executeSave(payload, { successMessage = 'Trip updated successfully.', showToast = true } = {}) {
    saving.value = true;

    try {
        const trip = await updateTrip(route.params.id, payload);
        populateFromTrip(trip);
        savedExpenses.value = trip.expenses ?? [];
        form.trip_number = trip.trip_number ?? '';
        originalStatus.value = trip.status ?? form.status;
        snapshotVehicleLabel.value = trip.vehicle?.registration_number ?? snapshotVehicleLabel.value;
        snapshotDriverName.value = trip.driver?.name ?? snapshotDriverName.value;
        snapshotSubstituteDriverName.value = trip.substitute_driver?.name ?? snapshotSubstituteDriverName.value;
        snapshotCustomerName.value = trip.customer_name ?? snapshotCustomerName.value;
        snapshotBrokerName.value = trip.broker_name ?? snapshotBrokerName.value;
        applyLegacyPartyLookups(trip, customers.value, brokers.value);
        refreshOriginalConfirmPayload();
        if (showToast) {
            toast.success(successMessage);
        }
    } catch (error) {
        if (!applyApiError(error, 'Failed to update trip.')) {
            tripFormError.value = error?.response?.data?.message ?? 'Failed to update trip.';
        }

        toast.error(tripFormError.value);
        scrollToFirstFormError();

        throw error;
    } finally {
        saving.value = false;
    }
}

async function handleStatusUpdated() {
    const payload = await prepareSave();

    if (!payload) {
        return;
    }

    await executeSave(payload, {
        successMessage: `Trip marked as ${form.status === 'COMPLETED' ? 'completed' : form.status === 'CANCELLED' ? 'cancelled' : 'in progress'}.`,
    });
}

function cancelSaveConfirm() {
    showSaveConfirm.value = false;
    pendingPayload.value = null;
}

async function confirmSave() {
    if (!pendingPayload.value) {
        return;
    }

    try {
        await executeSave(pendingPayload.value, { showToast: false });
        showSaveConfirm.value = false;
        pendingPayload.value = null;
        await router.push(tripListReturnWithFlash({ updated: '1' }));
    } catch {
        showSaveConfirm.value = false;
    }
}

async function handleSubmit() {
    if (!canSave.value) {
        return;
    }

    const payload = await prepareSave();

    if (!payload) {
        return;
    }

    pendingPayload.value = payload;
    saveConfirmSummary.value = buildConfirmSummary();
    showSaveConfirm.value = true;
}

onMounted(async () => {
    await loadLookups();
    await loadTrip();
});
</script>

