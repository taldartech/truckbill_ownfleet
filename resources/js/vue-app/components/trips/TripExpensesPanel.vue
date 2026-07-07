<template>
    <div class="trip-expenses-panel">
        <div v-if="routeLegs.length === 0 && context === 'embedded'" class="alert alert-info mb-3">
            <i class="bi bi-info-circle me-1"></i>
            Add stops in the <strong>Round Trip Route</strong> table above, then add expenses here.
        </div>

        <div v-else-if="routeLegs.length === 0 && context === 'standalone'" class="alert alert-info mb-3">
            <i class="bi bi-info-circle me-1"></i>
            This trip has no route stops yet. You can still add <strong>whole-trip</strong> expenses below.
        </div>

        <!-- Whole trip -->
        <div class="stop-expense-card">
            <button
                type="button"
                class="stop-expense-card__header w-100 border-0 text-start"
                @click="toggleCollapsed('general')"
            >
                <div class="stop-expense-card__header-row">
                    <div class="stop-expense-card__header-left">
                        <i class="bi stop-expense-card__chevron" :class="isCollapsed('general') ? 'bi-chevron-right' : 'bi-chevron-down'"></i>
                        <span class="load-badge load-badge--general">Whole trip</span>
                        <span class="stop-expense-card__title">General expenses</span>
                        <span class="stop-expense-card__hint d-none d-md-inline">(full-route toll, permits, etc.)</span>
                    </div>
                    <span class="stop-header-amount">₹{{ formatMoney(generalSubtotal) }}</span>
                </div>
            </button>
            <div v-show="!isCollapsed('general')" class="stop-expense-card__body">
                <QuickExpensePills v-if="!readonly" scope="whole-trip" @add="(type) => quickAdd('', type)" />
                <div v-if="generalItems.length > 0" class="expense-entries-scroll">
                    <div
                        class="expense-entry-columns"
                        :class="sectionUsesDieselColumns(generalItems) ? 'expense-entry-columns--wide' : 'expense-entry-columns--narrow'"
                    >
                        <span>Date</span>
                        <span>Type</span>
                        <template v-if="sectionUsesDieselColumns(generalItems)">
                            <span>km</span>
                            <span>L</span>
                            <span>₹/L</span>
                        </template>
                        <span>Amount</span>
                        <span>Note</span>
                        <span aria-hidden="true"></span>
                    </div>
                    <template v-for="item in generalItems" :key="item.globalIndex">
                        <TripExpenseEntry
                            :expense="item.expense"
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
                <div class="stop-subtotal">
                    Subtotal: <strong>₹{{ formatMoney(generalSubtotal) }}</strong>
                </div>
            </div>
        </div>

        <!-- Per stop -->
        <div v-for="stop in stopCards" :key="stop.key" class="stop-expense-card">
            <button
                type="button"
                class="stop-expense-card__header w-100 border-0 text-start"
                @click="toggleCollapsed(stop.key)"
            >
                <div class="stop-expense-card__header-row">
                    <div class="stop-expense-card__header-left">
                        <i class="bi stop-expense-card__chevron" :class="isCollapsed(stop.key) ? 'bi-chevron-right' : 'bi-chevron-down'"></i>
                        <span class="stop-expense-card__title">
                            Stop {{ stop.sequence }} — {{ stop.fromLabel }} → {{ stop.toLabel }}
                        </span>
                        <span class="load-badge" :class="stop.loadBadge">{{ stop.loadLabel }}</span>
                    </div>
                    <span class="stop-header-amount">₹{{ formatMoney(stop.subtotal) }}</span>
                </div>
            </button>
            <div v-show="!isCollapsed(stop.key)" class="stop-expense-card__body">
                <QuickExpensePills v-if="!readonly" scope="stop" @add="(type) => quickAdd(stop.sequence, type)" />
                <div v-if="stop.items.length > 0" class="expense-entries-scroll">
                    <div
                        class="expense-entry-columns"
                        :class="sectionUsesDieselColumns(stop.items) ? 'expense-entry-columns--wide' : 'expense-entry-columns--narrow'"
                    >
                        <span>Date</span>
                        <span>Type</span>
                        <template v-if="sectionUsesDieselColumns(stop.items)">
                            <span>km</span>
                            <span>L</span>
                            <span>₹/L</span>
                        </template>
                        <span>Amount</span>
                        <span>Note</span>
                        <span aria-hidden="true"></span>
                    </div>
                    <template v-for="item in stop.items" :key="item.globalIndex">
                        <TripExpenseEntry
                            :expense="item.expense"
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
                    <span>Tap Diesel, Toll, Loading… to add an expense for this stop.</span>
                </div>
                <div class="stop-subtotal">
                    Subtotal: <strong>₹{{ formatMoney(stop.subtotal) }}</strong>
                </div>
            </div>
        </div>

        <!-- Summary -->
        <div v-if="expenses.length > 0" class="expense-summary-wrap">
            <h6 class="expense-summary-title">Expense total by stop</h6>
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
                            <th>Grand total</th>
                            <th class="text-end">₹{{ formatMoney(grandTotal) }}</th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, nextTick, reactive, ref, watch } from 'vue';
import { getLegacyFuelWarning, isDieselExpenseType, legLoadCategoryMeta } from '../../api/trips';
import QuickExpensePills from './QuickExpensePills.vue';
import TripExpenseEntry from './TripExpenseEntry.vue';

const props = defineProps({
    expenses: { type: Array, default: () => [] },
    routeLegs: { type: Array, default: () => [] },
    stations: { type: Array, default: () => [] },
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

        collapsed.general = true;
        const firstSequence = Number(legs[0]?.sequence_no ?? 1);
        collapsed[`stop-${firstSequence}`] = false;
        collapseInitialized.value = true;
    },
    { immediate: true },
);

const grandTotal = computed(() =>
    props.expenses.reduce((sum, expense) => sum + Number(expense.amount ?? 0), 0),
);

const generalItems = computed(() => collectItems(''));

const generalSubtotal = computed(() => sumItems(generalItems.value));

const stopCards = computed(() =>
    props.routeLegs.map((leg, index) => {
        const sequence = Number(leg.sequence_no ?? index + 1);
        const items = collectItems(sequence);
        const meta = legLoadCategoryMeta(leg.weight_ton, props.maxLoadTon);

        return {
            key: `stop-${sequence}`,
            sequence,
            fromLabel: legLabel(leg.from_station_id, leg.from_city),
            toLabel: legLabel(leg.to_station_id, leg.to_city),
            loadLabel: meta.label,
            loadBadge: loadBadgeClass(meta.value),
            items,
            subtotal: sumItems(items),
        };
    }),
);

const summaryRows = computed(() => {
    const rows = [
        { key: 'general', label: 'Whole trip (general)', total: generalSubtotal.value },
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

function loadBadgeClass(category) {
    const normalized = String(category ?? '').toUpperCase();

    if (normalized === 'EMPTY') {
        return 'load-badge--empty';
    }

    return 'load-badge--full';
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
.stop-expense-card {
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    overflow: hidden;
    background: #fff;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06);
}
.stop-expense-card__header {
    background: var(--brand-gradient);
    color: #fff;
    padding: 14px 18px;
}
.stop-expense-card__header-row {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
}
.stop-expense-card__header-left {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px;
    min-width: 0;
}
.stop-expense-card__chevron {
    font-size: 14px;
    opacity: 0.9;
}
.stop-expense-card__title {
    font-size: 15px;
    font-weight: 500;
    line-height: 1.35;
}
.stop-expense-card__hint {
    font-size: 12px;
    color: rgba(255, 255, 255, 0.65);
}
.load-badge {
    display: inline-block;
    border-radius: 99px;
    padding: 4px 12px;
    font-size: 12px;
    font-weight: 600;
    line-height: 1.2;
    white-space: nowrap;
}
.load-badge--full {
    background: var(--brand-active-bg);
    color: var(--brand-primary-dark);
}
.load-badge--half {
    background: #fef9c3;
    color: #854d0e;
}
.load-badge--empty {
    background: #faeeda;
    color: #854f0b;
}
.load-badge--general {
    background: rgba(255, 255, 255, 0.2);
    color: #fff;
}
.stop-km-label {
    font-size: 12px;
    color: rgba(255, 255, 255, 0.7);
    margin-left: 8px;
}
.stop-header-amount {
    font-size: 14px;
    font-weight: 600;
    color: #fff;
    background: rgba(0, 0, 0, 0.22);
    border-radius: 99px;
    padding: 5px 12px;
    white-space: nowrap;
}
.stop-expense-card__body {
    padding: 16px;
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
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: #9ca3af;
}

.expense-entries-scroll {
    overflow-x: auto;
    min-width: 0;
}

.expense-entry-columns--wide {
    grid-template-columns: 8rem 14rem 5rem 3.75rem 4.25rem 6.75rem minmax(4.5rem, 1fr) 2rem;
    min-width: 49rem;
}

.expense-entry-columns--narrow {
    grid-template-columns: 8rem 14rem 6.75rem minmax(4.5rem, 1fr) 2rem;
    min-width: 35rem;
}

.expense-entries-scroll :deep(.trip-expense-entry--wide) {
    min-width: 49rem;
}

.expense-entries-scroll :deep(.trip-expense-entry--narrow) {
    min-width: 35rem;
}

@media (max-width: 992px) {
    .expense-entry-columns {
        display: none;
    }
}
.stop-subtotal {
    border-top: 1px dashed #e5e7eb;
    padding-top: 10px;
    margin-top: 12px;
    text-align: right;
    font-size: 13px;
    color: #6b7280;
}
.stop-subtotal strong {
    color: #111827;
}
.expense-empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 6px;
    text-align: center;
    color: #9ca3af;
    font-size: 13px;
    border: 1px dashed #d1d5db;
    border-radius: 8px;
    padding: 16px;
    margin-bottom: 4px;
}
.expense-empty-state__icon {
    font-size: 1.15rem;
    opacity: 0.85;
}
.expense-summary-wrap {
    margin-top: 4px;
}
.expense-summary-title {
    font-size: 14px;
    font-weight: 600;
    margin-bottom: 10px;
    color: #374151;
}
.expense-summary-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    overflow: hidden;
    font-size: 13px;
    background: #fff;
}
.expense-summary-table thead th {
    background: #f9fafb;
    padding: 10px 14px;
    font-weight: 600;
    color: #374151;
    border-bottom: 1px solid #e5e7eb;
}
.expense-summary-table tbody td {
    padding: 9px 14px;
    border-bottom: 1px solid #f3f4f6;
    color: #4b5563;
}
.expense-summary-table tbody tr.is-striped {
    background: #f9fafb;
}
.expense-summary-table tfoot .grand-total-row th {
    background: #f0fdf4;
    color: #166534;
    font-weight: 700;
    padding: 11px 14px;
    border-top: 1px solid #bbf7d0;
}
</style>
