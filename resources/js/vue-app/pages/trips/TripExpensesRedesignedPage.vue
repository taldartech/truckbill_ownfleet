<template>
    <div class="trip-expenses-redesigned-page app-index-page">
        <div v-if="needsCompanySelection" class="alert alert-warning">
            Select a company and click <strong>Enter</strong> before managing trip expenses.
        </div>

        <div v-else-if="loading" class="text-center py-5 text-muted">Loading trip expenses…</div>

        <template v-else-if="trip">
            <div class="trf">
                <!-- Section 1 — Trip overview -->
                <div class="trf-section">
                    <div class="trf-section__head">
                        <div class="trf-section__head-left">
                            <div class="trf-step-num">1</div>
                            <div>
                                <h2 class="trf-section__title">Trip Expenses</h2>
                                <p class="trf-section__desc">
                                    {{ trip.trip_number ?? '—' }}
                                    <span v-if="trip.vehicle?.registration_number">
                                        · {{ trip.vehicle.registration_number }}
                                    </span>
                                    <span v-if="routePreview"> · {{ routePreview }}</span>
                                </p>
                            </div>
                        </div>
                        <div class="trf-section__head-right">
                            <div class="trf-trip-id trf-trip-id--head">
                                <div class="trf-trip-id__meta">
                                    <span class="trf-trip-id__label">Status</span>
                                    <span class="trf-trip-num-badge" :class="statusMeta.badge">
                                        {{ statusMeta.label }}
                                    </span>
                                </div>
                                <div class="trf-trip-id__preview trf-trip-id__preview--static">
                                    {{ trip.trip_number || '—' }}
                                </div>
                            </div>
                            <RouterLink :to="tripDetailReturnTo" class="trf-back-link">
                                <i class="bi bi-arrow-left"></i> Back to Trip
                            </RouterLink>
                        </div>
                    </div>

                    <div class="trf-section__body">
                        <div class="trf-summary-block trf-summary-block--money">
                            <div class="trf-summary-block__label">Overview</div>
                            <div class="trf-vehicle-info trf-vehicle-info--summary trf-vehicle-info--money trf-vehicle-info--overview">
                                <div class="trf-vehicle-info__item">
                                    <div class="trf-vehicle-info__icon" style="--ic:#4f46e5;--bg:#eef2ff">
                                        <i class="bi bi-calendar3"></i>
                                    </div>
                                    <div>
                                        <span class="trf-vehicle-info__name">Trip dates</span>
                                        <span class="trf-vehicle-info__val">
                                            {{ formatDate(trip.start_at) }} – {{ formatDate(trip.end_at) }}
                                        </span>
                                    </div>
                                </div>
                                <div class="trf-vehicle-info__item">
                                    <div class="trf-vehicle-info__icon" style="--ic:#be185d;--bg:#fdf2f8">
                                        <i class="bi bi-currency-rupee"></i>
                                    </div>
                                    <div>
                                        <span class="trf-vehicle-info__name">Total freight</span>
                                        <span class="trf-vehicle-info__val">₹{{ formatMoney(trip.total_freight_amount) }}</span>
                                    </div>
                                </div>
                                <div class="trf-vehicle-info__item">
                                    <div class="trf-vehicle-info__icon" style="--ic:#0d9488;--bg:#f0fdfa">
                                        <i class="bi bi-receipt"></i>
                                    </div>
                                    <div>
                                        <span class="trf-vehicle-info__name">Total expenses</span>
                                        <span class="trf-vehicle-info__val">₹{{ formatMoney(liveExpenseTotal) }}</span>
                                    </div>
                                </div>
                                <div class="trf-vehicle-info__item trf-vehicle-info__item--hero">
                                    <div class="trf-vehicle-info__icon" style="--ic:#2563eb;--bg:#eff6ff">
                                        <i class="bi bi-graph-up-arrow"></i>
                                    </div>
                                    <div>
                                        <span class="trf-vehicle-info__name">Profit</span>
                                        <span
                                            class="trf-vehicle-info__val"
                                            :class="{
                                                'trf-vehicle-info__val--profit': profitAmount >= 0,
                                                'trf-vehicle-info__val--loss': profitAmount < 0,
                                            }"
                                        >
                                            ₹{{ formatMoney(profitAmount) }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Section 2 — Trip reference -->
                <div v-if="hasTripMasterData" class="trf-section">
                    <div class="trf-section__head trf-section__head--green">
                        <div class="trf-section__head-left">
                            <div class="trf-step-num trf-step-num--green">2</div>
                            <div>
                                <h2 class="trf-section__title">Trip Reference</h2>
                                <p class="trf-section__desc">Meter readings and diesel defaults from trip master</p>
                            </div>
                        </div>
                    </div>
                    <div class="trf-section__body">
                        <div class="trf-vehicle-info trf-vehicle-info--summary">
                            <div class="trf-vehicle-info__item">
                                <div class="trf-vehicle-info__icon" style="--ic:#16a34a;--bg:#f0fdf4">
                                    <i class="bi bi-speedometer"></i>
                                </div>
                                <div>
                                    <span class="trf-vehicle-info__name">Start Meter Reading</span>
                                    <span class="trf-vehicle-info__val">{{ formatMeter(form.start_meter_reading_km) }}</span>
                                </div>
                            </div>
                            <div class="trf-vehicle-info__item">
                                <div class="trf-vehicle-info__icon" style="--ic:#ea580c;--bg:#fff7ed">
                                    <i class="bi bi-speedometer2"></i>
                                </div>
                                <div>
                                    <span class="trf-vehicle-info__name">End Meter Reading</span>
                                    <span class="trf-vehicle-info__val">{{ formatMeter(form.end_meter_reading_km) }}</span>
                                </div>
                            </div>
                            <div class="trf-vehicle-info__item">
                                <div class="trf-vehicle-info__icon" style="--ic:#0d9488;--bg:#f0fdfa">
                                    <i class="bi bi-fuel-pump-fill"></i>
                                </div>
                                <div>
                                    <span class="trf-vehicle-info__name">Diesel rate</span>
                                    <span class="trf-vehicle-info__val">
                                        {{ form.diesel_rate_per_liter ? `₹${formatMoney(form.diesel_rate_per_liter)}/Ltr` : '—' }}
                                    </span>
                                </div>
                            </div>
                            <div class="trf-vehicle-info__item">
                                <div class="trf-vehicle-info__icon" style="--ic:#4f46e5;--bg:#eef2ff">
                                    <i class="bi bi-droplet-half"></i>
                                </div>
                                <div>
                                    <span class="trf-vehicle-info__name">Expected mileage</span>
                                    <span class="trf-vehicle-info__val">
                                        {{ form.planned_mileage_km_per_liter ? `${formatNum(form.planned_mileage_km_per_liter)} KM/Ltr` : '—' }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Section 3 — Expense entries -->
                <div class="trf-section">
                    <div class="trf-section__head trf-section__head--expense">
                        <div class="trf-section__head-left">
                            <div class="trf-step-num trf-step-num--expense">{{ hasTripMasterData ? 3 : 2 }}</div>
                            <div>
                                <h2 class="trf-section__title">Expense Entries</h2>
                                <p class="trf-section__desc">Add whole-trip and per-stop expenses</p>
                            </div>
                        </div>
                        <div class="trf-section__head-right">
                            <div class="trf-live-total">
                                Live total: ₹{{ formatMoney(liveExpenseTotal) }}
                            </div>
                        </div>
                    </div>
                    <div class="trf-section__body trf-section__body--expenses">
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
            </div>

            <div class="trip-form-actions trip-form-actions--inline">
                <div class="d-flex flex-wrap gap-2 justify-content-center">
                    <AppButton
                        variant="secondary"
                        outline
                        size="sm"
                        icon="bi bi-eye"
                        label="View Trip"
                        :to="{ name: 'trips.show', params: { id: trip.id } }"
                    />
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
.trip-expenses-redesigned-page {
    max-width: 1180px;
    margin: 0 auto;
    padding: 24px 20px 40px;
}

.trf {
    --radius: 12px;
    --border: #dee2e6;
    --shadow: 0 1px 3px rgba(0, 0, 0, .07), 0 1px 2px rgba(0, 0, 0, .04);
    --primary: #0d6efd;
    --success: #198754;
    --warning: #fd7e14;
    --text: #212529;
    --muted: #6c757d;
    --fs-title: .9rem;
    --fs-desc: .72rem;
    --fs-value: .82rem;
    font-family: system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
    color: var(--text);
    font-size: .82rem;
}

.trf-section {
    background: #fff;
    border: 1px solid var(--border);
    border-radius: var(--radius);
    box-shadow: var(--shadow);
    margin-bottom: 20px;
    overflow: hidden;
}

.trf-section__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 20px;
    border-bottom: 1px solid var(--border);
    background: linear-gradient(135deg, #e7f1ff 0%, #f8f9fa 100%);
    gap: 14px;
    min-height: 62px;
}

.trf-section__head--green {
    background: linear-gradient(135deg, #d1e7dd 0%, #f8f9fa 100%);
    border-bottom-color: #badbcc;
}

.trf-section__head--expense {
    background: linear-gradient(135deg, #fff3cd 0%, #f8f9fa 100%);
    border-bottom-color: #ffe69c;
}

.trf-section__head-left {
    display: flex;
    align-items: center;
    gap: 14px;
    min-width: 0;
    flex: 0 1 auto;
}

.trf-section__head-right {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
    min-width: 0;
    flex: 1 1 auto;
    justify-content: flex-end;
}

.trf-section__title {
    font-size: var(--fs-title);
    font-weight: 700;
    color: var(--text);
    margin: 0;
    line-height: 1.3;
}

.trf-section__desc {
    font-size: var(--fs-desc);
    color: var(--muted);
    margin: 2px 0 0;
}

.trf-section__body {
    padding: 18px 20px;
}

.trf-section__body--expenses {
    background: #fffef8;
}

.trf-step-num {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: var(--primary);
    color: #fff;
    font-weight: 800;
    font-size: .8rem;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.trf-step-num--green { background: var(--success); }
.trf-step-num--expense { background: #d97706; }

.trf-trip-id--head {
    text-align: right;
}

.trf-trip-id__meta {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 8px;
    margin-bottom: 2px;
}

.trf-trip-id__label {
    font-size: .66rem;
    font-weight: 700;
    letter-spacing: .04em;
    text-transform: uppercase;
    color: var(--muted);
}

.trf-trip-num-badge {
    display: inline-flex;
    align-items: center;
    padding: 2px 8px;
    border-radius: 99px;
    font-size: .68rem;
    font-weight: 700;
}

.trf-trip-id__preview--static {
    font-size: .95rem;
    font-weight: 800;
    color: var(--text);
    line-height: 1.2;
}

.trf-back-link {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: .75rem;
    font-weight: 600;
    color: var(--primary);
    text-decoration: none;
}

.trf-back-link:hover {
    text-decoration: underline;
}

.trf-live-total {
    font-size: .9rem;
    font-weight: 700;
    color: #92400e;
    background: #fffbeb;
    border: 1px solid #fde68a;
    border-radius: 99px;
    padding: 6px 14px;
}

.trf-summary-block {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.trf-summary-block__label {
    font-size: .68rem;
    font-weight: 700;
    letter-spacing: .04em;
    text-transform: uppercase;
    color: var(--muted);
}

.trf-vehicle-info {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 8px;
}

.trf-vehicle-info--overview {
    grid-template-columns: repeat(4, minmax(0, 1fr));
}

.trf-vehicle-info__item {
    display: flex;
    align-items: center;
    gap: 8px;
    min-height: 0;
    padding: 8px 12px;
    border: 1px solid var(--border);
    border-radius: 8px;
    background: #fafafa;
}

.trf-vehicle-info__icon {
    width: 28px;
    height: 28px;
    border-radius: 7px;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: .85rem;
    background: var(--bg);
    color: var(--ic);
}

.trf-vehicle-info__name {
    display: block;
    font-size: .68rem;
    font-weight: 700;
    color: var(--muted);
    line-height: 1.2;
}

.trf-vehicle-info__val {
    display: block;
    margin-top: 1px;
    font-size: var(--fs-value);
    font-weight: 800;
    color: var(--text);
    line-height: 1.2;
}

.trf-vehicle-info__val--profit { color: #16a34a; }
.trf-vehicle-info__val--loss { color: #dc2626; }

.trf-vehicle-info__item--hero {
    background: #f8fbff;
    border-color: #bfdbfe;
}

.trf-vehicle-info__item--hero .trf-vehicle-info__val {
    font-size: 1.05em;
}

@media (max-width: 991px) {
    .trf-vehicle-info,
    .trf-vehicle-info--overview {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .trf-section__head {
        flex-wrap: wrap;
    }
}

@media (max-width: 575.98px) {
    .trf-vehicle-info,
    .trf-vehicle-info--overview {
        grid-template-columns: 1fr;
    }

    .trip-expenses-redesigned-page {
        padding: 16px 12px 32px;
    }
}
</style>
