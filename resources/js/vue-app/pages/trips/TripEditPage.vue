<template>
    <div class="container mt-4 pb-5">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
            <div>
                <h3 class="mb-1">Edit Trip</h3>
                <RouterLink :to="{ name: 'trips.index' }" class="text-muted small text-decoration-none">
                    <i class="bi bi-arrow-left"></i> Back to Trips List
                </RouterLink>
            </div>
            <span v-if="form.trip_number" class="badge bg-light text-dark border">{{ form.trip_number }}</span>
        </div>

        <div v-if="needsCompanySelection" class="alert alert-warning">
            Select a company and click <strong>Enter</strong> before editing trips.
        </div>

        <div v-else-if="loading" class="text-center py-5 text-muted">Loading trip…</div>

        <form v-else novalidate @submit.prevent="handleSubmit">
            <TripForm
                :form="form"
                :vehicles="vehicles"
                :drivers="drivers"
                :stations="stations"
                :can-quick-add-station="canQuickAddStation"
                :form-error="tripFormError"
                :field-errors="fieldErrors"
                :trip-number-display="form.trip_number"
                :driver-warning="driverWarning"
                :loading-driver="loadingDriver"
                :status-locked="form.status === 'COMPLETED' || form.status === 'CANCELLED'"
                @add-stop="handleAddStop"
                @return-to-start="handleReturnToStart"
                @remove-route-leg="removeRouteLeg"
                @add-expense="addExpense"
                @remove-expense="removeExpense"
                @start-station-change="onStartStationChange"
                @stop-change="onStopStationChange"
                @station-created="onStationCreated"
            />

            <div class="trip-form-actions sticky-bottom-bar card shadow-sm mt-3">
                <div class="card-body d-flex flex-wrap gap-2 justify-content-end py-3">
                    <AppButton variant="secondary" outline label="Cancel" :to="{ name: 'trips.index' }" />
                    <AppButton type="submit" icon="bi bi-check-lg" label="Update Trip" :loading="saving" />
                </div>
            </div>
        </form>
    </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { listAllDrivers } from '../../api/drivers';
import { listAllStations } from '../../api/stations';
import { fetchTrip, updateTrip, validateTripPayload } from '../../api/trips';
import { listAllVehicles } from '../../api/vehicles';
import TripForm from '../../components/trips/TripForm.vue';
import AppButton from '../../components/ui/AppButton.vue';
import { useApiFormErrors } from '../../composables/useApiFormErrors';
import { useAppToast } from '../../composables/useAppToast';
import { usePermissions } from '../../composables/usePermissions';
import { useRequireCompanyContext } from '../../composables/useRequireCompanyContext';
import { useTripForm } from '../../composables/useTripForm';
import { useVehicleDriverSync } from '../../composables/useVehicleDriverSync';
import { scrollToFirstFormError } from '../../utils/formErrors';

const route = useRoute();
const router = useRouter();
const toast = useAppToast();
const { needsCompanySelection } = useRequireCompanyContext();
const { canInCompany } = usePermissions();
const { fieldErrors, formError: tripFormError, clearErrors, applyApiError } = useApiFormErrors();

const {
    form,
    populateFromTrip,
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

const vehicles = ref([]);
const drivers = ref([]);
const stations = ref([]);
const loading = ref(true);
const saving = ref(false);
const driverSyncEnabled = ref(false);

const { driverWarning, loadingDriver } = useVehicleDriverSync(form, {
    enabled: driverSyncEnabled,
    onWarning: (message) => toast.warning(message),
});

function onStationCreated({ station, legIndex }) {
    stations.value = [station, ...stations.value.filter((s) => s.id !== station.id)];

    if (legIndex !== null && form.route_legs[legIndex]) {
        form.route_legs[legIndex].to_station_id = station.id;
        onStopStationChange(legIndex);
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
    [vehicles.value, drivers.value, stations.value] = await Promise.all([
        listAllVehicles(),
        listAllDrivers({ activeOnly: true }),
        listAllStations(),
    ]);
}

async function loadTrip() {
    loading.value = true;
    clearErrors();

    try {
        const trip = await fetchTrip(route.params.id);
        populateFromTrip(trip);
        form.trip_number = trip.trip_number ?? '';
        driverSyncEnabled.value = true;
    } catch (error) {
        applyApiError(error, 'Failed to load trip.');
        toast.error(tripFormError.value);
        await router.replace({ name: 'trips.index' });
    } finally {
        loading.value = false;
    }
}

async function handleSubmit() {
    clearErrors();

    const payload = buildPayload(stations.value);
    const clientErrors = validateTripPayload(payload);

    if (Object.keys(clientErrors).length > 0) {
        fieldErrors.value = clientErrors;
        tripFormError.value = 'Please fix the errors below before saving.';
        toast.error(tripFormError.value);
        scrollToFirstFormError();

        return;
    }

    saving.value = true;

    try {
        await updateTrip(route.params.id, payload);
        toast.success('Trip updated successfully.');
        await router.push({ name: 'trips.index', query: { updated: '1' } });
    } catch (error) {
        if (!applyApiError(error, 'Failed to update trip.')) {
            tripFormError.value = error?.response?.data?.message ?? 'Failed to update trip.';
        }

        toast.error(tripFormError.value);
        scrollToFirstFormError();
    } finally {
        saving.value = false;
    }
}

onMounted(async () => {
    await loadLookups();
    await loadTrip();
});
</script>

<style scoped>
.sticky-bottom-bar {
    position: sticky;
    bottom: 0.75rem;
    z-index: 20;
}
</style>
