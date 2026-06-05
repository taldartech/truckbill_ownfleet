<template>
    <div class="container mt-4 pb-5 trip-create-page">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
            <div>
                <h3 class="mb-1">Create Trip</h3>
                <RouterLink :to="{ name: 'trips.index' }" class="text-muted small text-decoration-none">
                    <i class="bi bi-arrow-left"></i> Back to Trips List
                </RouterLink>
            </div>
            <span v-if="previewTripNumber" class="badge bg-light text-dark border">
                Next: {{ previewTripNumber }}
            </span>
        </div>

        <div v-if="needsCompanySelection" class="alert alert-warning">
            Select a company and click <strong>Enter</strong> before creating trips.
        </div>

        <form v-else novalidate @submit.prevent="handleSubmit">
            <TripForm
                :form="form"
                :vehicles="vehicles"
                :drivers="drivers"
                :stations="stations"
                :can-quick-add-station="canQuickAddStation"
                :form-error="tripFormError"
                :field-errors="fieldErrors"
                :trip-number-display="previewTripNumber"
                :driver-warning="driverWarning"
                :loading-driver="loadingDriver"
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
                    <AppButton type="submit" icon="bi bi-check-lg" label="Create Trip" :loading="saving" />
                </div>
            </div>
        </form>
    </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { listAllDrivers } from '../../api/drivers';
import { listAllStations } from '../../api/stations';
import { createTrip, fetchNextTripNumber, validateTripPayload } from '../../api/trips';
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

const router = useRouter();
const toast = useAppToast();
const { needsCompanySelection } = useRequireCompanyContext();
const { canInCompany } = usePermissions();
const { fieldErrors, formError: tripFormError, clearErrors, applyApiError } = useApiFormErrors();

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

const vehicles = ref([]);
const drivers = ref([]);
const stations = ref([]);
const saving = ref(false);
const previewTripNumber = ref('');

const { driverWarning, loadingDriver } = useVehicleDriverSync(form, {
    onWarning: (message) => toast.warning(message),
});

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
        listAllVehicles({ activeOnly: true }),
        listAllDrivers({ activeOnly: true }),
        listAllStations(),
    ]);
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
        const trip = await createTrip(payload);
        toast.success(`Trip ${trip.trip_number ?? ''} created successfully.`);
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
    form.start_at = new Date().toISOString().slice(0, 16);

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
