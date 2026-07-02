<template>
    <div class="container mt-4 pb-5 app-index-page trip-expenses-page">
        <div v-if="needsCompanySelection" class="alert alert-warning">
            Select a company and click <strong>Enter</strong> before managing trip expenses.
        </div>

        <div v-else-if="loading" class="text-center py-5 text-muted">Loading trip expenses…</div>

        <template v-else-if="trip">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
                <div>
                    <RouterLink :to="tripListReturnTo" class="text-decoration-none small">
                        <i class="bi bi-arrow-left"></i> Back to Trips
                    </RouterLink>
                    <div class="d-flex flex-wrap align-items-center gap-2 mt-1">
                        <h3 class="mb-0">Trip Expenses</h3>
                        <span class="badge" :class="statusMeta.badge">{{ statusMeta.label }}</span>
                    </div>
                    <p class="text-muted small mb-0">
                        {{ trip.trip_number ?? '—' }}
                        <span v-if="trip.vehicle?.registration_number">
                            · {{ trip.vehicle.registration_number }}
                        </span>
                        <span v-if="routePreview"> · {{ routePreview }}</span>
                    </p>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <AppButton
                        variant="secondary"
                        outline
                        size="sm"
                        icon="bi bi-eye"
                        label="View Trip"
                        :to="{ name: 'trips.show', params: { id: trip.id } }"
                    />
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-body py-3">
                    <div class="row g-3 text-center text-md-start">
                        <div class="col-6 col-md-3">
                            <div class="small text-muted">Trip dates</div>
                            <strong class="small">{{ formatDate(trip.start_at) }} – {{ formatDate(trip.end_at) }}</strong>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="small text-muted">Total freight</div>
                            <strong>₹{{ formatMoney(trip.total_freight_amount) }}</strong>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="small text-muted">Total expenses</div>
                            <strong>₹{{ formatMoney(liveExpenseTotal) }}</strong>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="small text-muted">Profit</div>
                            <strong :class="profitAmount >= 0 ? 'text-success' : 'text-danger'">
                                ₹{{ formatMoney(profitAmount) }}
                            </strong>
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="hasTripMasterData" class="card mb-3 trip-expenses-master-card">
                <div class="card-body py-3">
                    <div class="small text-muted mb-2">Trip reference (from trip master)</div>
                    <div class="row g-3 text-center text-md-start">
                        <div class="col-6 col-md-3">
                            <div class="small text-muted">Start Meter Reading</div>
                            <strong>{{ formatMeter(form.start_meter_reading_km) }}</strong>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="small text-muted">End Meter Reading</div>
                            <strong>{{ formatMeter(form.end_meter_reading_km) }}</strong>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="small text-muted">Diesel rate</div>
                            <strong>{{ form.diesel_rate_per_liter ? `₹${formatMoney(form.diesel_rate_per_liter)}/Ltr` : '—' }}</strong>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="small text-muted">Expected mileage</div>
                            <strong>{{ form.planned_mileage_km_per_liter ? `${formatNum(form.planned_mileage_km_per_liter)} KM/Ltr` : '—' }}</strong>
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="formError" class="alert alert-danger">{{ formError }}</div>

            <div class="card mb-4 trip-expenses-card">
                <div class="card-header bg-gradient-primary trip-expenses-card__header">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                        <h3 class="card-title mb-0">
                            <i class="bi bi-receipt me-2"></i>Expense entries
                        </h3>
                        <div class="trip-expenses-card__live-total">
                            Live total: ₹{{ formatMoney(liveExpenseTotal) }}
                        </div>
                    </div>
                </div>
                <div class="card-body trip-expenses-card__body">
                    <TripExpensesPanel
                        context="standalone"
                        :expenses="form.expenses"
                        :route-legs="form.route_legs"
                        :stations="stations"
                        :max-load-ton="form.max_load_ton"
                        :trip-start-date="form.start_at"
                        :trip-end-date="form.end_at"
                        :trip-start-meter-km="form.start_meter_reading_km"
                        :trip-end-meter-km="form.end_meter_reading_km"
                        :field-errors="fieldErrors"
                        @add-expense="(options) => addExpense(options)"
                        @remove-expense="removeExpense"
                    />
                </div>
            </div>

            <div class="trip-form-actions card shadow-sm">
                <div class="card-body d-flex flex-wrap gap-2 justify-content-end">
                    <AppButton
                        variant="secondary"
                        outline
                        size="sm"
                        icon="bi bi-x-lg"
                        label="Exit"
                        :to="tripListReturnTo"
                    />
                    <AppButton
                        variant="primary"
                        size="sm"
                        icon="bi bi-floppy-fill"
                        label="Save"
                        :loading="saving"
                        :disabled="saving"
                        @click="handleSave"
                    />
                </div>
            </div>
        </template>
    </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { listAllStations } from '../../api/stations';
import {
    canManageTripExpenses,
    fetchTrip,
    tripStatusMeta,
    updateTrip,
    validateExpenseDates,
    validateExpenseMeterReadings,
} from '../../api/trips';
import TripExpensesPanel from '../../components/trips/TripExpensesPanel.vue';
import AppButton from '../../components/ui/AppButton.vue';
import { useApiFormErrors } from '../../composables/useApiFormErrors';
import { useAppToast } from '../../composables/useAppToast';
import { usePermissions } from '../../composables/usePermissions';
import { useRequireCompanyContext } from '../../composables/useRequireCompanyContext';
import { useTripExpensesForm } from '../../composables/useTripExpensesForm';
import { useTripListReturn } from '../../composables/useTripListReturn';
import { formatDate } from '../../utils/datetime';
import { formatMeterKm } from '../../utils/formatMeter';
import { scrollToFirstFormError } from '../../utils/formErrors';

const route = useRoute();
const router = useRouter();
const toast = useAppToast();
const { needsCompanySelection } = useRequireCompanyContext();
const { tripListReturnTo } = useTripListReturn();
const { user } = usePermissions();
const { fieldErrors, formError, clearErrors, applyApiError } = useApiFormErrors();

const { form, populateFromTrip, addExpense, removeExpense, buildExpensesPayload } = useTripExpensesForm();

const trip = ref(null);
const stations = ref([]);
const loading = ref(true);
const saving = ref(false);

const statusMeta = computed(() => tripStatusMeta(trip.value?.status));
const liveExpenseTotal = computed(() =>
    form.expenses.reduce((sum, expense) => sum + Number(expense.amount ?? 0), 0),
);
const profitAmount = computed(() => {
    const freight = Number(trip.value?.total_freight_amount ?? 0);

    return freight - liveExpenseTotal.value;
});

const hasTripMasterData = computed(
    () =>
        form.start_meter_reading_km !== '' ||
        form.end_meter_reading_km !== '' ||
        form.diesel_rate_per_liter !== '' ||
        form.planned_mileage_km_per_liter !== '' ||
        Boolean(trip.value?.vehicle?.registration_number),
);

const routePreview = computed(() => {
    const legs = trip.value?.route_legs ?? [];

    if (legs.length === 0) {
        return '';
    }

    const labels = legs.map((leg) => leg.to_city || leg.to_location || '—');

    return labels.join(' → ');
});

function formatMoney(value) {
    return Number(value ?? 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function formatNum(value) {
    return Number(value ?? 0).toLocaleString('en-IN', { maximumFractionDigits: 2 });
}

function formatMeter(value) {
    return formatMeterKm(value);
}

async function loadTrip() {
    loading.value = true;
    clearErrors();

    try {
        const loaded = await fetchTrip(route.params.id);

        if (!canManageTripExpenses(loaded.status, user.value)) {
            toast.error('You cannot edit expenses for this trip.');
            await router.replace({ name: 'trips.show', params: { id: loaded.id } });

            return;
        }

        trip.value = loaded;
        populateFromTrip(loaded);
    } catch (error) {
        applyApiError(error, 'Failed to load trip.');
        toast.error(formError.value);
        await router.replace(tripListReturnTo);
    } finally {
        loading.value = false;
    }
}

async function handleSave() {
    clearErrors();

    const payload = buildExpensesPayload();
    const clientErrors = {
        ...validateExpenseDates(payload.expenses, {
            startAt: form.start_at || null,
            endAt: form.end_at || null,
        }),
        ...validateExpenseMeterReadings(payload.expenses, {
            startMeterKm: form.start_meter_reading_km,
            endMeterKm: form.end_meter_reading_km,
        }),
    };

    if (Object.keys(clientErrors).length > 0) {
        fieldErrors.value = clientErrors;
        formError.value = 'Please fix the errors below before saving.';
        toast.error(formError.value);
        scrollToFirstFormError();

        return;
    }

    saving.value = true;

    try {
        const updated = await updateTrip(route.params.id, payload);
        trip.value = updated;
        populateFromTrip(updated);
        toast.success('Trip expenses saved successfully.');
        await router.push({ name: 'trips.show', params: { id: route.params.id } });
    } catch (error) {
        if (!applyApiError(error, 'Failed to save trip expenses.')) {
            formError.value = error?.response?.data?.message ?? 'Failed to save trip expenses.';
        }

        toast.error(formError.value);
        scrollToFirstFormError();
    } finally {
        saving.value = false;
    }
}

onMounted(async () => {
    stations.value = await listAllStations();
    await loadTrip();
});
</script>

<style scoped>
.trip-expenses-card__live-total {
    font-size: 0.95rem;
    font-weight: 600;
}

.trip-expenses-card__body {
    background: #f0fdf4;
}

.trip-expenses-master-card {
    border-color: #bbf7d0;
    background: #f8fff9;
}
</style>
