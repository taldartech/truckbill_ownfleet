<template>
    <div class="trip-mp-create-page app-index-page">
        <div v-if="needsCompanySelection" class="alert alert-warning">
            Select a company and click <strong>Enter</strong> before creating trips.
        </div>

        <form v-else novalidate @submit.prevent="handleSubmit">

            <TripFormRedesigned
                ref="tripFormRef"
                mode="create"
                route-layout="multi-point"
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
                @station-created="onStationCreated"
                @broker-created="onBrokerCreated"
                @customer-created="onCustomerCreated"
                @goods-type-created="onGoodsTypeCreated"
                @vehicle-selected="onVehicleSelected"
                @clear-field-error="clearFieldError"
                @trip-sequence-change="onTripSequenceChange"
            />

            <div class="trip-form-actions trip-form-actions--inline">
                <div class="d-flex flex-wrap gap-2 justify-content-center">
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
            confirm-mode="index"
            @cancel="cancelSaveConfirm"
            @confirm="confirmSave"
        />
    </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import { listAllBrokers } from '../../api/brokers';
import { listAllCustomers } from '../../api/customers';
import { listAllDrivers } from '../../api/drivers';
import { listAllGoodsTypes } from '../../api/goodsTypes';
import { listAllStations } from '../../api/stations';
import { createTrip, fetchNextTripNumber, validateMultiPointTripForm, validateTripPayload } from '../../api/trips';
import { fetchVehicle } from '../../api/vehicles';
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
import { dateOnlyFromDatetimeLocal, toDateInputValue } from '../../utils/datetime';
import { scrollToFirstFormError } from '../../utils/formErrors';
import { buildTripSaveConfirmViewModel } from '../../utils/tripSaveConfirm';

const router = useRouter();
const toast = useAppToast();
const { needsCompanySelection } = useRequireCompanyContext();
const { tripListReturnTo } = useTripListReturn();
const { canInCompany } = usePermissions();
const { fieldErrors, formError: tripFormError, clearErrors, clearFieldError, applyApiError } = useApiFormErrors();

const {
    form,
    resetForm,
    buildPayload,
    switchToMultiPoint,
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
const saveConfirmSummary = ref(buildTripSaveConfirmViewModel({ route_legs: [], pickups: [], expenses: [] }));
const pendingPayload = ref(null);
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

function onStationCreated({ station }) {
    stations.value = [station, ...stations.value.filter((item) => item.id !== station.id)];
}

function onBrokerCreated(broker) {
    brokers.value = [broker, ...brokers.value.filter((item) => item.id !== broker.id)];
    form.broker_id = broker.id;
}

function onCustomerCreated({ customer }) {
    customers.value = [customer, ...customers.value.filter((item) => item.id !== customer.id)];
    form.customer_id = customer.id;
}

function onGoodsTypeCreated({ goodsType }) {
    goodsTypes.value = [
        goodsType,
        ...goodsTypes.value.filter((item) => String(item.id) !== String(goodsType.id)),
    ];
}

function handleClearForm() {
    resetForm();
    form.start_at = toDateInputValue();
    switchToMultiPoint();
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

    const formErrors = validateMultiPointTripForm(form, {
        mode: 'create',
        startDate: dateOnlyFromDatetimeLocal(form.start_at),
        endDate: dateOnlyFromDatetimeLocal(form.end_at),
    });

    if (Object.keys(formErrors).length > 0) {
        fieldErrors.value = formErrors;
        const firstError = Object.values(formErrors).flat()[0] ?? 'Please fix the error below before saving.';
        tripFormError.value = firstError;
        toast.error(firstError);
        scrollToFirstFormError();

        return null;
    }

    let payload;

    try {
        payload = buildPayload(stations.value, {
            mode: 'create',
            includeExpenses: false,
            customers: customers.value,
        });
    } catch (error) {
        fieldErrors.value = error?.fieldErrors ?? { drops: [error?.message || 'Could not allocate drops to pickups.'] };
        tripFormError.value = error?.message || 'Could not allocate drops to pickups.';
        toast.error(tripFormError.value);
        scrollToFirstFormError();

        return null;
    }

    const clientErrors = validateTripPayload(
        {
            ...payload,
            start_meter_reading_km: form.start_meter_reading_km,
            truck_running_meter_km: vehicleOdometerKm.value,
        },
        { mode: 'create', tripType: 'MULTI_POINT' },
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

async function handleSubmit() {
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

async function confirmSave() {
    if (!pendingPayload.value) {
        return;
    }

    saving.value = true;

    try {
        const trip = await createTrip(pendingPayload.value);
        showSaveConfirm.value = false;
        pendingPayload.value = null;
        tripFormRef.value?.clearProgressDraft?.();

        await router.push({
            name: 'trips.show',
            params: { id: trip.id },
            query: { created: '1' },
        });
    } catch (error) {
        showSaveConfirm.value = false;

        if (!applyApiError(error, 'Failed to create multi-point trip.')) {
            tripFormError.value = error?.response?.data?.message ?? 'Failed to create multi-point trip.';
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
    switchToMultiPoint();

    // 1. Restore draft immediately into form (silent; toast is shown after reconciling DB lookups)
    const restored = tripFormRef.value?.restoreProgressDraft?.({ notify: false }) ?? false;

    if (!restored) {
        switchToMultiPoint();
    }

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

        // Ensure multipoint structure is intact if pickups/drops were emptied
        if (!Array.isArray(form.pickups) || form.pickups.length === 0) {
            switchToMultiPoint();
        }
    }
});
</script>

<style scoped>
.trip-mp-create-page {
    max-width: 1180px;
    margin: 0 auto;
    padding: 24px 20px 40px;
}
</style>
