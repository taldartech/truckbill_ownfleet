<template>
    <div class="trip-expenses-redesigned-page app-index-page">
        <div v-if="needsCompanySelection" class="alert alert-warning">
            Select a company and click <strong>Enter</strong> before managing trip expenses.
        </div>

        <div v-else-if="loading" class="text-center py-5 text-muted">Loading trip expenses…</div>

        <template v-else-if="trip">
            <div class="trf">
                <!-- Trip overview (compact) -->
                <div class="trf-section trf-section--summary">
                    <div class="trf-section__head trf-section__head--compact">
                        <div class="trf-section__head-left">
                            <div>
                                <h2 class="trf-section__title">Trip Overview</h2>
                                <p class="trf-section__desc">Customer, Diesel &amp; Meters</p>
                            </div>
                        </div>
                        <div class="trf-section__head-right">
                            <div class="trf-trip-id trf-trip-id--head trf-trip-id--compact">
                                <span class="trf-trip-num-badge" :class="statusMeta.badge">
                                    {{ statusMeta.label }}
                                </span>
                                <span class="trf-trip-id__preview trf-trip-id__preview--static">
                                    {{ trip.trip_number || '—' }}
                                </span>
                            </div>
                            <RouterLink :to="tripDetailReturnTo" class="trf-back-link">
                                <i class="bi bi-arrow-left"></i> Back
                            </RouterLink>
                        </div>
                    </div>

                    <div class="trf-section__body trf-section__body--summary">
                        <div class="trf-overview-meta">
                            <span class="trf-overview-meta__item trf-overview-meta__item--route">
                                <span class="trf-overview-meta__label">Route</span>
                                <span
                                    v-if="routePoints.length"
                                    class="trf-route"
                                    :class="{ 'trf-route--multi': routePoints.length > 2 }"
                                    :title="routeFullLabel"
                                >
                                    <span class="trf-route__city is-endpoint">{{ routeSource }}</span>
                                    <template v-if="routeViaPoints.length">
                                        <span class="trf-route__arrow" aria-hidden="true">→</span>
                                        <span class="trf-route__via" aria-hidden="true">
                                            <span
                                                v-for="(city, index) in routeViaPoints"
                                                :key="`${city}-${index}`"
                                                class="trf-route__via-part"
                                            >
                                                <span v-if="index > 0" class="trf-route__arrow">→</span>
                                                <span class="trf-route__city is-via">{{ city }}</span>
                                            </span>
                                        </span>
                                    </template>
                                    <template v-if="routeDestination">
                                        <span class="trf-route__arrow" aria-hidden="true">→</span>
                                        <span class="trf-route__city is-endpoint">{{ routeDestination }}</span>
                                    </template>
                                </span>
                                <span v-else class="trf-overview-meta__value">—</span>
                            </span>
                            <span class="trf-overview-meta__divider" aria-hidden="true"></span>
                            <span class="trf-overview-meta__item">
                                <span class="trf-overview-meta__label">Dates</span>
                                <span class="trf-overview-meta__value">
                                    {{ formatDate(trip.start_at) }}
                                    <span class="trf-overview-meta__sep">→</span>
                                    {{ formatDate(trip.end_at) || 'Open' }}
                                </span>
                            </span>
                            <span class="trf-overview-meta__divider" aria-hidden="true"></span>
                            <span class="trf-overview-meta__item">
                                <span class="trf-overview-meta__label">Truck</span>
                                <span class="trf-overview-meta__value">{{ trip.vehicle?.registration_number || '—' }}</span>
                            </span>
                            <span class="trf-overview-meta__divider" aria-hidden="true"></span>
                            <span class="trf-overview-meta__item">
                                <span class="trf-overview-meta__label">Driver</span>
                                <span class="trf-overview-meta__value">
                                    {{ trip.driver?.name || '—' }}
                                    <template v-if="trip.substitute_driver?.name">
                                        · Sub {{ trip.substitute_driver.name }}
                                    </template>
                                </span>
                            </span>
                        </div>

                        <div class="trf-summary-grid trf-summary-grid--compact">
                            <div class="trf-summary-tile trf-summary-tile--accent">
                                <span class="trf-summary-tile__label">Customer</span>
                                <span class="trf-summary-tile__value">{{ primaryCustomerLabel }}</span>
                                <span v-if="form.broker_name" class="trf-summary-tile__sub">
                                    Broker: {{ form.broker_name }}
                                </span>
                                <span
                                    v-else-if="stopCustomerLabels.length > 1"
                                    class="trf-summary-tile__sub"
                                >
                                    +{{ stopCustomerLabels.length - 1 }} More On Stops
                                </span>
                            </div>
                            <div class="trf-summary-tile trf-summary-tile--diesel">
                                <span class="trf-summary-tile__label">Expected Diesel</span>
                                <span class="trf-summary-tile__value">{{ expectedDieselLabel }}</span>
                                <span class="trf-summary-tile__sub">
                                    <template v-if="form.diesel_rate_per_liter">
                                        ₹{{ formatMoney(form.diesel_rate_per_liter) }}/LTR
                                    </template>
                                    <template v-if="form.diesel_rate_per_liter && form.planned_mileage_km_per_liter"> · </template>
                                    <template v-if="form.planned_mileage_km_per_liter">
                                        {{ formatNum(form.planned_mileage_km_per_liter) }} KM/LTR
                                    </template>
                                    <template v-if="!form.diesel_rate_per_liter && !form.planned_mileage_km_per_liter">
                                        Rate / Mileage —
                                    </template>
                                </span>
                            </div>
                            <div class="trf-summary-tile">
                                <span class="trf-summary-tile__label">Freight</span>
                                <span class="trf-summary-tile__value">₹{{ formatMoney(freightAmount) }}</span>
                            </div>
                            <div class="trf-summary-tile trf-summary-tile--loadmix">
                                <span class="trf-summary-tile__label">Load Mix</span>
                                <div class="trf-summary-loadmix">
                                    <div
                                        v-for="line in loadMixLines"
                                        :key="line.label"
                                        class="trf-summary-loadmix__line"
                                    >
                                        <span class="trf-summary-loadmix__key">{{ line.label }}:</span>
                                        <span class="trf-summary-loadmix__val">{{ line.value }}</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Meters + distance stay at the right end of the row -->
                            <template v-if="hasBothMeters">
                                <div class="trf-summary-tile trf-summary-tile--meters">
                                    <span class="trf-summary-tile__label">Meter Readings</span>
                                    <div class="trf-meters-pair">
                                        <div class="trf-meters-pair__row">
                                            <span class="trf-meters-pair__key">Start Meter Reading</span>
                                            <span class="trf-meters-pair__val">{{ formatMeter(form.start_meter_reading_km) }}</span>
                                        </div>
                                        <div class="trf-meters-pair__row">
                                            <span class="trf-meters-pair__key">End Meter Reading</span>
                                            <span class="trf-meters-pair__val">{{ formatMeter(form.end_meter_reading_km) }}</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="trf-summary-tile trf-summary-tile--distance">
                                    <span class="trf-summary-tile__label">Distance</span>
                                    <span class="trf-summary-tile__value">{{ formatNum(meterDistanceKm) }} km</span>
                                    <span class="trf-summary-tile__sub">End − Start</span>
                                </div>
                            </template>
                            <template v-else>
                                <div
                                    class="trf-summary-tile"
                                    :class="{ 'trf-summary-tile--editable': startMeterEditable }"
                                >
                                    <span class="trf-summary-tile__label">Start Meter Reading</span>
                                    <template v-if="startMeterEditable">
                                        <div class="trf-meter-edit">
                                            <input
                                                v-model.number="form.start_meter_reading_km"
                                                type="number"
                                                min="1"
                                                step="1"
                                                class="form-control form-control-sm trf-meter-edit__input"
                                                placeholder="Enter km"
                                                aria-label="Start Meter Reading"
                                            />
                                        </div>
                                    </template>
                                    <span v-else class="trf-summary-tile__value">
                                        {{ formatMeter(form.start_meter_reading_km) }}
                                    </span>
                                </div>
                                <div class="trf-summary-tile">
                                    <span class="trf-summary-tile__label">End Meter Reading</span>
                                    <span class="trf-summary-tile__value">{{ formatMeter(form.end_meter_reading_km) }}</span>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>

                <!-- Stop-wise expenses (page focus) -->
                <div class="trf-section">
                    <div class="trf-section__head trf-section__head--expense">
                        <div class="trf-section__head-left">
                            <div class="trf-step-num trf-step-num--expense">1</div>
                            <div>
                                <h2 class="trf-section__title">Stop-wise Expenses</h2>
                                <p class="trf-section__desc">Add expenses for the whole trip or each stop</p>
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
                            @remove-expense="requestRemoveExpense"
                            @fuel-field-input="markFuelFieldDirty"
                            @field-input="onExpenseFieldInput"
                        />
                    </div>
                </div>

                <!-- Profitability (after expenses) -->
                <div class="trf-section trf-section--profit">
                    <div class="trf-section__head trf-section__head--profit">
                        <div class="trf-section__head-left">
                            <div class="trf-step-num trf-step-num--profit">
                                <i class="bi bi-graph-up-arrow" aria-hidden="true"></i>
                            </div>
                            <div>
                                <h2 class="trf-section__title">Trip Profitability</h2>
                                <p class="trf-section__desc">Freight vs live expenses · updates as you edit</p>
                            </div>
                        </div>
                        <div class="trf-section__head-right">
                            <div
                                class="trf-profit-badge"
                                :class="profitAmount >= 0 ? 'is-profit' : 'is-loss'"
                            >
                                {{ profitAmount >= 0 ? 'Profit' : 'Loss' }}
                                ₹{{ formatMoney(Math.abs(profitAmount)) }}
                            </div>
                        </div>
                    </div>
                    <div class="trf-section__body trf-section__body--profit">
                        <div class="trf-profit-grid">
                            <div class="trf-profit-stat">
                                <span class="trf-profit-stat__label">Freight</span>
                                <span class="trf-profit-stat__value">₹{{ formatMoney(freightAmount) }}</span>
                            </div>
                            <div class="trf-profit-stat">
                                <span class="trf-profit-stat__label">Expenses</span>
                                <span class="trf-profit-stat__value trf-profit-stat__value--expense">
                                    ₹{{ formatMoney(liveExpenseTotal) }}
                                </span>
                            </div>
                            <div class="trf-profit-stat trf-profit-stat--result">
                                <span class="trf-profit-stat__label">
                                    {{ profitAmount >= 0 ? 'Net Profit' : 'Net Loss' }}
                                </span>
                                <span
                                    class="trf-profit-stat__value"
                                    :class="profitAmount >= 0 ? 'is-profit' : 'is-loss'"
                                >
                                    ₹{{ formatMoney(profitAmount) }}
                                </span>
                            </div>
                        </div>
                        <div class="trf-profit-bar" role="img" :aria-label="profitBarAriaLabel">
                            <div
                                class="trf-profit-bar__freight"
                                :style="{ width: profitBarFreightPct + '%' }"
                            ></div>
                            <div
                                class="trf-profit-bar__expense"
                                :style="{ width: profitBarExpensePct + '%' }"
                            ></div>
                        </div>
                        <div class="trf-profit-legend">
                            <span><i class="trf-dot trf-dot--freight"></i> Freight</span>
                            <span><i class="trf-dot trf-dot--expense"></i> Expenses</span>
                            <span class="trf-profit-legend__margin">
                                Margin {{ profitMarginLabel }}
                            </span>
                        </div>
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

        <AppConfirmModal
            :show="showRemoveExpenseConfirm"
            title="Remove Expense"
            icon="bi bi-trash"
            icon-tone="danger"
            confirm-label="Yes, Remove"
            confirm-variant="danger"
            confirm-icon="bi bi-trash"
            @cancel="cancelRemoveExpense"
            @confirm="confirmRemoveExpense"
        >
            <template #message>
                This expense will be removed from the list. Save the trip to apply the change permanently.
            </template>

            <div v-if="pendingRemoveExpenseSummary" class="app-confirm-card text-start">
                <div class="app-confirm-card__body">
                    <div class="app-confirm-row">
                        <span class="app-confirm-row__label">Type</span>
                        <span class="app-confirm-row__value">{{ pendingRemoveExpenseSummary.type }}</span>
                    </div>
                    <div class="app-confirm-row">
                        <span class="app-confirm-row__label">Date</span>
                        <span class="app-confirm-row__value">{{ pendingRemoveExpenseSummary.date }}</span>
                    </div>
                    <div class="app-confirm-row">
                        <span class="app-confirm-row__label">Amount</span>
                        <span class="app-confirm-row__value">₹{{ pendingRemoveExpenseSummary.amount }}</span>
                    </div>
                    <div v-if="pendingRemoveExpenseSummary.stop" class="app-confirm-row">
                        <span class="app-confirm-row__label">Stop</span>
                        <span class="app-confirm-row__value">{{ pendingRemoveExpenseSummary.stop }}</span>
                    </div>
                    <div v-if="pendingRemoveExpenseSummary.note" class="app-confirm-row">
                        <span class="app-confirm-row__label">Note</span>
                        <span class="app-confirm-row__value">{{ pendingRemoveExpenseSummary.note }}</span>
                    </div>
                </div>
            </div>
        </AppConfirmModal>
    </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { listAllStations } from '../../api/stations';
import {
    canFullEditTrip,
    canManageTripExpenses,
    calcDieselAmount,
    expenseTypeLabel,
    fetchTrip,
    isDieselExpenseType,
    isTripArchived,
    summarizeTripForm,
    tripStatusMeta,
    updateTrip,
} from '../../api/trips';
import TripExpensesPanel from '../../components/trips/TripExpensesPanel.vue';
import TripExpenseSaveConfirmModal from '../../components/trips/TripExpenseSaveConfirmModal.vue';
import TripExpenseValidationSummary from '../../components/trips/TripExpenseValidationSummary.vue';
import AppButton from '../../components/ui/AppButton.vue';
import AppConfirmModal from '../../components/ui/AppConfirmModal.vue';
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

const { form, populateFromTrip, canEditStartMeter, addExpense, removeExpense, buildExpensesPayload, markFuelFieldDirty } = useTripExpensesForm();

const trip = ref(null);
const stations = ref([]);
const loading = ref(true);
const saving = ref(false);
const showSaveConfirm = ref(false);
const saveConfirmSummary = ref(buildExpenseSaveConfirmViewModel({ expenses: [] }));
const originalExpenses = ref([]);
const originalStartMeter = ref('');
const expensesPanelRef = ref(null);
const validationAttempted = ref(false);
const showRemoveExpenseConfirm = ref(false);
const pendingRemoveExpenseIndex = ref(null);

const tripDetailReturnTo = computed(() => ({
    name: 'trips.show',
    params: { id: route.params.id },
}));

const statusMeta = computed(() => tripStatusMeta(trip.value?.status));
const liveExpenseTotal = computed(() =>
    form.expenses.reduce((sum, expense) => sum + Number(expense.amount ?? 0), 0),
);
const freightAmount = computed(() => Number(form.total_freight_amount ?? trip.value?.total_freight_amount ?? 0));
const profitAmount = computed(() => freightAmount.value - liveExpenseTotal.value);

const startMeterEditable = computed(() => canEditStartMeter());

const canPersistStartMeter = computed(() =>
    canFullEditTrip(trip.value?.status, user.value, { has_invoices: trip.value?.has_invoices }),
);

const stopCustomerLabels = computed(() => {
    const names = (form.route_legs ?? [])
        .map((leg) => String(leg.customer_name ?? '').trim())
        .filter(Boolean);

    return [...new Set(names)];
});

const primaryCustomerLabel = computed(() => {
    const tripCustomer = String(form.customer_name ?? '').trim();

    if (tripCustomer) {
        return tripCustomer;
    }

    return stopCustomerLabels.value[0] || '—';
});

const expectedDieselLabel = computed(() => {
    const planned = Number(form.planned_diesel_liters);

    if (Number.isFinite(planned) && planned > 0) {
        return `${formatNum(planned)} LTR`;
    }

    const distance = Number(form.total_distance_km);
    const mileage = Number(form.planned_mileage_km_per_liter);

    if (Number.isFinite(distance) && distance > 0 && Number.isFinite(mileage) && mileage > 0) {
        return `${formatNum(distance / mileage)} LTR`;
    }

    return '—';
});

const loadMixLines = computed(() => {
    const summary = summarizeTripForm(form);

    return [
        { label: 'Full', value: summary.fullLoadStops ?? 0 },
        { label: 'Half', value: summary.halfLoadStops ?? 0 },
        { label: 'Empty', value: summary.emptyStops ?? 0 },
    ];
});

const hasStartMeterValue = computed(() => {
    const start = Number(form.start_meter_reading_km);

    return Number.isFinite(start) && start > 0;
});

const hasEndMeterValue = computed(() => {
    const end = Number(form.end_meter_reading_km);

    return Number.isFinite(end) && end > 0;
});

const hasBothMeters = computed(() => hasStartMeterValue.value && hasEndMeterValue.value);

const meterDistanceKm = computed(() => {
    if (!hasBothMeters.value) {
        return 0;
    }

    return Math.max(0, Number(form.end_meter_reading_km) - Number(form.start_meter_reading_km));
});

const profitBarScale = computed(() => Math.max(freightAmount.value, liveExpenseTotal.value, 1));
const profitBarFreightPct = computed(() =>
    Math.min(100, (freightAmount.value / profitBarScale.value) * 100),
);
const profitBarExpensePct = computed(() =>
    Math.min(100, (liveExpenseTotal.value / profitBarScale.value) * 100),
);
const profitMarginLabel = computed(() => {
    if (!(freightAmount.value > 0)) {
        return '—';
    }

    const margin = (profitAmount.value / freightAmount.value) * 100;

    return `${margin.toFixed(1)}%`;
});
const profitBarAriaLabel = computed(
    () =>
        `Freight ₹${formatMoney(freightAmount.value)}, expenses ₹${formatMoney(liveExpenseTotal.value)}, ` +
        `${profitAmount.value >= 0 ? 'profit' : 'loss'} ₹${formatMoney(Math.abs(profitAmount.value))}`,
);

const isDirty = computed(() => {
    const expensesDirty = JSON.stringify(form.expenses) !== JSON.stringify(originalExpenses.value);
    const startMeterDirty =
        startMeterEditable.value
        && String(form.start_meter_reading_km ?? '') !== String(originalStartMeter.value ?? '');

    return expensesDirty || startMeterDirty;
});

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

const routePoints = computed(() => {
    const legs = trip.value?.route_legs ?? form.route_legs ?? [];

    if (legs.length === 0) {
        return [];
    }

    const points = [];

    if (legs[0]?.from_city || legs[0]?.from_location) {
        points.push(legs[0].from_city || legs[0].from_location);
    }

    legs.forEach((leg) => {
        const label = leg.to_city || leg.to_location;

        if (label) {
            points.push(label);
        }
    });

    return points.filter(Boolean);
});

const routeFullLabel = computed(() => routePoints.value.join(' → '));
const routeSource = computed(() => routePoints.value[0] || '');
const routeDestination = computed(() => {
    if (routePoints.value.length < 2) {
        return '';
    }

    return routePoints.value[routePoints.value.length - 1];
});
const routeViaPoints = computed(() => {
    if (routePoints.value.length <= 2) {
        return [];
    }

    return routePoints.value.slice(1, -1);
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

const pendingRemoveExpenseSummary = computed(() => {
    const index = pendingRemoveExpenseIndex.value;

    if (index == null) {
        return null;
    }

    const expense = form.expenses[index];

    if (!expense) {
        return null;
    }

    const amount = isDieselExpenseType(expense.expense_type)
        ? calcDieselAmount(expense.fuel_liters, expense.fuel_rate)
        : Number(expense.amount ?? 0);

    const sequence = expense.route_leg_sequence;
    let stop = 'Whole Trip';

    if (sequence !== '' && sequence != null) {
        const leg = form.route_legs.find(
            (item) => Number(item.sequence_no) === Number(sequence),
        );
        const from = leg?.from_city || '—';
        const to = leg?.to_city || '—';
        stop = `Stop ${sequence}: ${from} → ${to}`;
    }

    return {
        type: expenseTypeLabel(expense.expense_type, expense.other_type),
        date: expense.expense_date ? formatDate(expense.expense_date) : '—',
        amount: formatMoney(amount),
        stop,
        note: String(expense.notes ?? '').trim(),
    };
});

function requestRemoveExpense(index) {
    pendingRemoveExpenseIndex.value = index;
    showRemoveExpenseConfirm.value = true;
}

function cancelRemoveExpense() {
    showRemoveExpenseConfirm.value = false;
    pendingRemoveExpenseIndex.value = null;
}

function confirmRemoveExpense() {
    const index = pendingRemoveExpenseIndex.value;

    if (index != null) {
        removeExpense(index);
    }

    cancelRemoveExpense();
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
        originalStartMeter.value = form.start_meter_reading_km;
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
        const payload = buildExpensesPayload({
            includeStartMeter: canPersistStartMeter.value,
        });
        const updated = await updateTrip(route.params.id, payload);
        trip.value = updated;
        populateFromTrip(updated);
        originalExpenses.value = JSON.parse(JSON.stringify(form.expenses));
        originalStartMeter.value = form.start_meter_reading_km;
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
    --border: var(--brand-border, #e2e8f0);
    --shadow: 0 1px 3px rgba(0, 0, 0, .07), 0 1px 2px rgba(0, 0, 0, .04);
    --primary: #0d6efd;
    --success: #198754;
    --warning: #fd7e14;
    --text: var(--brand-text-primary, #475569);
    --muted: var(--brand-text-muted, #64748b);
    --fs-title: var(--app-font-size-section-title, .9rem);
    --fs-desc: var(--app-font-size-meta, .75rem);
    --fs-value: var(--app-font-size-input, .875rem);
    font-family: var(--app-font-family);
    color: var(--text);
    font-size: var(--app-font-size-input, .875rem);
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

.trf-section__head--profit {
    background: linear-gradient(135deg, #d1e7dd 0%, #f0fdf4 55%, #f8f9fa 100%);
    border-bottom-color: #badbcc;
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

.trf-section__body--summary {
    padding: 10px 16px 12px;
    background: #f8fafc;
}

.trf-section__body--profit {
    padding: 16px 20px 18px;
    background: #f6fbf8;
}

.trf-section__body--expenses {
    background: #fffef8;
}

.trf-section--summary {
    margin-bottom: 14px;
}

.trf-section__head--compact {
    min-height: 48px;
    padding: 8px 16px;
}

.trf-trip-id--compact {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    text-align: right;
}

.trf-trip-id--compact .trf-trip-id__preview--static {
    font-size: 0.85rem;
}

.trf-overview-meta {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 4px 0;
    margin-bottom: 10px;
    padding: 12px 8px;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    background: #fff;
}

.trf-overview-meta__item {
    display: inline-flex;
    flex-wrap: wrap;
    align-items: baseline;
    gap: 8px;
    min-width: 0;
    padding: 4px 18px;
}

.trf-overview-meta__item--route {
    flex: 1 1 220px;
    min-width: 0;
}

.trf-overview-meta__divider {
    width: 1px;
    align-self: stretch;
    min-height: 1.6em;
    margin: 0 6px;
    background: #e2e8f0;
    flex-shrink: 0;
}

.trf-overview-meta__label {
    font-size: 0.62rem;
    font-weight: 700;
    letter-spacing: 0.03em;
    color: var(--muted);
    flex-shrink: 0;
}

.trf-overview-meta__value {
    font-size: 0.78rem;
    font-weight: 700;
    color: var(--text);
    line-height: 1.3;
    word-break: break-word;
}

.trf-overview-meta__sep {
    color: var(--muted);
    font-weight: 600;
    margin: 0 4px;
}

.trf-route {
    display: inline-flex;
    align-items: center;
    min-width: 0;
    max-width: 100%;
    font-size: 0.78rem;
    line-height: 1.3;
    white-space: nowrap;
    overflow: hidden;
    cursor: help;
}

.trf-route__city {
    min-width: 0;
}

.trf-route__city.is-endpoint {
    flex-shrink: 0;
    font-weight: 800;
    color: #0f172a;
    padding: 1px 6px;
    border-radius: 4px;
    background: #e8f1ff;
    border: 1px solid #bfdbfe;
}

.trf-route__city.is-via {
    font-weight: 600;
    color: #64748b;
}

.trf-route__arrow {
    flex-shrink: 0;
    margin: 0 5px;
    color: #94a3b8;
    font-weight: 600;
}

.trf-route__via {
    display: inline-flex;
    align-items: center;
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
}

.trf-route__via-part {
    display: inline-flex;
    align-items: center;
    min-width: 0;
}

.trf-route--multi .trf-route__via {
    max-width: 12rem;
}

.trf-summary-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 8px;
}

.trf-summary-grid--compact {
    grid-template-columns: repeat(6, minmax(0, 1fr));
    gap: 6px;
}

.trf-summary-tile--meters,
.trf-summary-tile--distance {
    border-color: #cbd5e1;
    background: #f8fafc;
}

.trf-meters-pair {
    display: flex;
    flex-direction: column;
    gap: 2px;
    margin-top: 2px;
}

.trf-meters-pair__row {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: 10px;
}

.trf-meters-pair__key {
    font-size: 0.62rem;
    font-weight: 700;
    color: var(--muted);
    line-height: 1.15;
}

.trf-meters-pair__val {
    font-size: 0.78rem;
    font-weight: 800;
    color: var(--text);
    line-height: 1.15;
    white-space: nowrap;
}

.trf-summary-tile {
    display: flex;
    flex-direction: column;
    gap: 1px;
    min-width: 0;
    padding: 7px 9px;
    border: 1px solid var(--border);
    border-radius: 8px;
    background: #fff;
}

.trf-summary-tile--accent {
    border-color: #bfdbfe;
    background: #f8fbff;
}

.trf-summary-tile--diesel {
    border-color: #fde68a;
    background: #fffbeb;
}

.trf-summary-tile--loadmix {
    border-color: #fcd34d;
    background: #fffbeb;
}

.trf-summary-tile--editable {
    border-color: #93c5fd;
    background: #eff6ff;
}

.trf-summary-tile__label {
    font-size: 0.6rem;
    font-weight: 700;
    letter-spacing: 0.03em;
    color: var(--muted);
    line-height: 1.2;
}

.trf-summary-tile__value {
    font-size: 0.78rem;
    font-weight: 700;
    color: var(--text);
    line-height: 1.25;
    word-break: break-word;
}

.trf-summary-tile__sub {
    display: block;
    margin-top: 1px;
    font-size: 0.66rem;
    font-weight: 600;
    color: #b45309;
    line-height: 1.25;
}

.trf-summary-loadmix {
    display: flex;
    flex-direction: column;
    gap: 2px;
    min-width: 0;
    margin-top: 2px;
}

.trf-summary-loadmix__line {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: 10px;
    min-width: 0;
}

.trf-summary-loadmix__key {
    font-size: 0.62rem;
    font-weight: 700;
    color: var(--muted);
    line-height: 1.15;
}

.trf-summary-loadmix__val {
    font-size: 0.78rem;
    font-weight: 800;
    color: var(--text);
    line-height: 1.15;
    white-space: nowrap;
}

.trf-meter-edit {
    display: flex;
    flex-direction: column;
    gap: 2px;
    margin-top: 1px;
}

.trf-meter-edit__input {
    font-weight: 700;
    max-width: 120px;
    padding-top: 0.2rem;
    padding-bottom: 0.2rem;
}

.trf-profit-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 10px;
    margin-bottom: 14px;
}

.trf-profit-stat {
    padding: 12px 14px;
    border-radius: 10px;
    border: 1px solid #d8e9df;
    background: #fff;
}

.trf-profit-stat--result {
    border-color: #86efac;
    background: linear-gradient(180deg, #ecfdf5 0%, #fff 100%);
}

.trf-profit-stat__label {
    display: block;
    font-size: 0.66rem;
    font-weight: 700;
    letter-spacing: 0.03em;
    color: var(--muted);
    margin-bottom: 4px;
}

.trf-profit-stat__value {
    font-size: 1.05rem;
    font-weight: 800;
    color: var(--text);
    line-height: 1.2;
}

.trf-profit-stat__value--expense {
    color: #b45309;
}

.trf-profit-stat__value.is-profit {
    color: #15803d;
}

.trf-profit-stat__value.is-loss {
    color: #dc2626;
}

.trf-profit-badge {
    display: inline-flex;
    align-items: center;
    padding: 6px 12px;
    border-radius: 99px;
    font-size: 0.78rem;
    font-weight: 800;
    border: 1px solid transparent;
}

.trf-profit-badge.is-profit {
    color: #166534;
    background: #dcfce7;
    border-color: #86efac;
}

.trf-profit-badge.is-loss {
    color: #991b1b;
    background: #fee2e2;
    border-color: #fca5a5;
}

.trf-profit-bar {
    position: relative;
    height: 10px;
    border-radius: 99px;
    background: #e9ecef;
    overflow: hidden;
    margin-bottom: 8px;
}

.trf-profit-bar__freight,
.trf-profit-bar__expense {
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    border-radius: 99px;
    transition: width 0.25s ease;
}

.trf-profit-bar__freight {
    background: #86efac;
    z-index: 1;
}

.trf-profit-bar__expense {
    background: rgba(245, 158, 11, 0.85);
    z-index: 2;
    mix-blend-mode: multiply;
}

.trf-profit-legend {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    align-items: center;
    font-size: 0.72rem;
    font-weight: 600;
    color: var(--muted);
}

.trf-profit-legend__margin {
    margin-left: auto;
    color: var(--text);
}

.trf-dot {
    display: inline-block;
    width: 8px;
    height: 8px;
    border-radius: 50%;
    margin-right: 6px;
    vertical-align: middle;
}

.trf-dot--freight { background: #22c55e; }
.trf-dot--expense { background: #f59e0b; }

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

.trf-step-num--expense { background: #d97706; }
.trf-step-num--profit {
    background: #15803d;
    font-size: 0.95rem;
}

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

@media (max-width: 1199px) {
    .trf-summary-grid--compact {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
}

@media (max-width: 991px) {
    .trf-overview-meta__divider {
        display: none;
    }

    .trf-overview-meta__item {
        width: calc(50% - 4px);
        padding: 8px 10px;
    }

    .trf-overview-meta__item--route {
        width: 100%;
        flex: 1 1 100%;
    }

    .trf-route--multi .trf-route__via {
        max-width: 8rem;
    }

    .trf-summary-grid,
    .trf-summary-grid--compact,
    .trf-profit-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .trf-section__head {
        flex-wrap: wrap;
    }
}

@media (max-width: 575.98px) {
    .trf-overview-meta__item {
        width: 100%;
    }

    .trf-route--multi .trf-route__via {
        max-width: 5.5rem;
    }

    .trf-summary-grid,
    .trf-summary-grid--compact,
    .trf-profit-grid {
        grid-template-columns: 1fr;
    }

    .trf-profit-legend__margin {
        margin-left: 0;
        width: 100%;
    }

    .trip-expenses-redesigned-page {
        padding: 16px 12px 32px;
    }
}
</style>
