<template>
    <div class="trip-create-redesigned-page app-index-page">
        <div v-if="needsCompanySelection" class="alert alert-warning">
            Select a company and click <strong>Enter</strong> before creating trips.
        </div>

        <form v-else novalidate @submit.prevent="handleSubmit('index')">
            <TripFormRedesigned
                ref="tripFormRef"
                mode="create"
                :show-expenses="false"
                :form="form"
                :stations="stations"
                :brokers="brokers"
                :customers="customers"
                :goods-types="goodsTypes"
                :can-quick-add-station="canQuickAddStation"
                :can-quick-add-broker="canQuickAddBroker"
                :can-quick-add-customer="canQuickAddCustomer"
                :can-quick-add-goods-type="canQuickAddGoodsType"
                :form-error="tripFormError"
                :field-errors="fieldErrors"
                :trip-number-display="previewTripNumber"
                :driver-warning="driverWarning"
                :loading-driver="loadingDriver"
                :primary-driver-name="primaryDriverName"
                :drivers="drivers"
                :vehicle-odometer-km="vehicleOdometerKm"
                :vehicle-expected-mileage="vehicleDefaultMileage"
                :vehicle-max-load="vehicleMaxLoad"
                :vehicle-fuel-tank-capacity="vehicleFuelTankCapacity"
                @add-stop="handleAddStop"
                @return-to-start="handleReturnToStart"
                @remove-route-leg="removeRouteLeg"
                @start-station-change="onStartStationChange"
                @stop-change="onStopStationChange"
                @station-created="onStationCreated"
                @broker-created="onBrokerCreated"
                @customer-created="onCustomerCreated"
                @goods-type-created="onGoodsTypeCreated"
                @vehicle-selected="onVehicleSelected"
                @clear-field-error="clearFieldError"
                @trip-sequence-change="onTripSequenceChange"
            />

            <div class="trip-form-actions trip-form-actions--inline">
                <div class="d-flex align-items-center justify-content-center gap-1 gap-sm-2 trip-form-actions__row">
                    <AppButton
                        variant="secondary"
                        outline
                        size="sm"
                        icon="bi bi-x-lg"
                        label="Cancel"
                        @click="handleCancel"
                    />
                    <AppButton
                        type="button"
                        variant="secondary"
                        outline
                        size="sm"
                        icon="bi bi-eraser"
                        label="Clear"
                        :disabled="saving"
                        @click="handleClearForm"
                    />
                    <AppButton
                        type="submit"
                        variant="success"
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
            :confirm-mode="saveMode"
            @cancel="cancelSaveConfirm"
            @confirm="confirmSave"
        />
    </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { listAllBrokers } from '../../api/brokers';
import { listAllCustomers } from '../../api/customers';
import { listAllDrivers } from '../../api/drivers';
import { listAllGoodsTypes } from '../../api/goodsTypes';
import { listAllStations } from '../../api/stations';
import { createTrip, fetchNextTripNumber, validateTripPayload } from '../../api/trips';
import { fetchVehicle } from '../../api/vehicles';
import { useTripWindowValidation } from '../../composables/useTripWindowValidation';
import TripFormRedesigned from '../../components/trips/TripFormRedesigned.vue';
import TripSaveConfirmModal from '../../components/trips/TripSaveConfirmModal.vue';
import AppButton from '../../components/ui/AppButton.vue';
import { useApiFormErrors } from '../../composables/useApiFormErrors';
import { useAppToast } from '../../composables/useAppToast';
import { usePermissions } from '../../composables/usePermissions';
import { useRequireCompanyContext } from '../../composables/useRequireCompanyContext';
import { useTripForm } from '../../composables/useTripForm';
import { useTripListReturn } from '../../composables/useTripListReturn';
import { useVehicleDriverSync } from '../../composables/useVehicleDriverSync';
import { toDateInputValue } from '../../utils/datetime';
import { scrollToFirstFormError } from '../../utils/formErrors';
import { buildTripSaveConfirmViewModel } from '../../utils/tripSaveConfirm';

const router = useRouter();
const route = useRoute();
const toast = useAppToast();
const { needsCompanySelection } = useRequireCompanyContext();
const { tripListReturnTo, tripListReturnWithFlash } = useTripListReturn();
const { canInCompany } = usePermissions();
const { fieldErrors, formError: tripFormError, clearErrors, clearFieldError, applyApiError } = useApiFormErrors();

const {
    form,
    resetForm,
    addStop,
    addReturnToStart,
    removeRouteLeg,
    buildPayload,
    onStartStationChange,
    onStopStationChange,
} = useTripForm();

const canQuickAddStation = computed(
    () => canInCompany('stations.manage') || canInCompany('trips.create'),
);
const canQuickAddBroker = computed(
    () => canInCompany('brokers.manage') || canInCompany('trips.create'),
);
const canQuickAddCustomer = computed(
    () => canInCompany('customers.manage') || canInCompany('trips.create'),
);
const canQuickAddGoodsType = computed(
    () =>
        canInCompany('goods_types.manage')
        || canInCompany('goods_types.create')
        || canInCompany('trips.create'),
);

const stations = ref([]);
const brokers = ref([]);
const customers = ref([]);
const goodsTypes = ref([]);
const drivers = ref([]);
const saving = ref(false);
const tripFormRef = ref(null);
const previewTripNumber = ref('');
const showSaveConfirm = ref(false);
const saveConfirmSummary = ref(buildTripSaveConfirmViewModel({ route_legs: [], expenses: [] }));
const pendingPayload = ref(null);
const saveMode = ref('index');
const selectedVehicleLabel = ref('');
const vehicleOdometerKm = ref('');
const vehicleDefaultMileage = ref('');
const vehicleMaxLoad = ref('');
const vehicleFuelTankCapacity = ref('');

const { driverWarning, loadingDriver, primaryDriverName, setPrimaryDriverFromSelection } = useVehicleDriverSync(form);
const { applyWindowValidation } = useTripWindowValidation(form, {
    fieldErrors,
    clearFieldError,
});

function onVehicleSelected(vehicle) {
    if (!vehicle) {
        form.vehicle_id = '';
        setPrimaryDriverFromSelection(null);
        selectedVehicleLabel.value = '';
        vehicleOdometerKm.value = '';
        vehicleDefaultMileage.value = '';
        vehicleMaxLoad.value = '';
        vehicleFuelTankCapacity.value = '';
        form.planned_mileage_km_per_liter = '';
        form.start_meter_reading_km = '';
        form.end_meter_reading_km = '';
        form.planned_diesel_liters = '';
        form.max_load_ton = '';
        form.planned_diesel_manually_set = false;
        return;
    }

    setPrimaryDriverFromSelection(vehicle);
    selectedVehicleLabel.value = vehicle?.registration_number ?? '—';
    vehicleOdometerKm.value =
        vehicle?.odometer_km !== undefined && vehicle?.odometer_km !== null && vehicle?.odometer_km !== ''
            ? vehicle.odometer_km
            : '';
    const defaultMileage =
        vehicle?.default_mileage_km_per_liter ?? vehicle?.expected_mileage_km_per_liter ?? 0;
    const maxLoad = vehicle?.max_load_ton ?? 0;
    const fuelTankCapacity = vehicle?.fuel_tank_capacity_ltr ?? 0;

    form.planned_mileage_km_per_liter =
        defaultMileage !== '' && defaultMileage != null ? Number(defaultMileage) : 0;
    form.max_load_ton = maxLoad !== '' && maxLoad != null ? Number(maxLoad) : 0;
    form.planned_diesel_manually_set = false;

    vehicleDefaultMileage.value = defaultMileage !== '' && defaultMileage != null ? defaultMileage : '';
    vehicleMaxLoad.value = maxLoad !== '' && maxLoad != null ? maxLoad : '';
    vehicleFuelTankCapacity.value =
        fuelTankCapacity !== '' && fuelTankCapacity != null ? fuelTankCapacity : '';
}

function onStationCreated({ station, legIndex }) {
    stations.value = [station, ...stations.value.filter((s) => s.id !== station.id)];

    if (legIndex !== null && form.route_legs[legIndex]) {
        form.route_legs[legIndex].to_station_id = station.id;
        onStopStationChange(legIndex);
    } else if (!form.start_station_id) {
        form.start_station_id = station.id;
        onStartStationChange();
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
    if (!form.vehicle_id) {
        toast.warning('Select a truck first.');
        scrollToFirstFormError();

        return;
    }

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

function handleClearForm() {
    const tripType = 'ONE_WAY';
    resetForm();
    form.trip_type = tripType;
    form.start_at = toDateInputValue();
    clearErrors();
    selectedVehicleLabel.value = '';
    vehicleOdometerKm.value = '';
    vehicleDefaultMileage.value = '';
    vehicleMaxLoad.value = '';
    vehicleFuelTankCapacity.value = '';
    tripFormRef.value?.clearProgressDraft?.();
    refreshTripNumberPreview({ syncSequence: true });
}

function handleCancel() {
    router.push(tripListReturnTo.value);
}

async function hydrateRestoredVehicle(vehicleId) {
    try {
        const vehicle = await fetchVehicle(vehicleId);

        selectedVehicleLabel.value = vehicle?.registration_number ?? '—';
        vehicleOdometerKm.value =
            vehicle?.odometer_km !== undefined && vehicle?.odometer_km !== null && vehicle?.odometer_km !== ''
                ? vehicle.odometer_km
                : '';
        vehicleDefaultMileage.value =
            vehicle?.default_mileage_km_per_liter !== undefined
            && vehicle?.default_mileage_km_per_liter !== null
            && vehicle?.default_mileage_km_per_liter !== ''
                ? vehicle.default_mileage_km_per_liter
                : '';
        vehicleMaxLoad.value =
            vehicle?.max_load_ton !== undefined && vehicle?.max_load_ton !== null && vehicle?.max_load_ton !== ''
                ? vehicle.max_load_ton
                : '';
        vehicleFuelTankCapacity.value =
            vehicle?.fuel_tank_capacity_ltr !== undefined
            && vehicle?.fuel_tank_capacity_ltr !== null
            && vehicle?.fuel_tank_capacity_ltr !== ''
                ? vehicle.fuel_tank_capacity_ltr
                : '';
    } catch {
        // Vehicle does not exist (e.g. deleted from master)
        form.vehicle_id = '';
        form.driver_id = '';
        form.planned_mileage_km_per_liter = '';
        form.start_meter_reading_km = '';
        form.end_meter_reading_km = '';
        form.planned_diesel_liters = '';
        form.max_load_ton = '';
        form.planned_diesel_manually_set = false;
        selectedVehicleLabel.value = '';
        vehicleOdometerKm.value = '';
        vehicleDefaultMileage.value = '';
        vehicleMaxLoad.value = '';
        vehicleFuelTankCapacity.value = '';
    }
}

let tripNumberPreviewTimer = null;
let tripNumberPreviewRequestId = 0;

function onTripSequenceChange() {
    if (tripNumberPreviewTimer) {
        clearTimeout(tripNumberPreviewTimer);
    }

    tripNumberPreviewTimer = setTimeout(() => {
        refreshTripNumberPreview({ syncSequence: false });
    }, 350);
}

async function refreshTripNumberPreview({ syncSequence = false } = {}) {
    if (needsCompanySelection.value) {
        previewTripNumber.value = '';

        return;
    }

    const requestId = ++tripNumberPreviewRequestId;
    const sequenceRaw = form.trip_sequence;
    const hasSequence = sequenceRaw !== '' && sequenceRaw != null;

    try {
        const preview = await fetchNextTripNumber({
            startAt: form.start_at || null,
            sequence: hasSequence ? Number(sequenceRaw) : null,
        });

        if (requestId !== tripNumberPreviewRequestId) {
            return;
        }

        previewTripNumber.value = preview.trip_number || '';

        if (syncSequence && preview.sequence != null) {
            form.trip_sequence = String(preview.sequence);
        }
    } catch {
        if (requestId === tripNumberPreviewRequestId) {
            previewTripNumber.value = '';
        }
    }
}

watch(
    () => form.start_at,
    () => {
        refreshTripNumberPreview({ syncSequence: true });
    },
);

function handleReturnToStart() {
    if (form.route_legs.length === 0) {
        toast.warning('Add at least one stop first.');

        return;
    }

    addReturnToStart();
    toast.info('Return to source added (empty load).');
}

const lookupsLoaded = ref({
    stations: false,
    brokers: false,
    customers: false,
    goodsTypes: false,
    drivers: false,
});

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
        lookupsLoaded.value.stations = true;
    }
    if (brokersRes.status === 'fulfilled') {
        brokers.value = brokersRes.value ?? [];
        lookupsLoaded.value.brokers = true;
    }
    if (customersRes.status === 'fulfilled') {
        customers.value = customersRes.value ?? [];
        lookupsLoaded.value.customers = true;
    }
    if (goodsTypesRes.status === 'fulfilled') {
        goodsTypes.value = goodsTypesRes.value ?? [];
        lookupsLoaded.value.goodsTypes = true;
    }
    if (driversRes.status === 'fulfilled') {
        drivers.value = driversRes.value ?? [];
        lookupsLoaded.value.drivers = true;
    }
}

function substituteDriverLabel() {
    if (!form.substitute_driver_id) {
        return '—';
    }

    return drivers.value.find((driver) => String(driver.id) === String(form.substitute_driver_id))?.name ?? '—';
}

function buildConfirmSummary() {
    return buildTripSaveConfirmViewModel(form, {
        mode: 'create',
        stations: stations.value,
        customers: customers.value,
        brokers: brokers.value,
        drivers: drivers.value,
        vehicleLabel: selectedVehicleLabel.value || '—',
        driverLabel: primaryDriverName.value || '—',
        substituteDriverLabel: substituteDriverLabel(),
        tripNumber: previewTripNumber.value,
    });
}

async function prepareSave() {
    clearErrors();

    const payload = buildPayload(stations.value, { mode: 'create', includeExpenses: false, customers: customers.value });
    const clientErrors = validateTripPayload(
        {
            ...payload,
            start_meter_reading_km: form.start_meter_reading_km,
            truck_running_meter_km: vehicleOdometerKm.value,
        },
        { mode: 'create' },
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

async function handleSubmit(mode = 'index') {
    saveMode.value = mode;
    const payload = await prepareSave();

    if (!payload) {
        return;
    }

    pendingPayload.value = payload;
    saveConfirmSummary.value = buildConfirmSummary();
    showSaveConfirm.value = true;
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
    saving.value = true;

    try {
        const trip = await createTrip(pendingPayload.value);
        showSaveConfirm.value = false;

        pendingPayload.value = null;
        tripFormRef.value?.clearProgressDraft?.();

        if (saveMode.value === 'expenses' && trip?.id) {
            await router.push({ name: 'trips.expenses', params: { id: trip.id } });

            return;
        }

        await router.push(tripListReturnWithFlash({ created: '1' }));
    } catch (error) {
        showSaveConfirm.value = false;

        if (!applyApiError(error, 'Failed to create trip.')) {
            tripFormError.value = error?.response?.data?.message ?? 'Failed to create trip.';
        }

        toast.error(tripFormError.value);
        scrollToFirstFormError();
    } finally {
        saving.value = false;
    }
}

onMounted(async () => {
    resetForm();
    form.start_at = toDateInputValue();
    form.trip_type = 'ONE_WAY';

    const typeParam = String(route.query.type ?? '');

    // Optional legacy query still accepted, but route shape will override on create.
    if (typeParam === 'one_way') {
        form.trip_type = 'ONE_WAY';
    } else if (typeParam === 'round_trip') {
        form.trip_type = 'ROUND_TRIP';
    }

    // 1. Restore draft immediately into form (silent; toast is shown after reconciling DB lookups)
    const restored = tripFormRef.value?.restoreProgressDraft?.({ notify: false }) ?? false;

    // 2. Fetch lookups, vehicle specs, and trip preview in parallel in background
    const backgroundTasks = [];

    if (!needsCompanySelection.value) {
        backgroundTasks.push(loadLookups());
    }

    if (restored && form.vehicle_id) {
        backgroundTasks.push(hydrateRestoredVehicle(form.vehicle_id));
    }

    if (!needsCompanySelection.value) {
        const hasSequence = form.trip_sequence !== '' && form.trip_sequence != null;
        backgroundTasks.push(refreshTripNumberPreview({ syncSequence: !hasSequence }));
    }

    await Promise.allSettled(backgroundTasks);

    // 3. Reconcile restored draft against fresh lookups from DB
    if (restored) {
        tripFormRef.value?.reconcileRestoredLookups?.({
            customers: customers.value,
            stations: stations.value,
            brokers: brokers.value,
            drivers: drivers.value,
            lookupsLoaded: {
                customers: lookupsLoaded.value.customers,
                stations: lookupsLoaded.value.stations,
                brokers: lookupsLoaded.value.brokers,
                drivers: lookupsLoaded.value.drivers,
            },
        });
    }
});
</script>

<style scoped>
.trip-create-redesigned-page {
    max-width: 1180px;
    margin: 0 auto;
    padding: 24px 20px 40px;
}

@media (max-width: 767.98px) {
    .trip-create-redesigned-page {
        padding: 10px 8px 24px;
    }
    .trip-form-actions {
        padding: 8px 0;
    }
    .trip-form-actions__row {
        flex-wrap: nowrap !important;
        width: 100%;
        gap: 6px !important;
    }
    .trip-form-actions__row :deep(.app-btn),
    .trip-form-actions__row :deep(.btn) {
        flex: 1 1 0 !important;
        min-width: 0 !important;
        padding: 0.38rem 0.25rem !important;
        font-size: 0.75rem !important;
        white-space: nowrap !important;
        justify-content: center !important;
        height: 36px !important;
    }
    .trip-form-actions__row :deep(.app-btn i),
    .trip-form-actions__row :deep(.btn i) {
        font-size: 0.75rem !important;
        margin-right: 3px !important;
    }
}
</style>
