<template>
    <div class="trip-expense-entry" :class="[accentClass, { 'trip-expense-entry--readonly': readonly }]">
        <div class="trip-expense-entry__inner">
            <div class="row g-2 align-items-end">
                <div class="col-md-2 col-6">
                    <span class="expense-type-pill" :class="typePillClass">{{ expenseTypeLabel(expense.expense_type) }}</span>
                </div>

                <template v-if="!isDiesel">
                    <div class="col-md-3 col-6">
                        <label class="form-label expense-label mb-1">Amount</label>
                        <div v-if="!readonly" class="amount-input-wrap">
                            <span class="amount-input-prefix">₹</span>
                            <input
                                ref="amountInputRef"
                                v-model.number="expense.amount"
                                v-no-scroll-change
                                type="number"
                                min="0"
                                step="0.01"
                                class="form-control form-control-sm amount-input"
                                placeholder="0.00"
                            />
                        </div>
                        <span v-else class="fw-semibold">₹{{ formatMoney(expense.amount) }}</span>
                    </div>
                    <div class="col-md-6 col-10">
                        <label class="form-label expense-label mb-1">Note</label>
                        <input
                            v-if="!readonly"
                            v-model="expense.notes"
                            type="text"
                            class="form-control form-control-sm"
                            placeholder="e.g. HP pump, NH48"
                        />
                        <span v-else class="small text-muted">{{ expense.notes || '—' }}</span>
                    </div>
                </template>

                <div v-if="!readonly" class="col-md-1 col-2 text-end">
                    <button type="button" class="btn btn-sm btn-delete-expense" title="Remove" @click="$emit('remove')">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            </div>

            <div v-if="isDiesel" class="diesel-row">
                <p class="diesel-formula mb-2">Liters × Rate = Amount</p>
                <div class="row g-2 align-items-end">
                    <div class="col-6 col-md-3">
                        <label class="form-label expense-label mb-1">Liters</label>
                        <input
                            v-if="!readonly"
                            ref="litersInputRef"
                            v-model.number="expense.fuel_liters"
                            v-no-scroll-change
                            type="number"
                            min="0"
                            step="0.01"
                            class="form-control form-control-sm"
                            placeholder="100"
                            @input="recalcDieselAmount"
                        />
                        <span v-else>{{ formatNum(expense.fuel_liters) }} L</span>
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label expense-label mb-1">Rate ₹/L</label>
                        <input
                            v-if="!readonly"
                            v-model.number="expense.fuel_rate"
                            v-no-scroll-change
                            type="number"
                            min="0"
                            step="0.01"
                            class="form-control form-control-sm"
                            placeholder="95"
                            @input="recalcDieselAmount"
                        />
                        <span v-else>₹{{ formatMoney(expense.fuel_rate) }}</span>
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label expense-label mb-1">Amount</label>
                        <div class="form-control form-control-sm amount-readonly text-end fw-semibold">
                            ₹{{ formatMoney(expense.amount) }}
                        </div>
                    </div>
                    <div class="col-md-3 col-12">
                        <label class="form-label expense-label mb-1">Note</label>
                        <input
                            v-if="!readonly"
                            v-model="expense.notes"
                            type="text"
                            class="form-control form-control-sm"
                            placeholder="e.g. HP pump, NH48"
                        />
                        <span v-else class="small text-muted">{{ expense.notes || '—' }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import { EXPENSE_TYPES, calcDieselAmount, isDieselExpenseType } from '../../api/trips';

const props = defineProps({
    expense: { type: Object, required: true },
    readonly: { type: Boolean, default: false },
    autofocus: { type: Boolean, default: false },
});

defineEmits(['remove']);

const amountInputRef = ref(null);
const litersInputRef = ref(null);

const isDiesel = computed(() => isDieselExpenseType(props.expense.expense_type));

const typePillClass = computed(() => {
    const map = {
        DIESEL: 'expense-type-pill--diesel',
        TOLL: 'expense-type-pill--toll',
        LOADING_CHARGES: 'expense-type-pill--loading',
        UNLOADING_CHARGES: 'expense-type-pill--unloading',
        FOOD_EXPENSE: 'expense-type-pill--food',
    };

    return map[props.expense.expense_type] ?? 'expense-type-pill--other';
});

const accentClass = computed(() => {
    const map = {
        DIESEL: 'trip-expense-entry--diesel',
        TOLL: 'trip-expense-entry--toll',
        LOADING_CHARGES: 'trip-expense-entry--loading',
        UNLOADING_CHARGES: 'trip-expense-entry--unloading',
        FOOD_EXPENSE: 'trip-expense-entry--food',
    };

    return map[props.expense.expense_type] ?? 'trip-expense-entry--other';
});

function focusField() {
    if (props.readonly || !props.autofocus) {
        return;
    }

    nextTick(() => {
        const target = isDiesel.value ? litersInputRef.value : amountInputRef.value;

        target?.focus();
    });
}

watch(() => props.autofocus, (value) => {
    if (value) {
        focusField();
    }
});

onMounted(() => {
    focusField();
});

function recalcDieselAmount() {
    props.expense.amount = calcDieselAmount(props.expense.fuel_liters, props.expense.fuel_rate);
}

function expenseTypeLabel(type) {
    const labels = {
        LOADING_CHARGES: 'Loading',
        UNLOADING_CHARGES: 'Unloading',
        FOOD_EXPENSE: 'Food',
        DRIVER_EXPENSE: 'Driver',
        OTHER: 'Other',
    };

    if (labels[type]) {
        return labels[type];
    }

    return EXPENSE_TYPES.find((item) => item.value === type)?.label ?? type ?? '—';
}

function formatNum(value) {
    return Number(value ?? 0).toLocaleString('en-IN', { maximumFractionDigits: 2 });
}

function formatMoney(value) {
    return Number(value ?? 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
</script>

<style scoped>
.trip-expense-entry {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-left-width: 3px;
    border-radius: 8px;
    margin-bottom: 8px;
    transition: background-color 0.15s ease;
}
.trip-expense-entry:hover {
    background: #fafafa;
}
.trip-expense-entry--diesel {
    border-left-color: #1d4ed8;
}
.trip-expense-entry--toll {
    border-left-color: #854d0e;
}
.trip-expense-entry--loading {
    border-left-color: #166534;
}
.trip-expense-entry--unloading {
    border-left-color: #991b1b;
}
.trip-expense-entry--food {
    border-left-color: #6b21a8;
}
.trip-expense-entry--other {
    border-left-color: #6b7280;
}
.trip-expense-entry__inner {
    padding: 12px 16px;
}
.expense-label {
    font-size: 12px;
    color: #6b7280;
}
.expense-type-pill {
    display: inline-block;
    font-size: 12px;
    font-weight: 600;
    padding: 3px 10px;
    border-radius: 99px;
    white-space: nowrap;
}
.expense-type-pill--diesel {
    background: #eff6ff;
    color: #1d4ed8;
}
.expense-type-pill--toll {
    background: #fefce8;
    color: #854d0e;
}
.expense-type-pill--loading {
    background: #f0fdf4;
    color: #166534;
}
.expense-type-pill--unloading {
    background: #fef2f2;
    color: #991b1b;
}
.expense-type-pill--food {
    background: #faf5ff;
    color: #6b21a8;
}
.expense-type-pill--other {
    background: #f9fafb;
    color: #374151;
}
.amount-input-wrap {
    position: relative;
}
.amount-input-prefix {
    position: absolute;
    left: 10px;
    top: 50%;
    transform: translateY(-50%);
    color: #6b7280;
    font-size: 13px;
    z-index: 1;
    pointer-events: none;
}
.amount-input {
    padding-left: 1.65rem;
    text-align: right;
}
.amount-readonly {
    background: #f9fafb;
}
.diesel-row {
    margin-top: 10px;
    padding-top: 10px;
    border-top: 1px dashed #e5e7eb;
}
.diesel-formula {
    font-size: 12px;
    color: #6b7280;
    margin: 0;
}
.btn-delete-expense {
    color: #dc2626;
    border: 1px solid transparent;
    background: transparent;
    opacity: 0;
    transition: opacity 0.15s ease, background-color 0.15s ease;
}
.trip-expense-entry:hover .btn-delete-expense,
.trip-expense-entry--readonly .btn-delete-expense {
    opacity: 1;
}
.trip-expense-entry--readonly .btn-delete-expense {
    display: none;
}
.btn-delete-expense:hover {
    background: #fef2f2;
    border-color: #fecaca;
    color: #b91c1c;
}
</style>
