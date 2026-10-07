<template>
    <div class="trip-expenses-panel">
        <div v-if="routeLegs.length === 0 && context === 'embedded'" class="alert alert-info mb-3">
            <i class="bi bi-info-circle me-1"></i>
            Add stops in the route section above, then add expenses here.
        </div>

        <div v-else-if="routeLegs.length === 0 && context === 'standalone'" class="alert alert-info mb-3">
            <i class="bi bi-info-circle me-1"></i>
            This trip has no route stops yet. You can still add <strong>whole-trip</strong> expenses below.
        </div>

        <!-- Single stop: merge whole-trip + stop into one expense card -->
        <div
            v-if="isSingleStopTrip"
            class="trf-leg__card"
            :class="{ 'trf-leg__card--collapsed': isCollapsed(singleStopCard.key) }"
        >
            <button
                type="button"
                class="trf-leg__summary"
                :class="{ 'trf-leg__summary--error': stopHasErrors(singleStopCard) || generalHasErrors }"
                :aria-expanded="!isCollapsed(singleStopCard.key)"
                @click="toggleCollapsed(singleStopCard.key)"
            >
                <div class="trf-leg__summary-main">
                    <span class="trf-leg__summary-stop">Trip Expenses</span>
                    <span class="trf-leg__summary-sep">·</span>
                    <span class="trf-leg__summary-route">{{ singleStopCard.fromLabel }} → {{ singleStopCard.toLabel }}</span>
                    <span class="trf-leg__summary-sep">·</span>
                    <span
                        class="trf-leg__load-badge"
                        :class="{ 'trf-leg__load-badge--over': singleStopCard.loadOverloaded }"
                    >
                        Load Type: {{ singleStopCard.loadLabel }}
                        <template v-if="singleStopCard.loadOverloaded"> · Over capacity</template>
                    </span>
                    <template v-if="singleStopCard.customerLabel">
                        <span class="trf-leg__summary-sep">·</span>
                        <span class="trf-leg__summary-customer">{{ singleStopCard.customerLabel }}</span>
                    </template>
                </div>
                <div class="trf-leg__summary-meta">
                    <span
                        class="trf-leg__summary-freight"
                        :class="{ 'is-empty': !(mergedSingleStopSubtotal > 0) }"
                    >
                        {{ mergedSingleStopSubtotal > 0 ? `₹${formatMoney(mergedSingleStopSubtotal)}` : 'No expenses' }}
                    </span>
                    <i
                        class="bi"
                        :class="isCollapsed(singleStopCard.key) ? 'bi-chevron-down' : 'bi-chevron-up'"
                        aria-hidden="true"
                    ></i>
                </div>
            </button>

            <div v-show="!isCollapsed(singleStopCard.key)" class="trf-leg__expense-body">
                <div
                    v-if="singleStopCard.freightAmount > 0 || singleStopCard.distanceKm > 0"
                    class="trf-leg__stop-facts"
                >
                    <span v-if="singleStopCard.distanceKm > 0" class="trf-leg__stop-fact">
                        <i class="bi bi-signpost-2" aria-hidden="true"></i>
                        {{ formatNum(singleStopCard.distanceKm) }} km
                    </span>
                    <span v-if="singleStopCard.freightAmount > 0" class="trf-leg__stop-fact">
                        <i class="bi bi-currency-rupee" aria-hidden="true"></i>
                        Freight ₹{{ formatMoney(singleStopCard.freightAmount) }}
                    </span>
                    <span v-if="singleStopCard.goodsName" class="trf-leg__stop-fact">
                        <i class="bi bi-box-seam" aria-hidden="true"></i>
                        {{ singleStopCard.goodsName }}
                    </span>
                </div>

                <QuickExpensePills
                    v-if="!readonly"
                    scope="stop"
                    :active-types="resolvedExpenseTypes"
                    @add="(type) => quickAdd(singleStopCard.sequence, type)"
                />
                <div v-if="mergedSingleStopItems.length > 0" class="expense-entries-scroll">
                    <div
                        class="expense-entry-columns"
                        :class="sectionUsesDieselColumns(mergedSingleStopItems) ? 'expense-entry-columns--wide' : 'expense-entry-columns--narrow'"
                    >
                        <span>Date</span>
                        <span>Type</span>
                        <template v-if="sectionUsesDieselColumns(mergedSingleStopItems)">
                            <span>KM</span>
                            <span>LTR</span>
                            <span>₹/LTR</span>
                        </template>
                        <span>Amount</span>
                        <span>Note</span>
                        <span aria-hidden="true"></span>
                    </div>
                    <template v-for="item in mergedSingleStopItems" :key="item.globalIndex">
                        <TripExpenseEntry
                            :expense="item.expense"
                            :expense-types="resolvedExpenseTypes"
                            :expense-index="item.globalIndex"
                            :show-diesel-columns="sectionUsesDieselColumns(mergedSingleStopItems)"
                            :disabled="readonly"
                            :trip-start-date="tripStartDate"
                            :trip-end-date="tripEndDate"
                            :trip-start-meter-km="tripStartMeterKm"
                            :trip-end-meter-km="tripEndMeterKm"
                            :date-field-error="fieldErrorFor(item.globalIndex, 'expense_date')"
                            :odometer-field-error="fieldErrorFor(item.globalIndex, 'fuel_odometer_km')"
                            :liters-field-error="fieldErrorFor(item.globalIndex, 'fuel_liters')"
                            :rate-field-error="fieldErrorFor(item.globalIndex, 'fuel_rate')"
                            :amount-field-error="fieldErrorFor(item.globalIndex, 'amount')"
                            :other-type-field-error="fieldErrorFor(item.globalIndex, 'other_type')"
                            :legacy-warning="legacyWarningFor(item.globalIndex)"
                            @remove="$emit('remove-expense', item.globalIndex)"
                            @fuel-field-input="$emit('fuel-field-input', item.globalIndex)"
                            @field-input="$emit('field-input', item.globalIndex)"
                        />
                    </template>
                </div>
                <div v-if="mergedSingleStopItems.length === 0" class="expense-empty-state">
                    <i class="bi bi-plus-circle expense-empty-state__icon"></i>
                    <span>Tap Diesel, Toll, or Loading to add an expense for this trip.</span>
                </div>
                <div class="trf-leg__meta">
                    <div class="trf-leg__meta-left">
                        <span class="trf-leg__meta-label">Subtotal</span>
                    </div>
                    <div class="trf-leg__meta-right">
                        <strong>₹{{ formatMoney(mergedSingleStopSubtotal) }}</strong>
                    </div>
                </div>
            </div>
        </div>

        <template v-else>
            <!-- Whole trip (multi-stop or no stops) -->
            <div
                class="trf-leg__card"
                :class="{ 'trf-leg__card--collapsed': isCollapsed('general') }"
            >
                <button
                    type="button"
                    class="trf-leg__summary"
                    :aria-expanded="!isCollapsed('general')"
                    @click="toggleCollapsed('general')"
                >
                    <div class="trf-leg__summary-main">
                        <span class="trf-leg__summary-stop">Whole Trip</span>
                        <span class="trf-leg__summary-sep">·</span>
                        <span class="trf-leg__summary-route">General Expenses</span>
                        <span class="trf-leg__summary-sep d-none d-md-inline">·</span>
                        <span class="trf-leg__summary-customer d-none d-md-inline">Full-route toll, permits, etc.</span>
                    </div>
                    <div class="trf-leg__summary-meta">
                        <span
                            class="trf-leg__summary-freight"
                            :class="{ 'is-empty': !(generalSubtotal > 0) }"
                        >
                            {{ generalSubtotal > 0 ? `₹${formatMoney(generalSubtotal)}` : 'No expenses' }}
                        </span>
                        <i
                            class="bi"
                            :class="isCollapsed('general') ? 'bi-chevron-down' : 'bi-chevron-up'"
                            aria-hidden="true"
                        ></i>
                    </div>
                </button>

                <div v-show="!isCollapsed('general')" class="trf-leg__expense-body">
                    <QuickExpensePills v-if="!readonly" scope="whole-trip" :active-types="resolvedExpenseTypes" @add="(type) => quickAdd('', type)" />
                    <div v-if="generalItems.length > 0" class="expense-entries-scroll">
                        <div
                            class="expense-entry-columns"
                            :class="sectionUsesDieselColumns(generalItems) ? 'expense-entry-columns--wide' : 'expense-entry-columns--narrow'"
                        >
                            <span>Date</span>
                            <span>Type</span>
                            <template v-if="sectionUsesDieselColumns(generalItems)">
                                <span>KM</span>
                                <span>LTR</span>
                                <span>₹/LTR</span>
                            </template>
                            <span>Amount</span>
                            <span>Note</span>
                            <span aria-hidden="true"></span>
                        </div>
                        <template v-for="item in generalItems" :key="item.globalIndex">
                            <TripExpenseEntry
                                :expense="item.expense"
                                :expense-types="resolvedExpenseTypes"
                                :expense-index="item.globalIndex"
                                :show-diesel-columns="sectionUsesDieselColumns(generalItems)"
                                :disabled="readonly"
                                :trip-start-date="tripStartDate"
                                :trip-end-date="tripEndDate"
                                :trip-start-meter-km="tripStartMeterKm"
                                :trip-end-meter-km="tripEndMeterKm"
                                :date-field-error="fieldErrorFor(item.globalIndex, 'expense_date')"
                                :odometer-field-error="fieldErrorFor(item.globalIndex, 'fuel_odometer_km')"
                                :liters-field-error="fieldErrorFor(item.globalIndex, 'fuel_liters')"
                                :rate-field-error="fieldErrorFor(item.globalIndex, 'fuel_rate')"
                                :amount-field-error="fieldErrorFor(item.globalIndex, 'amount')"
                                :other-type-field-error="fieldErrorFor(item.globalIndex, 'other_type')"
                                :legacy-warning="legacyWarningFor(item.globalIndex)"
                                @remove="$emit('remove-expense', item.globalIndex)"
                                @fuel-field-input="$emit('fuel-field-input', item.globalIndex)"
                                @field-input="$emit('field-input', item.globalIndex)"
                            />
                        </template>
                    </div>
                    <div v-if="generalItems.length === 0" class="expense-empty-state">
                        <i class="bi bi-receipt expense-empty-state__icon"></i>
                        <span>No whole-trip expenses yet. Tap a button above to add one.</span>
                    </div>
                    <div class="trf-leg__meta">
                        <div class="trf-leg__meta-left">
                            <span class="trf-leg__meta-label">Subtotal</span>
                        </div>
                        <div class="trf-leg__meta-right">
                            <strong>₹{{ formatMoney(generalSubtotal) }}</strong>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Per stop — same card pattern as create-trip route legs -->
            <div
                v-for="stop in stopCards"
                :key="stop.key"
                class="trf-leg__card"
                :class="{ 'trf-leg__card--collapsed': isCollapsed(stop.key) }"
            >
                <button
                    type="button"
                    class="trf-leg__summary"
                    :class="{ 'trf-leg__summary--error': stopHasErrors(stop) }"
                    :aria-expanded="!isCollapsed(stop.key)"
                    @click="toggleCollapsed(stop.key)"
                >
                    <div class="trf-leg__summary-main">
                        <span class="trf-leg__summary-stop">Stop {{ stop.sequence }}</span>
                        <span class="trf-leg__summary-sep">·</span>
                        <span class="trf-leg__summary-route">{{ stop.fromLabel }} → {{ stop.toLabel }}</span>
                        <span class="trf-leg__summary-sep">·</span>
                        <span
                            class="trf-leg__load-badge"
                            :class="{ 'trf-leg__load-badge--over': stop.loadOverloaded }"
                        >
                            Load Type: {{ stop.loadLabel }}
                            <template v-if="stop.loadOverloaded"> · Over capacity</template>
                        </span>
                        <template v-if="stop.customerLabel">
                            <span class="trf-leg__summary-sep">·</span>
                            <span class="trf-leg__summary-customer">{{ stop.customerLabel }}</span>
                        </template>
                    </div>
                    <div class="trf-leg__summary-meta">
                        <span
                            class="trf-leg__summary-freight"
                            :class="{ 'is-empty': !(stop.subtotal > 0) }"
                        >
                            {{ stop.subtotal > 0 ? `₹${formatMoney(stop.subtotal)}` : 'No expenses' }}
                        </span>
                        <i
                            class="bi"
                            :class="isCollapsed(stop.key) ? 'bi-chevron-down' : 'bi-chevron-up'"
                            aria-hidden="true"
                        ></i>
                    </div>
                </button>

                <div v-show="!isCollapsed(stop.key)" class="trf-leg__expense-body">
                    <div v-if="stop.freightAmount > 0 || stop.distanceKm > 0" class="trf-leg__stop-facts">
                        <span v-if="stop.distanceKm > 0" class="trf-leg__stop-fact">
                            <i class="bi bi-signpost-2" aria-hidden="true"></i>
                            {{ formatNum(stop.distanceKm) }} km
                        </span>
                        <span v-if="stop.freightAmount > 0" class="trf-leg__stop-fact">
                            <i class="bi bi-currency-rupee" aria-hidden="true"></i>
                            Freight ₹{{ formatMoney(stop.freightAmount) }}
                        </span>
                        <span v-if="stop.goodsName" class="trf-leg__stop-fact">
                            <i class="bi bi-box-seam" aria-hidden="true"></i>
                            {{ stop.goodsName }}
                        </span>
                    </div>

                    <QuickExpensePills v-if="!readonly" scope="stop" :active-types="resolvedExpenseTypes" @add="(type) => quickAdd(stop.sequence, type)" />
                    <div v-if="stop.items.length > 0" class="expense-entries-scroll">
                        <div
                            class="expense-entry-columns"
                            :class="sectionUsesDieselColumns(stop.items) ? 'expense-entry-columns--wide' : 'expense-entry-columns--narrow'"
                        >
                            <span>Date</span>
                            <span>Type</span>
                            <template v-if="sectionUsesDieselColumns(stop.items)">
                                <span>KM</span>
                                <span>LTR</span>
                                <span>₹/LTR</span>
                            </template>
                            <span>Amount</span>
                            <span>Note</span>
                            <span aria-hidden="true"></span>
                        </div>
                        <template v-for="item in stop.items" :key="item.globalIndex">
                            <TripExpenseEntry
                                :expense="item.expense"
                                :expense-types="resolvedExpenseTypes"
                                :expense-index="item.globalIndex"
                                :show-diesel-columns="sectionUsesDieselColumns(stop.items)"
                                :disabled="readonly"
                                :trip-start-date="tripStartDate"
                                :trip-end-date="tripEndDate"
                                :trip-start-meter-km="tripStartMeterKm"
                                :trip-end-meter-km="tripEndMeterKm"
                                :date-field-error="fieldErrorFor(item.globalIndex, 'expense_date')"
                                :odometer-field-error="fieldErrorFor(item.globalIndex, 'fuel_odometer_km')"
                                :liters-field-error="fieldErrorFor(item.globalIndex, 'fuel_liters')"
                                :rate-field-error="fieldErrorFor(item.globalIndex, 'fuel_rate')"
                                :amount-field-error="fieldErrorFor(item.globalIndex, 'amount')"
                                :other-type-field-error="fieldErrorFor(item.globalIndex, 'other_type')"
                                :legacy-warning="legacyWarningFor(item.globalIndex)"
                                @remove="$emit('remove-expense', item.globalIndex)"
                                @fuel-field-input="$emit('fuel-field-input', item.globalIndex)"
                                @field-input="$emit('field-input', item.globalIndex)"
                            />
                        </template>
                    </div>
                    <div v-if="stop.items.length === 0" class="expense-empty-state">
                        <i class="bi bi-plus-circle expense-empty-state__icon"></i>
                        <span>Tap Diesel, Toll, or Loading to add an expense for this stop.</span>
                    </div>
                    <div class="trf-leg__meta">
                        <div class="trf-leg__meta-left">
                            <span class="trf-leg__meta-label">Stop Subtotal</span>
                        </div>
                        <div class="trf-leg__meta-right">
                            <strong>₹{{ formatMoney(stop.subtotal) }}</strong>
                        </div>
                    </div>
                </div>
            </div>
        </template>

        <!-- Summary -->
        <div v-if="expenses.length > 0" class="expense-summary-wrap">
            <h6 class="expense-summary-title">Expense Total By Stop</h6>
            <div class="table-responsive">
                <table class="expense-summary-table">
                    <thead>
                        <tr>
                            <th>Stop</th>
                            <th class="text-end">Total ₹</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(row, index) in summaryRows" :key="row.key" :class="{ 'is-striped': index % 2 === 1 }">
                            <td>{{ row.label }}</td>
                            <td class="text-end fw-medium">₹{{ formatMoney(row.total) }}</td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr class="grand-total-row">
                            <th>Grand Total</th>
                            <th class="text-end">₹{{ formatMoney(grandTotal) }}</th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, nextTick, onMounted, reactive, ref, watch } from 'vue';
import { getLegacyFuelWarning, isDieselExpenseType, legLoadCategoryMeta } from '../../api/trips';
import { fetchActiveTripExpenseTypes } from '../../api/tripExpenseTypes';
import QuickExpensePills from './QuickExpensePills.vue';
import TripExpenseEntry from './TripExpenseEntry.vue';

const props = defineProps({
    expenses: { type: Array, default: () => [] },
    routeLegs: { type: Array, default: () => [] },
    stations: { type: Array, default: () => [] },
    expenseTypes: { type: Array, default: null },
    readonly: { type: Boolean, default: false },
    tripStartDate: { type: String, default: '' },
    tripEndDate: { type: String, default: '' },
    tripStartMeterKm: { type: [Number, String], default: '' },
    tripEndMeterKm: { type: [Number, String], default: '' },
    fieldErrors: { type: Object, default: () => ({}) },
    maxLoadTon: { type: [Number, String], default: '' },
    context: {
        type: String,
        default: 'embedded',
        validator: (value) => ['embedded', 'standalone'].includes(value),
    },
});

const emit = defineEmits(['add-expense', 'remove-expense', 'fuel-field-input', 'field-input']);

const loadedExpenseTypes = ref([]);

onMounted(async () => {
    if (props.expenseTypes && props.expenseTypes.length > 0) {
        return;
    }
    try {
        const types = await fetchActiveTripExpenseTypes();
        if (types && types.length > 0) {
            loadedExpenseTypes.value = types;
        }
    } catch {
        // Fallback to defaults handled gracefully
    }
});

const resolvedExpenseTypes = computed(() => {
    if (props.expenseTypes && props.expenseTypes.length > 0) {
        return props.expenseTypes;
    }
    if (loadedExpenseTypes.value.length > 0) {
        return loadedExpenseTypes.value;
    }
    return null;
});

function fieldErrorFor(index, field) {
    const errors = props.fieldErrors[`expenses.${index}.${field}`];

    if (Array.isArray(errors)) {
        return errors[0] ?? '';
    }

    return errors ?? '';
}

function sectionUsesDieselColumns(items) {
    return items.some(({ expense }) => isDieselExpenseType(expense.expense_type));
}

function legacyWarningFor(index) {
    const expense = props.expenses[index];

    if (!expense) {
        return '';
    }

    return getLegacyFuelWarning(expense, {
        startMeterKm: props.tripStartMeterKm,
        endMeterKm: props.tripEndMeterKm,
    });
}

const collapsed = reactive({});
const collapseInitialized = ref(false);
const lastFocusedIndex = ref(-1);

watch(
    () => props.routeLegs,
    (legs) => {
        if (collapseInitialized.value || legs.length === 0) {
            return;
        }

        if (legs.length === 1) {
            const firstSequence = Number(legs[0]?.sequence_no ?? 1);
            collapsed[`stop-${firstSequence}`] = false;
        } else {
            collapsed.general = true;
            const firstSequence = Number(legs[0]?.sequence_no ?? 1);
            collapsed[`stop-${firstSequence}`] = false;
        }

        collapseInitialized.value = true;
    },
    { immediate: true },
);

const grandTotal = computed(() =>
    props.expenses.reduce((sum, expense) => sum + Number(expense.amount ?? 0), 0),
);

const generalItems = computed(() => collectItems(''));

const generalSubtotal = computed(() => sumItems(generalItems.value));

const isSingleStopTrip = computed(() => props.routeLegs.length === 1);

const stopCards = computed(() =>
    props.routeLegs.map((leg, index) => {
        const sequence = Number(leg.sequence_no ?? index + 1);
        const items = collectItems(sequence);
        const meta = legLoadCategoryMeta(leg.weight_ton, props.maxLoadTon);
        const maxLoad = Number(props.maxLoadTon ?? 0);
        const weight = Number(leg.weight_ton ?? 0);

        return {
            key: `stop-${sequence}`,
            sequence,
            fromLabel: legLabel(leg.from_station_id, leg.from_city),
            toLabel: legLabel(leg.to_station_id, leg.to_city),
            loadLabel: meta.label,
            loadOverloaded: maxLoad > 0 && weight > maxLoad,
            customerLabel: String(leg.customer_name ?? '').trim(),
            goodsName: String(leg.goods_name ?? '').trim(),
            freightAmount: Number(leg.freight_amount ?? 0),
            distanceKm: Number(leg.distance_km ?? 0),
            items,
            subtotal: sumItems(items),
        };
    }),
);

const singleStopCard = computed(() => stopCards.value[0] ?? null);

const mergedSingleStopItems = computed(() => {
    if (!isSingleStopTrip.value || !singleStopCard.value) {
        return [];
    }

    return [...generalItems.value, ...singleStopCard.value.items].sort(
        (a, b) => a.globalIndex - b.globalIndex,
    );
});

const mergedSingleStopSubtotal = computed(() => sumItems(mergedSingleStopItems.value));

const generalHasErrors = computed(() =>
    generalItems.value.some(({ globalIndex }) =>
        Object.keys(props.fieldErrors).some((key) => key.startsWith(`expenses.${globalIndex}.`)),
    ),
);

const summaryRows = computed(() => {
    if (isSingleStopTrip.value && singleStopCard.value) {
        return [
            {
                key: 'merged',
                label: `Trip: ${singleStopCard.value.fromLabel} → ${singleStopCard.value.toLabel}`,
                total: mergedSingleStopSubtotal.value,
            },
        ].filter((row) => row.total > 0);
    }

    const rows = [
        { key: 'general', label: 'Whole Trip (General)', total: generalSubtotal.value },
        ...stopCards.value.map((stop) => ({
            key: stop.key,
            label: `Stop ${stop.sequence}: ${stop.fromLabel} → ${stop.toLabel}`,
            total: stop.subtotal,
        })),
    ];

    return rows.filter((row) => row.total > 0);
});

function collectItems(sequence) {
    const items = [];

    props.expenses.forEach((expense, globalIndex) => {
        const expenseSeq = expense.route_leg_sequence;

        if (sequence === '') {
            if (expenseSeq === '' || expenseSeq == null) {
                items.push({ expense, globalIndex });
            }

            return;
        }

        if (Number(expenseSeq) === Number(sequence)) {
            items.push({ expense, globalIndex });
        }
    });

    return items;
}

function sumItems(items) {
    return items.reduce((sum, item) => sum + Number(item.expense.amount ?? 0), 0);
}

function stationLabel(stationId) {
    if (!stationId) {
        return '—';
    }

    const match = props.stations.find((s) => String(s.id) === String(stationId));

    return match?.name ?? `#${stationId}`;
}

function legLabel(stationId, fallbackName = '') {
    if (fallbackName) {
        return fallbackName;
    }

    return stationLabel(stationId);
}

function isCollapsed(key) {
    return collapsed[key] !== false;
}

function toggleCollapsed(key) {
    collapsed[key] = isCollapsed(key) ? false : true;
}

function stopHasErrors(stop) {
    return stop.items.some(({ globalIndex }) =>
        Object.keys(props.fieldErrors).some((key) => key.startsWith(`expenses.${globalIndex}.`)),
    );
}

function quickAdd(routeLegSequence, expenseType) {
    const nextIndex = props.expenses.length;

    emit('add-expense', { routeLegSequence, expenseType });

    const collapseKey = routeLegSequence === '' ? 'general' : `stop-${routeLegSequence}`;
    collapsed[collapseKey] = false;

    nextTick(() => {
        lastFocusedIndex.value = nextIndex;
    });
}

function formatMoney(value) {
    return Number(value ?? 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function formatNum(value) {
    return Number(value ?? 0).toLocaleString('en-IN', { maximumFractionDigits: 2 });
}

function collapseKeyForExpense(expense) {
    if (isSingleStopTrip.value && singleStopCard.value) {
        return singleStopCard.value.key;
    }

    const seq = expense?.route_leg_sequence;

    if (seq === '' || seq == null) {
        return 'general';
    }

    return `stop-${seq}`;
}

function expandExpenseSection(expenseIndex) {
    const expense = props.expenses[expenseIndex];

    if (!expense) {
        return;
    }

    collapsed[collapseKeyForExpense(expense)] = false;
}

function focusExpenseField({ expenseIndex, field, focusId }) {
    const expense = props.expenses[expenseIndex];

    if (!expense) {
        return;
    }

    expandExpenseSection(expenseIndex);

    nextTick(() => {
        const targetId = focusId ?? `expense-${expenseIndex}-${field}`;
        const target = document.getElementById(targetId);

        target?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        target?.focus?.();
    });
}

defineExpose({ grandTotal, focusExpenseField });
</script>

<style scoped>
.trip-expenses-panel {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.trf-leg__card {
    display: flex;
    flex-direction: column;
    width: 100%;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    background: #fff;
    overflow: hidden;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
}

.trf-leg__card--collapsed {
    height: auto;
    min-height: 0;
}

.trf-leg__summary {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    width: 100%;
    margin: 0;
    padding: 10px 14px;
    border: 0;
    border-bottom: 1px solid #eef2f7;
    background: #f8fafc;
    text-align: left;
    cursor: pointer;
    color: inherit;
    font: inherit;
}

.trf-leg__card--collapsed .trf-leg__summary {
    border-bottom: 0;
}

.trf-leg__summary:hover {
    background: #f1f5f9;
}

.trf-leg__summary--error {
    background: #fff5f5;
    border-bottom-color: #fecaca;
}

.trf-leg__summary--error .trf-leg__summary-stop {
    color: #dc2626;
}

.trf-leg__summary-main {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.2rem 0.15rem;
    min-width: 0;
    flex: 1 1 auto;
    font-size: 0.78rem;
    line-height: 1.35;
    color: #334155;
}

.trf-leg__summary-stop {
    font-weight: 800;
    color: #0f172a;
}

.trf-leg__summary-sep {
    color: #cbd5e1;
    font-weight: 600;
    margin: 0 0.1rem;
}

.trf-leg__summary-route,
.trf-leg__summary-customer {
    font-weight: 600;
    color: #475569;
}

.trf-leg__summary-meta {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    flex-shrink: 0;
}

.trf-leg__summary-freight {
    font-size: 0.78rem;
    font-weight: 800;
    color: #0f172a;
    font-variant-numeric: tabular-nums;
    white-space: nowrap;
}

.trf-leg__summary-freight.is-empty {
    color: #94a3b8;
    font-weight: 600;
}

.trf-leg__summary-meta .bi {
    font-size: 0.75rem;
    color: #94a3b8;
}

.trf-leg__load-badge {
    display: inline-flex;
    align-items: center;
    padding: 2px 8px;
    border-radius: 999px;
    background: #e8f5ee;
    color: #146c43;
    font-size: 0.68rem;
    font-weight: 700;
    white-space: nowrap;
}

.trf-leg__load-badge--over {
    background: #fef2f2;
    color: #b91c1c;
}

.trf-leg__expense-body {
    display: flex;
    flex-direction: column;
    gap: 12px;
    padding: 14px;
    background: #fff;
}

.trf-leg__stop-facts {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.trf-leg__stop-fact {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 10px;
    border-radius: 999px;
    background: #f1f5f9;
    color: #475569;
    font-size: 0.72rem;
    font-weight: 600;
}

.trf-leg__stop-fact .bi {
    font-size: 0.7rem;
    color: #64748b;
}

.trf-leg__meta {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 8px 14px;
    margin-top: 2px;
    padding: 8px 12px;
    border-top: 1px solid #eef2f7;
    background: #f8fafc;
    border-radius: 8px;
    font-size: 0.75rem;
    font-weight: 600;
    color: #475569;
}

.trf-leg__meta-left,
.trf-leg__meta-right {
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.trf-leg__meta-label {
    color: #64748b;
    font-weight: 600;
}

.expense-entry-columns {
    display: grid;
    align-items: center;
    gap: 0.35rem;
    padding: 0 0 0.35rem;
    margin-bottom: 0.15rem;
    border-bottom: 1px solid #e5e7eb;
    font-size: 0.65rem;
    font-weight: 700;
    letter-spacing: 0.04em;
    color: #9ca3af;
}

.expense-entries-scroll {
    overflow-x: auto;
    min-width: 0;
}

.expense-entry-columns--wide {
    grid-template-columns: 8rem 12rem 5rem 4.5rem 4.25rem 7.5rem minmax(4rem, 1fr) 2rem;
    min-width: 48.5rem;
}

.expense-entry-columns--narrow {
    grid-template-columns: 8rem 14rem 6.75rem minmax(4.5rem, 1fr) 2rem;
    min-width: 35rem;
}

.expense-entries-scroll :deep(.trip-expense-entry--wide) {
    min-width: 48.5rem;
}

.expense-entries-scroll :deep(.trip-expense-entry--narrow) {
    min-width: 35rem;
}

@media (max-width: 992px) {
    .expense-entry-columns {
        display: none;
    }
}

.expense-empty-state {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 14px 12px;
    border: 1px dashed #dbe3ee;
    border-radius: 8px;
    background: #f8fafc;
    color: #64748b;
    font-size: 0.8rem;
}

.expense-empty-state__icon {
    font-size: 1.1rem;
    color: #94a3b8;
}

.expense-summary-wrap {
    margin-top: 4px;
    padding: 14px 16px;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    background: #fff;
}

.expense-summary-title {
    margin: 0 0 10px;
    font-size: 0.78rem;
    font-weight: 700;
    color: #334155;
}

.expense-summary-table {
    width: 100%;
    margin: 0;
    font-size: 0.8rem;
    border-collapse: collapse;
}

.expense-summary-table th,
.expense-summary-table td {
    padding: 8px 4px;
    border-bottom: 1px solid #eef2f7;
}

.expense-summary-table thead th {
    font-size: 0.68rem;
    font-weight: 700;
    letter-spacing: 0.04em;
    color: #94a3b8;
}

.expense-summary-table tr.is-striped td {
    background: #f8fafc;
}

.expense-summary-table .grand-total-row th {
    border-bottom: 0;
    padding-top: 12px;
    font-size: 0.85rem;
    color: #0f172a;
}
</style>
