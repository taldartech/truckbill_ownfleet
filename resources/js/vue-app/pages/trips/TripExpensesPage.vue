<template>
    <div class="container mt-4 pb-5 app-index-page trip-expenses-page">
        <div v-if="needsCompanySelection" class="alert alert-warning">
            Select a company and click <strong>Enter</strong> before managing trip expenses.
        </div>

        <div v-else-if="loading" class="text-center py-5 text-muted">Loading trip expenses…</div>

        <template v-else-if="trip">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
                <div>
                    <RouterLink :to="tripDetailReturnTo" class="text-decoration-none small">
                        <i class="bi bi-arrow-left"></i> Back to Trip
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
                            <strong>{{ form.diesel_rate_per_liter ? `₹${formatMoney(form.diesel_rate_per_liter)}/LTR` : '—' }}</strong>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="small text-muted">Expected mileage</div>
                            <strong>{{ form.planned_mileage_km_per_liter ? `${formatNum(form.planned_mileage_km_per_liter)} KM/LTR` : '—' }}</strong>
                        </div>
                    </div>
                </div>
            </div>

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
                    <TripExpenseValidationSummary
                        v-if="showValidationSummary"
                        :items="validationSummaryItems"
                        @focus-field="handleFocusField"
                    />

                    <TripExpensesPanel
                        ref="expensesPanelRef"
                        context="standalone"
                        :expenses="form.expenses"
                        :route-legs="form.route_legs"
                        :stations="stations"
                        :max-load-ton="form.max_load_ton"
                        :trip-start-date="form.start_at"
                        :trip-end-date="form.end_at"
                        :trip-start-meter-km="form.start_meter_reading_km"
                        :trip-end-meter-km="form.end_meter_reading_km"
                        :field-errors="liveFieldErrors"
                        @add-expense="(options) => addExpense(options)"
                        @remove-expense="removeExpense"
                        @fuel-field-input="markFuelFieldDirty"
                        @field-input="onExpenseFieldInput"
                    />
                </div>
            </div>

            <div class="trip-form-actions card shadow-sm">
                <div class="card-body d-flex flex-wrap gap-2 justify-content-end align-items-center">
                    <AppButton
                        variant="secondary"
                        outline
                        size="sm"
                        icon="bi bi-x-lg"
                        label="Cancel"
                        :to="tripDetailReturnTo"
                    />
                    <AppButton
                        variant="primary"
                        size="sm"
                        icon="bi bi-floppy-fill"
                        :label="saveButtonLabel"
                        :loading="saving"
                        :disabled="saving || (hasValidationErrors && hasStartedExpense)"
                        @click="handleSave"
                    />
                </div>
            </div>
        </template>

        <TripExpenseSaveConfirmModal
            :show="showSaveConfirm"
            :summary="saveConfirmSummary"
            :saving="saving"
            @cancel="cancelSaveConfirm"
            @confirm="confirmSave"
        />
    </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { listAllStations } from '../../api/stations';
import {
    canManageTripExpenses,
    fetchTrip,
    isTripArchived,
    tripStatusMeta,
    updateTrip,
} from '../../api/trips';
import TripExpensesPanel from '../../components/trips/TripExpensesPanel.vue';
import TripExpenseSaveConfirmModal from '../../components/trips/TripExpenseSaveConfirmModal.vue';
import TripExpenseValidationSummary from '../../components/trips/TripExpenseValidationSummary.vue';
import AppButton from '../../components/ui/AppButton.vue';
import { useApiFormErrors } from '../../composables/useApiFormErrors';
import { useAppToast } from '../../composables/useAppToast';
import { usePermissions } from '../../composables/usePermissions';
import { useRequireCompanyContext } from '../../composables/useRequireCompanyContext';
import { useTripExpensesForm } from '../../composables/useTripExpensesForm';
import { useUnsavedFormGuard } from '../../composables/useUnsavedFormGuard';
import { formatDate } from '../../utils/datetime';
import { buildExpenseSaveConfirmViewModel } from '../../utils/expenseSaveConfirm';
import {
    buildExpenseValidationSummaryItems,
    countValidationErrors,
    validateAllTripExpenses,
} from '../../utils/expenseValidation';
import { formatMeterKm } from '../../utils/formatMeter';
import { scrollToFirstFormError } from '../../utils/formErrors';

const route = useRoute();
const router = useRouter();
const toast = useAppToast();
const { needsCompanySelection } = useRequireCompanyContext();
const { user } = usePermissions();
const { fieldErrors, formError, clearErrors, applyApiError } = useApiFormErrors();

const { form, populateFromTrip, addExpense, removeExpense, buildExpensesPayload, markFuelFieldDirty } = useTripExpensesForm();

const trip = ref(null);
const stations = ref([]);
const loading = ref(true);
const saving = ref(false);
const showSaveConfirm = ref(false);
const saveConfirmSummary = ref(buildExpenseSaveConfirmViewModel({ expenses: [] }));
const originalExpenses = ref([]);
const expensesPanelRef = ref(null);
const validationAttempted = ref(false);

const tripDetailReturnTo = computed(() => ({
    name: 'trips.show',
    params: { id: route.params.id },
}));

const statusMeta = computed(() => tripStatusMeta(trip.value?.status));
const liveExpenseTotal = computed(() =>
    form.expenses.reduce((sum, expense) => sum + Number(expense.amount ?? 0), 0),
);
const profitAmount = computed(() => {
    const freight = Number(trip.value?.total_freight_amount ?? 0);

    return freight - liveExpenseTotal.value;
});

const isDirty = computed(
    () => JSON.stringify(form.expenses) !== JSON.stringify(originalExpenses.value),
);

useUnsavedFormGuard(
    isDirty,
    'You have unsaved expense changes. Leave without saving?',
);

const liveFieldErrors = computed(() => {
    const clientErrors = validateAllTripExpenses(form, { strict: validationAttempted.value });

    if (Object.keys(fieldErrors.value).length === 0) {
        return clientErrors;
    }

    return { ...clientErrors, ...fieldErrors.value };
});

const validationSummaryItems = computed(() =>
    buildExpenseValidationSummaryItems(liveFieldErrors.value, form.expenses, form),
);

const validationErrorCount = computed(() => countValidationErrors(liveFieldErrors.value));

const hasValidationErrors = computed(() => validationErrorCount.value > 0);

const hasStartedExpense = computed(() =>
    form.expenses.some(
        (expense) =>
            expense._fuelDirty === true
            || Number(expense.amount) > 0
            || expense.id != null,
    ),
);

const showValidationSummary = computed(
    () =>
        validationSummaryItems.value.length > 0
        && (validationAttempted.value || form.expenses.some((expense) => expense._fuelDirty)),
);

const saveButtonLabel = computed(() => {
    if (hasValidationErrors.value && hasStartedExpense.value) {
        const count = validationErrorCount.value;

        return `Fix ${count} issue${count === 1 ? '' : 's'}`;
    }

    return 'Save';
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

function onExpenseFieldInput() {
    if (Object.keys(fieldErrors.value).length > 0) {
        fieldErrors.value = {};
    }
}

function handleFocusField(item) {
    expensesPanelRef.value?.focusExpenseField(item);
}

async function loadTrip() {
    loading.value = true;
    clearErrors();
    validationAttempted.value = false;

    try {
        const loaded = await fetchTrip(route.params.id);

        if (isTripArchived(loaded)) {
            toast.error('This trip has been archived and expenses cannot be modified.');
            await router.replace({ name: 'trips.show', params: { id: loaded.id } });

            return;
        }

        if (!canManageTripExpenses(loaded.status, user.value, { has_invoices: loaded.has_invoices })) {
            toast.error('You cannot edit expenses for this trip.');
            await router.replace({ name: 'trips.show', params: { id: loaded.id } });

            return;
        }

        trip.value = loaded;
        populateFromTrip(loaded);
        originalExpenses.value = JSON.parse(JSON.stringify(form.expenses));
    } catch (error) {
        applyApiError(error, 'Failed to load trip.');
        toast.error(formError.value);
        await router.replace(tripDetailReturnTo.value);
    } finally {
        loading.value = false;
    }
}

function buildConfirmSummary() {
    return buildExpenseSaveConfirmViewModel(form, {
        originalExpenses: originalExpenses.value,
        tripNumber: trip.value?.trip_number ?? '',
        vehicleLabel: trip.value?.vehicle?.registration_number ?? '—',
    });
}

function cancelSaveConfirm() {
    showSaveConfirm.value = false;
}

async function confirmSave() {
    saving.value = true;

    try {
        const payload = buildExpensesPayload();
        const updated = await updateTrip(route.params.id, payload);
        trip.value = updated;
        populateFromTrip(updated);
        originalExpenses.value = JSON.parse(JSON.stringify(form.expenses));
        showSaveConfirm.value = false;
        validationAttempted.value = false;
        toast.success('Trip expenses saved successfully.');
        await router.push({ name: 'trips.show', params: { id: route.params.id } });
    } catch (error) {
        showSaveConfirm.value = false;
        validationAttempted.value = true;

        if (!applyApiError(error, 'Failed to save trip expenses.')) {
            formError.value = error?.response?.data?.message ?? 'Failed to save trip expenses.';
        }

        toast.error(formError.value);
        scrollToFirstFormError();
    } finally {
        saving.value = false;
    }
}

async function handleSave() {
    clearErrors();
    validationAttempted.value = true;

    if (hasValidationErrors.value) {
        const firstItem = validationSummaryItems.value[0];

        if (firstItem) {
            handleFocusField(firstItem);
        } else {
            scrollToFirstFormError();
        }

        toast.error(`Fix ${validationErrorCount.value} issue${validationErrorCount.value === 1 ? '' : 's'} before saving.`);

        return;
    }

    saveConfirmSummary.value = buildConfirmSummary();
    showSaveConfirm.value = true;
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
