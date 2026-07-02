<template>
    <div class="trip-expense-entry">
        <div class="trip-expense-entry__row">
            <div class="trip-expense-entry__field trip-expense-entry__field--date">
                <label class="trip-expense-entry__label">Date</label>
                <AppDateInput
                    v-model="expense.expense_date"
                    size="sm"
                    :min="tripStartDate || undefined"
                    :max="tripEndDate || undefined"
                    :disabled="disabled"
                />
            </div>

            <div class="trip-expense-entry__field trip-expense-entry__field--type">
                <label class="trip-expense-entry__label">Type</label>
                <select
                    v-if="!isOther"
                    v-model="expense.expense_type"
                    class="form-control form-control-sm"
                    :disabled="disabled"
                    @change="onTypeChange"
                >
                    <option v-for="type in expenseTypes" :key="type.value" :value="type.value">
                        {{ type.label }}
                    </option>
                </select>
                <input
                    v-else
                    v-model="expense.other_type"
                    type="text"
                    class="form-control form-control-sm"
                    placeholder="Enter expense type"
                    :disabled="disabled"
                />
            </div>

            <template v-if="isDiesel">
                <div class="trip-expense-entry__field trip-expense-entry__field--odometer">
                    <label class="trip-expense-entry__label">Meter Reading(KM)</label>
                    <input
                        v-model.number="expense.fuel_odometer_km"
                        type="number"
                        :min="tripStartMeterKm || undefined"
                        :max="tripEndMeterKm || undefined"
                        step="0.01"
                        class="form-control form-control-sm"
                        :class="{ 'is-invalid': odometerFieldError }"
                        placeholder="0"
                        :disabled="disabled"
                    />
                    <div v-if="odometerFieldError" class="invalid-feedback d-block">
                        {{ odometerFieldError }}
                    </div>
                </div>
                <div class="trip-expense-entry__field trip-expense-entry__field--fuel">
                    <label class="trip-expense-entry__label">Liters</label>
                    <input
                        v-model.number="expense.fuel_liters"
                        type="number"
                        min="0"
                        step="0.01"
                        class="form-control form-control-sm"
                        placeholder="0"
                        :disabled="disabled"
                        @input="recalculateDieselAmount"
                    />
                </div>
                <div class="trip-expense-entry__field trip-expense-entry__field--fuel">
                    <label class="trip-expense-entry__label">Rate</label>
                    <input
                        v-model.number="expense.fuel_rate"
                        type="number"
                        min="0"
                        step="0.01"
                        class="form-control form-control-sm"
                        placeholder="0"
                        :disabled="disabled"
                        @input="recalculateDieselAmount"
                    />
                </div>
            </template>

            <div class="trip-expense-entry__field trip-expense-entry__field--amount">
                <label class="trip-expense-entry__label">Amount</label>
                <input
                    v-model.number="expense.amount"
                    type="number"
                    min="0"
                    step="0.01"
                    class="form-control form-control-sm"
                    placeholder="0"
                    :disabled="disabled || isDiesel"
                    :readonly="isDiesel"
                />
            </div>

            <div class="trip-expense-entry__field trip-expense-entry__field--notes">
                <label class="trip-expense-entry__label">Note</label>
                <input
                    v-model="expense.notes"
                    type="text"
                    class="form-control form-control-sm"
                    placeholder="Optional"
                    :disabled="disabled"
                />
            </div>

            <div class="trip-expense-entry__actions">
                <button
                    type="button"
                    class="btn btn-sm btn-outline-danger"
                    :disabled="disabled"
                    title="Remove expense"
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
import { EXPENSE_FORM_TYPES } from '../../api/trips';

const props = defineProps({
    expense: {
        type: Object,
        required: true,
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
    odometerFieldError: {
        type: String,
        default: '',
    },
});

defineEmits(['remove']);

const expenseTypes = EXPENSE_FORM_TYPES;

const isDiesel = computed(() => props.expense.expense_type === 'DIESEL');
const isOther = computed(() => props.expense.expense_type === 'OTHER');

function recalculateDieselAmount() {
    const liters = Number(props.expense.fuel_liters) || 0;
    const rate = Number(props.expense.fuel_rate) || 0;

    props.expense.amount = Math.round(liters * rate * 100) / 100;
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
    }
}
</script>

<style scoped>
.trip-expense-entry {
    padding: 0.5rem 0;
    border-bottom: 1px solid var(--bs-border-color-translucent, #dee2e6);
}

.trip-expense-entry:last-child {
    border-bottom: none;
}

.trip-expense-entry__row {
    display: grid;
    grid-template-columns: 7.5rem 9rem repeat(3, minmax(4.5rem, 1fr)) minmax(5rem, 1fr) minmax(6rem, 1.5fr) auto;
    gap: 0.5rem;
    align-items: end;
}

.trip-expense-entry__row:not(:has(.trip-expense-entry__field--odometer)) {
    grid-template-columns: 7.5rem 9rem minmax(5rem, 1fr) minmax(6rem, 1.5fr) auto;
}

.trip-expense-entry__label {
    display: block;
    font-size: 0.7rem;
    color: var(--bs-secondary-color);
    margin-bottom: 0.15rem;
}

.trip-expense-entry__actions {
    display: flex;
    align-items: flex-end;
    padding-bottom: 0.1rem;
}

@media (max-width: 992px) {
    .trip-expense-entry__row,
    .trip-expense-entry__row:not(:has(.trip-expense-entry__field--odometer)) {
        grid-template-columns: 1fr 1fr;
    }

    .trip-expense-entry__actions {
        grid-column: 1 / -1;
        justify-content: flex-end;
    }
}
</style>
