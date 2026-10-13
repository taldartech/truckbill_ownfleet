<template>
    <div
        class="trip-expense-entry"
        :class="showDieselColumns ? 'trip-expense-entry--wide' : 'trip-expense-entry--narrow'"
        :data-expense-index="expenseIndex"
    >
        <div class="trip-expense-entry__row">
            <div class="trip-expense-entry__cell trip-expense-entry__cell--date">
                <AppDateInput
                    :input-id="fieldId('expense_date')"
                    v-model="expense.expense_date"
                    size="sm"
                    :min="tripStartDate || undefined"
                    :max="tripEndDate || undefined"
                    :disabled="disabled"
                    :invalid="Boolean(dateFieldError)"
                    @update:model-value="onFieldInput"
                />
            </div>

            <div class="trip-expense-entry__cell trip-expense-entry__cell--type" :title="selectedTypeLabel || undefined">
                <AppSelect
                    v-if="!isOther"
                    v-model="expense.expense_type"
                    :options="availableExpenseTypes"
                    placeholder="Type"
                    search-placeholder="Search type…"
                    :disabled="disabled"
                    @change="onTypeChange"
                />
                <input
                    v-else
                    :id="fieldId('other_type')"
                    v-model="expense.other_type"
                    type="text"
                    class="form-control form-control-sm"
                    :class="{ 'is-invalid': otherTypeFieldError }"
                    :title="otherTypeFieldError || undefined"
                    placeholder="Type name"
                    :disabled="disabled"
                    @input="onFieldInput"
                />
            </div>

            <template v-if="isDiesel">
                <div class="trip-expense-entry__cell trip-expense-entry__cell--meter">
                    <input
                        :id="fieldId('fuel_odometer_km')"
                        v-model="expense.fuel_odometer_km"
                        type="number"
                        :min="tripStartMeterKm || undefined"
                        :max="tripEndMeterKm > 0 ? tripEndMeterKm : undefined"
                        step="1"
                        class="form-control form-control-sm"
                        :class="{ 'is-invalid': odometerFieldError }"
                        :placeholder="meterPlaceholder"
                        :title="odometerFieldError || legacyWarning || meterTitle || undefined"
                        :disabled="disabled"
                        @input="onFuelFieldInput"
                    />
                </div>

                <div class="trip-expense-entry__cell trip-expense-entry__cell--liters">
                    <input
                        :id="fieldId('fuel_liters')"
                        v-model="expense.fuel_liters"
                        type="number"
                        min="0.01"
                        :max="maxFuelLiters"
                        step="0.01"
                        class="form-control form-control-sm"
                        :class="{ 'is-invalid': litersFieldError }"
                        placeholder="LTR"
                        :title="litersFieldError || 'Fuel liters'"
                        :disabled="disabled"
                        @input="onFuelInput"
                    />
                </div>

                <div class="trip-expense-entry__cell trip-expense-entry__cell--rate">
                    <input
                        :id="fieldId('fuel_rate')"
                        v-model="expense.fuel_rate"
                        type="number"
                        min="0.01"
                        :max="maxFuelRate"
                        step="0.01"
                        class="form-control form-control-sm"
                        :class="{ 'is-invalid': rateFieldError }"
                        placeholder="₹/LTR"
                        :title="rateFieldError || 'Diesel rate per liter'"
                        :disabled="disabled"
                        @input="onFuelInput"
                    />
                </div>
            </template>

            <div
                v-else-if="showDieselColumns"
                class="trip-expense-entry__cell trip-expense-entry__cell--diesel-gap"
                aria-hidden="true"
            ></div>

            <div class="trip-expense-entry__cell trip-expense-entry__cell--amount">
                <div v-if="isDiesel" class="trip-expense-entry__amount" :title="amountTooltip">
                    <strong v-if="hasDieselCalc">₹{{ formatMoney(expense.amount) }}</strong>
                    <span v-else class="trip-expense-entry__amount-empty">—</span>
                </div>
                <input
                    v-else
                    :id="fieldId('amount')"
                    v-model.number="expense.amount"
                    type="number"
                    min="0"
                    step="0.01"
                    class="form-control form-control-sm"
                    :class="{ 'is-invalid': amountFieldError }"
                    :title="amountFieldError || undefined"
                    placeholder="₹"
                    :disabled="disabled"
                    @input="onFieldInput"
                />
            </div>

            <div class="trip-expense-entry__cell trip-expense-entry__cell--note">
                <input
                    v-model="expense.notes"
                    type="text"
                    class="form-control form-control-sm"
                    placeholder="Note"
                    :disabled="disabled"
                />
            </div>

            <div class="trip-expense-entry__cell trip-expense-entry__cell--actions">
                <button
                    type="button"
                    class="btn btn-sm btn-outline-danger trip-expense-entry__delete"
                    :disabled="disabled"
                    title="Remove"
                    @click="$emit('remove')"
                >
                    <i class="fa fa-trash" />
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import AppSelect from '../ui/AppSelect.vue';
import { MAX_FUEL_LITERS, MAX_FUEL_RATE } from '../../api/trips';
import { buildMeterLimitHints, expenseFieldFocusId } from '../../utils/expenseValidation';

const props = defineProps({
    expense: {
        type: Object,
        required: true,
    },
    expenseTypes: {
        type: Array,
        default: () => [],
    },
    expenseIndex: {
        type: Number,
        required: true,
    },
    showDieselColumns: {
        type: Boolean,
        default: false,
    },
    disabled: {
        type: Boolean,
        default: false,
    },
    tripStartDate: {
        type: String,
        default: '',
    },
    tripEndDate: {
        type: String,
        default: '',
    },
    tripStartMeterKm: {
        type: [Number, String],
        default: '',
    },
    tripEndMeterKm: {
        type: [Number, String],
        default: '',
    },
    dateFieldError: {
        type: String,
        default: '',
    },
    odometerFieldError: {
        type: String,
        default: '',
    },
    litersFieldError: {
        type: String,
        default: '',
    },
    rateFieldError: {
        type: String,
        default: '',
    },
    amountFieldError: {
        type: String,
        default: '',
    },
    otherTypeFieldError: {
        type: String,
        default: '',
    },
    legacyWarning: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['remove', 'fuel-field-input', 'field-input']);

const maxFuelLiters = MAX_FUEL_LITERS;
const maxFuelRate = MAX_FUEL_RATE;

const availableExpenseTypes = computed(() => {
    const list = props.expenseTypes && props.expenseTypes.length > 0 ? props.expenseTypes : [];
    const currentVal = props.expense.expense_type;

    if (currentVal && !list.some((type) => type.value === currentVal)) {
        return [
            ...list,
            {
                value: currentVal,
                label: props.expense.expense_type_label || (currentVal === 'OTHER' ? 'Other' : currentVal),
            },
        ];
    }

    return list;
});

const isDiesel = computed(() => props.expense.expense_type === 'DIESEL');
const isOther = computed(() => props.expense.expense_type === 'OTHER');

const selectedTypeLabel = computed(() => {
    const match = availableExpenseTypes.value.find((type) => type.value === props.expense.expense_type);

    return match?.label ?? props.expense.expense_type_label ?? '';
});

const limitHints = computed(() =>
    buildMeterLimitHints({
        startMeterKm: props.tripStartMeterKm,
        endMeterKm: props.tripEndMeterKm,
    }),
);

const meterTitle = computed(() => {
    const allowed = limitHints.value.allowed;

    return allowed && allowed !== '—' ? `Allowed: ${allowed}` : '';
});

const meterPlaceholder = computed(() => {
    const allowed = limitHints.value.allowed;

    if (allowed && allowed !== '—') {
        return allowed.replace(/\s*KM/gi, ' km');
    }

    return 'km';
});

const hasDieselCalc = computed(() => {
    const liters = Number(props.expense.fuel_liters);
    const rate = Number(props.expense.fuel_rate);

    return liters > 0 && rate > 0;
});

const amountTooltip = computed(() => {
    if (!hasDieselCalc.value) {
        return 'Liters × Rate';
    }

    return `${formatNum(props.expense.fuel_liters)} L × ₹${formatNum(props.expense.fuel_rate)} = ₹${formatMoney(props.expense.amount)}`;
});

function fieldId(field) {
    return expenseFieldFocusId(props.expenseIndex, field);
}

function recalculateDieselAmount() {
    const liters = Number(props.expense.fuel_liters) || 0;
    const rate = Number(props.expense.fuel_rate) || 0;

    props.expense.amount = Math.round(liters * rate * 100) / 100;
}

function onFuelInput() {
    recalculateDieselAmount();
    emit('fuel-field-input');
    emit('field-input');
}

function onFuelFieldInput() {
    emit('fuel-field-input');
    emit('field-input');
}

function onFieldInput() {
    emit('field-input');
}

function onTypeChange() {
    if (props.expense.expense_type === 'OTHER') {
        props.expense.other_type = props.expense.other_type || '';
    } else {
        props.expense.other_type = '';
    }

    if (props.expense.expense_type !== 'DIESEL') {
        props.expense.fuel_liters = '';
        props.expense.fuel_rate = '';
        props.expense.fuel_odometer_km = '';
    } else {
        const startMeter = Number(props.tripStartMeterKm);
        const currentOdo = props.expense.fuel_odometer_km;

        if (
            (currentOdo === '' || currentOdo == null)
            && Number.isFinite(startMeter)
            && startMeter > 0
        ) {
            props.expense.fuel_odometer_km = Math.trunc(startMeter);
        }
    }

    emit('field-input');
}

function formatMoney(value) {
    return Number(value ?? 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function formatNum(value) {
    return Number(value ?? 0).toLocaleString('en-IN', { maximumFractionDigits: 2 });
}
</script>

<style scoped>
.trip-expense-entry {
    border-bottom: 1px solid #f3f4f6;
}

.trip-expense-entry:last-child {
    border-bottom: none;
}

.trip-expense-entry__row {
    display: grid;
    align-items: center;
    gap: 0.35rem;
    min-height: 2.1rem;
    padding: 0.3rem 0;
}

.trip-expense-entry--wide .trip-expense-entry__row {
    grid-template-columns: 8rem 12rem 5rem 4.5rem 4.25rem 7.5rem minmax(4rem, 1fr) 2rem;
}

.trip-expense-entry--narrow .trip-expense-entry__row {
    grid-template-columns: 8rem 14rem 6.75rem minmax(4.5rem, 1fr) 2rem;
}

.trip-expense-entry__cell--diesel-gap {
    grid-column: 3 / 6;
}

.trip-expense-entry__cell :deep(.form-control),
.trip-expense-entry__cell :deep(.app-date-input__text),
.trip-expense-entry__cell :deep(.select-trigger) {
    min-height: 1.85rem;
    height: 1.85rem;
    padding-top: 0.2rem;
    padding-bottom: 0.2rem;
    font-size: 0.8rem;
}

.trip-expense-entry__cell :deep(.app-date-input) {
    min-width: 0;
}

.trip-expense-entry__cell--date :deep(.app-date-input__text) {
    padding-right: 2.15rem;
    letter-spacing: -0.01em;
}

.trip-expense-entry__amount {
    font-size: 0.8rem;
    color: #374151;
    padding: 0 0.15rem;
    white-space: nowrap;
}

.trip-expense-entry__amount strong {
    color: #166534;
    font-weight: 700;
}

.trip-expense-entry__amount-empty {
    color: #d1d5db;
}

.trip-expense-entry__delete {
    width: 1.85rem;
    height: 1.85rem;
    padding: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.trip-expense-entry__cell--actions {
    display: flex;
    justify-content: center;
}

@media (max-width: 992px) {
    .trip-expense-entry--wide .trip-expense-entry__row,
    .trip-expense-entry--narrow .trip-expense-entry__row {
        grid-template-columns: 1fr 1fr;
    }

    .trip-expense-entry__cell--diesel-gap {
        display: none;
    }

    .trip-expense-entry__cell--actions {
        grid-column: 1 / -1;
        justify-content: flex-end;
    }
}
</style>
