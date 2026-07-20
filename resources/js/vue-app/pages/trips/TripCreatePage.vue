<template>
    <div class="container mt-4 pb-5 app-index-page trip-create-page">
        <div v-if="needsCompanySelection" class="alert alert-warning">
            Select a company and click <strong>Enter</strong> before creating trips.
        </div>

        <form v-else novalidate @submit.prevent="handleSubmit('index')">
            <TripForm
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
            >
               
                
            </TripForm>

            <div class="trip-form-actions card shadow-sm">
                <div class="card-body d-flex flex-wrap gap-2 justify-content-end">
                    <AppButton
                        variant="secondary"
                        outline
                        size="sm"
                        icon="bi bi-x-lg"
                        label="Cancel"
                        :to="tripListReturnTo"
                    />
                    <AppButton
                        type="button"
                        variant="success"
                        outline
                        size="sm"
                        icon="bi bi-eraser"
                        label="Clear"
                        :disabled="saving"
                        @click="handleClearForm"
                    />
                  
                    <AppButton
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
import { useTripWindowValidation } from '../../composables/useTripWindowValidation';
import TripForm from '../../components/trips/TripForm.vue';
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
const previewTripNumber = ref('');
const showSaveConfirm = ref(false);
const saveConfirmSummary = ref(buildTripSaveConfirmViewModel({ route_legs: [], expenses: [] }));
const pendingPayload = ref(null);
const saveMode = ref('index');
const selectedVehicleLabel = ref('');
const vehicleOdometerKm = ref('');
const vehicleDefaultMileage = ref('');
const vehicleMaxLoad = ref('');

const { driverWarning, loadingDriver, primaryDriverName, setPrimaryDriverFromSelection } = useVehicleDriverSync(form);
const { applyWindowValidation } = useTripWindowValidation(form, {
    fieldErrors,
    clearFieldError,
});

function onVehicleSelected(vehicle) {
    setPrimaryDriverFromSelection(vehicle);
    selectedVehicleLabel.value = vehicle?.registration_number ?? '—';
    vehicleOdometerKm.value =
        vehicle?.odometer_km !== undefined && vehicle?.odometer_km !== null && vehicle?.odometer_km !== ''
            ? vehicle.odometer_km
            : '';
    const defaultMileage =
        vehicle?.default_mileage_km_per_liter ?? vehicle?.expected_mileage_km_per_liter ?? 0;
    const maxLoad = vehicle?.max_load_ton ?? 0;

    form.planned_mileage_km_per_liter =
        defaultMileage !== '' && defaultMileage != null ? Number(defaultMileage) : 0;
    form.max_load_ton = maxLoad !== '' && maxLoad != null ? Number(maxLoad) : 0;
    form.planned_diesel_manually_set = false;

    vehicleDefaultMileage.value = defaultMileage !== '' && defaultMileage != null ? defaultMileage : '';
    vehicleMaxLoad.value = maxLoad !== '' && maxLoad != null ? maxLoad : '';
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
    if (!form.start_station_id) {
        fieldErrors.value = {
            ...fieldErrors.value,
            start_station_id: ['Select the start station before adding a stop.'],
        };
        toast.warning('Select a start station first.');
        scrollToFirstFormError();

        return;
    }

    clearFieldError('start_station_id');
    addStop();
}

function handleClearForm() {
    const tripType = form.trip_type;
    resetForm();
    form.trip_type = tripType;
    form.start_at = toDateInputValue();
    clearErrors();
    selectedVehicleLabel.value = '';
    vehicleOdometerKm.value = '';
    vehicleDefaultMileage.value = '';
    vehicleMaxLoad.value = '';
    refreshTripNumberPreview({ syncSequence: true });
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

    const typeParam = String(route.query.type ?? '');

    if (typeParam === 'one_way') {
        form.trip_type = 'ONE_WAY';
    } else if (typeParam === 'round_trip') {
        form.trip_type = 'ROUND_TRIP';
    } else {
        await router.replace(tripListReturnWithFlash({ create: '1' }));

        return;
    }

    if (!needsCompanySelection.value) {
        await loadLookups();
        await refreshTripNumberPreview({ syncSequence: true });
    }
});
</script>

