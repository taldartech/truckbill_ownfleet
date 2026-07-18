<template>
    <div class="trip-edit-redesigned-page app-index-page">
        <div v-if="needsCompanySelection" class="alert alert-warning">
            Select a company and click <strong>Enter</strong> before editing trips.
        </div>

        <div v-else-if="loading" class="text-center py-5 text-muted">Loading trip…</div>

        <template v-else>
            <div v-if="terminalAlert" class="alert alert-secondary mb-3">
                <i class="bi bi-lock me-1"></i>
                {{ terminalAlert }}
            </div>

            <form novalidate @submit.prevent="handleSubmit('index')">
                <TripFormRedesigned
                    ref="tripFormRef"
                    mode="edit"
                    :show-expenses="false"
                    :form="form"
                    :stations="stations"
                    :brokers="brokers"
                    :customers="customers"
                    :goods-types="goodsTypes"
                    :readonly="isFormReadonly"
                    :expense-totals-by-sequence="expenseTotalsBySequence"
                    :can-quick-add-station="canQuickAddStation && canEditTripFields"
                    :can-quick-add-broker="canQuickAddBroker && canEditTripFields"
                    :can-quick-add-customer="canQuickAddCustomer && canEditTripFields"
                    :can-quick-add-goods-type="canQuickAddGoodsType && canEditTripFields"
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
                    :vehicle-fuel-tank-capacity="vehicleFuelTankCapacity"
                    :status-locked="isTerminalStatus"
                    :display-status="originalStatus"
                    :vehicle-make="vehicleMake"
                    :vehicle-model="vehicleModel"
                    status-actions-in-footer
                    @add-stop="handleAddStop"
                    @return-to-start="handleReturnToStart"
                    @remove-route-leg="removeRouteLeg"
                    @start-station-change="onStartStationChange"
                    @stop-change="onStopStationChange"
                    @station-created="onStationCreated"
                    @broker-created="onBrokerCreated"
                    @customer-created="onCustomerCreated"
                    @goods-type-created="onGoodsTypeCreated"
                    @status-updated="handleStatusUpdated"
                    @clear-field-error="clearFieldError"
                />

                <div class="trip-form-actions card shadow-sm mt-4">
                    <div class="card-body d-flex flex-wrap gap-2 justify-content-end">
                        <template v-if="footerStatusActions.length > 0">
                            <AppButton
                                v-for="action in footerStatusActions"
                                :key="action.value"
                                :variant="action.variant"
                                size="sm"
                                :icon="action.icon"
                                :label="action.label"
                                @click="handleFooterStatusChange(action.value)"
                            />
                        </template>
                        <AppButton
                            v-else-if="canManageExpenses"
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
                            label="Cancel"
                            :to="tripListReturnTo"
                        />
                        <AppButton
                            v-if="canSave"
                            type="submit"
                            variant="primary"
                            size="sm"
                            icon="bi bi-floppy-fill"
                            label="Update"
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
                :confirm-mode="saveMode"
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
import { listAllGoodsTypes } from '../../api/goodsTypes';
import { listAllDrivers } from '../../api/drivers';
import { listAllStations } from '../../api/stations';
import {
    canFullEditTrip,
    canManageTripExpenses,
    expenseTotalsBySequence as computeExpenseTotalsBySequence,
    fetchTrip,
    isTripArchived,
    resolveTripEditAccess,
    tripAllowedNextStatuses,
    updateTrip,
    validateTripPayload,
    validateTripStatusChange,
} from '../../api/trips';
import TripFormRedesigned from '../../components/trips/TripFormRedesigned.vue';
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
    buildStatusChangePayload,
    onStartStationChange,
    onStopStationChange,
    syncLegCustomersFromTrip,
} = useTripForm();

const canQuickAddStation = computed(
    () => canInCompany('stations.manage') || canInCompany('trips.edit'),
);
const canQuickAddBroker = computed(
    () => canInCompany('brokers.manage') || canInCompany('trips.edit'),
);
const canQuickAddCustomer = computed(
    () => canInCompany('customers.manage') || canInCompany('trips.edit'),
);
const canQuickAddGoodsType = computed(
    () =>
        canInCompany('goods_types.manage')
        || canInCompany('goods_types.create')
        || canInCompany('trips.edit')
        || canInCompany('trips.create'),
);

const stations = ref([]);
const brokers = ref([]);
const customers = ref([]);
const goodsTypes = ref([]);
const drivers = ref([]);
const loading = ref(true);
const saving = ref(false);
const snapshotVehicleLabel = ref('');
const vehicleOdometerKm = ref('');
const vehicleExpectedMileage = ref('');
const vehicleFuelTankCapacity = ref('');
const vehicleMake = ref('');
const vehicleModel = ref('');
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
const saveMode = ref('index');

const isTerminalStatus = computed(
    () => originalStatus.value === 'COMPLETED' || originalStatus.value === 'CANCELLED',
);

const canManage = computed(() => canInCompany('trips.edit'));
const hasInvoices = ref(false);
const tripEditOptions = computed(() => ({ has_invoices: hasInvoices.value }));
const canManageExpenses = computed(
    () => canManage.value && canManageTripExpenses(originalStatus.value, user.value, tripEditOptions.value),
);
const editAccess = computed(() => resolveTripEditAccess(originalStatus.value, user.value, tripEditOptions.value));
const canSave = computed(() => editAccess.value.fullEdit);
const canEditTripFields = computed(() => editAccess.value.fullEdit);
const isFormReadonly = computed(() => !editAccess.value.fullEdit);
const expenseTotalsBySequence = computed(() => computeExpenseTotalsBySequence(savedExpenses.value));

const terminalAlert = computed(() => {
    if (editAccess.value.invoiceLocked) {
        return 'This trip has invoices and cannot be edited.';
    }

    if (!isTerminalStatus.value) {
        return '';
    }

    if (editAccess.value.fullEdit) {
        return '';
    }

    const label = originalStatus.value === 'COMPLETED' ? 'completed' : 'cancelled';

    return `This trip is ${label} and is shown in read-only mode.`;
});

const tripFormRef = ref(null);

const { applyWindowValidation } = useTripWindowValidation(form, {
    fieldErrors,
    clearFieldError,
    ignoreTripId: computed(() => route.params.id),
});

const footerStatusActions = computed(() => {
    if (isTerminalStatus.value || isFormReadonly.value) {
        return [];
    }

    return tripAllowedNextStatuses(originalStatus.value);
});

function hasUnsavedTripChanges() {
    if (!originalConfirmPayload.value) {
        return false;
    }

    syncLegCustomersFromTrip();

    const currentPayload = buildPayload(stations.value, { mode: 'edit', includeExpenses: false });

    return JSON.stringify(currentPayload) !== JSON.stringify(originalConfirmPayload.value);
}

async function savePendingTripDetailsIfNeeded() {
    if (!hasUnsavedTripChanges()) {
        return true;
    }

    toast.info('Saving trip details before changing status…');

    const payload = await prepareSave();

    if (!payload) {
        toast.error('Please fix the errors below and click Update before changing trip status.');

        return false;
    }

    try {
        await executeSave(payload, { showToast: false });
        toast.success('Trip details saved.');

        return true;
    } catch {
        return false;
    }
}

async function handleFooterStatusChange(status) {
    const saved = await savePendingTripDetailsIfNeeded();

    if (!saved) {
        return;
    }

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

function onGoodsTypeCreated({ goodsType, legIndex = null }) {
    goodsTypes.value = [
        goodsType,
        ...goodsTypes.value.filter((item) => String(item.id) !== String(goodsType.id)),
    ];

    if (legIndex !== null && form.route_legs[legIndex]) {
        form.route_legs[legIndex].goods_name = goodsType.name;
        clearFieldError(`route_legs.${legIndex}.goods_name`);
    }
}

function handleAddStop() {
    if (!form.start_station_id) {
        fieldErrors.value = {
            ...fieldErrors.value,
            start_station_id: ['Select the source (starting point) before adding a stop.'],
        };
        toast.warning('Select the source (starting point) first.');
        scrollToFirstFormError();

        return;
    }

    clearFieldError('start_station_id');
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
    const [stationList, brokerList, customerList, goodsTypeList, driverList] = await Promise.all([
        listAllStations(),
        listAllBrokers(),
        listAllCustomers(),
        listAllGoodsTypes(),
        listAllDrivers({ activeOnly: true }),
    ]);

    stations.value = stationList;
    brokers.value = brokerList;
    customers.value = customerList;
    goodsTypes.value = goodsTypeList;
    drivers.value = driverList;
}

async function loadTrip() {
    loading.value = true;
    clearErrors();

    try {
        const trip = await fetchTrip(route.params.id);

        if (isTripArchived(trip)) {
            toast.error('This trip has been archived and cannot be edited.');
            await router.replace({ name: 'trips.archived' });

            return;
        }

        hasInvoices.value = Boolean(trip.has_invoices);

        if (!canFullEditTrip(trip.status, user.value, { has_invoices: hasInvoices.value })) {
            const access = resolveTripEditAccess(trip.status, user.value, { has_invoices: hasInvoices.value });

            if (access.expensesOnly) {
                toast.error(
                    access.invoiceLocked
                        ? 'This trip has invoices and cannot be edited. Only expenses can be updated.'
                        : 'Trip details are locked. Redirecting to expenses.',
                );
                await router.replace({ name: 'trips.expenses', params: { id: trip.id } });

                return;
            }

            toast.error(
                access.invoiceLocked
                    ? 'This trip has invoices and cannot be edited.'
                    : 'This trip cannot be edited.',
            );
            await router.replace({ name: 'trips.show', params: { id: trip.id } });

            return;
        }

        populateFromTrip(trip);
        savedExpenses.value = trip.expenses ?? [];
        form.trip_number = trip.trip_number ?? '';
        originalStatus.value = trip.status ?? 'CREATED';
        snapshotVehicleLabel.value = trip.vehicle?.registration_number ?? '—';
        vehicleOdometerKm.value = trip.vehicle?.odometer_km ?? '';
        vehicleExpectedMileage.value = trip.vehicle?.default_mileage_km_per_liter ?? '';
        vehicleFuelTankCapacity.value = trip.vehicle?.fuel_tank_capacity_ltr ?? '';
        vehicleMake.value = trip.vehicle?.make ?? '';
        vehicleModel.value = trip.vehicle?.model ?? '';
        snapshotDriverName.value = trip.driver?.name ?? '—';
        snapshotSubstituteDriverName.value = trip.substitute_driver?.name ?? '';
        snapshotCustomerName.value = trip.customer_id ? (trip.customer_name ?? '') : '';
        snapshotBrokerName.value = trip.broker_id ? (trip.broker_name ?? '') : '';
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
        showExpensesAction: canManageExpenses.value,
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
            tripType: form.trip_type,
            hasInvoices: hasInvoices.value,
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
        snapshotCustomerName.value = trip.customer_id ? (trip.customer_name ?? '') : '';
        snapshotBrokerName.value = trip.broker_id ? (trip.broker_name ?? '') : '';
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
    if (!canManage.value) {
        return;
    }

    const previousStatus = originalStatus.value;
    clearErrors();

    syncLegCustomersFromTrip();

    const payload = buildStatusChangePayload();
    const clientErrors = validateTripStatusChange(payload, {
        tripType: form.trip_type,
        routeLegs: form.route_legs,
        startStationId: form.start_station_id,
        previousStatus: originalStatus.value,
        user: user.value,
        startMeterReadingKm: form.start_meter_reading_km,
        hasInvoices: hasInvoices.value,
    });

    if (Object.keys(clientErrors).length > 0) {
        form.status = previousStatus;
        fieldErrors.value = clientErrors;
        tripFormError.value = 'Could not update trip status.';
        toast.error(Object.values(clientErrors).flat()[0] ?? tripFormError.value);
        scrollToFirstFormError();

        return;
    }

    try {
        await executeSave(payload, {
            successMessage: `Trip marked as ${
                form.status === 'COMPLETED'
                    ? 'completed'
                    : form.status === 'CANCELLED'
                      ? 'cancelled'
                      : 'in progress'
            }.`,
        });
    } catch {
        form.status = previousStatus;
    }
}

function cancelSaveConfirm() {
    showSaveConfirm.value = false;
    pendingPayload.value = null;
}

async function confirmSave(mode = 'index') {
    if (!pendingPayload.value) {
        return;
    }

    saveMode.value = mode;

    try {
        await executeSave(pendingPayload.value, { showToast: false });
        showSaveConfirm.value = false;
        pendingPayload.value = null;

        if (saveMode.value === 'expenses') {
            await router.push({ name: 'trips.expenses', params: { id: route.params.id } });

            return;
        }

        await router.push(tripListReturnWithFlash({ updated: '1' }));
    } catch {
        showSaveConfirm.value = false;
    }
}

async function handleSubmit(mode = 'index') {
    if (!canSave.value) {
        return;
    }

    saveMode.value = mode;
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

<style scoped>
.trip-edit-redesigned-page {
    max-width: 1180px;
    margin: 0 auto;
    padding: 24px 20px 80px;
}
.trip-form-actions {
    border-radius: 12px;
    border: 1px solid #dee2e6;
}
</style>
