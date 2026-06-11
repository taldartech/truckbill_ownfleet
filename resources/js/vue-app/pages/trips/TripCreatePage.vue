<template>
    <div class="container mt-4 pb-5 trip-create-page">
        <div v-if="needsCompanySelection" class="alert alert-warning">
            Select a company and click <strong>Enter</strong> before creating trips.
        </div>

        <form v-else novalidate @submit.prevent="handleSubmit">
            <TripForm
                mode="create"
                :form="form"
                :stations="stations"
                :brokers="brokers"
                :customers="customers"
                :can-quick-add-station="canQuickAddStation"
                :can-quick-add-broker="canQuickAddBroker"
                :can-quick-add-customer="canQuickAddCustomer"
                :form-error="tripFormError"
                :field-errors="fieldErrors"
                :trip-number-display="previewTripNumber ? `Next: ${previewTripNumber}` : ''"
                :driver-warning="driverWarning"
                :loading-driver="loadingDriver"
                :primary-driver-name="primaryDriverName"
                :drivers="drivers"
                @add-stop="handleAddStop"
                @return-to-start="handleReturnToStart"
                @remove-route-leg="removeRouteLeg"
                @add-expense="(options) => addExpense(options)"
                @remove-expense="removeExpense"
                @start-station-change="onStartStationChange"
                @stop-change="onStopStationChange"
                @station-created="onStationCreated"
                @broker-created="onBrokerCreated"
                @customer-created="onCustomerCreated"
                @vehicle-selected="onVehicleSelected"
                @clear-field-error="clearFieldError"
            >
                <template #trip-details-actions>
                    <button type="submit" class="btn btn-sm btn-light fw-semibold px-3" :disabled="saving">
                        <span v-if="saving" class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>
                        <i v-else class="bi bi-check-lg me-1"></i>
                        Create Trip
                    </button>
                </template>
            </TripForm>

            <div class="trip-form-actions sticky-bottom-bar card shadow-sm mt-3">
                <div class="card-body d-flex flex-wrap gap-2 justify-content-end py-3">
                    <RouterLink :to="{ name: 'trips.index' }" class="btn btn-sm btn-outline-secondary">
                        Cancel
                    </RouterLink>
                    <button type="submit" class="btn btn-sm btn-primary" :disabled="saving">
                        <span v-if="saving" class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>
                        <i v-else class="bi bi-check-lg me-1"></i>
                        Create Trip
                    </button>
                </div>
            </div>
        </form>
    </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { listAllBrokers } from '../../api/brokers';
import { listAllCustomers } from '../../api/customers';
import { listAllDrivers } from '../../api/drivers';
import { listAllStations } from '../../api/stations';
import { createTrip, fetchNextTripNumber, validateTripPayload } from '../../api/trips';
import { useTripWindowValidation } from '../../composables/useTripWindowValidation';
import TripForm from '../../components/trips/TripForm.vue';
import { useApiFormErrors } from '../../composables/useApiFormErrors';
import { useAppToast } from '../../composables/useAppToast';
import { usePermissions } from '../../composables/usePermissions';
import { useRequireCompanyContext } from '../../composables/useRequireCompanyContext';
import { useTripForm } from '../../composables/useTripForm';
import { useVehicleDriverSync } from '../../composables/useVehicleDriverSync';
import { toDateInputValue } from '../../utils/datetime';
import { scrollToFirstFormError } from '../../utils/formErrors';

const router = useRouter();
const toast = useAppToast();
const { needsCompanySelection } = useRequireCompanyContext();
const { canInCompany } = usePermissions();
const { fieldErrors, formError: tripFormError, clearErrors, clearFieldError, applyApiError } = useApiFormErrors();

const {
    form,
    resetForm,
    addStop,
    addReturnToStart,
    removeRouteLeg,
    addExpense,
    removeExpense,
    buildPayload,
    onStartStationChange,
    onStopStationChange,
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
const saving = ref(false);
const previewTripNumber = ref('');

const { driverWarning, loadingDriver, primaryDriverName, setPrimaryDriverFromSelection } = useVehicleDriverSync(form);
const { applyWindowValidation } = useTripWindowValidation(form, {
    fieldErrors,
    clearFieldError,
});

function onVehicleSelected(vehicle) {
    setPrimaryDriverFromSelection(vehicle);
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

function onCustomerCreated(customer) {
    customers.value = [customer, ...customers.value.filter((item) => item.id !== customer.id)];
    form.customer_id = customer.id;
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

async function handleSubmit() {
    clearErrors();

    const payload = buildPayload(stations.value, { mode: 'create' });
    const clientErrors = validateTripPayload(payload, { mode: 'create' });

    if (Object.keys(clientErrors).length > 0) {
        fieldErrors.value = clientErrors;
        tripFormError.value = 'Please fix the errors below before saving.';
        toast.error(tripFormError.value);
        scrollToFirstFormError();

        return;
    }

    try {
        const windowAvailable = await applyWindowValidation();

        if (!windowAvailable) {
            tripFormError.value = 'This truck already has another open trip in the selected date range.';
            toast.error(tripFormError.value);
            scrollToFirstFormError();

            return;
        }
    } catch (error) {
        if (!applyApiError(error, 'Could not verify trip dates for this truck.')) {
            tripFormError.value = error?.response?.data?.message ?? 'Could not verify trip dates for this truck.';
        }

        toast.error(tripFormError.value);
        scrollToFirstFormError();

        return;
    }

    saving.value = true;

    try {
        await createTrip(payload);
        await router.push({ name: 'trips.index', query: { created: '1' } });
    } catch (error) {
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

    if (!needsCompanySelection.value) {
        await loadLookups();
        try {
            previewTripNumber.value = await fetchNextTripNumber();
        } catch {
            previewTripNumber.value = '';
        }
    }
});
</script>

<style scoped>
.sticky-bottom-bar {
    position: sticky;
    bottom: 0.75rem;
    z-index: 20;
}
</style>
