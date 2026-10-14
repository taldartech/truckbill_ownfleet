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
                    mode="edit"
                    :can-change-truck="canChangeTruck"
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
                    :primary-driver-name="primaryDriverName"
                    :loading-driver="loadingDriver"
                    :driver-warning="driverWarning"
                    :drivers="drivers"
                    :vehicle-odometer-km="vehicleOdometerKm"
                    :vehicle-expected-mileage="vehicleExpectedMileage"
                    :vehicle-fuel-tank-capacity="vehicleFuelTankCapacity"
                    :display-status="originalStatus"
                    :vehicle-make="vehicleMake"
                    :vehicle-model="vehicleModel"
                    @vehicle-selected="onVehicleSelected"
                    @add-stop="handleAddStop"
                    @return-to-start="handleReturnToStart"
                    @remove-route-leg="removeRouteLeg"
                    @start-station-change="onStartStationChange"
                    @stop-change="onStopStationChange"
                    @station-created="onStationCreated"
                    @broker-created="onBrokerCreated"
                    @customer-created="onCustomerCreated"
                    @goods-type-created="onGoodsTypeCreated"
                    @clear-field-error="clearFieldError"
                />

                <div class="trip-form-actions trip-form-actions--inline">
                    <div class="d-flex flex-wrap gap-2 justify-content-center">
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
                            variant="warning"
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
    updateTrip,
    validateTripPayload,
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
import { useVehicleDriverSync } from '../../composables/useVehicleDriverSync';
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
    () => canInCompany('trips.expenses') && canManageTripExpenses(originalStatus.value, user.value, tripEditOptions.value),
);
const editAccess = computed(() => resolveTripEditAccess(originalStatus.value, user.value, tripEditOptions.value));
const canSave = computed(() => editAccess.value.fullEdit);
const canEditTripFields = computed(() => editAccess.value.fullEdit);
const canChangeTruck = computed(
    () => originalStatus.value === 'PENDING' && savedExpenses.value.length === 0 && canEditTripFields.value,
);
const { driverWarning, loadingDriver, primaryDriverName, setPrimaryDriverFromSelection } = useVehicleDriverSync(form, {
    enabled: canChangeTruck,
});
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

    const status = String(originalStatus.value ?? '').toUpperCase();
    const label = status === 'CANCELLED'
        ? 'cancelled'
        : status === 'COMPLETED' || status === 'INVOICED'
          ? 'completed'
          : 'read-only';

    return `This trip is ${label} and is shown in read-only mode.`;
});

const { applyWindowValidation } = useTripWindowValidation(form, {
    fieldErrors,
    clearFieldError,
    ignoreTripId: computed(() => route.params.id),
});

function onVehicleSelected(vehicle) {
    if (!canChangeTruck.value) {
        return;
    }

    setPrimaryDriverFromSelection(vehicle);
    snapshotVehicleLabel.value = vehicle?.registration_number ?? '—';
    snapshotDriverName.value = vehicle?.primary_driver_name ?? primaryDriverName.value ?? '—';

    vehicleOdometerKm.value =
        vehicle?.odometer_km !== undefined && vehicle?.odometer_km !== null && vehicle?.odometer_km !== ''
            ? vehicle.odometer_km
            : '';
    vehicleExpectedMileage.value =
        vehicle?.default_mileage_km_per_liter ?? vehicle?.expected_mileage_km_per_liter ?? '';
    vehicleFuelTankCapacity.value =
        vehicle?.fuel_tank_capacity_ltr !== undefined && vehicle?.fuel_tank_capacity_ltr !== null && vehicle?.fuel_tank_capacity_ltr !== ''
            ? vehicle.fuel_tank_capacity_ltr
            : '';
    vehicleMake.value = vehicle?.make ?? '';
    vehicleModel.value = vehicle?.model ?? '';

    // USER REQUIREMENT:
    // Only driver auto-fills from the new truck.
    // Truck-related fuel & meter fields must be BLANKED OUT!
    // Customer, route legs, consignor, consignee, etc., remain intact.
    form.start_meter_reading_km = '';
    form.planned_mileage_km_per_liter = '';
    form.planned_diesel_liters = '';
    form.planned_diesel_manually_set = true;

    clearFieldError('vehicle_id');
    clearFieldError('driver_id');
    clearFieldError('start_meter_reading_km');
    clearFieldError('planned_diesel_liters');
    clearFieldError('planned_mileage_km_per_liter');
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
    const [
        stationsRes,
        brokersRes,
        customersRes,
        goodsTypesRes,
        driversRes,
    ] = await Promise.allSettled([
        listAllStations(),
        listAllBrokers(),
        listAllCustomers(),
        listAllGoodsTypes(),
        listAllDrivers({ activeOnly: true }),
    ]);

    if (stationsRes.status === 'fulfilled') {
        stations.value = stationsRes.value ?? [];
    }
    if (brokersRes.status === 'fulfilled') {
        brokers.value = brokersRes.value ?? [];
    }
    if (customersRes.status === 'fulfilled') {
        customers.value = customersRes.value ?? [];
    }
    if (goodsTypesRes.status === 'fulfilled') {
        goodsTypes.value = goodsTypesRes.value ?? [];
    }
    if (driversRes.status === 'fulfilled') {
        drivers.value = driversRes.value ?? [];
    }
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

        if (String(trip.trip_type ?? '').toUpperCase() === 'MULTI_POINT') {
            await router.replace({ name: 'trips.multi-point.edit', params: { id: trip.id } });

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

        populateFromTrip(trip, customers.value);
        savedExpenses.value = trip.expenses ?? [];
        form.trip_number = trip.trip_number ?? '';
        originalStatus.value = trip.status ?? 'PENDING';
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
    const payload = buildPayload(stations.value, { mode: 'edit', includeExpenses: false, customers: customers.value });
    if (canChangeTruck.value && form.vehicle_id) {
        payload.vehicle_id = form.vehicle_id;
        payload.driver_id = form.driver_id || null;
    }
    originalConfirmPayload.value = JSON.parse(JSON.stringify(payload));
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

    const payload = buildPayload(stations.value, { mode: 'edit', includeExpenses: false, customers: customers.value });
    if (canChangeTruck.value && form.vehicle_id) {
        payload.vehicle_id = form.vehicle_id;
        payload.driver_id = form.driver_id || null;
    }
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
        const firstError = Object.values(clientErrors).flat()[0] ?? 'Please fix the error below before saving.';
        tripFormError.value = firstError;
        toast.error(firstError);
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
        populateFromTrip(trip, customers.value);
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
    padding: 24px 20px 40px;
}
</style>
